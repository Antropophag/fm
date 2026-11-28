(() => {
  "use strict";
  const Store = globalThis.FMonitorFieldStore;
  const userId = Number(document.body.dataset.authenticatedUserId);
  if (!Store || !Number.isInteger(userId)) return;

  function workerCall(worker, message, timeout = 5000) {
    return new Promise((resolve, reject) => {
      const channel = new MessageChannel();
      const timer = setTimeout(() => reject(new Error("Service worker did not respond")), timeout);
      channel.port1.onmessage = (event) => {
        clearTimeout(timer);
        resolve(event.data || {});
      };
      worker.postMessage(message, [channel.port2]);
    });
  }

  async function registration() {
    if (!("serviceWorker" in navigator) || !isSecureContext) {
      throw new Error("Работа без сети недоступна в этом браузере.");
    }
    const existing = await navigator.serviceWorker.getRegistration?.("/pilot/");
    if (existing?.waiting && existing.active) return existing;
    let value;
    try {
      value = await navigator.serviceWorker.register("/pilot/assets/checklist-sw.js", {
        scope: "/pilot/",
        updateViaCache: "none",
      });
    } catch (error) {
      if (!existing?.active && !navigator.serviceWorker.controller) throw error;
      value = existing;
    }
    if (!value.active && !navigator.serviceWorker.controller) {
      await Promise.race([
        navigator.serviceWorker.ready,
        new Promise((_, reject) => setTimeout(() => reject(new Error("Service worker activation timed out")), 15000)),
      ]);
    }
    return value;
  }

  function updateRequired(message = "Закройте другие вкладки FMonitor и снова откройте страницу для обновления.") {
    const error = new Error(message);
    error.code = "UPDATE_WAITING";
    return error;
  }

  function authenticationRequired(message = "Войдите снова, чтобы открыть сохранённые данные.") {
    const error = new Error(message);
    error.code = "AUTH_REQUIRED";
    return error;
  }

  async function settleInstalling(registrationValue) {
    const installing = registrationValue?.installing;
    if (!installing || !["installing", "installed", "activating"].includes(installing.state)) return;
    if (installing.state === "installed") return;
    await Promise.race([
      new Promise((resolve) => {
        const changed = () => {
          if (!["installing", "activating"].includes(installing.state)) {
            installing.removeEventListener("statechange", changed);
            resolve();
          }
        };
        installing.addEventListener("statechange", changed);
        changed();
      }),
      new Promise((_, reject) => setTimeout(() => reject(updateRequired("Обновление FMonitor ещё устанавливается. Закройте другие вкладки и снова откройте страницу.")), 15000)),
    ]);
  }

  async function settleSelectedWorker(worker) {
    if (!worker?.state || worker.state === "activated") return;
    if (worker.state === "redundant") throw updateRequired("Обновление FMonitor не активировалось. Закройте вкладки и снова откройте страницу.");
    await Promise.race([
      new Promise((resolve, reject) => {
        const changed = () => {
          if (worker.state === "activated" || worker.state === "redundant") {
            worker.removeEventListener("statechange", changed);
            worker.state === "activated" ? resolve() : reject(updateRequired("Обновление FMonitor не активировалось. Закройте вкладки и снова откройте страницу."));
          }
        };
        worker.addEventListener("statechange", changed);
        changed();
      }),
      new Promise((_, reject) => setTimeout(() => reject(updateRequired("Обновление FMonitor ещё активируется. Закройте вкладки и снова откройте страницу.")), 15000)),
    ]);
  }

  async function exactActorAgreement(worker, deviceId) {
    const [actor, checked] = await Promise.all([
      Store.actorState(deviceId),
      workerCall(worker, { type: "FIELD_CHECK_ACTOR" }),
    ]);
    const workerActor = checked.ok ? checked.identity : null;
    return actor && !actor.tombstonedAt && Number(actor.activeUserId) === userId &&
      workerActor && !workerActor.tombstone && Number(workerActor.userId) === userId &&
      Number(workerActor.epoch) === Number(actor.epoch) ? actor : null;
  }

  async function confirmExplicitLogin(worker, deviceId) {
    const proofResult = await workerCall(worker, { type: "CONSUME_LOGIN_CONFIRM", userId, url: location.href });
    const proof = proofResult.confirmed ? proofResult.proof : null;
    if (!proof || Number(proof.userId) !== userId) {
      const agreed = await exactActorAgreement(worker, deviceId);
      if (agreed) return agreed;
      throw authenticationRequired();
    }
    let actor;
    try {
      actor = await Store.confirmLogin(deviceId, userId, proof.nonce);
    } catch (error) {
      const agreed = await exactActorAgreement(worker, deviceId);
      if (agreed) return agreed;
      if (/Login proof|Подтверждённый вход/i.test(error?.message || "")) {
        throw authenticationRequired();
      }
      throw error;
    }
    try {
      const acknowledged = await workerCall(worker, {
        type: "FIELD_ACTOR_CONFIRMED",
        userId,
        epoch: actor.epoch,
        nonce: proof.nonce,
      });
      if (acknowledged.ok) return actor;
    } catch {}
    const agreed = await exactActorAgreement(worker, deviceId);
    if (agreed) return agreed;
    throw authenticationRequired("Не удалось подтвердить новую сессию. Войдите снова.");
  }

  async function initialize() {
    let registered = await registration();
    await settleInstalling(registered);
    registered = (await navigator.serviceWorker.getRegistration?.("/pilot/")) || registered;
    if (registered.waiting && registered.active) {
      showUpdateNotice();
      throw updateRequired();
    }
    const worker = registered.active || navigator.serviceWorker.controller;
    if (!worker) throw new Error("Не удалось запустить работу без сети.");
    await settleSelectedWorker(worker);
    let runtime;
    try {
      runtime = await workerCall(worker, { type: "FIELD_RUNTIME_INFO" }, 3000);
    } catch {
      throw updateRequired("Открыта устаревшая версия FMonitor. Закройте все вкладки FMonitor и снова откройте страницу.");
    }
    if (Number(runtime.protocol) !== 3 || typeof runtime.generation !== "string" || runtime.generation === "") {
      throw updateRequired("Открыта несовместимая версия FMonitor. Закройте все вкладки FMonitor и снова откройте страницу.");
    }
    await Store.open();
    const deviceId = await Store.deviceId();
    let actor = await Store.actorState(deviceId);
    if (!actor) {
      actor = await Store.putActorState({
        deviceInstallationId: deviceId,
        activeUserId: userId,
        epoch: 0,
        tombstonedAt: null,
        confirmedAt: Date.now(),
        loginProof: null,
      });
    }
    if (actor.tombstonedAt || Number(actor.activeUserId) !== userId) {
      actor = await confirmExplicitLogin(worker, deviceId);
    } else {
      const checked = await workerCall(worker, { type: "FIELD_CHECK_ACTOR" });
      const workerActor = checked.ok ? checked.identity : null;
      if (workerActor?.tombstone || (workerActor?.userId && (Number(workerActor.userId) !== userId || Number(workerActor.epoch) !== Number(actor.epoch)))) {
        actor = await confirmExplicitLogin(worker, deviceId);
      } else if (!workerActor) {
        const established = await workerCall(worker, {
          type: "FIELD_ESTABLISH_ACTOR",
          userId,
          epoch: actor.epoch,
          generation: runtime.generation,
          url: location.href,
        });
        if (!established.ok) throw new Error("Не удалось подтвердить текущую сессию.");
      }
    }
    await workerCall(worker, {type:"FIELD_CLIENT_GENERATION",userId,epoch:actor.epoch,generation:runtime.generation}).catch(()=>({ok:false}));
    if (registered.waiting) showUpdateNotice();
  }

  const ready = initialize();
  globalThis.FMonitorFieldLifecycle = Object.freeze({ ready });

  function hidePrivate(message = "Учётная запись изменилась. Войдите снова, чтобы открыть данные.", showLogin = false) {
    document.querySelector(".fm2-shell")?.setAttribute("hidden", "");
    let alert = document.querySelector("[data-field-identity-alert]");
    if (!alert) {
      alert = document.createElement("p");
      alert.dataset.fieldIdentityAlert = "";
      alert.className = "shlz-alert";
      alert.setAttribute("role", "alert");
      document.body.prepend(alert);
    }
    alert.replaceChildren(document.createTextNode(message));
    if (showLogin) {
      const link = document.createElement("a");
      link.className = "shlz-link";
      link.href = "/pilot/login?reauth=1";
      link.textContent = "Войти снова";
      alert.append(document.createTextNode(" "), link);
    }
  }

  function showUpdateNotice() {
    if (document.querySelector("[data-field-refresh-notice]")) return;
    const alert = document.createElement("p");
    alert.dataset.fieldRefreshNotice = "";
    alert.className = "shlz-alert";
    alert.setAttribute("role", "status");
    alert.textContent = "Доступно обновление для работы без сети. Закройте другие вкладки FMonitor, затем закройте и снова откройте эту страницу.";
    document.body.prepend(alert);
  }

  navigator.serviceWorker?.addEventListener("message", (event) => {
    if (event.data?.type === "FIELD_UPDATE_READY") showUpdateNotice();
  });
  Store.onActorChange?.((actor) => {
    if (actor.tombstoned || Number(actor.activeUserId) !== userId) hidePrivate(undefined, true);
  });

  async function pendingCount() {
    if (globalThis.FMonitorField?.pending) return Number((await globalThis.FMonitorField.pending(userId)).total) || 0;
    const deviceId = await Store.deviceId();
    const prefix = `${userId}:${deviceId}:`;
    return Store.transaction(["operations"], "readonly", (transaction) => new Promise((resolve, reject) => {
      const request = transaction.objectStore("operations").getAll();
      request.onerror = () => reject(request.error);
      request.onsuccess = () => resolve(request.result.filter((row) =>
        row.scope?.startsWith(prefix) && !["accepted", "discarded", "superseded"].includes(row.status)).length);
    }));
  }

  async function pendingState() {
    let durable = 0;
    try { durable = await pendingCount(); } catch {}
    const unsaved = Number(globalThis.FMonitorField?.unsavedCount?.(userId)) || 0;
    return { durable, unsaved };
  }

  function confirmLogout({ durable, unsaved }) {
    return new Promise((resolve) => {
      const dialog = document.createElement("dialog");
      dialog.className = "shlz-modal fm2-confirm-dialog";
      dialog.innerHTML = '<div class="shlz-modal__surface"><header class="shlz-modal__header"><h2 class="shlz-modal__title">Выйти из FMonitor?</h2></header><div class="shlz-modal__body"><p></p></div><footer class="shlz-modal__footer"><button class="shlz-button shlz-button--secondary" type="button" data-cancel>Остаться</button><button class="shlz-button shlz-button--primary" type="button" data-field-logout-confirm>Выйти</button></footer></div>';
      const messages = [];
      if (unsaved > 0) messages.push(`Несохранённых фотографий: ${unsaved}. Они будут потеряны при выходе.`);
      if (durable > 0) messages.push(`Действий на устройстве: ${durable}. Они сохранятся и отправятся после входа тем же пользователем.`);
      dialog.querySelector("p").textContent = messages.join(" ");
      const finish = (value) => { dialog.close(); dialog.remove(); resolve(value); };
      dialog.querySelector("[data-cancel]").addEventListener("click", () => finish(false));
      dialog.querySelector("[data-field-logout-confirm]").addEventListener("click", () => finish(true));
      document.body.append(dialog);
      dialog.showModal();
    });
  }

  const form = document.querySelector(".fm2-logout-form");
  let submitted = false;
  form?.addEventListener("submit", async (event) => {
    if (submitted) return;
    event.preventDefault();
    const pending = await pendingState();
    if ((pending.durable > 0 || pending.unsaved > 0) && !(await confirmLogout(pending))) return;
    globalThis.FMonitorField?.closeAll?.();
    hidePrivate("Выполняется выход. Не закрывайте страницу.");
    let cleanupFailed = false;
    try {
      const tasks = [];
      try {
        let worker = navigator.serviceWorker?.controller || null;
        if (!worker) {
          const registered = await registration();
          worker = registered.active || null;
        }
        if (!worker) throw new Error("worker unavailable");
        tasks.push(workerCall(worker, { type: "FIELD_LOGOUT" }));
      } catch (error) {
        tasks.push(Promise.reject(error));
      }
      try {
        const deviceId = await Store.deviceId();
        tasks.push(Store.transitionActor(deviceId, null, true));
      } catch (error) {
        tasks.push(Promise.reject(error));
      }
      const results = await Promise.allSettled(tasks);
      cleanupFailed = results.length < 2 || results.some((result) => result.status === "rejected");
    } catch {
      cleanupFailed = true;
    } finally {
      if (cleanupFailed) {
        const alert = document.querySelector("[data-field-identity-alert]");
        if (alert) alert.textContent = "Локальное завершение сеанса подтверждено не полностью. Серверный выход продолжается.";
      }
      submitted = true;
      form.requestSubmit();
    }
  });

  ready.catch((error) => {
    if (error.code === "UPDATE_WAITING") showUpdateNotice();
    else hidePrivate(error.message, error.code === "AUTH_REQUIRED");
  });
})();
