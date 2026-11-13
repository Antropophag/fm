const root = document.querySelector('[data-register]');

if (root) {
  const panel = root.querySelector('[data-register-settings]');
  const open = root.querySelector('[data-register-open]');
  const list = root.querySelector('[data-register-column-list]');
  const table = root.querySelector('[data-register-table]');
  const status = root.querySelector('[data-register-status]');
  const save = root.querySelector('[data-register-save]');
  const parse = (selector) => JSON.parse(root.querySelector(selector).textContent);
  let persisted = parse('[data-register-state]');
  const defaults = parse('[data-register-defaults]');
  let returnFocus = open;

  const rows = () => [...list.querySelectorAll('[data-column-key]')];
  const control = (row, type) => row.querySelector(`input[type=${type}]`);
  const snapshot = () => ({
    revision: persisted.revision,
    visible: rows().filter((row) => control(row, 'checkbox').checked).map((row) => row.dataset.columnKey),
    widths: Object.fromEntries(rows().map((row) => [row.dataset.columnKey, Number(control(row, 'number').value)])),
  });
  const orderedKeys = (state) => [...state.visible, ...Object.keys(state.widths).filter((key) => !state.visible.includes(key))];

  function apply(state) {
    const byKey = new Map(rows().map((row) => [row.dataset.columnKey, row]));
    orderedKeys(state).forEach((key) => byKey.get(key) && list.append(byKey.get(key)));
    rows().forEach((row) => {
      const key = row.dataset.columnKey;
      control(row, 'checkbox').checked = state.visible.includes(key);
      control(row, 'number').value = state.widths[key];
    });
    if (!table) return;
    orderedKeys(state).forEach((key) => {
      table.querySelectorAll(`[data-column="${CSS.escape(key)}"]`).forEach((cell) => cell.parentElement.append(cell));
    });
    table.querySelectorAll('[data-column]').forEach((cell) => {
      const key = cell.dataset.column;
      cell.hidden = !state.visible.includes(key);
      cell.style.setProperty('--register-column-width', `${state.widths[key]}px`);
    });
  }

  function setPanel(show) {
    panel.hidden = !show;
    open.setAttribute('aria-expanded', String(show));
    if (show) {
      returnFocus = document.activeElement;
      panel.querySelector('[data-register-column-search]').focus();
    } else if (returnFocus instanceof HTMLElement) returnFocus.focus();
  }
  function message(text, kind = '') {
    status.textContent = text;
    status.dataset.kind = kind;
  }
  function restore() { apply(persisted); message(''); }

  open.addEventListener('click', () => {
    if (!panel.hidden) {
      restore();
      setPanel(false);
      return;
    }
    setPanel(true);
  });
  root.querySelector('[data-register-cancel]').addEventListener('click', () => { restore(); setPanel(false); });
  root.querySelector('[data-register-reset]').addEventListener('click', () => { apply({...defaults, revision: persisted.revision}); message('Изменения по умолчанию ещё не сохранены.'); });
  panel.addEventListener('keydown', (event) => {
    if (event.key === 'Escape') { event.preventDefault(); restore(); setPanel(false); }
  });
  panel.querySelector('[data-register-column-search]').addEventListener('input', (event) => {
    const value = event.target.value.trim().toLocaleLowerCase('ru');
    rows().forEach((row) => { row.hidden = value !== '' && !row.dataset.columnLabel.includes(value); });
  });
  list.addEventListener('input', () => apply(snapshot()));
  list.addEventListener('click', (event) => {
    const button = event.target.closest('[data-column-up],[data-column-down]');
    if (!button) return;
    const row = button.closest('[data-column-key]');
    const sibling = button.hasAttribute('data-column-up') ? row.previousElementSibling : row.nextElementSibling;
    if (!sibling || sibling.dataset.columnKey === 'address') return;
    if (button.hasAttribute('data-column-up')) list.insertBefore(row, sibling);
    else list.insertBefore(sibling, row);
    apply(snapshot());
    button.focus();
  });
  save.addEventListener('click', async () => {
    const draft = snapshot();
    if (draft.visible[0] !== 'address' || Object.values(draft.widths).some((width) => !Number.isInteger(width) || width < 80 || width > 640)) {
      message('Проверьте выбранные колонки и ширину от 80 до 640 px.', 'error');
      return;
    }
    save.disabled = true;
    message('Сохраняем…');
    const csrf = root.querySelector('[data-register-preferences-form] input');
    const body = new URLSearchParams({
      [csrf.name]: csrf.value,
      expectedRevision: String(persisted.revision),
      visible: JSON.stringify(draft.visible),
      widths: JSON.stringify(draft.widths),
    });
    try {
      const response = await fetch('/pilot/address-register/preferences', {
        method: 'POST', credentials: 'same-origin',
        headers: {'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8', 'Accept': 'application/json'},
        body,
      });
      const result = await response.json().catch(() => ({}));
      if (response.status === 409) {
        message('Настройки изменились в другой вкладке. Обновите страницу и повторите.', 'error');
        return;
      }
      if (!response.ok || !Number.isInteger(result.revision) || !Array.isArray(result.visible) || typeof result.widths !== 'object') throw new Error('save failed');
      persisted = result;
      apply(persisted);
      message('Настройки сохранены.', 'success');
    } catch (_) {
      message('Не удалось сохранить настройки. Повторите позже.', 'error');
    } finally { save.disabled = false; }
  });
  apply(persisted);
}
