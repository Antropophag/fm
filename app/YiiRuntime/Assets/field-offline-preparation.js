(() => {
  "use strict";
  const queue = document.querySelector("[data-control-queue]"),
    shell = document.querySelector("[data-offline-shell]");
  if (!queue && !shell) return;
  let userId = Number((queue || shell).dataset.userId);
  const Store = globalThis.FMonitorFieldStore;
  let preparing = false,
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
    const count = document.querySelector("[data-offline-count]");
    if (count) count.textContent = state === "ready"
      ? `Доступно оффлайн: ${plural(total)}`
      : state === "error"
        ? "Подготовка не удалась"
        : `Доступно оффлайн: ${completed} из ${total}`;
    const detail =
      message ||
      (state === "ready"
        ? `Готово без сети: ${plural(total)}`
        : `Готовим для работы без сети: ${completed} из ${total}`);
    const label = updatedAt
      ? `${detail} Обновлено ${new Intl.DateTimeFormat("ru-RU", { dateStyle: "short", timeStyle: "short" }).format(new Date(updatedAt))}`
      : detail;
    root.setAttribute("aria-label", label);
    root.title = label;
    root.querySelector(".fm2-visually-hidden").textContent = label;
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
        if (e.data?.ok) {
          resolve(e.data);
          return;
        }
        const error = Error(e.data?.message || "Не удалось сохранить объект.");
        error.reasonCode = e.data?.reasonCode;
        reject(error);
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
  function cachePresent(worker, item, identity, generation) {
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
          epoch: identity.state.epoch,
          generation,
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
      const ownAccepted = await workerCall(workerTarget, {
        type: "FIELD_SET_OWN_OBJECTS", userId, epoch: identity.state.epoch,
        generation, setVersion: manifest.setVersion, items: manifest.items,
      });
      if (!ownAccepted?.ok) throw Error("Не удалось подтвердить набор своих объектов.");
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
          const present = await cachePresent(workerTarget, item, identity, generation);
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
            errors.push({
              objectId: item.objectId,
              message: error.message,
              reasonCode: error.reasonCode,
            });
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
          ? errors.some((error) => error.reasonCode === "CACHE_BUDGET_EXHAUSTED")
            ? `Подготовлено ${done.size} из ${manifest.total}. Недостаточно места для сохранения остальных объектов.`
            : `Подготовлено ${done.size} из ${manifest.total}. Не удалось сохранить часть объектов.`
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
  function cacheStatusCopy(managed, count) {
    const messages = [];
    if (managed.storageVerified === false)
      messages.push("резерв хранилища не подтверждён");
    if (managed.limited) messages.push("очистка временно ограничена");
    const reason = managed.reasonCode || managed.reason;
    if (reason === "CACHE_BUDGET_EXHAUSTED")
      messages.push("недостаточно места для новых объектов");
    else if (reason && !managed.limited)
      messages.push("состояние хранилища требует подключения");
    const available = count
      ? `Доступно без сети: ${plural(count)}`
      : "Сначала подключитесь и подготовьте объекты";
    return `${available}${messages.length ? `. ${messages.join("; ")}.` : "."}`;
  }
  function sourceCopy(item) {
    const parts = [item.source === "own" ? "Свой объект" : "Подмена"];
    if (item.protected) parts.push("Есть несинхронизированные изменения");
    else if (item.source !== "own" && Number.isFinite(Number(item.expiresAt)))
      parts.push(
        `до ${new Intl.DateTimeFormat("ru-RU").format(new Date(Number(item.expiresAt)))}`,
      );
    return parts.join(" · ");
  }
  async function renderShell() {
    const state = document.querySelector("[data-offline-state]"),
      list = document.querySelector("[data-offline-list]");
    try {
      const identity = await actor(),
        registration = await navigator.serviceWorker.ready,
        worker = registration.active || navigator.serviceWorker.controller,
        workerActor = worker ? await workerIdentity(worker) : null,
        runtime = worker ? await runtimeInfo(worker) : null,
        managed = worker && runtime ? await workerCall(worker, {type:"FIELD_CACHE_STATUS",userId,epoch:identity.state.epoch,generation:runtime.generation}) : null,
        items = managed?.ok ? managed.items : [];
      if (
        !workerActor ||
        workerActor.tombstone ||
        Number(workerActor.userId) !== userId ||
        Number(workerActor.epoch) !== Number(identity.state.epoch)
      )
        throw Error("Войдите снова, чтобы открыть сохранённые объекты.");
      list.replaceChildren(
        ...items.map((item) => {
          const row = document.createElement("tr");
          row.className = "fm2-control-row"; row.dataset.offlineObject=""; row.dataset.controlRow="";
          row.dataset.objectId=String(item.objectId); row.dataset.completed=item.completed?"true":"false";
          row.innerHTML = `<td class="fm2-object-cell"><a class="fm2-control-link" rel="nofollow"><span class="fm2-control-address"></span><span class="fm2-control-reg"></span></a><small data-offline-source></small></td><td data-offline-activity></td><td class="fm2-shipment-cell" aria-label="Данные о поставке доступны после подключения" title="Требуется подключение"><span>Нет сохранённых данных</span></td><td>Нет сохранённых данных</td><td class="fm2-row-action"><a class="fm2-control-checklist-rail" aria-label="Открыть чек-лист"><img src="/pilot/assets/shlz-icons/chevron-right-duo.svg" alt=""></a></td>`;
          row.querySelector(".fm2-control-address").textContent =
            item.address + (item.entrance ? `, подъезд ${item.entrance}` : "");
          row.querySelector(".fm2-control-reg").textContent =
            `Рег. № ${item.registrationNumber}`;
          row.querySelector("[data-offline-activity]").textContent = item.completed
            ? "Завершён · только просмотр"
            : "Нет сохранённых данных";
          const label=row.querySelector("[data-offline-source]");
          label.textContent=sourceCopy(item);
          for(const link of row.querySelectorAll("a"))link.href=item.checklistUrl;
          row.dataset.search=[item.address,item.entrance,item.registrationNumber].join(" ");
          return row;
        }),
      );
      state.textContent = cacheStatusCopy(managed, items.length);
      document.querySelector("[data-offline-login]").hidden = true;
      const search = document.querySelector("[data-offline-search]");
      const completed=document.querySelector("[data-show-completed]"), summary=document.querySelector("[data-list-summary]"), empty=document.querySelector("[data-control-empty]");
      const params = new URLSearchParams(location.search);
      search.value = params.get("query") || "";
      if (completed) completed.checked = params.get("completed") === "1";
      const apply=() => {
        const ids = new Set(globalThis.FMonitorAddressSearch.ids(items.map((item) => ({
          id: String(item.objectId),
          address: String(item.address || ""),
          entrance: item.entrance == null ? "" : String(item.entrance),
          extra: [item.registrationNumber == null ? "" : String(item.registrationNumber)],
        })), search.value));
        let shown=0;for (const row of list.children){row.hidden=!ids.has(row.dataset.objectId)||(!completed?.checked&&row.dataset.completed==="true");if(!row.hidden)shown++;}
        if(summary)summary.textContent=`Показано ${shown} из ${items.length}`;
        if(empty){
          empty.hidden=shown!==0;
          const title=empty.querySelector("strong"),copy=empty.querySelector("span");
          const hasQuery=search.value.trim()!=="";
          const completedHidden=items.length>0&&!hasQuery&&!completed?.checked&&items.every((item)=>item.completed);
          if(title)title.textContent=completedHidden?"Завершённые объекты скрыты":hasQuery?"Объекты не найдены":"Сохранённых объектов пока нет";
          if(copy)copy.textContent=completedHidden?"Включите показ завершённых объектов или сбросьте фильтры.":hasQuery?"Измените запрос или сбросьте фильтры.":"Подключитесь к сети, чтобы подготовить объекты для работы без сети.";
        }
      };
      search.addEventListener("input",apply);completed?.addEventListener("change",apply);
      document.querySelector("[data-clear-filters]")?.addEventListener("click",()=>{search.value="";if(completed)completed.checked=false;apply();search.focus();});apply();
    } catch (error) {
      state.textContent = error.message;
      document.querySelector("[data-offline-login]").hidden = false;
    }
  }
  window.addEventListener("pagehide", () => {
    cancelAttempt(activeAttempt);
    cancelAttempt(completedAttempt);
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
