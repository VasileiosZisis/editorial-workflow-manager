(function () {
  const editorRoot = document.getElementById('ediworman-template-items-editor');
  if (!editorRoot) {
    return;
  }

  const rowsContainer = editorRoot.querySelector('.ediworman-template-items-rows');
  const addButton = editorRoot.querySelector('.ediworman-template-item-add');
  const rowTemplate = document.getElementById('ediworman-template-item-row-template');
  const form = editorRoot.closest('form');
  const i18nData = window.EDIWORMAN_TEMPLATE_EDITOR_DATA || {};

  if (!rowsContainer || !addButton || !rowTemplate || !form) {
    return;
  }

  const emptyLabelMessage =
    typeof i18nData.emptyLabelMessage === 'string' && i18nData.emptyLabelMessage
      ? i18nData.emptyLabelMessage
      : 'Item label is required.';

  const getRows = () =>
    Array.from(rowsContainer.querySelectorAll('.ediworman-template-item-row'));

  const clearRowError = (row) => {
    const input = row.querySelector('.ediworman-template-item-label');
    const error = row.querySelector('.ediworman-template-item-error');
    if (!input || !error) {
      return;
    }

    input.removeAttribute('aria-invalid');
    error.hidden = true;
    error.textContent = '';
  };

  const setRowError = (row, message) => {
    const input = row.querySelector('.ediworman-template-item-label');
    const error = row.querySelector('.ediworman-template-item-error');
    if (!input || !error) {
      return;
    }

    input.setAttribute('aria-invalid', 'true');
    error.hidden = false;
    error.textContent = message;
  };

  const refreshRows = () => {
    const rows = getRows();
    const total = rows.length;

    rows.forEach((row, index) => {
      const labelInput = row.querySelector('.ediworman-template-item-label');
      const labelLabel = row.querySelector('.ediworman-template-item-label-label');
      const requiredSelect = row.querySelector('.ediworman-template-item-required');
      const requiredLabel = row.querySelector('.ediworman-template-item-required-label');
      const upButton = row.querySelector('.ediworman-template-item-up');
      const downButton = row.querySelector('.ediworman-template-item-down');

      if (labelInput && labelLabel) {
        const labelInputId = `ediworman-template-item-label-${index}`;
        labelInput.id = labelInputId;
        labelLabel.setAttribute('for', labelInputId);
      }

      if (requiredSelect && requiredLabel) {
        const requiredInputId = `ediworman-template-item-required-${index}`;
        requiredSelect.id = requiredInputId;
        requiredLabel.setAttribute('for', requiredInputId);
      }

      if (upButton) {
        upButton.disabled = index === 0;
      }

      if (downButton) {
        downButton.disabled = index === total - 1;
      }
    });
  };

  const addNewRow = () => {
    const fragment = rowTemplate.content.cloneNode(true);
    rowsContainer.appendChild(fragment);
    refreshRows();

    const rows = getRows();
    const lastRow = rows[rows.length - 1];
    const input = lastRow
      ? lastRow.querySelector('.ediworman-template-item-label')
      : null;
    if (input) {
      input.focus();
    }
  };

  addButton.addEventListener('click', () => {
    addNewRow();
  });

  rowsContainer.addEventListener('click', (event) => {
    const target = event.target;
    if (!(target instanceof HTMLElement)) {
      return;
    }

    const row = target.closest('.ediworman-template-item-row');
    if (!row) {
      return;
    }

    const upButton = target.closest('.ediworman-template-item-up');
    const downButton = target.closest('.ediworman-template-item-down');
    const removeButton = target.closest('.ediworman-template-item-remove');

    if (upButton) {
      const previous = row.previousElementSibling;
      if (previous) {
        rowsContainer.insertBefore(row, previous);
        refreshRows();
      }
      return;
    }

    if (downButton) {
      const next = row.nextElementSibling;
      if (next) {
        rowsContainer.insertBefore(next, row);
        refreshRows();
      }
      return;
    }

    if (removeButton) {
      row.remove();
      refreshRows();
    }
  });

  rowsContainer.addEventListener('input', (event) => {
    const target = event.target;
    if (!(target instanceof HTMLElement)) {
      return;
    }

    if (!target.classList.contains('ediworman-template-item-label')) {
      return;
    }

    const row = target.closest('.ediworman-template-item-row');
    if (row) {
      clearRowError(row);
    }
  });

  form.addEventListener('submit', (event) => {
    const rows = getRows();
    let firstInvalidInput = null;

    rows.forEach((row) => {
      const input = row.querySelector('.ediworman-template-item-label');
      if (!(input instanceof HTMLInputElement)) {
        return;
      }

      const value = input.value.trim();
      if (!value) {
        setRowError(row, emptyLabelMessage);
        if (!firstInvalidInput) {
          firstInvalidInput = input;
        }
        return;
      }

      clearRowError(row);
    });

    if (firstInvalidInput) {
      event.preventDefault();
      firstInvalidInput.focus();
    }
  });

  refreshRows();
})();
