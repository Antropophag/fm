// Execute the production worker and its exact local script dependencies in one VM.
import vm from 'node:vm';
import fs from 'node:fs';
const root=new URL('../../app/YiiRuntime/Assets/',import.meta.url);
export default {runInNewContext(source,sandbox){
 for(const [key,value] of Object.entries({atob,btoa,TextDecoder,structuredClone}))if(!(key in sandbox))sandbox[key]=value;
 const context=vm.createContext(sandbox);
 context.importScripts=(...urls)=>{for(const raw of urls){const url=new URL(raw,sandbox.self.location);if(url.origin!==sandbox.self.location.origin||!/^\/pilot\/assets\/field-offline-cache\.js$/.test(url.pathname))throw Error('Unexpected worker dependency '+raw);vm.runInContext(fs.readFileSync(new URL('field-offline-cache.js',root),'utf8'),context);}};
 if(context.self)context.self.importScripts=context.importScripts;
 return vm.runInContext(source.replaceAll('__FMONITOR_CACHE_MODULE__','/pilot/assets/field-offline-cache.js?v=fixture'),context);
}};
