(() => {
  "use strict";
  const e = document.querySelector("[data-control-queue]");
  if (!e) return;
  const t = [...e.querySelectorAll("[data-control-row]")],
    o = e.querySelector("[data-control-search]"),
    s = e.querySelector("[data-show-completed]"),
    r = "fm2-control-queue",
    n = [...e.querySelectorAll("input[name=ownership]")],
    a = () => {
      try {
        sessionStorage.setItem(r, JSON.stringify({ scrollY: window.scrollY }));
      } catch {}
    };
  try {
    const e = JSON.parse(sessionStorage.getItem(r) || "null");
    e && requestAnimationFrame(() => scrollTo(0, Number(e.scrollY) || 0));
  } catch {}
  async function l() {
    const pending =
        (await globalThis.FMonitorField?.pending(Number(e.dataset.userId))) ||
        {},
      o = new Map(
        Object.entries(pending.objects || {}).map(([id, value]) => [
          id,
          Array.isArray(value.statuses) ? value.statuses : [],
        ]),
      );
    for (const e of t) {
      const t = e.querySelector("[data-local-sync]");
      if (!t) continue;
      const s = o.get(e.dataset.objectId) || [],
        r = s.some((e) => ["conflict", "rejected"].includes(e))
          ? "blocked"
          : s.includes("retryable_error")
            ? "retryable"
            : s.includes("sending")
              ? "sending"
              : s.length
                ? "queued"
                : "synced",
        n = {
          synced: "Синхронизировано",
          queued: "Ожидает отправки",
          sending: "Отправляется",
          retryable: "Ошибка связи — можно повторить",
          blocked: "Конфликт или операция отклонена",
        }[r];
      ((t.dataset.state = r),
        t.setAttribute("aria-label", n),
        (t.querySelector(".fm2-visually-hidden").textContent = n));
    }
  }
  const p = e.querySelector("[data-control-filter-form]");
  // A server-filtered search reloads the page; retain only its one-shot input focus.
  const focusKey = `${r}:search:${e.dataset.userId}`;
  let timer,
    composing = false;
  const forgetFocus = () => {
    try {
      sessionStorage.removeItem(focusKey);
    } catch {}
  };
  const rememberFocus = () => {
    if (document.activeElement !== o) {
      forgetFocus();
      return;
    }
    try {
      sessionStorage.setItem(
        focusKey,
        JSON.stringify({
          path: new URL(p.action, location.href).pathname,
          query: o.value.trim(),
          ownership:
            p.querySelector('input[name="ownership"]:checked')?.value || "mine",
          completed: s.checked,
          start: o.selectionStart,
          end: o.selectionEnd,
          direction: o.selectionDirection,
          at: Date.now(),
        }),
      );
    } catch {}
  };
  const restoreFocus = () => {
    o.disabled = false;
    try {
      const saved = JSON.parse(sessionStorage.getItem(focusKey) || "null");
      forgetFocus();
      const params = new URL(location.href).searchParams;
      if (
        !saved ||
        saved.path !== location.pathname ||
        Date.now() - saved.at > 30000 ||
        Date.now() < saved.at ||
        saved.query !== o.value.trim() ||
        saved.query !== (params.get("query") || "").trim() ||
        saved.ownership !== (params.get("ownership") || "mine") ||
        saved.completed !== (params.get("completed") === "1")
      )
        return;
      o.focus({ preventScroll: true });
      if (Number.isInteger(saved.start) && Number.isInteger(saved.end))
        o.setSelectionRange(saved.start, saved.end, saved.direction || "none");
    } catch {}
  };
  const submit = () => {
    clearTimeout(timer);
    p.requestSubmit();
  };
  p.addEventListener("submit", () => {
    clearTimeout(timer);
    rememberFocus();
  });
  p.addEventListener("formdata", (event) => {
    if (o.value.trim() === "") event.formData.delete(o.name);
  });
  o.addEventListener("input", () => {
    clearTimeout(timer);
    if (!composing) timer = setTimeout(submit, 350);
  });
  o.addEventListener("compositionstart", () => {
    composing = true;
    clearTimeout(timer);
  });
  o.addEventListener("compositionend", () => {
    composing = false;
    clearTimeout(timer);
    timer = setTimeout(submit, 350);
  });
  s.addEventListener("change", submit);
  n.forEach((input) => input.addEventListener("change", submit));
  e.querySelector("[data-clear-filters]")?.addEventListener("click", () => {
    clearTimeout(timer);
    o.value = "";
    s.checked = false;
    n[0].checked = true;
    submit();
  });
  restoreFocus();
  e.addEventListener("click", (event) => {
    event.target.closest('a[href*="/checklist"]') && a();
  });
  let lifecycleRun = null;
  const runLifecycle = () => {
    if (lifecycleRun) return lifecycleRun;
    lifecycleRun = (async () => {
      try {
        if (globalThis.FMonitorFieldLifecycle?.ready)
          await globalThis.FMonitorFieldLifecycle.ready;
        if (e.hidden || document.querySelector("[data-field-identity-alert]"))
          return;
        await globalThis.FMonitorField?.syncPending(Number(e.dataset.userId));
        await l();
      } catch (error) {
        const identityClosed =
          /уч[её]тная запись|войдите снова|очередь остановлена|сесс/i.test(
            String(error?.message || ""),
          );
        if (identityClosed) e.hidden = true;
      }
    })().finally(() => {
      lifecycleRun = null;
    });
    return lifecycleRun;
  };
  window.addEventListener("pageshow", () => {
    o.disabled = false;
    runLifecycle();
  });
  window.addEventListener("online", runLifecycle);
  window.addEventListener("focus", runLifecycle);
  document.addEventListener("visibilitychange", () => {
    if (document.visibilityState === "visible") runLifecycle();
  });
  runLifecycle();
})();
