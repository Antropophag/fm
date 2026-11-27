(() => {
  "use strict";

  const DB_NAME = "fmonitor2-fast-pilot";
  const DB_VERSION = 3;
  const STORE_DEFINITIONS = {
    meta: null,
    operations: "id",
    photoBlobs: "id",
    actorState: "deviceInstallationId",
    preparations: "key",
    preparedDocuments: "key",
    leases: "scope",
    scopeState: "scope",
  };
  const ACTIVE = new Set(["queued", "sending", "retryable_error", "uncertain"]);
  const TERMINAL = new Set(["accepted", "discarded", "superseded"]);
  const LEGACY_LOCAL_FIELDS = new Set([
    "id", "scope", "status", "message", "previewUrl", "localPredecessorId",
    "localBatchId", "localBatchSequence", "acceptedRevision", "uploadProgress",
    "uploadStage", "sequence", "wire", "attempted", "attemptCount",
    "nextAttemptAt", "receipt", "reasonCode", "supersededBy", "storageError",
    "createdAt", "updatedAt", "userId", "objectId", "savedBlob",
  ]);

  let databasePromise;
  let blocked = false;
  const listeners = new Set();
  const actorListeners = new Set();
  const actorChannel = "BroadcastChannel" in globalThis ? new BroadcastChannel("fm2-field-actor") : null;
  if (actorChannel) actorChannel.onmessage = (event) => {
    for (const listener of actorListeners) listener(event.data);
  };

  const fallbackError = (code, message) => {
    const error = new Error(message);
    error.code = code;
    return error;
  };
  const request = (value) => new Promise((resolve, reject) => {
    value.onsuccess = () => resolve(value.result);
    value.onerror = () => reject(value.error || fallbackError("LOCAL_STORAGE_REQUEST_FAILED", "IndexedDB request failed"));
  });
  const complete = (transaction) => new Promise((resolve, reject) => {
    transaction.oncomplete = resolve;
    transaction.onabort = () => reject(transaction.error || fallbackError("LOCAL_STORAGE_TRANSACTION_ABORTED", "IndexedDB transaction aborted"));
    transaction.onerror = () => {};
  });
  const clone = (value) => value == null ? value : structuredClone(value);
  const notifyBlocked = (message) => {
    blocked = true;
    for (const listener of listeners) listener(message);
  };
  const actorEvent = (value) => ({
    deviceInstallationId: value.deviceInstallationId,
    activeUserId: value.activeUserId,
    epoch: value.epoch,
    tombstoned: Boolean(value.tombstonedAt),
  });
  const notifyActor = (value) => {
    const event = actorEvent(value);
    for (const listener of actorListeners) listener(event);
    actorChannel?.postMessage(event);
  };
  async function requireActor(tx, expected) {
    if (!expected?.deviceInstallationId || !Number.isInteger(Number(expected.epoch))) throw new Error("Actor fence is required");
    const actor = await request(tx.objectStore("actorState").get(expected.deviceInstallationId));
    if (!actor || actor.tombstonedAt || Number(actor.activeUserId) !== Number(expected.activeUserId) || Number(actor.epoch) !== Number(expected.epoch)) {
      const error = new Error("Учётная запись изменилась; операция остановлена"); error.code = "ACTOR_CHANGED"; throw error;
    }
    return actor;
  }

  function open() {
    if (databasePromise) return databasePromise;
    databasePromise = new Promise((resolve, reject) => {
      const opening = indexedDB.open(DB_NAME, DB_VERSION);
      opening.onupgradeneeded = () => {
        const db = opening.result;
        for (const [name, keyPath] of Object.entries(STORE_DEFINITIONS)) {
          if (!db.objectStoreNames.contains(name)) {
            db.createObjectStore(name, keyPath ? { keyPath } : undefined);
          }
        }
      };
      let blockedUpgrade = false;
      opening.onblocked = () => {
        blockedUpgrade = true;
        notifyBlocked("Закройте старые вкладки ФМ и обновите страницу");
        databasePromise = undefined;
        reject(fallbackError("LOCAL_STORAGE_BLOCKED", "Закройте старые вкладки ФМ и обновите страницу"));
      };
      opening.onerror = () => {
        databasePromise = undefined;
        reject(opening.error || fallbackError("LOCAL_STORAGE_OPEN_FAILED", "Локальное хранилище недоступно"));
      };
      opening.onsuccess = () => {
        const db = opening.result;
        if (blockedUpgrade) { db.close(); return; }
        db.onversionchange = () => {
          db.close();
          databasePromise = undefined;
          notifyBlocked("Доступно обновление. Обновите эту вкладку, чтобы продолжить");
        };
        blocked = false;
        resolve(db);
      };
    });
    return databasePromise;
  }

  async function transaction(names, mode, work) {
    const db = await open();
    if (blocked && mode === "readwrite") throw fallbackError("LOCAL_STORAGE_BLOCKED", "Обновите вкладку перед сохранением");
    let tx;
    try { tx = db.transaction(names, mode, { durability: mode === "readwrite" ? "strict" : "default" }); }
    catch { tx = db.transaction(names, mode); }
    const finished = complete(tx);
    let result;
    try {
      result = await work(tx);
    } catch (error) {
      try { tx.abort(); } catch {}
      await finished.catch(() => {});
      throw error;
    }
    await finished;
    return result;
  }

  async function deviceId() {
    return transaction(["meta"], "readwrite", async (tx) => {
      const store = tx.objectStore("meta");
      let id = await request(store.get("deviceInstallationId"));
      if (!id) {
        if (!globalThis.crypto?.randomUUID) throw new Error("Безопасное локальное сохранение недоступно");
        id = crypto.randomUUID();
        store.put(id, "deviceInstallationId");
      }
      return id;
    });
  }

  const actorState = (deviceInstallationId) => transaction(["actorState"], "readonly",
    (tx) => request(tx.objectStore("actorState").get(deviceInstallationId)));
  async function putActorState(value) {
    if (!value?.deviceInstallationId) throw new TypeError("deviceInstallationId is required");
    const result = await transaction(["actorState"], "readwrite", (tx) => {
      tx.objectStore("actorState").put(clone(value));
      return value;
    });
    notifyActor(value);
    return result;
  }
  async function updateActorState(deviceInstallationId, updater) {
    const result = await transaction(["actorState"], "readwrite", async (tx) => {
      const store = tx.objectStore("actorState");
      const previous = await request(store.get(deviceInstallationId));
      const next = updater(clone(previous) || { deviceInstallationId, epoch: 0 });
      if (!next || next.deviceInstallationId !== deviceInstallationId) throw new Error("Invalid actor state transition");
      store.put(clone(next));
      return next;
    });
    notifyActor(result);
    return result;
  }
  const metadataGet = (storeName, key) => transaction([storeName], "readonly",
    (tx) => request(tx.objectStore(storeName).get(key)));
  const metadataPut = (storeName, value) => transaction([storeName], "readwrite", (tx) => {
    tx.objectStore(storeName).put(clone(value));
    return value;
  });
  const metadataDelete = (storeName, key) => transaction([storeName], "readwrite", (tx) => {
    tx.objectStore(storeName).delete(key);
  });
  async function fencedMetadataPut(storeName, value, expectedActor) {
    return transaction([storeName, "actorState"], "readwrite", async (tx) => {
      const actor = await request(tx.objectStore("actorState").get(expectedActor.deviceInstallationId));
      if (!actor || actor.activeUserId !== expectedActor.activeUserId || actor.epoch !== expectedActor.epoch || actor.tombstonedAt) {
        throw new Error("Сеанс пользователя изменился; устаревшие данные не сохранены");
      }
      tx.objectStore(storeName).put(clone(value));
      return value;
    });
  }
  async function transitionActor(deviceInstallationId, activeUserId, tombstone = false) {
    return updateActorState(deviceInstallationId, (previous) => ({
      deviceInstallationId,
      activeUserId: tombstone ? null : Number(activeUserId),
      epoch: (Number(previous?.epoch) || 0) + 1,
      tombstonedAt: tombstone ? Date.now() : null,
      confirmedAt: tombstone ? null : Date.now(),
      loginProof: null,
      lastLoginNonce: previous?.loginProof?.nonce || previous?.lastLoginNonce || null,
    }));
  }
  async function confirmLogin(deviceInstallationId, activeUserId, nonce) {
    if (!nonce) throw new Error("Login proof nonce is required");
    return updateActorState(deviceInstallationId, (previous) => {
      if (previous?.lastLoginNonce === String(nonce)) throw new Error("Login proof already used");
      if (previous?.loginProof?.nonce === String(nonce)) {
        if (!previous.tombstonedAt && Number(previous.activeUserId) === Number(activeUserId) &&
            Number(previous.loginProof.userId) === Number(activeUserId) && !previous.loginProof.consumedAt) return previous;
        throw new Error("Login proof already used");
      }
      return {
        deviceInstallationId, activeUserId: Number(activeUserId),
        epoch: (Number(previous?.epoch) || 0) + 1, tombstonedAt: null, confirmedAt: Date.now(),
        loginProof: { userId: Number(activeUserId), nonce: String(nonce), confirmedAt: Date.now(), consumedAt: null },
        lastLoginNonce: previous?.lastLoginNonce || null,
      };
    });
  }
  async function consumeLoginProof(deviceInstallationId, activeUserId) {
    return updateActorState(deviceInstallationId, (previous) => {
      if (!previous || previous.tombstonedAt || Number(previous.activeUserId) !== Number(activeUserId) ||
          Number(previous.loginProof?.userId) !== Number(activeUserId) || previous.loginProof?.consumedAt) {
        throw new Error("Подтверждённый вход не найден");
      }
      return { ...previous, loginProof: { ...previous.loginProof, consumedAt: Date.now() } };
    });
  }
  async function listPreparedDocuments(preparationKey) {
    return transaction(["preparedDocuments"], "readonly", async (tx) => {
      const rows = await request(tx.objectStore("preparedDocuments").getAll());
      return rows.filter((row) => row.key === preparationKey || row.key?.startsWith(`${preparationKey}:`));
    });
  }

  function scopeFor(userId, installationId, objectId) {
    return `${Number(userId)}:${installationId}:${Number(objectId)}`;
  }

  function legacyWire(operation) {
    const wire = {};
    for (const [key, value] of Object.entries(operation)) {
      if (!LEGACY_LOCAL_FIELDS.has(key)) wire[key] = clone(value);
    }
    return wire;
  }

  function orderOperations(a, b) {
    if (Number.isInteger(a.sequence) || Number.isInteger(b.sequence)) return (a.sequence || 0) - (b.sequence || 0);
    if (a.localBatchId && a.localBatchId === b.localBatchId) {
      const sequence = (a.localBatchSequence || 0) - (b.localBatchSequence || 0);
      if (sequence) return sequence;
    }
    return String(a.deviceTime || "").localeCompare(String(b.deviceTime || "")) || String(a.id).localeCompare(String(b.id));
  }

  async function list(scope, withBlobs = false) {
    const names = withBlobs ? ["operations", "photoBlobs"] : ["operations"];
    return transaction(names, "readonly", async (tx) => {
      const rows = (await request(tx.objectStore("operations").getAll())).filter((row) => row.scope === scope).sort(orderOperations);
      if (withBlobs) {
        const blobs = tx.objectStore("photoBlobs");
        for (const row of rows) {
          if (row.type === "photo_uploaded") row.savedBlob = (await request(blobs.get(row.id)))?.blob || null;
        }
      }
      return rows;
    });
  }

  async function enqueue({ scope, userId, objectId, deviceInstallationId, actor, type, payload, blob, projection }) {
    return transaction(["operations", "photoBlobs", "scopeState", "actorState"], "readwrite", async (tx) => {
      await requireActor(tx, actor);
      const operations = tx.objectStore("operations");
      const blobs = tx.objectStore("photoBlobs");
      const states = tx.objectStore("scopeState");
      const rows = (await request(operations.getAll())).filter((row) => row.scope === scope).sort(orderOperations);
      const state = await request(states.get(scope)) || { scope, nextSequence: 1 };
      const currentProjection = state.projection && Number(state.projection.revision || 0) >= Number(projection?.revision || 0) ? state.projection : projection;
      if (type === "section_completed") {
        const sectionId = Number(payload.sectionId);
        const completed = currentProjection?.completedSections;
        const alreadyCompleted = Array.isArray(completed)
          ? completed.map(Number).includes(sectionId)
          : Boolean(completed && Object.prototype.hasOwnProperty.call(completed, String(sectionId)));
        if (alreadyCompleted) {
          const error = new Error("Раздел уже принят сервером");
          error.code = "SECTION_ALREADY_COMPLETED";
          throw error;
        }
        const existing = rows.find((row) => row.type === "section_completed" && Number(row.sectionId) === sectionId &&
          ["queued", "sending", "uncertain", "retryable_error", "auth_required"].includes(row.status));
        if (existing) return existing;
      }
      const highest = rows.reduce((value, row) => Math.max(value, Number(row.sequence) || 0), 0);
      const sequence = Math.max(Number(state.nextSequence) || 1, highest + 1);
      const predecessor = [...rows].reverse().find((row) => !TERMINAL.has(row.status));
      if (type === "photo_uploaded") {
        const sectionId = Number(payload.sectionId);
        const server = new Set((currentProjection?.photos || []).filter((photo) => !photo.revoked && Number(photo.sectionId) === sectionId).map((photo) => photo.clientOperationId || `server:${photo.id}`));
        let reserved = server.size;
        for (const row of rows) {
          if (row.type !== "photo_uploaded" || Number(row.sectionId) !== sectionId || ["accepted", "discarded", "superseded", "rejected", "conflict"].includes(row.status)) continue;
          reserved++;
        }
        if (reserved >= 10) {
          const error = new Error("Можно сохранить не больше 10 фотографий");
          error.code = "PHOTO_LIMIT_REACHED";
          throw error;
        }
        if (!(blob instanceof Blob)) throw new TypeError("Photo blob is required");
      }
      const id = crypto.randomUUID();
      const clientOperationId = crypto.randomUUID();
      const operation = {
        id, clientOperationId, deviceInstallationId, scope, userId: Number(userId), objectId: Number(objectId),
        type, status: "queued", deviceTime: new Date().toISOString(), sequence,
        localPredecessorId: predecessor?.clientOperationId || null, baseRevision: Number(currentProjection?.revision || 0), wire: null, attempted: false,
        attemptCount: 0, nextAttemptAt: null, createdAt: Date.now(), updatedAt: Date.now(), ...clone(payload),
      };
      operations.put(operation);
      if (blob) blobs.put({ id, blob });
      states.put({ ...state, scope, projection: clone(currentProjection), nextSequence: sequence + 1 });
      return operation;
    });
  }

  async function enqueueBatch({ scope, userId, objectId, deviceInstallationId, actor, type, payloads, projection }) {
    if (type !== "item_completed" || !Array.isArray(payloads) || payloads.length < 1) {
      throw new TypeError("Atomic batch supports item_completed operations only");
    }
    return transaction(["operations", "scopeState", "actorState"], "readwrite", async (tx) => {
      await requireActor(tx, actor);
      const operations = tx.objectStore("operations");
      const states = tx.objectStore("scopeState");
      const rows = (await request(operations.getAll())).filter((row) => row.scope === scope).sort(orderOperations);
      const state = await request(states.get(scope)) || { scope, nextSequence: 1 };
      const currentProjection = state.projection && Number(state.projection.revision || 0) >= Number(projection?.revision || 0)
        ? state.projection : projection;
      const highest = rows.reduce((value, row) => Math.max(value, Number(row.sequence) || 0), 0);
      let sequence = Math.max(Number(state.nextSequence) || 1, highest + 1);
      let predecessor = [...rows].reverse().find((row) => !TERMINAL.has(row.status)) || null;
      const saved = [];
      for (const rawPayload of payloads) {
        const payload = clone(rawPayload);
        if (!Number.isInteger(Number(payload?.sectionId)) || !Number.isInteger(Number(payload?.itemId))) {
          throw new TypeError("Batch item sectionId and itemId are required");
        }
        const id = crypto.randomUUID();
        const clientOperationId = crypto.randomUUID();
        const operation = {
          ...payload,
          id, clientOperationId, deviceInstallationId, scope, userId: Number(userId), objectId: Number(objectId),
          type, status: "queued", deviceTime: new Date().toISOString(), sequence,
          localPredecessorId: predecessor?.clientOperationId || null,
          baseRevision: Number(currentProjection?.revision || 0), wire: null, attempted: false,
          attemptCount: 0, nextAttemptAt: null, createdAt: Date.now(), updatedAt: Date.now(),
        };
        operations.put(operation);
        saved.push(operation);
        predecessor = operation;
        sequence++;
      }
      states.put({ ...state, scope, projection: clone(currentProjection), nextSequence: sequence });
      return saved;
    });
  }

  async function migrateLegacy(scope, actor) {
    return transaction(["operations", "photoBlobs", "scopeState", "actorState"], "readwrite", async (tx) => {
      await requireActor(tx, actor);
      const store = tx.objectStore("operations");
      const blobStore = tx.objectStore("photoBlobs");
      const rows = (await request(store.getAll())).filter((row) => row.scope === scope).sort(orderOperations);
      let sequence = 1;
      for (const row of rows) {
        let changed = false;
        if (!Number.isInteger(row.sequence)) { row.sequence = sequence; changed = true; }
        sequence = Math.max(sequence, row.sequence + 1);
        if (row.wire === undefined) {
          const provenUnattemptedBulk = row.status === "queued" && row.baseRevision === null && Boolean(row.localPredecessorId);
          const possiblyAttempted = !provenUnattemptedBulk && ["queued", "sending", "retryable_error", "uncertain"].includes(row.status);
          row.wire = possiblyAttempted ? legacyWire(row) : null;
          row.attempted = possiblyAttempted;
          if (possiblyAttempted && row.status !== "accepted") row.status = "uncertain";
          changed = true;
        }
        if (!Number.isInteger(row.attemptCount)) { row.attemptCount = row.attempted ? 1 : 0; changed = true; }
        if (row.type === "photo_uploaded") {
          const saved = await request(blobStore.get(row.id));
          if (!saved?.blob && row.status !== "accepted") {
            row.status = "storage_recovery";
            row.storageError = "Сохранённый файл фотографии не найден";
            changed = true;
          }
        }
        if (changed) store.put(row);
      }
      const stateStore = tx.objectStore("scopeState");
      const state = await request(stateStore.get(scope)) || { scope };
      stateStore.put({ ...state, scope, nextSequence: Math.max(Number(state.nextSequence) || 1, sequence) });
      return rows;
    });
  }

  async function dispatch(scope, clientOperationId, leaseToken, baseRevision, actor) {
    return transaction(["operations", "leases", "actorState"], "readwrite", async (tx) => {
      await requireActor(tx, actor);
      const lease = await request(tx.objectStore("leases").get(scope));
      if (!lease || lease.token !== leaseToken || lease.expiresAt <= Date.now()) return null;
      const store = tx.objectStore("operations");
      const rows = await request(store.getAll());
      const operation = rows.find((row) => row.scope === scope && row.clientOperationId === clientOperationId);
      if (!operation || operation.status === "accepted") return null;
      if (!operation.wire) {
        if (operation.localPredecessorId) {
          const predecessor = rows.find((row) => row.scope === scope && row.clientOperationId === operation.localPredecessorId);
          if (!predecessor || predecessor.status !== "accepted" || !Number.isInteger(predecessor.acceptedRevision)) return null;
          operation.baseRevision = predecessor.acceptedRevision;
        }
        operation.baseRevision = Number(operation.baseRevision ?? baseRevision);
        operation.wire = legacyWire(operation);
      }
      operation.attempted = true;
      operation.status = "sending";
      operation.attemptCount = (operation.attemptCount || 0) + 1;
      operation.updatedAt = Date.now();
      store.put(operation);
      return operation;
    });
  }

  async function retryState(scope, clientOperationId, leaseToken, patch, actor) {
    return transaction(["operations", "leases", "actorState"], "readwrite", async (tx) => {
      await requireActor(tx, actor);
      const lease = await request(tx.objectStore("leases").get(scope));
      if (!lease || lease.token !== leaseToken || lease.expiresAt <= Date.now()) return null;
      const store = tx.objectStore("operations");
      const rows = await request(store.getAll());
      const operation = rows.find((row) => row.scope === scope && row.clientOperationId === clientOperationId);
      if (!operation) return null;
      Object.assign(operation, clone(patch), { updatedAt: Date.now() });
      store.put(operation);
      return operation;
    });
  }

  async function accept(scope, clientOperationId, leaseToken, result, actor) {
    return transaction(["operations", "photoBlobs", "leases", "scopeState", "actorState"], "readwrite", async (tx) => {
      await requireActor(tx, actor);
      const lease = await request(tx.objectStore("leases").get(scope));
      if (!lease || lease.token !== leaseToken || lease.expiresAt <= Date.now()) return null;
      const store = tx.objectStore("operations");
      const rows = await request(store.getAll());
      const operation = rows.find((row) => row.scope === scope && row.clientOperationId === clientOperationId);
      if (!operation) return null;
      if (operation.type === "photo_uploaded") {
        const photo = result.matchedPhoto;
        if (!photo || photo.sha256 !== operation.sha256 || Number(photo.sectionId) !== Number(operation.sectionId) ||
            Number(photo.size) !== Number(operation.size) || photo.mime !== operation.mime ||
            !(photo.id != null || photo.viewUrl || photo.thumbnailUrl)) throw new Error("Photo receipt does not match durable bytes");
      }
      operation.status = "accepted";
      operation.acceptedRevision = Number(result.revision ?? result.projection?.revision);
      operation.receipt = clone(result);
      operation.reasonCode = null;
      operation.nextAttemptAt = null;
      operation.updatedAt = Date.now();
      store.put(operation);
      const states = tx.objectStore("scopeState");
      const state = await request(states.get(scope)) || { scope };
      state.projection = clone(result.projection || state.projection);
      state.acceptedRevision = operation.acceptedRevision;
      states.put(state);
      if (operation.type === "photo_uploaded") tx.objectStore("photoBlobs").delete(operation.id);
      return operation;
    });
  }

  async function projection(scope) {
    return transaction(["scopeState"], "readonly", async (tx) => (await request(tx.objectStore("scopeState").get(scope)))?.projection || null);
  }
  async function contextRetry(scope, actor) {
    return transaction(["scopeState", "actorState"], "readonly", async (tx) => {
      await requireActor(tx, actor);
      const state = await request(tx.objectStore("scopeState").get(scope));
      return { nextAttemptAt: state?.contextNextAttemptAt || null, attemptCount: state?.contextAttemptCount || 0 };
    });
  }
  async function recordContextFailure(scope, actor) {
    return transaction(["scopeState", "actorState"], "readwrite", async (tx) => {
      await requireActor(tx, actor);
      const store = tx.objectStore("scopeState");
      const state = await request(store.get(scope)) || { scope };
      const attemptCount = (Number(state.contextAttemptCount) || 0) + 1;
      const base = Math.min(60000, 2000 * 2 ** Math.max(0, attemptCount - 1));
      const nextAttemptAt = Date.now() + base * (0.75 + Math.random() * 0.5);
      store.put({ ...state, contextAttemptCount: attemptCount, contextNextAttemptAt: nextAttemptAt });
      return nextAttemptAt;
    });
  }
  async function clearContextFailure(scope, actor) {
    return transaction(["scopeState", "actorState"], "readwrite", async (tx) => {
      await requireActor(tx, actor);
      const store = tx.objectStore("scopeState");
      const state = await request(store.get(scope)) || { scope };
      store.put({ ...state, contextAttemptCount: 0, contextNextAttemptAt: null });
    });
  }
  async function reconcileAcceptedPhotos(scope, currentProjection, actor) {
    const photos = new Map((currentProjection?.photos || []).map((photo) => [photo.clientOperationId, photo]));
    return transaction(["operations", "photoBlobs", "actorState"], "readwrite", async (tx) => {
      await requireActor(tx, actor);
      if (!photos.size) return 0;
      const rows = (await request(tx.objectStore("operations").getAll())).filter((row) => row.scope === scope && row.type === "photo_uploaded" && row.status === "accepted");
      let removed = 0;
      for (const row of rows) {
        const server = photos.get(row.clientOperationId);
        if (server && server.sha256 === row.sha256 && Number(server.sectionId) === Number(row.sectionId) &&
            Number(server.size) === Number(row.size) && server.mime === row.mime &&
            (server.id != null || server.viewUrl || server.thumbnailUrl)) {
          tx.objectStore("photoBlobs").delete(row.id); removed++;
        }
      }
      return removed;
    });
  }
  async function blob(id) {
    return transaction(["photoBlobs"], "readonly", async (tx) => (await request(tx.objectStore("photoBlobs").get(id)))?.blob || null);
  }

  async function acquireLease(scope, token, actor, ttl = 45000) {
    return transaction(["leases", "actorState"], "readwrite", async (tx) => {
      await requireActor(tx, actor);
      const store = tx.objectStore("leases");
      const now = Date.now();
      const current = await request(store.get(scope));
      if (current && current.token !== token && current.expiresAt > now && current.acquiredAt <= now + ttl) return false;
      store.put({ scope, token, acquiredAt: current?.token === token ? current.acquiredAt : now, renewedAt: now, expiresAt: now + ttl });
      return true;
    });
  }
  async function renewLease(scope, token, actor, ttl = 45000) {
    return transaction(["leases", "actorState"], "readwrite", async (tx) => {
      await requireActor(tx, actor);
      const store = tx.objectStore("leases");
      const current = await request(store.get(scope));
      if (!current || current.token !== token) return false;
      const now = Date.now();
      store.put({ ...current, renewedAt: now, expiresAt: now + ttl });
      return true;
    });
  }
  async function releaseLease(scope, token) {
    return transaction(["leases"], "readwrite", async (tx) => {
      const store = tx.objectStore("leases");
      const current = await request(store.get(scope));
      if (current?.token === token) store.delete(scope);
    });
  }

  async function discard(scope, clientOperationId, actor) {
    return transaction(["operations", "photoBlobs", "actorState"], "readwrite", async (tx) => {
      await requireActor(tx, actor);
      const operations = tx.objectStore("operations");
      const rows = (await request(operations.getAll())).filter((row) => row.scope === scope);
      const root = rows.find((row) => row.clientOperationId === clientOperationId);
      if (!root || (root.attempted && !["rejected", "conflict"].includes(root.status))) throw new Error("Отправленное действие нельзя удалить без разрешения конфликта");
      if (root.type === "photo_uploaded" && root.sectionId === 0 && root.status === "queued" && !root.attempted) {
        const descendants = new Set([clientOperationId]);
        let found = true;
        while (found) {
          found = false;
          for (const row of rows) if (descendants.has(row.localPredecessorId) && !descendants.has(row.clientOperationId)) {
            descendants.add(row.clientOperationId); found = true;
          }
        }
        for (const row of rows.filter((item) => descendants.has(item.clientOperationId))) {
          if (row.attempted || row.wire) throw new Error("Зависимое действие уже отправлялось и требует отдельного решения");
        }
        for (const successor of rows.filter((row) => row.localPredecessorId === clientOperationId)) {
          successor.localPredecessorId = root.localPredecessorId; operations.put(successor);
        }
        root.status = "discarded"; root.updatedAt = Date.now(); operations.put(root);
        tx.objectStore("photoBlobs").delete(root.id);
        return 1;
      }
      const affected = new Set([clientOperationId]);
      let changed = true;
      while (changed) {
        changed = false;
        for (const row of rows) if (affected.has(row.localPredecessorId) && !affected.has(row.clientOperationId)) {
          if (row.attempted) throw new Error("Зависимое действие уже отправлялось и требует отдельного решения");
          affected.add(row.clientOperationId); changed = true;
        }
      }
      for (const row of rows.filter((item) => affected.has(item.clientOperationId))) {
        row.status = "discarded"; row.updatedAt = Date.now(); operations.put(row);
        if (row.type === "photo_uploaded") tx.objectStore("photoBlobs").delete(row.id);
      }
      return affected.size;
    });
  }

  async function resolve(scope, clientOperationId, baseRevision, actor) {
    return transaction(["operations", "photoBlobs", "scopeState", "actorState"], "readwrite", async (tx) => {
      await requireActor(tx, actor);
      const store = tx.objectStore("operations");
      const rows = (await request(store.getAll())).filter((row) => row.scope === scope).sort(orderOperations);
      const original = rows.find((row) => row.clientOperationId === clientOperationId);
      if (!original || !["conflict", "rejected"].includes(original.status)) throw new Error("Это действие нельзя применить заново");
      const states = tx.objectStore("scopeState");
      const state = await request(states.get(scope)) || { scope, nextSequence: rows.length + 1 };
      const replacement = clone(original);
      replacement.id = crypto.randomUUID(); replacement.clientOperationId = crypto.randomUUID();
      replacement.deviceTime = new Date().toISOString(); replacement.baseRevision = Number(baseRevision);
      replacement.sequence = Number(state.nextSequence) || rows.length + 1;
      replacement.status = "queued"; replacement.wire = null; replacement.attempted = false;
      replacement.attemptCount = 0; replacement.nextAttemptAt = null; replacement.receipt = null;
      replacement.reasonCode = null; replacement.message = ""; replacement.createdAt = Date.now(); replacement.updatedAt = Date.now();
      replacement.localPredecessorId = null;
      delete replacement.supersededBy; delete replacement.acceptedRevision; delete replacement.storageError;
      original.status = "superseded"; original.supersededBy = replacement.clientOperationId; original.updatedAt = Date.now();
      store.put(original); store.put(replacement);
      for (const dependent of rows.filter((row) => row.localPredecessorId === clientOperationId && !row.attempted)) {
        dependent.localPredecessorId = replacement.clientOperationId; store.put(dependent);
      }
      if (original.type === "photo_uploaded") {
        const oldBlob = await request(tx.objectStore("photoBlobs").get(original.id));
        if (!oldBlob?.blob) throw new Error("Сохранённый файл фотографии не найден");
        tx.objectStore("photoBlobs").put({ id: replacement.id, blob: oldBlob.blob });
      }
      states.put({ ...state, nextSequence: replacement.sequence + 1 });
      return replacement;
    });
  }

  globalThis.FMonitorFieldStore = Object.freeze({
    DB_NAME, DB_VERSION, open, transaction, deviceId, scopeFor, list, enqueue, enqueueBatch, migrateLegacy,
    dispatch, retryState, accept, projection, contextRetry, recordContextFailure, clearContextFailure,
    reconcileAcceptedPhotos, blob, acquireLease, renewLease, releaseLease,
    discard, resolve, orderOperations, legacyWire,
    actorState, putActorState, updateActorState,
    transitionActor, confirmLogin, consumeLoginProof,
    getPreparation: (key) => metadataGet("preparations", key),
    putPreparation: (value) => metadataPut("preparations", value),
    putPreparationFenced: (value, expectedActor) => fencedMetadataPut("preparations", value, expectedActor),
    deletePreparation: (key) => metadataDelete("preparations", key),
    getPreparedDocument: (key) => metadataGet("preparedDocuments", key),
    putPreparedDocument: (value) => metadataPut("preparedDocuments", value),
    putPreparedDocumentFenced: (value, expectedActor) => fencedMetadataPut("preparedDocuments", value, expectedActor),
    deletePreparedDocument: (key) => metadataDelete("preparedDocuments", key),
    listPreparedDocuments,
    onBlocked(listener) { listeners.add(listener); return () => listeners.delete(listener); },
    onActorChange(listener) { actorListeners.add(listener); return () => actorListeners.delete(listener); },
    isBlocked: () => blocked,
  });
})();
