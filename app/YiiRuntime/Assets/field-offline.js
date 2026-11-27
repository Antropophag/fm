(() => {
  "use strict";

  const Store = () => globalThis.FMonitorFieldStore;
  const Sync = () => globalThis.FMonitorFieldSync;
  const Photo = () => globalThis.FMonitorFieldPhoto;
  const sessions = new Set();
  const pendingEngines = new Map();
  function durableWriteError(error) {
    if (["PHOTO_LIMIT_REACHED", "SECTION_ALREADY_COMPLETED", "ACTOR_CHANGED"].includes(error?.code) || error instanceof TypeError) return error;
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
      this.previewUrls = new Set(); this.reviewTokens = new Map(); this.closed = false; this.refreshing = null;
      this.syncEngine = Sync().create({ scope, userId: this.userId, objectId: this.objectId, actor: this.actor,
        onState: (connection) => { this.connection = connection; this.emit(); }, onChanged: () => this.refresh() });
      this.stopActorListener = Store().onActorChange((change) => {
        if (change.deviceInstallationId === this.actor.deviceInstallationId &&
            (change.tombstoned || Number(change.activeUserId) !== this.userId || Number(change.epoch) !== Number(this.actor.epoch))) this.close();
      });
      this.online = () => this.sync();
      this.visibility = () => { if (document.visibilityState === "visible") this.sync(); };
      this.pageshow = () => this.sync();
      addEventListener("online", this.online); addEventListener("pageshow", this.pageshow);
      document.addEventListener("visibilitychange", this.visibility);
    }

    async refresh() {
      if (this.closed) return [];
      if (this.refreshing) return this.refreshing;
      this.refreshing = (async () => {
        for (const url of this.previewUrls) URL.revokeObjectURL(url);
        this.previewUrls.clear();
        const operations = await Store().list(this.scope, true);
        for (const operation of operations) if (operation.savedBlob) {
          operation.previewUrl = URL.createObjectURL(operation.savedBlob); this.previewUrls.add(operation.previewUrl);
          delete operation.savedBlob;
        }
        const durableProjection = await Store().projection(this.scope);
        this.projection = bestProjection(this.projection, durableProjection);
        this.operations = operations;
        this.emit();
        return operations;
      })().finally(() => { this.refreshing = null; });
      return this.refreshing;
    }

    visibleConnection() {
      const connection = { ...this.connection };
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
      });
    }
    list() { return this.refresh(); }

    async enqueue(type, payload, blob = null) {
      if (this.closed) throw new Error("Локальная сессия закрыта");
      let operation;
      try {
        operation = await Store().enqueue({ scope: this.scope, userId: this.userId, objectId: this.objectId,
          deviceInstallationId: this.deviceInstallationId, actor: this.actor, type, payload, blob, projection: this.projection });
      } catch (error) {
        throw durableWriteError(error);
      }
      await this.refresh();
      queueMicrotask(() => this.sync());
      return operation;
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
      await this.refresh();
      queueMicrotask(() => this.sync());
      return operations;
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
      this.closed = true; this.stopActorListener?.(); this.syncEngine.close(); sessions.delete(this);
      this.reviewTokens.clear();
      removeEventListener("online", this.online); removeEventListener("pageshow", this.pageshow);
      document.removeEventListener("visibilitychange", this.visibility);
      for (const url of this.previewUrls) URL.revokeObjectURL(url);
      this.previewUrls.clear();
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

  globalThis.FMonitorField = Object.freeze({
    open, pending, syncPending,
    preparePhoto(file) { requireRuntime(); return Photo().preparePhoto(file); },
    store: Store(),
    closeAll() {
      for (const session of [...sessions]) session.close();
      for (const holder of pendingEngines.values()) holder.engine.close();
      pendingEngines.clear();
    },
  });
})();
