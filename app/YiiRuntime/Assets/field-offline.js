(() => {
  "use strict";

  const Store = () => globalThis.FMonitorFieldStore;
  const Sync = () => globalThis.FMonitorFieldSync;
  const Photo = () => globalThis.FMonitorFieldPhoto;
  const sessions = new Set();
  const pendingEngines = new Map();
  const STORAGE_ERROR_NAMES = new Set([
    "AbortError", "InvalidStateError", "NotReadableError",
    "QuotaExceededError", "ReadOnlyError", "UnknownError",
    "VersionError", "SecurityError",
  ]);
  const STORAGE_ERROR_CODES = new Set([
    "LOCAL_STORAGE_OPEN_FAILED", "LOCAL_STORAGE_BLOCKED",
    "LOCAL_STORAGE_TRANSACTION_ABORTED", "LOCAL_STORAGE_REQUEST_FAILED",
  ]);
  function isDurableStoreFailure(error) {
    const cause = error?.cause || error;
    if (error instanceof TypeError || cause instanceof TypeError) return false;
    if (STORAGE_ERROR_CODES.has(error?.code) || STORAGE_ERROR_CODES.has(cause?.code)) return true;
    if (typeof error?.code === "string" && error.code) return false;
    if (typeof cause?.code === "string" && cause.code) return false;
    return typeof DOMException !== "undefined" && cause instanceof DOMException &&
      STORAGE_ERROR_NAMES.has(cause.name);
  }
  function durableWriteError(error) {
    if (!isDurableStoreFailure(error)) return error;
    const storageError = new Error(
      "Не удалось сохранить на устройстве. Повторите попытку; если ошибка повторится, проверьте свободное место.",
      { cause: error },
    );
    storageError.code = error?.code || error?.name || "LOCAL_STORAGE_WRITE_FAILED";
    return storageError;
  }
  class BoundedScheduler {
    constructor(limit) { this.limit = limit; this.active = 0; this.queue = []; }
    run(task) {
      return new Promise((resolve, reject) => { this.queue.push({ task, resolve, reject }); this.drain(); });
    }
    drain() {
      while (this.active < this.limit && this.queue.length) {
        const entry = this.queue.shift(); this.active++;
        Promise.resolve().then(entry.task).then(entry.resolve, entry.reject).finally(() => { this.active--; this.drain(); });
      }
    }
  }
  const pendingScheduler = new BoundedScheduler(2);

  function requireRuntime() {
    if (!Store() || !Sync() || !Photo()) throw new Error("Модуль локального сохранения загружен не полностью");
    if (!globalThis.indexedDB || !globalThis.crypto?.randomUUID || !globalThis.crypto?.subtle) {
      throw new Error("Безопасное локальное сохранение недоступно в этом браузере");
    }
  }

  function bestProjection(initial, durable) {
    if (!durable) return structuredClone(initial || { revision: 0, items: {}, photos: [], completedSections: {} });
    return Number(durable.revision || 0) >= Number(initial?.revision || 0) ? durable : structuredClone(initial);
  }

  async function storageState() {
    const state = { available: true, persisted: null, quota: null, usage: null, message: "" };
    try {
      if (navigator.storage?.persisted) state.persisted = await navigator.storage.persisted();
      if (!state.persisted && navigator.storage?.persist) state.persisted = await navigator.storage.persist();
      if (navigator.storage?.estimate) {
        const estimate = await navigator.storage.estimate(); state.quota = estimate.quota ?? null; state.usage = estimate.usage ?? null;
      }
      if (state.persisted === false) state.message = "Браузер может удалить данные при нехватке места. Не очищайте данные сайта до отправки";
    } catch { state.message = "Не удалось проверить доступное место"; }
    return state;
  }

  class Session {
    constructor(options, actor, scope, projection, storage) {
      this.userId = Number(options.userId); this.objectId = Number(options.objectId);
      this.actor = { ...actor }; this.deviceInstallationId = actor.deviceInstallationId; this.scope = scope;
      this.projection = projection; this.storage = storage; this.connection = { state: navigator.onLine ? "online" : "offline", message: "", nextAttemptAt: null };
      this.onChange = typeof options.onChange === "function" ? options.onChange : () => {};
      this.onClose = typeof options.onClose === "function" ? options.onClose : () => {};
      this.previewUrls = new Set(); this.reviewTokens = new Map();
      this.unsavedPhotos = new Map(); this.unsavedReservations = new Map();
      this.generation = 0; this.operationsRevision = 0; this.closed = false; this.refreshing = null;
      this.syncEngine = Sync().create({ scope, userId: this.userId, objectId: this.objectId, actor: this.actor,
        onState: (connection) => { this.connection = connection; this.emit(); }, onChanged: () => this.refresh() });
      this.stopActorListener = Store().onActorChange((change) => {
        if (change.deviceInstallationId === this.actor.deviceInstallationId &&
            (change.tombstoned || Number(change.activeUserId) !== this.userId || Number(change.epoch) !== Number(this.actor.epoch))) this.close();
      });
      this.online = () => this.sync();
      this.visibility = () => { if (document.visibilityState === "visible") this.sync(); };
      this.pageshow = () => this.sync();
      this.beforeUnload = (event) => {
        if (this.unsavedTotal() < 1) return;
        event.preventDefault();
        event.returnValue = "";
      };
      addEventListener("online", this.online); addEventListener("pageshow", this.pageshow);
      addEventListener("beforeunload", this.beforeUnload);
      document.addEventListener("visibilitychange", this.visibility);
    }

    async refresh() {
      if (this.closed) return [];
      if (this.refreshing) return this.refreshing;
      const revision = this.operationsRevision;
      const candidateUrls = new Set();
      let candidateInstalled = false;
      this.refreshing = (async () => {
        try {
          const operations = await Store().list(this.scope, true);
          for (const operation of operations) if (operation.savedBlob) {
            operation.previewUrl = URL.createObjectURL(operation.savedBlob);
            candidateUrls.add(operation.previewUrl);
            delete operation.savedBlob;
          }
          const durableProjection = await Store().projection(this.scope);
          if (this.closed || revision !== this.operationsRevision) {
            for (const url of candidateUrls) URL.revokeObjectURL(url);
            return this.operations || [];
          }
          const previousUrls = this.previewUrls;
          this.previewUrls = candidateUrls;
          candidateInstalled = true;
          this.projection = bestProjection(this.projection, durableProjection);
          this.operations = operations;
          for (const url of previousUrls) URL.revokeObjectURL(url);
          this.emit();
          return operations;
        } catch (error) {
          if (!candidateInstalled)
            for (const url of candidateUrls) URL.revokeObjectURL(url);
          throw error;
        }
      })().finally(() => { this.refreshing = null; });
      return this.refreshing;
    }

    unsavedTotal() {
      let total = this.unsavedPhotos.size;
      for (const count of this.unsavedReservations.values()) total += count;
      return total;
    }

    unsavedSectionTotal(sectionId) {
      let total = Number(this.unsavedReservations.get(sectionId) || 0);
      for (const entry of this.unsavedPhotos.values())
        if (entry.sectionId === sectionId) total++;
      return total;
    }

    reserveUnsaved(sectionId) {
      if (this.unsavedSectionTotal(sectionId) >= 10) {
        const error = new Error("Можно восстановить не больше 10 несохранённых фотографий в разделе");
        error.code = "UNSAVED_PHOTO_LIMIT";
        throw error;
      }
      this.unsavedReservations.set(
        sectionId,
        Number(this.unsavedReservations.get(sectionId) || 0) + 1,
      );
      this.emitRecovery();
    }

    releaseUnsavedReservation(sectionId) {
      const count = Number(this.unsavedReservations.get(sectionId) || 0);
      if (count <= 1) this.unsavedReservations.delete(sectionId);
      else this.unsavedReservations.set(sectionId, count - 1);
    }

    unsavedMetadata() {
      return [...this.unsavedPhotos.values()].map((entry) => ({
        id: entry.id,
        sectionId: entry.sectionId,
        name: entry.name,
        mime: entry.mime,
        size: entry.size,
        sha256: entry.sha256,
        previewUrl: entry.previewUrl,
        message: entry.message,
        saving: entry.saving,
      }));
    }

    emitRecovery() {
      try { this.emit(); } catch {}
    }

    retainUnsavedPhoto(payload, blob, error) {
      const entry = {
        id: crypto.randomUUID(),
        sectionId: Number(payload.sectionId),
        name: String(payload.originalName || payload.name || "photo"),
        mime: String(payload.mime || blob.type || "application/octet-stream"),
        size: Number(payload.size ?? blob.size),
        sha256: String(payload.sha256 || ""),
        previewUrl: "",
        message: error.message,
        saving: false,
        payload: structuredClone(payload),
        blob,
        flight: null,
        revoked: false,
      };
      this.unsavedPhotos.set(entry.id, entry);
      try { entry.previewUrl = URL.createObjectURL(blob); } catch {}
      this.emitRecovery();
      return entry;
    }

    revokeUnsaved(entry) {
      if (!entry.revoked && entry.previewUrl) URL.revokeObjectURL(entry.previewUrl);
      entry.revoked = true;
      entry.previewUrl = "";
      entry.blob = null;
      entry.payload = null;
    }

    removeUnsaved(entry, emit = true) {
      if (this.unsavedPhotos.get(entry.id) === entry)
        this.unsavedPhotos.delete(entry.id);
      this.revokeUnsaved(entry);
      if (emit && !this.closed) this.emitRecovery();
    }

    rememberCommitted(value) {
      this.operationsRevision++;
      const committed = Array.isArray(value) ? value : [value];
      const operations = [...(this.operations || [])];
      for (const operation of committed) {
        if (!operation) continue;
        const index = operations.findIndex((row) =>
          (operation.clientOperationId && row.clientOperationId === operation.clientOperationId) ||
          (operation.id && row.id === operation.id));
        if (index >= 0) operations[index] = operation;
        else operations.push(operation);
      }
      operations.sort((left, right) => Number(left.sequence || 0) - Number(right.sequence || 0));
      this.operations = operations;
    }

    async afterDurableCommit(operation) {
      if (!this.closed) {
        this.rememberCommitted(operation);
        this.emitRecovery();
        try { await this.refresh(); } catch {}
        if (!this.closed) queueMicrotask(() => this.sync());
      }
      return operation;
    }

    visibleConnection() {
      const connection = { ...this.connection };
      if (this.unsavedPhotos.size > 0) {
        return {
          state: "storage_recovery",
          message: "Есть фотографии, которые не удалось сохранить на устройстве",
          nextAttemptAt: null,
        };
      }
      if (this.unsavedTotal() > 0) return {
        state: "local_saving",
        message: "Сохраняем фотографию на устройстве",
        nextAttemptAt: null,
      };
      if (connection.state !== "online") return connection;
      const pending = (this.operations || []).filter((operation) =>
        !["accepted", "discarded", "superseded"].includes(operation.status));
      if (!pending.length) return connection;
      const statuses = new Set(pending.map((operation) => operation.status));
      if (statuses.has("sending")) return { state: "sending", message: "Отправляем сохранённые изменения", nextAttemptAt: null };
      if (statuses.has("auth_required")) return { state: "auth_required", message: "Войдите снова. Данные сохранены на устройстве", nextAttemptAt: null };
      if (statuses.has("access_denied")) return { state: "access_denied", message: "Доступ отозван. Данные сохранены на устройстве", nextAttemptAt: null };
      if (statuses.has("conflict") || statuses.has("rejected")) return { state: "conflict", message: "Есть действие, требующее решения", nextAttemptAt: null };
      if (statuses.has("storage_recovery")) return { state: "storage_recovery", message: "Локальные данные требуют восстановления", nextAttemptAt: null };
      if (statuses.has("uncertain")) return { state: "uncertain", message: "Результат отправки не подтверждён", nextAttemptAt: null };
      if (statuses.has("retryable_error")) {
        const nextAttemptAt = pending.find((operation) => operation.nextAttemptAt)?.nextAttemptAt || null;
        return { state: "retryable", message: "Связь с ФМ недоступна. Данные сохранены на устройстве", nextAttemptAt };
      }
      return {
        state: "pending",
        message: "Изменения сохранены на устройстве и ожидают подтверждения сервера",
        nextAttemptAt: null,
      };
    }

    emit() {
      if (!this.closed) this.onChange({
        operations: structuredClone(this.operations || []),
        projection: structuredClone(this.projection),
        connection: this.visibleConnection(),
        storage: { ...this.storage },
        unsavedPhotos: this.unsavedMetadata(),
      });
    }
    list() { return this.refresh(); }

    async enqueue(type, payload, blob = null) {
      if (this.closed) throw new Error("Локальная сессия закрыта");
      const recoverablePhoto = type === "photo_uploaded" && blob instanceof Blob;
      const preparedPayload = recoverablePhoto ? structuredClone(payload) : payload;
      const sectionId = recoverablePhoto ? Number(preparedPayload.sectionId) : null;
      const generation = this.generation;
      if (recoverablePhoto) this.reserveUnsaved(sectionId);
      let operation;
      try {
        operation = await Store().enqueue({ scope: this.scope, userId: this.userId, objectId: this.objectId,
          deviceInstallationId: this.deviceInstallationId, actor: this.actor, type, payload: preparedPayload, blob, projection: this.projection });
      } catch (error) {
        if (recoverablePhoto) this.releaseUnsavedReservation(sectionId);
        const reported = durableWriteError(error);
        if (
          recoverablePhoto &&
          !this.closed &&
          generation === this.generation &&
          isDurableStoreFailure(error)
        ) {
          try { this.retainUnsavedPhoto(preparedPayload, blob, reported); }
          catch { this.emitRecovery(); }
        } else if (!this.closed) this.emitRecovery();
        throw reported;
      }
      if (recoverablePhoto) {
        this.rememberCommitted(operation);
        this.releaseUnsavedReservation(sectionId);
      }
      return this.afterDurableCommit(operation);
    }

    async enqueueBatch(type, payloads) {
      if (this.closed) throw new Error("Локальная сессия закрыта");
      let operations;
      try {
        operations = await Store().enqueueBatch({
          scope: this.scope, userId: this.userId, objectId: this.objectId,
          deviceInstallationId: this.deviceInstallationId, actor: this.actor,
          type, payloads, projection: this.projection,
        });
      } catch (error) {
        throw durableWriteError(error);
      }
      return this.afterDurableCommit(operations);
    }

    retryUnsavedPhoto(id) {
      if (this.closed) return Promise.reject(new Error("Локальная сессия закрыта"));
      const entry = this.unsavedPhotos.get(String(id));
      if (!entry) return Promise.reject(new Error("Несохранённая фотография не найдена"));
      if (entry.flight) return entry.flight;
      const generation = this.generation;
      entry.saving = true;
      this.emitRecovery();
      const flight = (async () => {
        try {
          const operation = await Store().enqueue({
            scope: this.scope, userId: this.userId, objectId: this.objectId,
            deviceInstallationId: this.deviceInstallationId, actor: this.actor,
            type: "photo_uploaded", payload: entry.payload, blob: entry.blob,
            projection: this.projection,
          });
          if (!this.closed && generation === this.generation && this.unsavedPhotos.get(entry.id) === entry) {
            this.rememberCommitted(operation);
            this.removeUnsaved(entry);
          }
          return this.afterDurableCommit(operation);
        } catch (error) {
          const reported = durableWriteError(error);
          if (!this.closed && generation === this.generation && this.unsavedPhotos.get(entry.id) === entry) {
            entry.saving = false;
            entry.message = reported.message;
            this.emitRecovery();
          }
          throw reported;
        } finally {
          if (entry.flight === flight) entry.flight = null;
        }
      })();
      entry.flight = flight;
      return flight;
    }

    discardUnsavedPhoto(id) {
      if (this.closed) throw new Error("Локальная сессия закрыта");
      const entry = this.unsavedPhotos.get(String(id));
      if (!entry) throw new Error("Несохранённая фотография не найдена");
      if (entry.saving) {
        const error = new Error("Дождитесь завершения сохранения фотографии");
        error.code = "UNSAVED_PHOTO_SAVING";
        throw error;
      }
      this.removeUnsaved(entry);
      return true;
    }

    exportUnsavedPhoto(id) {
      if (this.closed) throw new Error("Локальная сессия закрыта");
      const entry = this.unsavedPhotos.get(String(id));
      if (!entry) throw new Error("Несохранённая фотография не найдена");
      return { blob: entry.blob, name: entry.name };
    }

    sync() { return this.syncEngine.sync(false); }
    retry() { return this.syncEngine.retry(); }

    async discard(clientOperationId) {
      const count = await Store().discard(this.scope, clientOperationId, this.actor);
      await this.refresh(); return count;
    }

    async freshContext() {
      const response = await fetch(`/pilot/construction-control/objects/${this.objectId}/sync-context`, {
        credentials: "same-origin", cache: "no-store", headers: { Accept: "application/json" }, signal: AbortSignal.timeout ? AbortSignal.timeout(15000) : undefined,
      });
      if (!response.ok || !(response.headers.get("content-type") || "").includes("application/json")) throw new Error("Войдите снова перед повторным применением");
      const context = await response.json();
      if (Number(context.userId) !== this.userId || !Number.isInteger(Number(context.revision))) throw new Error("Текущий пользователь не совпадает с владельцем очереди");
      return context;
    }

    async review(clientOperationId) {
      if (this.closed) throw new Error("Локальная сессия закрыта");
      const [context, operations] = await Promise.all([this.freshContext(), Store().list(this.scope)]);
      const operation = operations.find((row) => row.clientOperationId === clientOperationId);
      if (!operation || !["conflict", "rejected"].includes(operation.status)) throw new Error("Это действие больше не требует решения");
      for (const [token, value] of this.reviewTokens) if (value.clientOperationId === clientOperationId) this.reviewTokens.delete(token);
      const baseRevision = crypto.randomUUID();
      const reviewedRevision = Number(context.revision);
      this.reviewTokens.set(baseRevision, {
        clientOperationId,
        actorEpoch: Number(this.actor.epoch),
        reviewedRevision,
      });
      return {
        operation: structuredClone(operation),
        projection: structuredClone(context.projection),
        baseRevision,
      };
    }

    async resolve(clientOperationId, reviewedRevision = null) {
      let reviewed = null;
      if (reviewedRevision != null) {
        reviewed = this.reviewTokens.get(String(reviewedRevision));
        this.reviewTokens.delete(String(reviewedRevision));
        if (!reviewed || reviewed.clientOperationId !== clientOperationId || Number(reviewed.actorEpoch) !== Number(this.actor.epoch)) {
          const error = new Error("Просмотр изменений устарел. Посмотрите изменения ещё раз");
          error.code = "REVIEW_STALE";
          throw error;
        }
      }
      const context = await this.freshContext();
      if (reviewed && Number(context.revision) !== Number(reviewed.reviewedRevision)) {
        const error = new Error("Данные сервера изменились. Сначала снова посмотрите изменения");
        error.code = "REVIEW_STALE";
        throw error;
      }
      const baseRevision = reviewed ? reviewed.reviewedRevision : Number(context.revision);
      const replacement = await Store().resolve(this.scope, clientOperationId, baseRevision, this.actor);
      this.projection = bestProjection(this.projection, context.projection);
      await this.refresh(); queueMicrotask(() => this.sync()); return replacement;
    }

    async export(clientOperationId) {
      const operations = await Store().list(this.scope);
      const operation = operations.find((row) => row.clientOperationId === clientOperationId);
      if (!operation) throw new Error("Местное действие не найдено");
      if (operation.type === "photo_uploaded") {
        const blob = await Store().blob(operation.id);
        if (!blob) throw new Error("Сохранённый файл фотографии не найден");
        return { blob, name: operation.originalName || operation.name || "photo" };
      }
      const copy = { ...operation };
      for (const field of ["wire", "receipt", "previewUrl"]) delete copy[field];
      return { blob: new Blob([JSON.stringify(copy, null, 2)], { type: "application/json" }), name: `field-operation-${operation.clientOperationId}.json` };
    }

    close() {
      if (this.closed) return;
      this.generation++;
      this.closed = true; this.stopActorListener?.(); this.syncEngine.close(); sessions.delete(this);
      this.reviewTokens.clear();
      removeEventListener("online", this.online); removeEventListener("pageshow", this.pageshow);
      removeEventListener("beforeunload", this.beforeUnload);
      document.removeEventListener("visibilitychange", this.visibility);
      for (const url of this.previewUrls) URL.revokeObjectURL(url);
      this.previewUrls.clear();
      for (const entry of this.unsavedPhotos.values()) this.revokeUnsaved(entry);
      this.unsavedPhotos.clear();
      this.unsavedReservations.clear();
      try { this.onClose(); } catch {}
    }
  }

  async function open(options) {
    requireRuntime();
    if (!Number.isInteger(Number(options?.userId)) || !Number.isInteger(Number(options?.objectId))) throw new TypeError("userId and objectId are required");
    if (globalThis.FMonitorFieldLifecycle?.ready) await globalThis.FMonitorFieldLifecycle.ready;
    await Store().open();
    const installationId = await Store().deviceId();
    const scope = Store().scopeFor(options.userId, installationId, options.objectId);
    let actor = await Store().actorState(installationId);
    if (actor?.loginProof && !actor.loginProof.consumedAt && Number(actor.loginProof.userId) === Number(options.userId)) {
      actor = await Store().consumeLoginProof(installationId, Number(options.userId));
    }
    if (actor?.tombstonedAt || (actor?.activeUserId && Number(actor.activeUserId) !== Number(options.userId))) {
      throw new Error("Войдите снова, чтобы открыть сохранённые данные этого пользователя");
    }
    if (!actor?.activeUserId) {
      await Store().putActorState({ deviceInstallationId: installationId, activeUserId: Number(options.userId), epoch: Number(actor?.epoch) || 0, tombstonedAt: null, confirmedAt: Date.now() });
    }
    const actorFence = { deviceInstallationId: installationId, activeUserId: Number(options.userId), epoch: Number(actor?.epoch) || 0 };
    await Store().migrateLegacy(scope, actorFence);
    const restoredProjection = bestProjection(options.projection, await Store().projection(scope));
    const session = new Session(options, actorFence, scope, restoredProjection, await storageState());
    sessions.add(session); await session.refresh(); queueMicrotask(() => session.sync()); return session;
  }

  async function pendingRows(userId) {
    requireRuntime();
    const installationId = await Store().deviceId();
    const currentActor = await Store().actorState(installationId);
    if (!currentActor || currentActor.tombstonedAt || Number(currentActor.activeUserId) !== Number(userId)) throw new Error("Учётная запись изменилась; очередь остановлена");
    const actor = { deviceInstallationId: installationId, activeUserId: Number(userId), epoch: Number(currentActor.epoch) };
    const prefix = `${Number(userId)}:${installationId}:`;
    const rows = await Store().transaction(["operations"], "readonly", async (tx) => {
      const request = tx.objectStore("operations").getAll();
      return new Promise((resolve, reject) => { request.onsuccess = () => resolve(request.result); request.onerror = () => reject(request.error); });
    });
    return { rows: rows.filter((row) => row.scope?.startsWith(prefix) && !["accepted", "discarded", "superseded"].includes(row.status)), actor };
  }

  async function pending(userId) {
    const { rows: pendingRowsForUser } = await pendingRows(userId);
    return (() => {
      const objects = {};
      for (const row of pendingRowsForUser) {
        const objectId = row.objectId || Number(row.scope.split(":").at(-1));
        const summary = objects[objectId] || { count: 0, statuses: [] };
        summary.count++;
        if (!summary.statuses.includes(row.status)) summary.statuses.push(row.status);
        objects[objectId] = summary;
      }
      return { userId: Number(userId), total: pendingRowsForUser.length, objects };
    })();
  }

  async function syncPending(userId) {
    const { rows, actor } = await pendingRows(userId);
    const scopes = new Map();
    for (const row of rows) {
      const objectId = row.objectId || Number(row.scope.split(":").at(-1));
      if (!scopes.has(row.scope)) scopes.set(row.scope, objectId);
    }
    for (const [scope, holder] of pendingEngines) {
      if (holder.userId === Number(userId) && !scopes.has(scope)) { holder.engine.close(); pendingEngines.delete(scope); }
    }
    const work = [];
    for (const [scope, objectId] of scopes) {
      let holder = pendingEngines.get(scope);
      if (holder && Number(holder.actor.epoch) !== Number(actor.epoch)) { holder.engine.close(); pendingEngines.delete(scope); holder = null; }
      if (!holder) {
        await Store().migrateLegacy(scope, actor);
        holder = { userId: Number(userId), actor, engine: Sync().create({
          scope, userId: Number(userId), objectId, actor,
          scheduler: (task) => pendingScheduler.run(task),
        }) };
        pendingEngines.set(scope, holder);
      }
      work.push(holder.engine);
    }
    await Promise.all(work.map((engine) => engine.scheduledSync(false)));
    return pending(userId);
  }

  function unsavedCount(userId) {
    let total = 0;
    for (const session of sessions)
      if (!session.closed && session.userId === Number(userId)) total += session.unsavedTotal();
    return total;
  }

  globalThis.FMonitorField = Object.freeze({
    open, pending, syncPending, unsavedCount,
    preparePhoto(file) { requireRuntime(); return Photo().preparePhoto(file); },
    store: Store(),
    closeAll() {
      for (const session of [...sessions]) session.close();
      for (const holder of pendingEngines.values()) holder.engine.close();
      pendingEngines.clear();
    },
  });
})();
