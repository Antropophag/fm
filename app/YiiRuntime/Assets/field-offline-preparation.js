(() => {
  "use strict";
  const queue = document.querySelector("[data-control-queue]"),
    shell = document.querySelector("[data-offline-shell]");
  if (!queue && !shell) return;
  let userId = Number((queue || shell).dataset.userId);
  const Store = globalThis.FMonitorFieldStore;
  let preparing = false,
    deferredInstall = null,
    currentRunToken = null,
    activeAttempt = null,
    completedAttempt = null,
    pendingPreparation = Promise.resolve();
  const timeout = (ms = 15000) =>
    typeof AbortSignal.timeout === "function"
      ? AbortSignal.timeout(ms)
      : (() => {
          const c = new AbortController();
          setTimeout(() => c.abort(), ms);
          return c.signal;
        })();
  const plural = (n) =>
    `${n} ${n % 10 === 1 && n % 100 !== 11 ? "объект" : [2, 3, 4].includes(n % 10) && ![12, 13, 14].includes(n % 100) ? "объекта" : "объектов"}`;
  async function actor(confirmLogin = false) {
    if (!Store) throw Error("Локальное хранилище недоступно.");
    if (confirmLogin && globalThis.FMonitorFieldLifecycle?.ready) {
      await globalThis.FMonitorFieldLifecycle.ready;
    }
    await Store.open();
    const deviceInstallationId = await Store.deviceId();
    let state = await Store.actorState(deviceInstallationId);
    if (!state)
      state = await Store.putActorState({
        deviceInstallationId,
        activeUserId: userId,
        epoch: 0,
        tombstonedAt: null,
        confirmedAt: new Date().toISOString(),
      });
    if (state.tombstonedAt || Number(state.activeUserId) !== userId)
      throw Error("Войдите снова, чтобы открыть сохранённые объекты.");
    return { deviceInstallationId, state };
  }
  async function manifestPage(page) {
    const response = await fetch(
      `/pilot/construction-control/offline-manifest?page=${page}`,
      { credentials: "same-origin", cache: "no-store", signal: timeout() },
    );
    if (
      response.status === 401 ||
      response.status === 403 ||
      response.redirected
    )
      throw Error("Войдите снова. Сохранённые данные не удалены.");
    if (!response.ok) throw Error("Не удалось получить список объектов.");
    const type = response.headers.get("content-type") || "";
    if (!type.includes("application/json"))
      throw Error("Войдите снова. Сохранённые данные не удалены.");
    return response.json();
  }
  async function stableManifest() {
    for (let pass = 1; pass <= 3; pass++) {
      const first = await manifestPage(1),
        items = [...first.items];
      let valid = first.userId === userId;
      for (let page = 2; valid && page <= first.pages; page++) {
        const next = await manifestPage(page);
        valid =
          next.userId === userId &&
          next.setVersion === first.setVersion &&
          next.page === page &&
          next.pages === first.pages &&
          next.total === first.total;
        if (valid) items.push(...next.items);
      }
      const check = valid ? await manifestPage(1) : null;
      const ids = items.map((x) => x.objectId);
      valid =
        valid &&
        check.setVersion === first.setVersion &&
        new Set(ids).size === ids.length &&
        ids.length === first.total;
      if (valid) return { ...first, items, attempt: pass };
    }
    throw Error(
      "Список объектов изменился во время подготовки. Повторите попытку.",
    );
  }
  function summary(state, completed, total, message, updatedAt) {
    const root = document.querySelector("[data-offline-summary]");
    if (!root) return;
    root.dataset.state = state;
    root.querySelector("[data-offline-copy]").textContent =
      message ||
      (state === "ready"
        ? `Готово без сети: ${plural(total)}`
        : `Готовим для работы без сети: ${completed} из ${total}`);
    const date = root.querySelector("[data-offline-updated]");
    date.textContent = updatedAt
      ? `Обновлено ${new Intl.DateTimeFormat("ru-RU", { dateStyle: "short", timeStyle: "short" }).format(new Date(updatedAt))}`
      : "";
    root.querySelector("[data-offline-retry]").hidden = ![
      "partial",
      "error",
    ].includes(state);
  }
  async function register() {
    if (!("serviceWorker" in navigator) || !isSecureContext)
      throw Error("Работа без сети недоступна в этом браузере.");
    const registration = await navigator.serviceWorker.register(
      "/pilot/assets/checklist-sw.js",
      { scope: "/pilot/" },
    );
    await navigator.serviceWorker.ready;
    return registration;
  }
  function workerCall(worker, message, timeoutMs = 3000) {
    return new Promise((resolve, reject) => {
      const channel = new MessageChannel(),
        timer = setTimeout(
          () => reject(Error("Service worker did not respond")),
          timeoutMs,
        );
      channel.port1.onmessage = (event) => {
        clearTimeout(timer);
        resolve(event.data || {});
      };
      worker.postMessage(message, [channel.port2]);
    });
  }
  async function runtimeInfo(worker) {
    const value = await workerCall(worker, { type: "FIELD_RUNTIME_INFO" });
    if (
      value.protocol !== 3 ||
      typeof value.generation !== "string" ||
      !/^[a-f0-9]{64}$/.test(value.generation)
    )
      throw Error("Обновление для работы без сети ещё не готово.");
    return value;
  }
  function isCurrent(runToken, descriptor = null) {
    return (
      currentRunToken === runToken &&
      (!descriptor || activeAttempt === descriptor) &&
      !descriptor?.cancelled
    );
  }
  function cancelAttempt(descriptor) {
    if (!descriptor || descriptor.cancelled) return;
    descriptor.cancelled = true;
    descriptor.worker?.postMessage({
      type: "CANCEL_CHECKLIST_WARMUPS",
      userId: descriptor.userId,
      epoch: descriptor.epoch,
      generation: descriptor.generation,
      scope: descriptor.scope,
      attempt: descriptor.attempt,
    });
  }
  function cacheDocument(worker, item, identity, descriptor) {
    return new Promise((resolve, reject) => {
      const channel = new MessageChannel(),
        timer = setTimeout(
          () =>
            reject(Error("Подготовка объекта заняла слишком много времени.")),
          15000,
        );
      channel.port1.onmessage = (e) => {
        clearTimeout(timer);
        e.data?.ok
          ? resolve(e.data)
          : reject(Error(e.data?.message || "Не удалось сохранить объект."));
      };
      worker.postMessage(
        {
          type: "CACHE_CHECKLIST",
          url: new URL(item.checklistUrl, location.origin).href,
          userId,
          epoch: identity.state.epoch,
          generation: descriptor.generation,
          scope: descriptor.scope,
          attempt: descriptor.attempt,
          priority: "normal",
        },
        [channel.port2],
      );
    });
  }
  function cachePresent(worker, item) {
    return new Promise((resolve) => {
      const channel = new MessageChannel(),
        timer = setTimeout(() => resolve(false), 3000);
      channel.port1.onmessage = (e) => {
        clearTimeout(timer);
        resolve(e.data?.ok === true);
      };
      worker.postMessage(
        {
          type: "CHECK_CHECKLIST_CACHE",
          url: new URL(item.checklistUrl, location.origin).href,
          userId,
          contentHash: item.contentHash,
        },
        [channel.port2],
      );
    });
  }
  function workerIdentity(worker) {
    return new Promise((resolve) => {
      const channel = new MessageChannel(),
        timer = setTimeout(() => resolve(null), 3000);
      channel.port1.onmessage = (event) => {
        clearTimeout(timer);
        resolve(event.data?.ok ? event.data.identity : null);
      };
      worker.postMessage({ type: "FIELD_CHECK_ACTOR" }, [channel.port2]);
    });
  }
  async function runPreparation(runToken) {
    if (currentRunToken !== runToken) return;
    try {
      const identity = await actor(true);
      if (currentRunToken !== runToken) return;
      const manifest = await stableManifest();
      if (currentRunToken !== runToken) return;
      const registration = await register();
      if (currentRunToken !== runToken) return;
      const key = `${userId}:${identity.deviceInstallationId}`;
      const previous = await Store.getPreparation(key);
      if (currentRunToken !== runToken) return;
      const done = new Set();
      const workerTarget =
        registration.active || navigator.serviceWorker.controller;
      if (!workerTarget)
        throw Error(
          "Обновление ещё не готово. Закройте другие вкладки FMonitor и откройте страницу снова.",
        );
      const runtime = await runtimeInfo(workerTarget);
      if (currentRunToken !== runToken) return;
      const descriptor = {
        runToken,
        worker: workerTarget,
        userId,
        epoch: identity.state.epoch,
        generation: runtime.generation,
        scope: `field-preparation:${identity.deviceInstallationId}:${crypto.randomUUID()}`,
        attempt: crypto.randomUUID(),
        cancelled: false,
      };
      activeAttempt = descriptor;
      const generation = descriptor.generation;
      const previousItems = new Map(
        (previous?.items || []).map((item) => [Number(item.objectId), item]),
      );
      const items = manifest.items.map((item) => {
        const saved = previousItems.get(Number(item.objectId));
        return saved && ["ready", "stale"].includes(saved.state)
          ? {
              ...item,
              cachedAt: saved.cachedAt,
              contentHash: saved.contentHash,
              state: "verifying",
            }
          : { ...item, state: "pending" };
      });
      await Promise.all(
        items.map(async (item) => {
          if (item.state !== "verifying") return;
          const present = await cachePresent(workerTarget, item);
          if (!isCurrent(runToken, descriptor)) return;
          if (present) {
            item.state = "refreshing";
            done.add(item.objectId);
          } else {
            item.state = "pending";
            delete item.cachedAt;
            delete item.contentHash;
          }
        }),
      );
      if (!isCurrent(runToken, descriptor)) return;
      summary("preparing", done.size, manifest.total);
      let cursor = 0,
        errors = [];
      const fence = {
        deviceInstallationId: identity.deviceInstallationId,
        activeUserId: userId,
        epoch: identity.state.epoch,
      };
      await Store.putPreparationFenced(
        {
          key,
          userId,
          deviceInstallationId: identity.deviceInstallationId,
          epoch: identity.state.epoch,
          setVersion: manifest.setVersion,
          expectedIds: manifest.items.map((x) => x.objectId),
          total: manifest.total,
          completed: done.size,
          state: "preparing",
          attempt: manifest.attempt,
          updatedAt: new Date().toISOString(),
          generation,
          items,
          errors: [],
        },
        fence,
      );
      if (!isCurrent(runToken, descriptor)) return;
      async function worker() {
        while (isCurrent(runToken, descriptor) && cursor < items.length) {
          const item = items[cursor++];
          try {
            const cached = await cacheDocument(
              workerTarget,
              item,
              identity,
              descriptor,
            );
            if (!isCurrent(runToken, descriptor)) return;
            item.state = "ready";
            item.cachedAt = new Date().toISOString();
            item.contentHash = cached.contentHash;
            item.generation = cached.generation;
            await Store.putPreparedDocumentFenced(
              {
                key: `${key}:${item.objectId}`,
                userId,
                deviceInstallationId: identity.deviceInstallationId,
                objectId: item.objectId,
                canonicalUrl: item.checklistUrl,
                contentHash: item.contentHash,
                cachedAt: item.cachedAt,
                generation: item.generation || generation,
                state: "ready",
              },
              fence,
            );
            if (!isCurrent(runToken, descriptor)) return;
            done.add(item.objectId);
          } catch (error) {
            if (!isCurrent(runToken, descriptor)) return;
            item.state = done.has(item.objectId) ? "stale" : "error";
            item.error = error.message;
            errors.push({ objectId: item.objectId, message: error.message });
          }
          summary("preparing", done.size, manifest.total);
          await Store.putPreparationFenced(
            {
              key,
              userId,
              deviceInstallationId: identity.deviceInstallationId,
              epoch: identity.state.epoch,
              setVersion: manifest.setVersion,
              expectedIds: manifest.items.map((x) => x.objectId),
              total: manifest.total,
              completed: done.size,
              state: "preparing",
              attempt: manifest.attempt,
              updatedAt: new Date().toISOString(),
              generation,
              items,
              errors,
            },
            fence,
          );
          if (!isCurrent(runToken, descriptor)) return;
        }
      }
      await Promise.all([worker(), worker()]);
      if (!isCurrent(runToken, descriptor)) return;
      const state = errors.length ? "partial" : "ready",
        updatedAt = new Date().toISOString();
      await Store.putPreparationFenced(
        {
          key,
          userId,
          deviceInstallationId: identity.deviceInstallationId,
          epoch: identity.state.epoch,
          setVersion: manifest.setVersion,
          expectedIds: manifest.items.map((x) => x.objectId),
          total: manifest.total,
          completed: done.size,
          state,
          attempt: manifest.attempt,
          updatedAt,
          generation,
          items,
          errors,
        },
        fence,
      );
      if (!isCurrent(runToken, descriptor)) return;
      summary(
        state,
        done.size,
        manifest.total,
        errors.length
          ? `Подготовлено ${done.size} из ${manifest.total}. Не удалось сохранить часть объектов.`
          : "",
        updatedAt,
      );
    } catch (error) {
      if (currentRunToken === runToken) summary("error", 0, 0, error.message);
    } finally {
      if (activeAttempt?.runToken === runToken) {
        completedAttempt = activeAttempt;
        activeAttempt = null;
      }
      if (currentRunToken === runToken) preparing = false;
    }
  }
  function prepare() {
    const runToken = crypto.randomUUID();
    currentRunToken = runToken;
    preparing = true;
    cancelAttempt(activeAttempt);
    cancelAttempt(completedAttempt);
    activeAttempt = null;
    completedAttempt = null;
    const predecessor = pendingPreparation;
    pendingPreparation = (async () => {
      await predecessor.catch(() => {});
      await runPreparation(runToken);
    })();
    return pendingPreparation;
  }
  async function renderShell() {
    const state = document.querySelector("[data-offline-state]"),
      list = document.querySelector("[data-offline-list]");
    try {
      const identity = await actor(),
        record = await Store.getPreparation(
          `${userId}:${identity.deviceInstallationId}`,
        ),
        registration = await navigator.serviceWorker.ready,
        worker = registration.active || navigator.serviceWorker.controller,
        workerActor = worker ? await workerIdentity(worker) : null,
        items = (record?.items || []).filter((x) =>
          ["ready", "stale", "refreshing"].includes(x.state),
        );
      if (
        !workerActor ||
        workerActor.tombstone ||
        Number(workerActor.userId) !== userId ||
        Number(workerActor.epoch) !== Number(identity.state.epoch)
      )
        throw Error("Войдите снова, чтобы открыть сохранённые объекты.");
      list.replaceChildren(
        ...items.map((item) => {
          const a = document.createElement("a");
          a.className = "fm2-offline-object";
          a.dataset.offlineObject = "";
          a.dataset.objectId = item.objectId;
          a.dataset.search =
            `${item.address} ${item.registrationNumber} ${item.entrance}`.toLocaleLowerCase(
              "ru",
            );
          a.href = item.checklistUrl;
          a.innerHTML = `<span class="fm2-offline-object__copy"><strong></strong><span></span></span><span class="fm2-offline-object__status"></span>`;
          a.querySelector("strong").textContent =
            item.address + (item.entrance ? `, подъезд ${item.entrance}` : "");
          a.querySelector(".fm2-offline-object__copy span").textContent =
            `Рег. № ${item.registrationNumber}`;
          a.querySelector(".fm2-offline-object__status").textContent =
            item.completed ? "Завершён · только просмотр" : "Открыть чек-лист";
          return a;
        }),
      );
      state.textContent = items.length
        ? `Доступно без сети: ${plural(items.length)}`
        : "Сначала подключитесь и подготовьте объекты.";
      document.querySelector("[data-offline-login]").hidden = true;
      const search = document.querySelector("[data-offline-search]");
      search.addEventListener("input", () => {
        const q = search.value.trim().toLocaleLowerCase("ru");
        for (const row of list.children)
          row.hidden = q !== "" && !row.dataset.search.includes(q);
      });
    } catch (error) {
      state.textContent = error.message;
      document.querySelector("[data-offline-login]").hidden = false;
    }
  }
  document
    .querySelector("[data-offline-retry]")
    ?.addEventListener("click", prepare);
  window.addEventListener("pagehide", () => {
    cancelAttempt(activeAttempt);
    cancelAttempt(completedAttempt);
  });
  window.addEventListener("beforeinstallprompt", (event) => {
    event.preventDefault();
    deferredInstall = event;
    const button = document.querySelector("[data-offline-install]");
    if (button) button.hidden = false;
  });
  document
    .querySelector("[data-offline-install]")
    ?.addEventListener("click", async () => {
      if (!deferredInstall) return;
      await deferredInstall.prompt();
      deferredInstall = null;
    });
  Store?.onActorChange?.((change) => {
    if (Number(change.activeUserId) !== userId || change.tombstoned) {
      (queue || shell).hidden = true;
      const notice = document.createElement("p");
      notice.className = "shlz-alert";
      notice.setAttribute("role", "alert");
      notice.textContent =
        "Учётная запись изменилась. Войдите снова, чтобы открыть сохранённые данные.";
      (queue || shell).before(notice);
    }
  });
  if (shell) {
    (async () => {
      await Store.open();
      const device = await Store.deviceId(),
        state = await Store.actorState(device);
      if (!state?.activeUserId || state.tombstonedAt)
        throw Error("Войдите снова, чтобы открыть сохранённые объекты.");
      userId = Number(state.activeUserId);
      await renderShell();
    })().catch((error) => {
      document.querySelector("[data-offline-state]").textContent =
        error.message;
      document.querySelector("[data-offline-login]").hidden = false;
    });
  } else prepare();
})();
