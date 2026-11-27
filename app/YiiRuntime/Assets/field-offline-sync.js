(() => {
  "use strict";

  const Store = () => globalThis.FMonitorFieldStore;
  const sleep = (milliseconds) => new Promise((resolve) => setTimeout(resolve, milliseconds));
  const timeoutSignal = (milliseconds) => {
    if (AbortSignal.timeout) return AbortSignal.timeout(milliseconds);
    const controller = new AbortController();
    setTimeout(() => controller.abort(), milliseconds);
    return controller.signal;
  };
  const combinedSignal = (timeout, actorSignal) => {
    const timed = timeoutSignal(timeout);
    if (AbortSignal.any) return AbortSignal.any([timed, actorSignal]);
    const controller = new AbortController();
    const abort = () => controller.abort();
    timed.addEventListener("abort", abort, { once: true }); actorSignal.addEventListener("abort", abort, { once: true });
    return controller.signal;
  };
  const safeMessage = {
    auth_required: "Войдите снова. Данные сохранены на устройстве",
    access_denied: "Нет доступа для отправки. Данные сохранены на устройстве",
    retryable: "Связь с ФМ недоступна. Данные сохранены и будут отправлены позже",
    conflict: "Данные на сервере изменились. Выберите действие",
    sending: "Отправляем сохранённые изменения",
    online: "Изменения отправлены",
    offline: "Нет связи с ФМ. Изменения сохранены на устройстве",
  };

  async function jsonResponse(response) {
    const contentType = response.headers.get("content-type") || "";
    if (response.redirected || !contentType.toLowerCase().includes("application/json")) return null;
    try { return await response.json(); } catch { return null; }
  }

  class SyncEngine {
    constructor(options) {
      this.scope = options.scope;
      this.userId = Number(options.userId);
      this.objectId = Number(options.objectId);
      this.actor = { ...options.actor };
      this.onState = options.onState || (() => {});
      this.onChanged = options.onChanged || (() => {});
      this.scheduler = options.scheduler || ((task) => task());
      this.token = crypto.randomUUID();
      this.actorAbort = new AbortController();
      this.running = null;
      this.closed = false;
      this.renewal = null;
      this.retryTimer = null;
      this.channel = "BroadcastChannel" in globalThis ? new BroadcastChannel(`fm2-field:${this.scope}`) : null;
      if (this.channel) this.channel.onmessage = () => this.onChanged();
      this.stopActorListener = Store().onActorChange((change) => {
        if (change.deviceInstallationId === this.actor.deviceInstallationId &&
            (change.tombstoned || Number(change.activeUserId) !== this.userId || Number(change.epoch) !== Number(this.actor.epoch))) this.close();
      });
    }

    state(state, nextAttemptAt = null, message = null) {
      this.onState({ state, message: message || safeMessage[state] || "", nextAttemptAt });
    }

    async context() {
      let response;
      try {
        response = await fetch(`/pilot/construction-control/objects/${this.objectId}/sync-context`, {
          credentials: "same-origin", cache: "no-store", headers: { Accept: "application/json" }, signal: combinedSignal(15000, this.actorAbort.signal),
        });
      } catch (error) {
        error.retryable = true; throw error;
      }
      const body = await jsonResponse(response);
      if ([408, 429].includes(response.status) || response.status >= 500) {
        const error = new Error(safeMessage.retryable); error.retryable = true; throw error;
      }
      if (response.status === 401 || response.redirected) {
        const error = new Error(safeMessage.auth_required); error.auth = true; throw error;
      }
      if (response.status === 403) { const error = new Error(safeMessage.access_denied); error.denied = true; throw error; }
      if (!body || !response.ok) { const error = new Error("ФМ вернул некорректный ответ. Повторим безопасно"); error.retryable = true; throw error; }
      if (Number(body.userId) !== this.userId || !Number.isInteger(Number(body.revision)) || typeof body.csrf !== "string" || !body.projection) {
        const error = new Error(Number(body?.userId) !== this.userId ? "Текущий пользователь не совпадает с владельцем очереди" : safeMessage.retryable);
        if (Number(body?.userId) !== this.userId) error.auth = true; else error.retryable = true;
        throw error;
      }
      return body;
    }

    async request(operation, context) {
      const photo = operation.type === "photo_uploaded";
      const headers = { "X-FM2-CSRF": context.csrf, Accept: "application/json" };
      let body;
      if (photo) {
        body = await Store().blob(operation.id);
        if (!body) { const error = new Error("Сохранённый файл фотографии не найден"); error.storage = true; throw error; }
        headers["Content-Type"] = operation.wire.mime;
        headers["X-FM2-Operation"] = btoa(String.fromCharCode(...new TextEncoder().encode(JSON.stringify(operation.wire))));
      } else {
        headers["Content-Type"] = "application/json; charset=UTF-8";
        body = JSON.stringify(operation.wire);
      }
      let response;
      try {
        response = await fetch(`/pilot/objects/${this.objectId}/checklist/${photo ? "photos" : "operations"}`, {
          method: "POST", credentials: "same-origin", headers, body, signal: combinedSignal(photo ? 30000 : 15000, this.actorAbort.signal),
        });
      } catch (error) { error.retryable = true; throw error; }
      const result = await jsonResponse(response);
      return { response, result };
    }

    delay(operation, response) {
      const exponential = Math.min(60000, 2000 * 2 ** Math.max(0, (operation.attemptCount || 1) - 1));
      const jittered = exponential * (0.75 + Math.random() * 0.5);
      const header = response?.headers?.get("retry-after");
      let retryAfter = NaN;
      if (typeof header === "string" && header.trim() !== "") {
        const seconds = Number(header);
        retryAfter = Number.isFinite(seconds) ? seconds * 1000 : Date.parse(header) - Date.now();
      }
      return Math.min(60000, Number.isFinite(retryAfter) && retryAfter >= 0 ? retryAfter : jittered);
    }

    async markFailure(operation, kind, message, response = null, result = null) {
      const retryable = kind === "retryable";
      const delay = retryable ? this.delay(operation, response) : 0;
      const nextAttemptAt = retryable ? Date.now() + delay : null;
      await Store().retryState(this.scope, operation.clientOperationId, this.token, {
        status: retryable ? "retryable_error" : kind,
        reasonCode: result?.reasonCode || null,
        message: message || result?.message || safeMessage[kind] || "",
        nextAttemptAt,
      }, this.actor);
      this.channel?.postMessage("changed"); this.onChanged();
      if (retryable) this.schedule(nextAttemptAt);
      this.state(kind === "retryable" ? "retryable" : kind, nextAttemptAt, message);
    }

    schedule(at) {
      clearTimeout(this.retryTimer);
      if (this.closed || !at) return;
      this.retryTimer = setTimeout(() => this.scheduledSync(false), Math.max(0, Math.min(60000, at - Date.now())));
    }

    async acquire() {
      for (let tries = 0; tries < 4 && !this.closed; tries++) {
        if (await Store().acquireLease(this.scope, this.token, this.actor)) {
          this.renewal = setInterval(() => Store().renewLease(this.scope, this.token, this.actor).catch(() => this.close()), 10000);
          return true;
        }
        await sleep(75 + Math.random() * 125);
      }
      return false;
    }

    async run(force) {
      const contextRetry = await Store().contextRetry(this.scope, this.actor);
      if (!force && contextRetry.nextAttemptAt && contextRetry.nextAttemptAt > Date.now()) {
        this.schedule(contextRetry.nextAttemptAt); return;
      }
      if (!(await this.acquire())) {
        this.schedule(Date.now() + 1500 + Math.random() * 1000);
        return;
      }
      try {
        let context;
        try {
          context = await this.context();
          await Store().clearContextFailure(this.scope, this.actor);
        }
        catch (error) {
          this.state(error.auth ? "auth_required" : error.denied ? "access_denied" : "retryable", null, error.message);
          if (error.retryable) this.schedule(await Store().recordContextFailure(this.scope, this.actor));
          return;
        }
        await Store().reconcileAcceptedPhotos(this.scope, context.projection, this.actor);
        let madeProgress = true;
        while (madeProgress && !this.closed) {
          madeProgress = false;
          const rows = await Store().list(this.scope);
          const byId = new Map(rows.map((row) => [row.clientOperationId, row]));
          const candidates = rows.filter((candidate) => {
            if (!["queued", "sending", "retryable_error", "uncertain", "auth_required"].includes(candidate.status)) return false;
            if (candidate.wire) return true;
            if (!candidate.localPredecessorId) return true;
            return byId.get(candidate.localPredecessorId)?.status === "accepted";
          });
          const candidate = candidates[0];
          if (!candidate) break;
          if (this.closed) break;
          if (!force && candidate.nextAttemptAt && candidate.nextAttemptAt > Date.now()) { this.schedule(candidate.nextAttemptAt); break; }
          const operation = await Store().dispatch(this.scope, candidate.clientOperationId, this.token, context.revision, this.actor);
          if (!operation) continue;
          this.state("sending"); this.onChanged();
          let outcome;
          try { outcome = await this.request(operation, context); }
          catch (error) {
            await this.markFailure(operation, error.storage ? "storage_recovery" : "retryable", error.message);
            break;
          }
          const { response, result } = outcome;
          if ([408, 429].includes(response.status) || response.status >= 500) {
            await this.markFailure(operation, "retryable", result?.message, response, result); break;
          }
          if (response.status === 401 || response.redirected) {
            await this.markFailure(operation, "auth_required", safeMessage.auth_required, response, result); break;
          }
          if (response.status === 403) {
            await this.markFailure(operation, "access_denied", safeMessage.access_denied, response, result); break;
          }
          if (!result) {
            await this.markFailure(operation, "retryable", "ФМ вернул некорректный ответ. Повторим безопасно", response, result); break;
          }
          if (result?.reasonCode === "CSRF_INVALID") {
            try {
              context = await this.context();
              outcome = await this.request(operation, context);
            } catch (error) {
              await this.markFailure(operation, error.retryable ? "retryable" : "auth_required", error.message); break;
            }
          }
          const finalResponse = outcome.response;
          const finalResult = outcome.result;
          if ([408, 429].includes(finalResponse.status) || finalResponse.status >= 500) {
            await this.markFailure(operation, "retryable", finalResult?.message, finalResponse, finalResult); break;
          }
          if (finalResponse.status === 401 || finalResponse.redirected) {
            await this.markFailure(operation, "auth_required", safeMessage.auth_required, finalResponse, finalResult); break;
          }
          if (finalResponse.status === 403) {
            await this.markFailure(operation, "access_denied", safeMessage.access_denied, finalResponse, finalResult); break;
          }
          if (!finalResult) {
            await this.markFailure(operation, "retryable", "ФМ вернул некорректный ответ. Повторим безопасно", finalResponse, finalResult); break;
          }
          if (finalResponse.ok && ["accepted", "duplicate"].includes(finalResult?.status) && finalResult.projection && Number.isInteger(Number(finalResult.revision ?? finalResult.projection.revision))) {
            if (operation.type === "photo_uploaded") {
              const receipt = (finalResult.projection.photos || []).find((photo) =>
                (photo.clientOperationId === operation.clientOperationId || finalResult.status === "duplicate") &&
                photo.sha256 === operation.wire.sha256 && Number(photo.sectionId) === Number(operation.wire.sectionId) &&
                Number(photo.size) === Number(operation.wire.size) && photo.mime === operation.wire.mime &&
                (photo.id != null || photo.viewUrl || photo.thumbnailUrl));
              if (!receipt) {
                await this.markFailure(operation, "retryable", "Сервер не подтвердил сохранённую фотографию", finalResponse, finalResult); break;
              }
              finalResult.matchedPhoto = receipt;
            }
            let accepted;
            try { accepted = await Store().accept(this.scope, operation.clientOperationId, this.token, finalResult, this.actor); }
            catch (error) { this.state("retryable", Date.now() + 2000, "Не удалось сохранить подтверждение сервера; повтор безопасен"); this.schedule(Date.now() + 2000); break; }
            if (!accepted) { this.schedule(Date.now() + 2000); break; }
            context.revision = Number(finalResult.revision ?? finalResult.projection.revision);
            context.projection = finalResult.projection;
            this.channel?.postMessage("changed"); this.onChanged();
            madeProgress = true;
            continue;
          }
          if (finalResult?.status === "retryable") {
            await this.markFailure(operation, "retryable", finalResult?.message, finalResponse, finalResult); break;
          }
          if (finalResponse.status === 409 || finalResult?.status === "conflict") {
            await this.markFailure(operation, "conflict", finalResult?.message || safeMessage.conflict, finalResponse, finalResult); break;
          }
          await this.markFailure(operation, "rejected", finalResult?.message || "Сервер отклонил действие", finalResponse, finalResult); break;
        }
        const remaining = (await Store().list(this.scope)).filter((row) => !["accepted", "discarded", "superseded"].includes(row.status));
        if (!remaining.length) this.state("online");
      } finally {
        clearInterval(this.renewal); this.renewal = null;
        await Store().releaseLease(this.scope, this.token).catch(() => {});
      }
    }

    sync(force = false) {
      if (this.closed) return Promise.resolve();
      if (!this.running) this.running = this.run(force).catch((error) => {
        const at = Date.now() + 2000; this.state("retryable", at, error.message || safeMessage.retryable); this.schedule(at);
      }).finally(() => { this.running = null; });
      return this.running;
    }
    retry() { return this.sync(true); }
    scheduledSync(force = false) { return this.scheduler(() => this.sync(force)); }
    close() {
      this.closed = true; this.actorAbort.abort(); clearTimeout(this.retryTimer); clearInterval(this.renewal);
      this.stopActorListener?.(); this.channel?.close(); Store().releaseLease(this.scope, this.token).catch(() => {});
    }
  }

  globalThis.FMonitorFieldSync = Object.freeze({ create: (options) => new SyncEngine(options) });
})();
