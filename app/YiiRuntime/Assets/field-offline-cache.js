(() => {
  "use strict";
  const MiB = 1024 * 1024;
  const BUDGET = 100 * MiB;
  const RESERVE = 200 * MiB;
  const TTL = 7 * 24 * 60 * 60 * 1000;
  const META_CACHE = "fmonitor2-field-meta";
  const CATALOGUE_KEY = "/pilot/__field-cache-catalogue__";
  const OWN_KEY = "/pilot/__field-cache-own-set__";
  const DENIAL_KEY = "/pilot/__field-cache-denials__";
  const DENIAL_FALLBACK_KEY = "/pilot/__field-cache-denials-fallback__";
  const OWN_FALLBACK_KEY = "/pilot/__field-cache-own-fallback__";
  const REVISION_KEY = "/pilot/__field-cache-revision__";
  const TERMINAL = new Set(["accepted", "discarded", "superseded"]);
  let localLock = Promise.resolve();
  let physicalSnapshot = null;
  let physicalRevision = null;
  const volatileDenials = new Map();
  const volatileLimitReasons = new Map();
  const pendingRollbacks = new Map();
  const committedOverrides = new Map();
  const indeterminateOwnership = new Set();

  const json = async (key, fallback) => {
    try {
      const response = await (await caches.open(META_CACHE)).match(key);
      return response ? await response.json() : fallback;
    } catch { return fallback; }
  };
  const putJson = async (key, value) => (await caches.open(META_CACHE)).put(key,
    new Response(JSON.stringify(value), {headers:{"Content-Type":"application/json"}}));
  const catalogue = () => json(CATALOGUE_KEY, {users:{}});
  const ownSets = () => json(OWN_KEY, {users:{}});
  const denials = () => json(DENIAL_KEY, {users:{}});
  const fallbackDenials = () => json(DENIAL_FALLBACK_KEY, {users:{}});
  const ownershipFallbacks = () => json(OWN_FALLBACK_KEY, {users:{}});
  const hasUserRecord = (root, userId) => Object.prototype.hasOwnProperty.call(root.users, String(userId));
  const userEntries = (root, userId) => root.users[String(userId)] || {};
  const volatileDenied = (userId, objectId) => volatileDenials.get(String(userId))?.has(String(objectId)) === true;
  const rememberVolatileDenial = (userId, objectId) => {
    const key=String(userId),items=volatileDenials.get(key)||new Set();items.add(String(objectId));volatileDenials.set(key,items);
  };
  const clearVolatileDenial = (userId, objectId) => volatileDenials.get(String(userId))?.delete(String(objectId));
  const rememberLimitReason=(userId,objectId)=>{const key=String(userId),items=volatileLimitReasons.get(key)||new Set();items.add(String(objectId));volatileLimitReasons.set(key,items);};
  const clearLimitReason=(userId,objectId)=>volatileLimitReasons.get(String(userId))?.delete(String(objectId));
  async function coherentStateLocked(userId){
    const key=String(userId),override=committedOverrides.get(key);
    if(override)return {ownItems:structuredClone(override.ownItems),entries:structuredClone(override.entries)};
    const durable=userEntries(await ownershipFallbacks(),userId);
    if(durable.reasonCode==="CACHE_STATE_INDETERMINATE")return {ownItems:structuredClone(durable.ownItems||{}),entries:structuredClone(durable.entries||{})};
    return {ownItems:structuredClone(userEntries(await ownSets(),userId).items||{}),entries:structuredClone(userEntries(await catalogue(),userId))};
  }
  async function ownershipIsIndeterminate(userId){return indeterminateOwnership.has(String(userId))||userEntries(await ownershipFallbacks(),userId).reasonCode==="CACHE_STATE_INDETERMINATE";}
  const coherentState=(userId,alreadyLocked=false)=>alreadyLocked?coherentStateLocked(userId):serialize(()=>coherentStateLocked(userId));
  const fenced = async expected => !globalThis.FMonitorFieldCacheFence || await globalThis.FMonitorFieldCacheFence(expected);
  const serialize = (work) => {
    if (globalThis.navigator?.locks?.request) return globalThis.navigator.locks.request("fmonitor2-field-cache", work);
    const task = localLock.then(work, work); localLock = task.catch(() => {}); return task;
  };
  async function responseBytes(response) { return (await response.clone().arrayBuffer()).byteLength; }
  async function physical(force=false) {
    const revisionResponse=await(await caches.open(META_CACHE)).match(REVISION_KEY),revision=revisionResponse?Number(await revisionResponse.text())||0:0;
    if(!force&&physicalSnapshot&&physicalRevision===revision)return {...physicalSnapshot,entries:[...physicalSnapshot.entries]};
    let bytes = 0; const entries = [];
    for (const name of await caches.keys()) {
      if (!name.startsWith("fmonitor2-field-shell-") && !name.startsWith("fmonitor2-field-doc-") && !name.startsWith("fmonitor2-checklist-shell-") && !name.startsWith("fmonitor2-checklist-doc-")) continue;
      const cache = await caches.open(name);
      for (const request of await cache.keys()) {
        const response = await cache.match(request); if (!response) continue;
        const size = await responseBytes(response); bytes += size;
        entries.push({name, url:request.url, size});
      }
    }
    physicalSnapshot={bytes,entries};physicalRevision=revision;return {...physicalSnapshot,entries:[...entries]};
  }
  const invalidate=()=>{physicalSnapshot=null;physicalRevision=null;};
  const adjustPhysical=delta=>{if(physicalSnapshot)physicalSnapshot.bytes=Math.max(0,physicalSnapshot.bytes+delta);};
  async function markMutation(){const cache=await caches.open(META_CACHE),stored=await cache.match(REVISION_KEY),next=(stored?Number(await stored.text())||0:0)+1;await cache.put(REVISION_KEY,new Response(String(next)));if(physicalSnapshot)physicalRevision=next;}
  async function estimate() {
    try {
      const value = await globalThis.navigator?.storage?.estimate?.();
      if (!Number.isFinite(value?.quota) || !Number.isFinite(value?.usage) || value.quota<0 || value.usage<0 || value.quota<value.usage) throw Error();
      return {verified:true, quota:Number(value.quota), usage:Number(value.usage)};
    } catch { return {verified:false, quota:null, usage:null}; }
  }
  async function cacheBytes(cache){let bytes=0;for(const request of await cache.keys()){const response=await cache.match(request);if(response)bytes+=await responseBytes(response);}return bytes;}
  async function putVerified(cache,key,response){
    const expectedBytes=await responseBytes(response),expectedHash=await globalThis.FMonitorFieldCacheHash(response.clone());
    await cache.put(key,response);
    const readback=await cache.match(key);
    if(!readback||await responseBytes(readback)!==expectedBytes||await globalThis.FMonitorFieldCacheHash(readback.clone())!==expectedHash)throw Error("cache readback mismatch");
    return expectedBytes;
  }
  async function capacity(growth=0){const [current,storage]=await Promise.all([physical(),estimate()]);return {ok:current.bytes+growth<=BUDGET&&(!storage.verified||storage.quota-storage.usage-growth>=RESERVE),current,storage};}
  const request = value => new Promise((resolve, reject) => {
    value.onsuccess=()=>resolve(value.result); value.onerror=()=>reject(value.error||Error("IndexedDB read failed"));
  });
  async function openExistingDb(){if(!globalThis.indexedDB?.open)throw Error("IndexedDB unavailable");return new Promise((resolve,reject)=>{const open=indexedDB.open("fmonitor2-fast-pilot");open.onupgradeneeded=()=>{try{open.transaction.abort();}catch{}reject(Error("IndexedDB schema unavailable"));};open.onsuccess=()=>resolve(open.result);open.onerror=()=>reject(open.error||Error("IndexedDB unavailable"));open.onblocked=()=>reject(Error("IndexedDB blocked"));});}
  async function protection(userId) {
    try {
      const db = await openExistingDb();
      try {
        if (!db.objectStoreNames.contains("operations") || !db.objectStoreNames.contains("photoBlobs")) return {known:false, ids:new Set()};
        const tx=db.transaction(["operations","photoBlobs"],"readonly");
        const [operations, blobs]=await Promise.all([request(tx.objectStore("operations").getAll()),request(tx.objectStore("photoBlobs").getAll())]);
        const ids=new Set(),mapped=new Map();let orphan=false;
        for(const operation of operations){
          const match=typeof operation?.scope==="string"?operation.scope.match(/^([1-9][0-9]*):([^:]+):([1-9][0-9]*)$/):null;
          if(!operation?.id||!match){orphan=true;continue;}
          const derived={userId:Number(match[1]),deviceInstallationId:match[2],objectId:Number(match[3])};
          const agrees=(operation.userId==null||Number(operation.userId)===derived.userId)&&(operation.objectId==null||Number(operation.objectId)===derived.objectId)&&(operation.deviceInstallationId==null||String(operation.deviceInstallationId)===derived.deviceInstallationId);
          if(!agrees){orphan=true;continue;}
          mapped.set(operation.id,{operation,...derived});
          if(derived.userId===Number(userId)&&!TERMINAL.has(String(operation.status)))ids.add(derived.objectId);
        }
        for(const row of blobs){const value=mapped.get(row.id);if(!value){orphan=true;continue;}if(value.userId===Number(userId))ids.add(value.objectId);}
        return {known:!orphan, ids};
      } finally { db.close?.(); }
    } catch { return {known:false, ids:new Set()}; }
  }
  async function adoptPreparedOwn(userId, expected=null){
    const durableFallback=userEntries(await ownershipFallbacks(),userId);if(durableFallback.reasonCode==="CACHE_STATE_INDETERMINATE")return structuredClone(durableFallback.ownItems||{});
    const root=await ownSets();
    if(hasUserRecord(root,userId))return userEntries(root,userId).items||{};
    try{
      const db=await openExistingDb();
      try{
        if(!db.objectStoreNames.contains("preparations")||!db.objectStoreNames.contains("actorState"))return {};
        const tx=db.transaction(["preparations","actorState"],"readonly");
        const [preparations,actors]=await Promise.all([request(tx.objectStore("preparations").getAll()),request(tx.objectStore("actorState").getAll())]);
        const actor=actors.find(value=>Number(value.activeUserId)===Number(userId)&&!value.tombstonedAt&&Number(value.epoch)===Number(expected?.epoch));
        if(!actor||!(await fenced(expected)))return {};
        const record=preparations.filter(value=>Number(value.userId)===Number(userId)&&Number(value.epoch)===Number(actor.epoch)&&value.deviceInstallationId===actor.deviceInstallationId).sort((a,b)=>Date.parse(b.updatedAt||0)-Date.parse(a.updatedAt||0))[0];
        if(!record)return {};
        const items={};
        for(const item of record.items||[])if(["ready","stale","refreshing"].includes(item.state)&&await findObjectDocument(userId,Number(item.objectId),item.checklistUrl))items[String(item.objectId)]=item;
        if(!(await fenced(expected)))return {};
        root.users[String(userId)]={setVersion:record.setVersion||"adopted",items,updatedAt:Date.now(),deviceInstallationId:actor.deviceInstallationId,epoch:Number(actor.epoch)};
        await putJson(OWN_KEY,root);
        return items;
      }finally{db.close?.();}
    }catch{return {};}
  }
  async function setOwn(userId, setVersion, items, expected=null) {
    return serialize(async()=>{if(!(await fenced(expected)))return false;
      const previousRoot=await ownSets(),previousCat=await catalogue(),previousFallback=await ownershipFallbacks(),previousView=await coherentStateLocked(userId),root=structuredClone(previousRoot),cat=structuredClone(previousCat),fallback=structuredClone(previousFallback);root.users[String(userId)]={setVersion,items:Object.fromEntries(items.map(x=>[String(x.objectId),x])),updatedAt:Date.now()};
      const entries=structuredClone(previousView.entries), own=userEntries(root,userId).items;
      for(const [id,item] of Object.entries(own)){const previous=entries[id]||{};entries[id]={...previous,...item,source:"own",expiresAt:null,revoked:previous.revoked===true,lastConfirmedAt:previous.lastConfirmedAt||null};}
      for(const [id,item]of Object.entries(entries))if(!own[id]&&item.source==="own")entries[id]={...item,source:"substitution",expiresAt:0};
      cat.users[String(userId)]=entries;
      delete fallback.users[String(userId)];
      try{await putJson(OWN_KEY,root);await putJson(CATALOGUE_KEY,cat);if(!(await fenced(expected)))throw Error("stale own publication");await putJson(OWN_FALLBACK_KEY,fallback);if(!(await fenced(expected)))throw Error("stale own publication");committedOverrides.delete(String(userId));indeterminateOwnership.delete(String(userId));return true;}
      catch{
        let restored=true;try{await putJson(OWN_KEY,previousRoot);}catch{restored=false;}try{await putJson(CATALOGUE_KEY,previousCat);}catch{restored=false;}try{await putJson(OWN_FALLBACK_KEY,previousFallback);}catch{restored=false;}
        if(!restored){const durable=structuredClone(previousFallback);durable.users[String(userId)]={ownItems:structuredClone(previousView.ownItems),entries:structuredClone(previousView.entries),reasonCode:"CACHE_STATE_INDETERMINATE",updatedAt:Date.now()};try{await putJson(OWN_FALLBACK_KEY,durable);}catch{}committedOverrides.set(String(userId),{ownItems:structuredClone(previousView.ownItems),entries:structuredClone(previousView.entries)});indeterminateOwnership.add(String(userId));}
        return false;
      }
    });
  }
  async function ownItem(userId, objectId, alreadyLocked=false){const state=await coherentState(userId,alreadyLocked);return state.ownItems[String(objectId)]||null;}
  async function record(userId, metadata, foreground, expected=null) {
    return serialize(async()=>{
      if(!(await fenced(expected)))return false;
      const previousCat=await catalogue(),previousDenied=await denials(),previousFallback=await fallbackDenials(),previousOwnershipFallback=await ownershipFallbacks(),state=await coherentState(userId,true),cat=structuredClone(previousCat),denied=structuredClone(previousDenied),fallback=structuredClone(previousFallback),ownershipFallback=structuredClone(previousOwnershipFallback),entries=state.entries,own=state.ownItems[String(metadata.objectId)]||null,previous=entries[String(metadata.objectId)]||{};
      entries[String(metadata.objectId)]={...previous,...metadata,source:own?"own":"substitution",expiresAt:own?null:(foreground?Date.now()+TTL:previous.expiresAt||null),revoked:false,lastConfirmedAt:Date.now(),lastOpenedAt:Date.now()};cat.users[String(userId)]=entries;
      const deniedEntries=userEntries(denied,userId),fallbackEntries=userEntries(fallback,userId);delete deniedEntries[String(metadata.objectId)];delete fallbackEntries[String(metadata.objectId)];denied.users[String(userId)]=deniedEntries;fallback.users[String(userId)]=fallbackEntries;
      const fallbackActive=committedOverrides.has(String(userId))||userEntries(previousOwnershipFallback,userId).reasonCode==="CACHE_STATE_INDETERMINATE";
      if(fallbackActive)ownershipFallback.users[String(userId)]={ownItems:structuredClone(state.ownItems),entries:structuredClone(entries),reasonCode:"CACHE_STATE_INDETERMINATE",updatedAt:Date.now()};
      try{if(!(await fenced(expected)))throw Error("stale record");await putJson(CATALOGUE_KEY,cat);await putJson(DENIAL_KEY,denied);await putJson(DENIAL_FALLBACK_KEY,fallback);if(fallbackActive)await putJson(OWN_FALLBACK_KEY,ownershipFallback);if(!(await fenced(expected)))throw Error("stale record");if(committedOverrides.has(String(userId)))committedOverrides.set(String(userId),{ownItems:state.ownItems,entries:structuredClone(entries)});clearVolatileDenial(userId,metadata.objectId);clearLimitReason(userId,metadata.objectId);return true;}
      catch{
        let restored=true;try{await putJson(CATALOGUE_KEY,previousCat);}catch{restored=false;}try{await putJson(DENIAL_KEY,previousDenied);}catch{restored=false;}try{await putJson(DENIAL_FALLBACK_KEY,previousFallback);}catch{restored=false;}if(fallbackActive)try{await putJson(OWN_FALLBACK_KEY,previousOwnershipFallback);}catch{restored=false;}
        if(!restored){rememberVolatileDenial(userId,metadata.objectId);rememberLimitReason(userId,metadata.objectId);const safeFallback=await fallbackDenials(),safeEntries=userEntries(safeFallback,userId);safeEntries[String(metadata.objectId)]={epoch:Number(expected?.epoch),generation:expected?.generation,deniedAt:Date.now()};safeFallback.users[String(userId)]=safeEntries;try{await putJson(DENIAL_FALLBACK_KEY,safeFallback);}catch{}committedOverrides.set(String(userId),{ownItems:state.ownItems,entries:state.entries});}
        return false;
      }
    });
  }
  async function revoke(userId, objectId, expected=null) { return serialize(async()=>{
    if(!(await fenced(expected)))return false;
    rememberVolatileDenial(userId,objectId);
    const denied=await denials(),deniedEntries=userEntries(denied,userId);deniedEntries[String(objectId)]={epoch:Number(expected?.epoch),generation:expected?.generation,deniedAt:Date.now()};denied.users[String(userId)]=deniedEntries;
    try{await putJson(DENIAL_KEY,denied);}catch{const fallback=await fallbackDenials(),fallbackEntries=userEntries(fallback,userId);fallbackEntries[String(objectId)]={epoch:Number(expected?.epoch),generation:expected?.generation,deniedAt:Date.now()};fallback.users[String(userId)]=fallbackEntries;try{await putJson(DENIAL_FALLBACK_KEY,fallback);}catch{rememberLimitReason(userId,objectId);return {ok:false,limited:true,reasonCode:"DENIAL_PERSISTENCE_FAILED"};}}
    if(!(await fenced(expected)))return false;
    try{const cat=await catalogue(),entries=userEntries(cat,userId);if(entries[String(objectId)])entries[String(objectId)].revoked=true;cat.users[String(userId)]=entries;await putJson(CATALOGUE_KEY,cat);}catch{}
    return true;
  }); }
  async function access(userId,objectId,expected=null){await reconcile(userId,expected);if(volatileDenied(userId,objectId)||userEntries(await denials(),userId)[String(objectId)]||userEntries(await fallbackDenials(),userId)[String(objectId)])return false;const state=await coherentState(userId),value=state.entries[String(objectId)];if(!value)return false;if(value.revoked)return false;if(value.source==="substitution"&&Number(value.expiresAt)<=Date.now()){const p=await protection(userId);return p.known&&p.ids.has(Number(objectId));}return true;}
  async function cacheCheckAllowed(userId,objectId){
    if(volatileDenied(userId,objectId)||userEntries(await denials(),userId)[String(objectId)]||userEntries(await fallbackDenials(),userId)[String(objectId)])return false;
    const state=await coherentState(userId);if(state.ownItems[String(objectId)])return true;
    const value=state.entries[String(objectId)];if(!value||value.revoked)return false;
    if(value.source==="substitution"&&Number(value.expiresAt)<=Date.now()){const state=await protection(userId);return state.known&&state.ids.has(Number(objectId));}
    return true;
  }
  const docNames = (names,userId) => names.filter(n=>(n.startsWith("fmonitor2-field-doc-")||n.startsWith("fmonitor2-checklist-doc-"))&&n.endsWith(`-${userId}`));
  async function findDocument(userId,path) { for(const name of docNames(await caches.keys(),userId).reverse()){const response=await(await caches.open(name)).match(path);if(response)return {name,response};}return null; }
  async function findFieldDocument(userId,path){for(const name of docNames(await caches.keys(),userId).filter(value=>value.startsWith("fmonitor2-field-doc-")).reverse()){const response=await(await caches.open(name)).match(path);if(response)return {name,response};}return null;}
  const aliases=objectId=>[`/pilot/construction-control/objects/${objectId}/checklist`,`/pilot/objects/${objectId}/checklist`];
  async function findObjectDocument(userId,objectId,preferred=null){for(const path of [...new Set([preferred,...aliases(objectId)].filter(Boolean))]){const found=await findDocument(userId,path);if(found)return {...found,path};}return null;}
  async function removeDocument(userId,path){let changed=false;const id=Number(String(path).match(/\/objects\/(\d+)\/checklist$/)?.[1]),paths=id?aliases(id):[path];for(const name of docNames(await caches.keys(),userId)){const cache=await caches.open(name);for(const candidate of paths){const old=await cache.match(candidate);if(old&&await cache.delete(candidate)){adjustPhysical(-(await responseBytes(old)));changed=true;}}}if(changed)await markMutation();}
  const decodeRecord=value=>{try{return JSON.parse(new TextDecoder().decode(Uint8Array.from(atob(value),x=>x.charCodeAt(0))));}catch{return null;}};
  async function reconcile(userId,expected=null){return serialize(async()=>{
    if(expected&&!(await fenced(expected)))return {};await adoptPreparedOwn(userId,expected);const cat=await catalogue(),state=await coherentStateLocked(userId),entries=state.entries,own=state.ownItems,ownRoot=await ownSets(),explicitOwn=hasUserRecord(ownRoot,userId)||await ownershipIsIndeterminate(userId),denied={...userEntries(await denials(),userId),...userEntries(await fallbackDenials(),userId)};let changed=false;
    for(const name of docNames(await caches.keys(),userId)){const cache=await caches.open(name);for(const request of await cache.keys()){const response=await cache.match(request),writeId=response?.headers.get("X-FMonitor-Cache-Write");if(writeId&&pendingRollbacks.has(writeId))continue;const record=decodeRecord(response?.headers.get("X-FMonitor-Field-Record")||"");if(!record||Number(record.userId)!==Number(userId)||!Number.isInteger(Number(record.objectId)))continue;const id=String(Number(record.objectId));if(denied[id]||(record.source==="own"&&explicitOwn&&!own[id]))continue;const previous=entries[id];if(!previous||Number(record.confirmedAt||0)>Number(previous.lastConfirmedAt||0)){entries[id]={...record.metadata,source:own[id]?"own":record.source,expiresAt:own[id]?null:record.expiresAt,revoked:false,lastConfirmedAt:record.confirmedAt,lastOpenedAt:record.confirmedAt};changed=true;}}}
    for(const [id,item]of Object.entries(own))if(!entries[id]&&await findObjectDocument(userId,Number(id),item.checklistUrl)){entries[id]={...item,source:"own",expiresAt:null,revoked:false,lastConfirmedAt:null,lastOpenedAt:null};changed=true;}
    if(changed&&(!expected||await fenced(expected))){if(await ownershipIsIndeterminate(userId)){const fallbacks=await ownershipFallbacks();fallbacks.users[String(userId)]={ownItems:structuredClone(own),entries:structuredClone(entries),reasonCode:"CACHE_STATE_INDETERMINATE",updatedAt:Date.now()};await putJson(OWN_FALLBACK_KEY,fallbacks);if(committedOverrides.has(String(userId)))committedOverrides.set(String(userId),{ownItems:structuredClone(own),entries:structuredClone(entries)});}else{cat.users[String(userId)]=entries;await putJson(CATALOGUE_KEY,cat);}}return entries;
  });}
  async function status(userId,expected=null) {
    await reconcile(userId,expected);const [state, protect, store, usage]=await Promise.all([coherentState(userId),protection(userId),estimate(),physical()]);
    const entries=state.entries, ownItems=state.ownItems, deniedItems={...userEntries(await denials(),userId),...userEntries(await fallbackDenials(),userId)},items=[];
    for(const [id,value] of Object.entries(entries)){
      const protectedObject=protect.known&&protect.ids.has(Number(id));
      if(value.revoked||deniedItems[id]||volatileDenied(userId,id))continue;
      if(value.source!=="own"&&Number(value.expiresAt)<=Date.now()&&!protectedObject)continue;
      const path=value.checklistUrl; if(!path||!(await findObjectDocument(userId,Number(id),path)))continue;
      items.push({...value,source:ownItems[id]?"own":"substitution",expiresAt:ownItems[id]?null:value.expiresAt,protected:protectedObject});
    }
    const reasonCode=await ownershipIsIndeterminate(userId)?"CACHE_STATE_INDETERMINATE":(volatileLimitReasons.get(String(userId))?.size?"DENIAL_PERSISTENCE_FAILED":null);
    const pendingWriteCount=[...pendingRollbacks.values()].filter(value=>Number(value.userId)===Number(userId)).length;
    return {items,budgetBytes:BUDGET,reserveBytes:RESERVE,cacheBytes:usage.bytes,storageVerified:store.verified,pendingWriteCount,...(reasonCode?{limited:true,reasonCode}:{})};
  }
  async function admit(userId,path,response,expected=null,proof=null) {
    return serialize(async()=>{
      const body=await response.clone().arrayBuffer(), replacement=await(await caches.open(globalThis.FMonitorFieldCacheTarget(userId))).match(path);
      const old=replacement?await responseBytes(replacement):0, growth=body.byteLength-old;let current=await physical(), storage=await estimate();
      const fits=()=>current.bytes+growth<=BUDGET&&(!storage.verified||storage.quota-storage.usage-growth>=RESERVE);
      if(!fits()){
        const protect=await protection(userId), cat=await catalogue(), state=await coherentState(userId,true),own=state.ownItems,entries=state.entries;
        if(await ownershipIsIndeterminate(userId))return {ok:false,reasonCode:"CACHE_STATE_INDETERMINATE"};
        if(protect.known){for(const [id,item] of Object.entries(entries).sort((a,b)=>Number(a[1].lastOpenedAt||0)-Number(b[1].lastOpenedAt||0))){const openIds=await globalThis.FMonitorFieldOpenObjectIds?.()||new Set();if(own[id]||protect.ids.has(Number(id))||openIds.has(Number(id))||item.checklistUrl===path)continue;const fresh=await protection(userId);if(!fresh.known||fresh.ids.has(Number(id))||!(await fenced(expected)))break;await removeDocument(userId,item.checklistUrl);delete entries[id];current=await physical();storage=await estimate();if(fits())break;}if(!(await fenced(expected)))return {ok:false,reasonCode:"CACHE_CONTEXT_STALE"};cat.users[String(userId)]=entries;await putJson(CATALOGUE_KEY,cat);}
      }
      if(!fits())return {ok:false,reasonCode:"CACHE_BUDGET_EXHAUSTED"};
      if(!(await fenced(expected)))return {ok:false,reasonCode:"CACHE_CONTEXT_STALE"};
      const headers=new Headers(response.headers),writeId=crypto.randomUUID?.()||`${Date.now()}-${Math.random()}`;headers.set("X-FMonitor-Cache-Write",writeId);if(proof)headers.set("X-FMonitor-Field-Record",btoa(String.fromCharCode(...new TextEncoder().encode(JSON.stringify(proof)))));
      const target=await caches.open(globalThis.FMonitorFieldCacheTarget(userId));
      try {
        await putVerified(target,path,new Response(body,{status:response.status,statusText:response.statusText,headers}));
        const readback=await target.match(path);if(readback?.headers.get("X-FMonitor-Cache-Write")!==writeId)throw Error("cache write token mismatch");
        adjustPhysical(growth);await markMutation();
      }catch{
        try{if(replacement)await putVerified(target,path,replacement.clone());else await target.delete(path);}catch{}
        invalidate();try{await markMutation();}catch{}
        return {ok:false,reasonCode:"CACHE_BUDGET_EXHAUSTED"};
      }
      pendingRollbacks.set(writeId,{userId:Number(userId),path,previous:replacement?.clone()||null,previousBytes:old,newBytes:body.byteLength});
      return {ok:true,contentHash:await globalThis.FMonitorFieldCacheHash(new Response(body)),bytes:body.byteLength,writeId};
    });
  }
  async function commitWrite(writeId){pendingRollbacks.delete(writeId);return true;}
  async function deleteIfWrite(userId,path,writeId){return serialize(async()=>{
    const rollback=pendingRollbacks.get(writeId),cache=await caches.open(globalThis.FMonitorFieldCacheTarget(userId)),response=await cache.match(path);
    if(!response||response.headers.get("X-FMonitor-Cache-Write")!==writeId){pendingRollbacks.delete(writeId);return false;}
    const current=response.clone(),currentBytes=await responseBytes(response);
    try{
      if(rollback?.previous){await putVerified(cache,path,rollback.previous.clone());adjustPhysical(rollback.previousBytes-currentBytes);}
      else if(await cache.delete(path))adjustPhysical(-currentBytes);
      await markMutation();pendingRollbacks.delete(writeId);return true;
    }catch{
      try{await putVerified(cache,path,current);}catch{}
      pendingRollbacks.delete(writeId);invalidate();return false;
    }
  });}
  async function maintain(userId,currentGeneration,previousGeneration,liveGenerations,unknownLive,openObjectIds=new Set(),expected=null,alreadyLocked=false) {
    const work=async()=>{
      if(await ownershipIsIndeterminate(userId))return {ok:true,limited:true,reasonCode:"CACHE_STATE_INDETERMINATE"};
      const protect=await protection(userId); if(!protect.known)return {ok:true,limited:true};
      const cat=await catalogue(),state=await coherentState(userId,true),entries=state.entries,own=state.ownItems;
      for(const [id,item] of Object.entries(entries)){
        if(item.revoked||(!own[id]&&Number(item.expiresAt)<=Date.now()&&!protect.ids.has(Number(id)))){const fresh=await protection(userId);if(!fresh.known||!(await fenced(expected)))return {ok:true,limited:true};if(!fresh.ids.has(Number(id))&&!openObjectIds.has(Number(id))){await removeDocument(userId,item.checklistUrl);delete entries[id];}}
      }
      if(!(await fenced(expected)))return {ok:true,limited:true};cat.users[String(userId)]=entries;await putJson(CATALOGUE_KEY,cat);
      if(!unknownLive){
        const keep=new Set([currentGeneration,previousGeneration,...liveGenerations].filter(Boolean));
        const names=await caches.keys(), currentDocs=await caches.open(globalThis.FMonitorFieldCacheTarget(userId));
        for(const item of Object.values(entries)){if(await currentDocs.match(item.checklistUrl))continue;const found=await findFieldDocument(userId,item.checklistUrl);if(found){try{const promoted=globalThis.FMonitorFieldCachePromote?await globalThis.FMonitorFieldCachePromote(found.response.clone()):found.response.clone(), bytes=await responseBytes(promoted);if(!(await capacity(bytes)).ok)throw Error("promotion budget exhausted");await putVerified(currentDocs,item.checklistUrl,promoted);adjustPhysical(bytes);await markMutation();}catch{await currentDocs.delete(item.checklistUrl);invalidate();await markMutation();keep.add(found.name.match(/[a-f0-9]{64}/)?.[0]);}}}
        for(const name of names){const generation=name.match(/^fmonitor2-field-doc-([a-f0-9]{64})/)?.[1];if(!generation||keep.has(generation)||!name.endsWith(`-${userId}`))continue;if(!(await fenced(expected)))return {ok:true,limited:true};await caches.delete(name);invalidate();await markMutation();}
      }
      return {ok:true};
    };
    return alreadyLocked?work():serialize(work);
  }
  async function consolidateUser(userId,keepGenerations,expected,alreadyLocked=false){
    const work=async()=>{
    if(await ownershipIsIndeterminate(userId))return false;
    const protectedState=await protection(userId);if(!protectedState.known)return false;
    const names=docNames(await caches.keys(),userId).filter(name=>name.startsWith("fmonitor2-field-doc-")), targetName=globalThis.FMonitorFieldCacheTarget(userId), target=await caches.open(targetName);
    for(const name of names){if(name===targetName||keepGenerations.has(name.match(/^fmonitor2-field-doc-([a-f0-9]{64})/)?.[1]))continue;const source=await caches.open(name);for(const request of await source.keys()){if(await target.match(request))continue;const response=await source.match(request);if(!response)return false;const promoted=globalThis.FMonitorFieldCachePromote?await globalThis.FMonitorFieldCachePromote(response):response, bytes=await responseBytes(promoted);if(!(await capacity(bytes)).ok||!(await fenced(expected)))return false;try{await putVerified(target,request,promoted);adjustPhysical(bytes);await markMutation();}catch{await target.delete(request);invalidate();await markMutation();return false;}}}
    for(const name of names){const generation=name.match(/^fmonitor2-field-doc-([a-f0-9]{64})/)?.[1];if(name!==targetName&&!keepGenerations.has(generation)){if(!(await fenced(expected)))return false;await caches.delete(name);invalidate();await markMutation();}}
    return true;
    };
    return alreadyLocked?work():serialize(work);
  }
  async function consolidateLegacyUser(userId,openObjectIds,expected,alreadyLocked=false){
    const work=async()=>{
      if(await ownershipIsIndeterminate(userId))return false;
      const protectedState=await protection(userId);if(!protectedState.known||!(await fenced(expected)))return false;
      const own=userEntries(await ownSets(),userId).items||{},entries=userEntries(await catalogue(),userId),names=docNames(await caches.keys(),userId).filter(name=>name.startsWith("fmonitor2-checklist-doc-"));
      const target=await caches.open(globalThis.FMonitorFieldCacheTarget(userId));
      for(const name of names){
        const source=await caches.open(name);
        for(const request of await source.keys()){
          const objectId=Number(new URL(request.url).pathname.match(/\/objects\/(\d+)\/checklist$/)?.[1]);
          const required=Number.isInteger(objectId)&&(Boolean(own[String(objectId)]||entries[String(objectId)])||protectedState.ids.has(objectId)||openObjectIds.has(objectId));
          if(!required)continue;
          const response=await source.match(request);if(!response)return false;
          const promoted=globalThis.FMonitorFieldCachePromote?await globalThis.FMonitorFieldCachePromote(response.clone()):response.clone(),bytes=await responseBytes(promoted);
          const previous=await target.match(request);
          try{
            if(!(await capacity(previous?Math.max(0,bytes-await responseBytes(previous)):bytes)).ok||!(await fenced(expected)))return false;
            await putVerified(target,request,promoted);adjustPhysical(bytes-(previous?await responseBytes(previous):0));await markMutation();
          }catch{
            try{if(previous)await target.put(request,previous);else await target.delete(request);}catch{}
            invalidate();return false;
          }
        }
        if(!(await fenced(expected)))return false;
        await caches.delete(name);invalidate();await markMutation();
      }
      return true;
    };
    return alreadyLocked?work():serialize(work);
  }
  globalThis.FMonitorFieldCache=Object.freeze({BUDGET,RESERVE,TTL,setOwn,ownItem,record,revoke,access,cacheCheckAllowed,status,admit,commitWrite,deleteIfWrite,maintain,findDocument,findObjectDocument,protection,physical,cacheBytes,capacity,consolidateUser,consolidateLegacyUser,invalidate,reconcile,exclusive:serialize,markMutation});
})();
