import { enhanceDrawers, enhanceSelects } from '/pilot/assets/shlz-behaviors.js';

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
  enhanceDrawers(root);
  const notice = root.querySelector('[data-register-notice]');
  const filter = root.querySelector('[data-register-filter-dialog]');
  const filterSelect = enhanceSelects(filter)[0];
  const appliedStage = filter.querySelector('input[name=status]').value;
  root.querySelector('[data-register-filter-open]').addEventListener('click', () => {
    filter.querySelector('input[name=q]').value = root.querySelector('input[type=search][name=q]').value;
  }, {capture: true});
  filter.addEventListener('close', () => filterSelect.setValue(appliedStage));

  const rows = () => [...list.querySelectorAll('[data-column-key]')];
  const control = (row, type) => row.querySelector(`input[type=${type}]`);
  const snapshot = () => ({
    revision: persisted.revision,
    visible: rows().filter((row) => control(row, 'checkbox').checked).map((row) => row.dataset.columnKey),
    layoutVersion: 2,
  });
  const catalogueKeys = rows().map((row) => row.dataset.columnKey);
  const orderedKeys = (state) => [...state.visible, ...catalogueKeys.filter((key) => !state.visible.includes(key))];

  function apply(state) {
    const byKey = new Map(rows().map((row) => [row.dataset.columnKey, row]));
    orderedKeys(state).forEach((key) => byKey.get(key) && list.append(byKey.get(key)));
    rows().forEach((row) => {
      const key = row.dataset.columnKey;
      control(row, 'checkbox').checked = state.visible.includes(key);
    });
    root.querySelector('[data-register-selected-count]').textContent = `Выбрано ${state.visible.length} из ${catalogueKeys.length}`;
    if (!table) return;
    orderedKeys(state).forEach((key) => {
      table.querySelectorAll(`[data-column="${CSS.escape(key)}"]`).forEach((cell) => cell.parentElement.append(cell));
    });
    table.querySelectorAll('[data-column]').forEach((cell) => {
      const key = cell.dataset.column;
      cell.hidden = !state.visible.includes(key);
    });
  }

  function message(text, kind = '') {
    status.textContent = text;
    status.dataset.kind = kind;
  }
  function restore() { apply(persisted); message(''); }

  open.addEventListener('click', restore, {capture: true});
  panel.addEventListener('close', restore);
  root.querySelector('[data-register-reset]').addEventListener('click', () => { apply({...defaults, revision: persisted.revision}); message('Изменения по умолчанию ещё не сохранены.'); });
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
    if (!sibling || sibling.dataset.columnKey === 'registrationNumber') return;
    if (button.hasAttribute('data-column-up')) list.insertBefore(row, sibling);
    else list.insertBefore(sibling, row);
    apply(snapshot());
    button.focus();
  });
  save.addEventListener('click', async () => {
    const draft = snapshot();
    if (draft.visible[0] !== 'registrationNumber') {
      message('Регистрационный номер должен оставаться первой колонкой.', 'error');
      return;
    }
    save.disabled = true;
    message('Сохраняем…');
    const csrf = root.querySelector('[data-register-preferences-form] input');
    const body = new URLSearchParams({
      [csrf.name]: csrf.value,
      expectedRevision: String(persisted.revision),
      visible: JSON.stringify(draft.visible),
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
      if (!response.ok || !Number.isInteger(result.revision) || !Array.isArray(result.visible) || result.layoutVersion !== 2) throw new Error('save failed');
      persisted = result;
      apply(persisted);
      notice.textContent = 'Вид сохранён';
      panel.close('saved');
    } catch (_) {
      message('Не удалось сохранить настройки. Повторите позже.', 'error');
    } finally { save.disabled = false; }
  });
  apply(persisted);
}
