((globalThis.FMonitorChecklistProgressCap = (value, cap) =>
  cap === 100 ? 100 : Math.min(Number(value), Number(cap))),
  (() => {
    "use strict";
    const root = document.querySelector("[data-checklist]");
    if (!root) return;
    const userId = Number(root.dataset.userId),
      objectId = Number(root.dataset.objectId),
      itemEnabled = root.dataset.itemCompletionEnabled === "true",
      legacyEnabled = root.dataset.legacyOperationsEnabled === "true";
    const decode = (value) =>
        JSON.parse(
          new TextDecoder("utf-8", { fatal: true }).decode(
            Uint8Array.from(atob(value), (x) => x.charCodeAt(0)),
          ),
        ),
      initial = decode(root.dataset.projection),
      crew = initial.crew || [];
    let session,
      snapshot = {
        operations: [],
        unsavedPhotos: [],
        projection: initial,
        connection: { state: navigator.onLine ? "online" : "offline" },
        storage: {},
      },
      busy = false;
    let resolveSession, rejectSession;
    const sessionReady = new Promise((resolve, reject) => {
      resolveSession = resolve;
      rejectSession = reject;
    });
    sessionReady.catch(() => {});
    const sections = [...root.querySelectorAll("[data-check-section]")].filter(
        (x) => x.querySelector("[data-check-item]"),
      ),
      toast = root.querySelector("[data-toast]"),
      banner = root.querySelector("[data-sync-banner]"),
      reasonDialog = root.querySelector("[data-reason-dialog]");
    const mutatingControls = [
      ...root.querySelectorAll(
        ".fm2-check-toggle,[data-check-all],[data-installer-edit],[data-photo-input]",
      ),
    ];
    for (const control of mutatingControls) {
      control.dataset.fieldInitiallyDisabled = control.disabled
        ? "true"
        : "false";
      control.disabled = true;
    }
    for (const box of root.querySelectorAll(".fm2-check-box")) {
      const mark = document.createElement("span");
      mark.className = "fm2-check-mark";
      mark.setAttribute("aria-hidden", "true");
      mark.textContent = "✓";
      box.replaceChildren(mark);
    }
    banner.dataset.state = "loading";
    banner.querySelector("[data-sync-copy]").textContent =
      "Загружаем сохранённые данные…";
    const completingSections = new Set();
    function askReason(title, copy) {
      return new Promise((resolve) => {
        const input = reasonDialog.querySelector("[data-reason-input]");
        reasonDialog.querySelector("[data-reason-title]").textContent = title;
        reasonDialog.querySelector("[data-reason-copy]").textContent = copy;
        input.value = "";
        const finish = (value) => {
            reasonDialog.close();
            reasonDialog
              .querySelector("[data-reason-confirm]")
              .removeEventListener("click", confirmReason);
            reasonDialog
              .querySelector("[data-reason-cancel]")
              .removeEventListener("click", cancel);
            resolve(value);
          },
          confirmReason = () => {
            const value = input.value.trim();
            if (value) finish(value);
          },
          cancel = () => finish(null);
        reasonDialog
          .querySelector("[data-reason-confirm]")
          .addEventListener("click", confirmReason);
        reasonDialog
          .querySelector("[data-reason-cancel]")
          .addEventListener("click", cancel);
        reasonDialog.showModal();
        input.focus();
      });
    }
    function confirmAction(title, copy, action) {
      return new Promise((resolve) => {
        const dialog = document.createElement("dialog");
        dialog.className = "shlz-modal fm2-confirm-dialog";
        dialog.innerHTML =
          '<div class="shlz-modal__surface"><header class="shlz-modal__header"><h2 class="shlz-modal__title"></h2></header><div class="shlz-modal__body"><p></p></div><footer class="shlz-modal__footer"><button class="shlz-button shlz-button--secondary" type="button" data-cancel>Отмена</button><button class="shlz-button shlz-button--primary" type="button" data-confirm></button></footer></div>';
        dialog.querySelector("h2").textContent = title;
        dialog.querySelector("p").textContent = copy;
        dialog.querySelector("[data-confirm]").textContent = action;
        const finish = (value) => {
          dialog.close();
          dialog.remove();
          resolve(value);
        };
        dialog
          .querySelector("[data-cancel]")
          .addEventListener("click", () => finish(false));
        dialog
          .querySelector("[data-confirm]")
          .addEventListener("click", () => finish(true));
        document.body.append(dialog);
        dialog.showModal();
      });
    }
    function notify(message, error = false) {
      toast.textContent = message;
      toast.hidden = false;
      toast.dataset.state = error ? "error" : "success";
      clearTimeout(notify.timer);
      notify.timer = setTimeout(() => (toast.hidden = true), 5000);
    }
    function operationStatus(operation) {
      if (operation.storageError)
        return "Сохранённое фото недоступно. Не удаляйте исходное действие: сохраните сведения и обратитесь в поддержку для восстановления.";
      return (
        {
          queued: "Сохранено на устройстве · ожидает отправки",
          sending: "Отправляется",
          retryable_error: "Связь недоступна · отправим позже",
          uncertain: "Результат отправки не подтверждён · данные сохранены",
          auth_required: "Войдите снова — данные сохранены",
          access_denied: "Доступ отозван — данные сохранены",
          storage_recovery:
            "Локальное вложение требует восстановления — исходное действие сохранено",
          conflict: "Требует решения",
          rejected: "Отклонено сервером",
          accepted: "Принято сервером",
          superseded: "Заменено новым действием",
          discarded: "Удалено с устройства",
        }[operation.status] || operation.status
      );
    }
    function itemTitle(itemId) {
      const label = root.querySelector(
        `[data-check-item="${Number(itemId)}"] .fm2-check-label`,
      );
      return label?.childNodes[0]?.textContent?.trim() || `Работа ${itemId}`;
    }
    function sectionTitle(sectionId) {
      return (
        root
          .querySelector(
            `[data-check-section="${Number(sectionId)}"] .fm2-section-title strong`,
          )
          ?.textContent?.trim() || `Раздел ${sectionId}`
      );
    }
    function operationIdentity(operation) {
      if (
        [
          "item_completed",
          "item_installers_changed",
          "completion_retracted",
        ].includes(operation.type)
      )
        return `${sectionTitle(operation.sectionId)} · ${itemTitle(operation.itemId)}`;
      if (operation.type === "photo_uploaded")
        return `${sectionTitle(operation.sectionId)} · фото «${operation.originalName || "без названия"}»`;
      if (operation.type === "photo_revoked")
        return `${sectionTitle(operation.sectionId)} · отзыв фото`;
      if (operation.type === "section_completed")
        return `${sectionTitle(operation.sectionId)} · завершение раздела`;
      return "Местное действие";
    }
    function reviewAction(clientOperationId) {
      return new Promise((resolve) => {
        const dialog = document.createElement("dialog");
        dialog.className = "shlz-modal fm2-field-review";
        dialog.innerHTML =
          '<div class="shlz-modal__surface"><header class="shlz-modal__header"><h2 class="shlz-modal__title">Посмотреть изменения</h2></header><div class="shlz-modal__body"><section data-field-review-local><h3>Местное действие</h3><p>Загружаем…</p></section><section data-field-review-server><h3>Текущее состояние на сервере</h3><p data-review-state role="status">Загружаем изменения…</p><div data-review-items></div></section></div><footer class="shlz-modal__footer"><button class="shlz-button shlz-button--secondary" type="button" data-review-cancel>Отмена</button><button class="shlz-button shlz-button--secondary" type="button" data-review-retry hidden>Повторить загрузку</button><button class="shlz-button shlz-button--primary" type="button" data-review-confirm disabled>Применить заново</button></footer></div>';
        const local = dialog.querySelector("[data-field-review-local] p");
        const state = dialog.querySelector("[data-review-state]");
        const items = dialog.querySelector("[data-review-items]");
        const confirm = dialog.querySelector("[data-review-confirm]");
        const retry = dialog.querySelector("[data-review-retry]");
        let review = null;
        const close = (value) => {
          dialog.close();
          dialog.remove();
          resolve(value);
        };
        const load = async () => {
          confirm.disabled = true;
          retry.hidden = true;
          state.textContent = "Загружаем изменения…";
          items.replaceChildren();
          try {
            review = await session.review(clientOperationId);
            local.textContent = operationIdentity(review.operation);
            for (const row of root.querySelectorAll("[data-check-item]")) {
              const itemId = Number(row.dataset.checkItem);
              const entry = document.createElement("p");
              const completed = Boolean(
                review.projection.items?.[String(itemId)],
              );
              entry.dataset.reviewItem = String(itemId);
              entry.dataset.completed = String(completed);
              entry.textContent = `${itemTitle(itemId)} — ${completed ? "выполнено" : "не отмечено"}`;
              items.append(entry);
            }
            state.textContent = "Показано актуальное состояние сервера.";
            confirm.disabled = false;
          } catch (error) {
            review = null;
            state.textContent =
              error.message || "Не удалось загрузить изменения.";
            retry.hidden = false;
          }
        };
        dialog
          .querySelector("[data-review-cancel]")
          .addEventListener("click", () => close(null));
        retry.addEventListener("click", load);
        confirm.addEventListener("click", () => {
          if (review) close(review.baseRevision);
        });
        document.body.append(dialog);
        dialog.showModal();
        load();
      });
    }
    function activeOperations() {
      return snapshot.operations.filter(
        (x) => !["discarded", "superseded"].includes(x.status),
      );
    }
    function sectionCompleted(sectionId) {
      return (
        Boolean(snapshot.projection.completedSections?.[String(sectionId)]) ||
        activeOperations().some(
          (operation) =>
            operation.type === "section_completed" &&
            Number(operation.sectionId) === sectionId &&
            !["rejected", "conflict"].includes(operation.status),
        )
      );
    }
    function completedItems() {
      const map = new Map(
        Object.entries(snapshot.projection.items || {}).map(([id, value]) => [
          Number(id),
          { ...value, status: "accepted" },
        ]),
      );
      for (const operation of activeOperations())
        if (
          ["item_completed", "item_installers_changed"].includes(
            operation.type,
          ) &&
          operation.status !== "rejected" &&
          operation.status !== "conflict"
        )
          map.set(Number(operation.itemId), operation);
        else if (
          operation.type === "completion_retracted" &&
          operation.status !== "rejected" &&
          operation.status !== "conflict"
        )
          map.delete(Number(operation.itemId));
      return map;
    }
    function photos() {
      const map = new Map(
        (snapshot.projection.photos || []).map((x) => [
          x.clientOperationId,
          { ...x, status: "accepted" },
        ]),
      );
      for (const operation of activeOperations())
        if (
          operation.type === "photo_uploaded" &&
          operation.status !== "rejected" &&
          operation.status !== "conflict"
        ) {
          const accepted = map.get(operation.clientOperationId);
          map.set(
            operation.clientOperationId,
            accepted
              ? { ...operation, ...accepted, status: "accepted" }
              : operation,
          );
        } else if (
          operation.type === "photo_revoked" &&
          operation.status !== "rejected" &&
          operation.status !== "conflict"
        )
          for (const [key, value] of map)
            if (Number(value.id) === Number(operation.photoId)) map.delete(key);
      return [...map.values()];
    }
    function clearUnsavedPhotoCards() {
      for (const container of root.querySelectorAll("[data-unsaved-photos]"))
        container.remove();
    }
    function renderUnsavedPhotos(section, sectionId) {
      const strip = section.querySelector("[data-photo-strip]");
      if (!strip) return;
      const photos = (snapshot.unsavedPhotos || []).filter(
        (photo) => Number(photo.sectionId) === sectionId,
      );
      let container = section.querySelector("[data-unsaved-photos]");
      if (!photos.length) {
        container?.remove();
        return;
      }
      if (!container) {
        container = document.createElement("div");
        container.className = "fm2-unsaved-photos";
        container.dataset.unsavedPhotos = "";
        strip.after(container);
      }
      container.replaceChildren(
        ...photos.map((photo) => {
          const card = document.createElement("article");
          card.className = "fm2-unsaved-photo";
          card.dataset.unsavedPhoto = "";
          card.dataset.unsavedPhotoId = photo.id;

          const copy = document.createElement("div");
          copy.className = "fm2-unsaved-photo__copy";
          const name = document.createElement("strong");
          name.textContent = photo.name;
          const status = document.createElement("span");
          status.textContent = "Не сохранено";
          copy.append(name, status);
          if (photo.message) {
            const message = document.createElement("small");
            message.textContent = photo.message;
            copy.append(message);
          }

          const actions = document.createElement("div");
          actions.className = "fm2-unsaved-photo__actions";
          const download = document.createElement(
            photo.previewUrl ? "a" : "button",
          );
          download.className = "shlz-button shlz-button--secondary";
          download.dataset.unsavedDownload = "";
          if (photo.previewUrl) {
            download.href = photo.previewUrl;
            download.download = photo.name;
          } else {
            download.type = "button";
          }
          download.textContent = "Скачать";
          const retry = document.createElement("button");
          retry.className = "shlz-button shlz-button--primary";
          retry.type = "button";
          retry.dataset.unsavedRetry = "";
          retry.disabled = Boolean(photo.saving);
          retry.textContent = photo.saving
            ? "Сохраняем…"
            : "Повторить сохранение";
          const discard = document.createElement("button");
          discard.className = "shlz-button shlz-button--secondary";
          discard.type = "button";
          discard.dataset.unsavedDiscard = "";
          discard.disabled = Boolean(photo.saving);
          discard.textContent = "Удалить";
          actions.append(download, retry, discard);
          card.append(copy, actions);
          return card;
        }),
      );
    }
    function render() {
      const checked = completedItems(),
        allPhotos = photos();
      for (const section of sections) {
        const sectionId = Number(section.dataset.checkSection),
          items = [...section.querySelectorAll("[data-check-item]")],
          sectionPhotos = allPhotos.filter(
            (x) => Number(x.sectionId) === sectionId,
          );
        let weight = 0,
          count = 0;
        for (const row of items) {
          const id = Number(row.dataset.checkItem),
            operation = checked.get(id),
            button = row.querySelector(".fm2-check-toggle");
          row.classList.toggle("is-checked", Boolean(operation));
          button.setAttribute("aria-checked", operation ? "true" : "false");
          row.querySelector("[data-item-sync]").textContent = operation
            ? operationStatus(operation)
            : "Не отмечено";
          if (operation) {
            count++;
            weight += Number(row.dataset.weight) || 0;
          }
        }
        section.querySelector("[data-section-count]").textContent =
          `${count} из ${items.length}`;
        section.querySelector("[data-section-progress]").textContent =
          `${weight} из ${section.dataset.sectionWeight}%`;
        section.querySelector("[data-section-state]").textContent =
          sectionCompleted(sectionId)
            ? "Раздел завершён"
            : count === items.length && !sectionPhotos.length
              ? "Нужно фото"
              : count === items.length
                ? "Готов к завершению"
                : count
                  ? "В работе"
                  : "Не начат";
        const sectionReady = count === items.length && sectionPhotos.length > 0;
        section.classList.toggle("is-complete", sectionReady);
        section.classList.toggle(
          "is-progressing",
          count > 0 && count < items.length,
        );
        section.classList.toggle(
          "needs-photo",
          count === items.length && sectionPhotos.length === 0,
        );
        const bulk = section.querySelector("[data-check-all]");
        bulk?.classList.toggle("is-checked", count === items.length);
        if (bulk) {
          bulk.disabled = !legacyEnabled || count === items.length;
          bulk.setAttribute(
            "aria-label",
            count === items.length
              ? "Все работы раздела отмечены"
              : "Отметить все работы раздела",
          );
        }
        const strip = section.querySelector("[data-photo-strip]");
        const existingPhotos = new Map(
          [...strip.querySelectorAll("[data-photo-key]")].map((figure) => [
            figure.dataset.photoKey,
            figure,
          ]),
        );
        strip.replaceChildren();
        if (!sectionPhotos.length) {
          const empty = document.createElement("span");
          empty.className = "fm2-photo-empty";
          empty.textContent = "Фотографий пока нет";
          strip.append(empty);
        }
        let photoIndex = 0;
        for (const photo of sectionPhotos) {
          photoIndex += 1;
          const photoKey = String(photo.clientOperationId || photo.id);
          const photoUrl =
            photo.previewUrl || photo.thumbnailUrl || photo.viewUrl || "";
          const photoSignature = `${photoUrl}|${photo.status}|${photo.id || ""}`;
          const existing = existingPhotos.get(photoKey);
          if (existing?.dataset.photoSignature === photoSignature) {
            strip.append(existing);
            continue;
          }
          const figure = document.createElement("figure");
          figure.className = "fm2-photo-thumb";
          figure.title = operationStatus(photo);
          figure.dataset.photoKey = photoKey;
          figure.dataset.photoSignature = photoSignature;
          const link = document.createElement("a");
          link.href = photo.viewUrl || photo.previewUrl || "#";
          if (photo.viewUrl) {
            link.target = "_blank";
            link.rel = "noopener";
          }
          const img = document.createElement("img");
          img.alt = photo.originalName || "Фото раздела";
          img.src = photoUrl;
          img.addEventListener("error", () => {
            img.remove();
            let error = figure.querySelector("[data-photo-error]");
            if (!error) {
              error = document.createElement("span");
              error.dataset.photoError = "";
              const errorCopy = document.createElement("span");
              errorCopy.textContent = "Не удалось загрузить фото";
              const retry = document.createElement("button");
              retry.type = "button";
              retry.textContent = "Повторить";
              let sequence = 0;
              retry.addEventListener("click", () => {
                sequence++;
                img.src = `${photo.thumbnailUrl}${photo.thumbnailUrl.includes("?") ? "&" : "?"}retry=${Date.now() + sequence}`;
                link.prepend(img);
                error.remove();
              });
              error.append(errorCopy, retry);
              figure.prepend(error);
            }
          });
          link.append(img);
          figure.append(link);
          const caption = document.createElement("figcaption");
          caption.textContent = `Фото ${photoIndex}`;
          figure.append(caption);
          const serverPhotoId = Number(photo.id);
          if (
            photo.status === "accepted" &&
            Number.isInteger(serverPhotoId) &&
            serverPhotoId > 0 &&
            root.dataset.photoRevokeEnabled === "true"
          ) {
            const revoke = document.createElement("button");
            revoke.type = "button";
            revoke.textContent = "×";
            revoke.setAttribute("aria-label", `Отозвать фото ${photoIndex}`);
            revoke.title = `Отозвать фото ${photoIndex}`;
            revoke.addEventListener("click", async () => {
              const reason = await askReason(
                "Отозвать фото?",
                "Фото останется в истории, но исчезнет из актуального чек-листа.",
              );
              if (reason)
                await session.enqueue("photo_revoked", {
                  sectionId,
                  photoId: serverPhotoId,
                  reason,
                });
            });
            figure.append(revoke);
          }
          strip.append(figure);
        }
        renderUnsavedPhotos(section, sectionId);
      }
      const totalWeight = [...checked.keys()].reduce(
          (sum, id) =>
            sum +
            Number(
              root.querySelector(`[data-check-item="${id}"]`)?.dataset.weight ||
                0,
            ),
          0,
        ),
        cap = Number(
          root.querySelector("[data-total-progress]").dataset.progressCap,
        );
      root.querySelector("[data-total-progress]").textContent =
        globalThis.FMonitorChecklistProgressCap(totalWeight, cap);
      root.querySelector("[data-total-items]").textContent = checked.size;
      root.querySelector("[data-photo-total]").textContent = allPhotos.length;
      renderOperations();
      renderConnection();
    }
    function renderConnection() {
      const state = snapshot.connection.state,
        copy =
          {
            online: "Синхронизировано",
            offline: "Сохранение на устройстве",
            sending: "Отправляется",
            pending: "Сохранено на устройстве — ожидает отправки",
            retryable: "Связь недоступна — данные сохранены",
            auth_required: "Войдите снова. Данные сохранены",
            access_denied: "Доступ отозван — данные сохранены",
            uncertain: "Результат отправки не подтверждён",
            storage_recovery:
              snapshot.connection.message ||
              "Локальные данные требуют восстановления",
            conflict: "Есть действие, требующее решения",
          }[state] ||
          snapshot.connection.message ||
          "Сохранено на устройстве";
      banner.dataset.state = state === "online" ? "ok" : state;
      banner.querySelector("[data-sync-copy]").textContent = copy;
      const retry = banner.querySelector("[data-sync-now]");
      retry.hidden = ![
        "retryable",
        "auth_required",
        "conflict",
        "offline",
      ].includes(state);
      let storage = root.querySelector("[data-storage-warning]");
      const nearlyFull =
        Number(snapshot.storage?.quota) > 0 &&
        Number(snapshot.storage?.usage) / Number(snapshot.storage?.quota) > 0.9;
      const storageMessage = nearlyFull
        ? "На устройстве заканчивается место. Отправьте сохранённые данные перед новыми фотографиями."
        : snapshot.storage?.message || "";
      if (storageMessage && !storage) {
        storage = document.createElement("p");
        storage.dataset.storageWarning = "";
        storage.className = "fm2-storage-warning";
        storage.setAttribute("role", "status");
        root.querySelector(".fm2-check-topline")?.after(storage);
      }
      if (storage) {
        storage.textContent = storageMessage;
        storage.hidden = !storageMessage;
      }
    }
    function scheduleCompletions() {
      if (!session || !legacyEnabled) return;
      const checked = completedItems(),
        currentPhotos = photos();
      for (const section of sections) {
        const sectionId = Number(section.dataset.checkSection),
          ids = [...section.querySelectorAll("[data-check-item]")].map((row) =>
            Number(row.dataset.checkItem),
          );
        if (
          ids.every((id) => checked.has(id)) &&
          currentPhotos.some(
            (photo) => Number(photo.sectionId) === sectionId,
          ) &&
          !sectionCompleted(sectionId) &&
          !completingSections.has(sectionId)
        ) {
          completingSections.add(sectionId);
          session
            .enqueue("section_completed", { sectionId })
            .catch((error) => notify(error.message, true))
            .finally(() => completingSections.delete(sectionId));
        }
      }
    }
    function renderOperations() {
      let panel = root.querySelector("[data-field-operations]");
      if (!panel) {
        panel = document.createElement("section");
        panel.className = "fm2-field-operations";
        panel.dataset.fieldOperations = "";
        panel.setAttribute("aria-live", "polite");
        root.querySelector(".fm2-check-content").prepend(panel);
      }
      panel.replaceChildren();
      const routineDetails = document.createElement("details");
      routineDetails.dataset.fieldQueueDetails = "";
      const routineSummary = document.createElement("summary");
      const routineRows = document.createElement("div");
      routineDetails.append(routineSummary, routineRows);
      let routineCount = 0;
      for (const operation of activeOperations().filter(
        (x) => !["accepted"].includes(x.status),
      )) {
        const row = document.createElement("article");
        row.className = "fm2-field-operation";
        row.dataset.fieldOperation = "";
        row.dataset.operationId = operation.clientOperationId;
        row.dataset.status = operation.status;
        const text = document.createElement("p");
        text.textContent =
          operationIdentity(operation) +
          " · " +
          operationStatus(operation) +
          (operation.message ? ` · ${operation.message}` : "");
        row.append(text);
        const actions = document.createElement("div");
        if (["conflict", "rejected"].includes(operation.status)) {
          const resolve = document.createElement("button");
          resolve.type = "button";
          resolve.className = "shlz-button shlz-button--secondary";
          resolve.dataset.fieldResolve = "";
          resolve.textContent = "Посмотреть изменения";
          actions.append(resolve);
        }
        const exportButton = document.createElement("button");
        exportButton.type = "button";
        exportButton.className = "shlz-button";
        exportButton.dataset.fieldExport = "";
        exportButton.textContent =
          operation.type === "photo_uploaded"
            ? "Скачать фото"
            : "Сохранить сведения";
        actions.append(exportButton);
        const mayDiscard =
          ["rejected", "conflict"].includes(operation.status) ||
          (operation.status === "queued" && !operation.attempted);
        if (mayDiscard) {
          const discard = document.createElement("button");
          discard.type = "button";
          discard.className = "shlz-button";
          discard.dataset.fieldDiscard = "";
          discard.textContent = "Убрать местное действие";
          actions.append(discard);
        }
        row.append(actions);
        if (["queued", "sending"].includes(operation.status)) {
          routineCount += 1;
          routineRows.append(row);
        } else {
          panel.append(row);
        }
      }
      if (routineCount > 0) {
        routineSummary.textContent = `${routineCount} ${routineCount === 1 ? "действие сохранено на устройстве" : "действий сохранено на устройстве"}`;
        panel.append(routineDetails);
      }
    }
    function sectionIdFor(item) {
      return Number(item.closest("[data-check-section]").dataset.checkSection);
    }
    async function enqueueItem(row) {
      if (!itemEnabled || busy) return;
      busy = true;
      try {
        await sessionReady;
        const itemId = Number(row.dataset.checkItem),
          existing = completedItems().get(itemId),
          sectionId = sectionIdFor(row);
        if (existing) {
          if (!legacyEnabled) return;
          const reason = await askReason(
            "Снять отметку?",
            "Укажите причину исправления выполненной работы.",
          );
          if (!reason) return;
          await session.enqueue("completion_retracted", {
            sectionId,
            itemId,
            originalClientOperationId: existing.clientOperationId,
            reason,
          });
        } else
          await session.enqueue("item_completed", {
            sectionId,
            itemId,
            installerTabIds: crew
              .filter((x) => x.employmentStatus !== "dismissed")
              .map((x) => String(x.tabId)),
          });
        notify("Действие сохранено на устройстве.");
      } catch (error) {
        notify(error.message, true);
      } finally {
        busy = false;
      }
    }
    const bulkDialog = root.querySelector("[data-bulk-dialog]");
    let bulkSection = null;
    bulkDialog
      ?.querySelector("[data-bulk-cancel]")
      .addEventListener("click", () => {
        bulkDialog.close();
        bulkSection = null;
      });
    bulkDialog
      ?.querySelector("[data-bulk-confirm]")
      .addEventListener("click", async () => {
        const section = bulkSection;
        bulkDialog.close();
        bulkSection = null;
        if (!section) return;
        try {
          await sessionReady;
          const sectionId = Number(section.dataset.checkSection);
          const installerTabIds = crew
            .filter((person) => person.employmentStatus !== "dismissed")
            .map((person) => String(person.tabId));
          const payloads = [...section.querySelectorAll("[data-check-item]")]
            .filter(
              (row) => !completedItems().has(Number(row.dataset.checkItem)),
            )
            .map((row) => ({
              sectionId,
              itemId: Number(row.dataset.checkItem),
              installerTabIds,
            }));
          if (payloads.length)
            await session.enqueueBatch("item_completed", payloads);
          scheduleCompletions();
          notify("Работы раздела сохранены на устройстве.");
        } catch (error) {
          notify(error.message, true);
        }
      });
    for (const section of sections) {
      const head = section.querySelector(".fm2-section-toggle");
      head.addEventListener("click", () => {
        const open = head.getAttribute("aria-expanded") === "true";
        head.setAttribute("aria-expanded", String(!open));
        section.classList.toggle("is-open", !open);
      });
      for (const row of section.querySelectorAll("[data-check-item]")) {
        row
          .querySelector(".fm2-check-toggle")
          .addEventListener("click", () => enqueueItem(row));
        row
          .querySelector("[data-installer-edit]")
          ?.addEventListener("click", () => openInstallers(row));
      }
      section
        .querySelector("[data-check-all]")
        ?.addEventListener("click", () => {
          bulkSection = section;
          bulkDialog.querySelector("[data-bulk-section-name]").textContent =
            section
              .querySelector(".fm2-section-title strong")
              ?.textContent?.trim() || "";
          bulkDialog.showModal();
        });
      for (const input of section.querySelectorAll("[data-photo-input]"))
        input.addEventListener("change", async () => {
          for (const file of input.files || [])
            try {
              await sessionReady;
              const prepared =
                await globalThis.FMonitorField.preparePhoto(file);
              await session.enqueue(
                "photo_uploaded",
                {
                  sectionId: Number(section.dataset.checkSection),
                  mime: prepared.mime,
                  size: prepared.size,
                  sha256: prepared.sha256,
                  originalName: prepared.name,
                },
                prepared.blob,
              );
              notify("Фото сохранено на устройстве.");
            } catch (error) {
              notify(error.message, true);
            }
          input.value = "";
        });
    }
    const installerDialog = root.querySelector("[data-installer-dialog]");
    let installerRow = null;
    function openInstallers(row) {
      const completed = completedItems().get(Number(row.dataset.checkItem));
      if (!completed) return;
      installerRow = row;
      const selected = new Set(
          (
            completed.installerTabIds ||
            completed.installers?.map((x) => x.tabId) ||
            crew.map((x) => x.tabId)
          ).map(String),
        ),
        options = installerDialog.querySelector("[data-installer-options]");
      options.replaceChildren(
        ...crew.map((person) => {
          const label = document.createElement("label");
          label.className = "shlz-choice";
          const input = document.createElement("input");
          input.type = "checkbox";
          input.className = "shlz-checkbox";
          input.value = String(person.tabId);
          input.checked = selected.has(String(person.tabId));
          input.disabled =
            person.employmentStatus === "dismissed" && !input.checked;
          label.append(
            input,
            document.createTextNode(
              person.fio || person.fullName || String(person.tabId),
            ),
          );
          return label;
        }),
      );
      installerDialog.showModal();
    }
    installerDialog
      .querySelectorAll("[data-installer-cancel]")
      .forEach((button) =>
        button.addEventListener("click", () => installerDialog.close()),
      );
    installerDialog
      .querySelector("[data-installer-save]")
      .addEventListener("click", async () => {
        const ids = [
          ...installerDialog.querySelectorAll("input[type=checkbox]:checked"),
        ].map((x) => x.value);
        if (!ids.length) {
          installerDialog.querySelector("[data-installer-error]").hidden =
            false;
          return;
        }
        try {
          await session.enqueue("item_installers_changed", {
            sectionId: sectionIdFor(installerRow),
            itemId: Number(installerRow.dataset.checkItem),
            installerTabIds: ids,
          });
          installerDialog.close();
        } catch (error) {
          notify(error.message, true);
        }
      });
    banner.querySelector("[data-sync-now]").addEventListener("click", () => {
      if (!session) {
        location.reload();
        return;
      }
      session.retry().catch((error) => notify(error.message, true));
    });
    root.querySelector("[data-open-gallery]").addEventListener("click", () => {
      const gallery = root.querySelector("[data-gallery]"),
        content = gallery.querySelector("[data-gallery-content]");
      content.replaceChildren();
      for (const photo of photos()) {
        const link = document.createElement("a");
        link.href = photo.viewUrl || photo.previewUrl || "#";
        if (photo.viewUrl) {
          link.target = "_blank";
          link.rel = "noopener";
        }
        const img = document.createElement("img");
        img.src = photo.previewUrl || photo.thumbnailUrl || photo.viewUrl || "";
        img.alt = photo.originalName || "Фото чек-листа";
        link.append(img);
        content.append(link);
      }
      gallery.hidden = false;
      root.querySelector(".fm2-check-layout").hidden = true;
    });
    root.querySelector("[data-close-gallery]").addEventListener("click", () => {
      root.querySelector("[data-gallery]").hidden = true;
      root.querySelector(".fm2-check-layout").hidden = false;
    });
    root.addEventListener("click", async (event) => {
      const card = event.target.closest("[data-unsaved-photo]");
      if (!card) return;
      const id = card.dataset.unsavedPhotoId;
      try {
        const download = event.target.closest("[data-unsaved-download]");
        if (download) {
          if (download instanceof HTMLAnchorElement) return;
          if (!session) throw Error("Локальная сессия ещё не готова.");
          const file = session.exportUnsavedPhoto(id);
          let url = URL.createObjectURL(file.blob);
          try {
            const link = document.createElement("a");
            link.href = url;
            link.download = file.name;
            link.hidden = true;
            document.body.append(link);
            link.click();
            link.remove();
            const acceptedUrl = url;
            url = null;
            setTimeout(() => URL.revokeObjectURL(acceptedUrl), 1000);
          } finally {
            if (url) URL.revokeObjectURL(url);
          }
          return;
        }
        const activeSession = await sessionReady;
        if (event.target.closest("[data-unsaved-retry]")) {
          await activeSession.retryUnsavedPhoto(id);
          notify("Фото сохранено на устройстве.");
        }
        if (
          event.target.closest("[data-unsaved-discard]") &&
          (await confirmAction(
            "Удалить несохранённое фото?",
            "Фото не сохранено на устройстве и после удаления не сможет быть восстановлено из этой вкладки.",
            "Удалить фото",
          ))
        )
          await activeSession.discardUnsavedPhoto(id);
      } catch (error) {
        notify(error.message, true);
      }
    });
    root.addEventListener("click", async (event) => {
      const row = event.target.closest("[data-field-operation]");
      if (!row) return;
      const id = row.dataset.operationId;
      try {
        if (event.target.closest("[data-field-resolve]")) {
          const reviewedRevision = await reviewAction(id);
          if (reviewedRevision) await session.resolve(id, reviewedRevision);
        }
        if (
          event.target.closest("[data-field-discard]") &&
          (await confirmAction(
            "Убрать местное действие?",
            "Неотправленные данные этого действия будут удалены с устройства. Серверная история не изменится.",
            "Убрать местное действие",
          ))
        )
          await session.discard(id);
        if (event.target.closest("[data-field-export]")) {
          const file = await session.export(id),
            link = document.createElement("a");
          link.href = URL.createObjectURL(file.blob);
          link.download = file.name;
          link.click();
          setTimeout(() => URL.revokeObjectURL(link.href), 1000);
        }
      } catch (error) {
        notify(error.message, true);
      }
    });
    (async () => {
      if (globalThis.FMonitorFieldLifecycle?.ready)
        await globalThis.FMonitorFieldLifecycle.ready;
      const value = await globalThis.FMonitorField.open({
        userId,
        objectId,
        projection: initial,
        onClose: clearUnsavedPhotoCards,
        onChange: (value) => {
          snapshot = value;
          render();
          scheduleCompletions();
        },
      });
      session = value;
      resolveSession(value);
      render();
      scheduleCompletions();
      for (const control of mutatingControls)
        if (control.dataset.fieldInitiallyDisabled !== "true")
          control.disabled = false;
      root.dataset.fieldReady = "true";
    })().catch((error) => {
      rejectSession(error);
      root.dataset.fieldReady = "error";
      banner.dataset.state = "error";
      banner.querySelector("[data-sync-copy]").textContent =
        error.message || "Не удалось загрузить сохранённые данные.";
      banner.querySelector("[data-sync-now]").hidden = false;
      notify(error.message, true);
    });
    addEventListener("pagehide", () => session?.close(), { once: true });
  })());
