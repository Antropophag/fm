(() => {
  'use strict';
  const selection = document.querySelector('[data-selection-picker]');
  if (!selection) return;

  const offer = selection.querySelector('[data-template-offer]');
  const compositionForm = selection.querySelector('[data-composition-form]');
  const saveButton = selection.querySelector('[data-composition-save]');
  const status = selection.querySelector('[data-composition-status]');
  const templateButton = selection.querySelector('[data-template-action]');
  const templateForm = document.getElementById('assignment-order-template-form');
  const templateDate = selection.querySelector('[name="appendixStartDate"]');
  const exactCopy = offer?.querySelector('[data-template-offer-exact]');
  const saveFirstCopy = offer?.querySelector('[data-template-offer-save-first]');
  const pending = compositionForm?.dataset.pending === 'true';
  let templateBusy = false;

  if (templateForm && templateDate) templateDate.setAttribute('form', templateForm.id);

  const positiveId = value => /^\d+$/.test(String(value)) && Number(value) > 0 ? Number(value) : null;
  const normalizedIds = values => [...new Set(values.map(positiveId).filter(value => value !== null))].sort((a, b) => a - b);
  const savedInstallerIds = normalizedIds((selection.dataset.savedInstallerIds || '').split(','));
  const savedOrderId = positiveId(selection.dataset.savedOrderId);
  const savedEngineerId = positiveId(selection.dataset.savedEngineerId);
  const currentEngineerId = positiveId(selection.dataset.currentEngineerId);

  const state = () => {
    const installerIds = normalizedIds([...selection.querySelectorAll('input[name="installerTabIds[]"]')].map(input => input.value));
    const ready = installerIds.length > 0 && currentEngineerId !== null;
    const exact = ready && savedOrderId !== null && currentEngineerId === savedEngineerId
      && installerIds.length === savedInstallerIds.length
      && installerIds.every((id, index) => id === savedInstallerIds[index]);
    return {ready, exact};
  };

  const reconcile = () => {
    const {ready, exact} = state();
    if (saveButton) {
      saveButton.disabled = !ready || (pending && exact);
      const dirtyFocus = !saveButton.disabled && !exact;
      saveButton.classList.toggle('shlz-button--primary', dirtyFocus);
      saveButton.classList.toggle('shlz-button--secondary', !dirtyFocus);
    }
    if (status) status.textContent = exact ? 'Состав сохранён' : (ready ? 'Есть несохранённые изменения' : 'Выберите монтажников');
    if (templateButton) templateButton.disabled = !exact || templateBusy;
    if (exactCopy) exactCopy.hidden = !exact;
    if (saveFirstCopy) saveFirstCopy.hidden = exact;
  };

  document.addEventListener('change', reconcile);
  document.addEventListener('click', reconcile);

  templateForm?.addEventListener('submit', async event => {
    event.preventDefault();
    if (!templateButton || templateButton.disabled || !templateForm.reportValidity()) return;
    templateBusy = true;
    reconcile();
    try {
      selection.querySelector('[data-template-error]')?.remove();
      const response = await fetch(templateForm.action, {
        method: 'POST',
        body: new URLSearchParams(new FormData(templateForm)),
        credentials: 'same-origin',
      });
      if (!response.ok || !(response.headers.get('content-type') || '').includes('application/pdf')) throw new Error('template');
      const blob = await response.blob();
      const temporary = URL.createObjectURL(blob);
      const download = document.createElement('a');
      download.href = temporary;
      download.download = 'assignment-order.pdf';
      download.click();
      URL.revokeObjectURL(temporary);
      let link = selection.querySelector('[data-last-template-link]');
      if (!link) {
        link = document.createElement('a');
        link.className = 'fm2-order-workflow__file-link';
        link.dataset.lastTemplateLink = '';
        link.href = templateForm.action;
        link.textContent = 'Скачать последний шаблон';
        offer?.after(link);
      }
      link.hidden = false;
    } catch {
      let failure = selection.querySelector('[data-template-error]');
      if (!failure) {
        failure = document.createElement('p');
        failure.className = 'fm2-problem';
        failure.dataset.templateError = '';
        failure.setAttribute('role', 'alert');
        offer?.after(failure);
      }
      failure.textContent = 'Не удалось сформировать шаблон. Повторите попытку.';
    } finally {
      templateBusy = false;
      reconcile();
    }
  });

  reconcile();
})();
