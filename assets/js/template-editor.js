(function () {
  const editorRoot = document.getElementById('ediworman-template-items-editor');
  if (!editorRoot) {
    return;
  }

  const rowsContainer = editorRoot.querySelector('.ediworman-template-items-rows');
  const addButton = editorRoot.querySelector('.ediworman-template-item-add');
  const rowTemplate = document.getElementById('ediworman-template-item-row-template');
  const liveRegion = editorRoot.querySelector('.ediworman-template-editor-status');
  const form = editorRoot.closest('form');
  const i18nData = window.EDIWORMAN_TEMPLATE_EDITOR_DATA || {};
  const { sprintf } = wp.i18n;

  if (!rowsContainer || !addButton || !rowTemplate || !liveRegion || !form) {
    return;
  }

  const getMessage = (key, fallback) =>
    typeof i18nData[key] === 'string' && i18nData[key]
      ? i18nData[key]
      : fallback;

  const messages = {
    emptyLabel: getMessage('emptyLabelMessage', 'Item label is required.'),
    untitledItem: getMessage('untitledItem', 'Untitled checklist item'),
    rowLabel: getMessage(
      'rowLabel',
      'Checklist item label, item %1$d of %2$d',
    ),
    descriptionLabel: getMessage(
      'descriptionLabel',
      'Helper text for %1$s, item %2$d of %3$d',
    ),
    urlLabel: getMessage(
      'urlLabel',
      'Reference URL for %1$s, item %2$d of %3$d',
    ),
    requiredLabel: getMessage(
      'requiredLabel',
      'Checklist item type for %1$s, item %2$d of %3$d',
    ),
    rowActions: getMessage(
      'rowActionsLabel',
      'Actions for %1$s, item %2$d of %3$d',
    ),
    moveUp: getMessage(
      'moveUpLabel',
      'Move %1$s up, item %2$d of %3$d',
    ),
    moveDown: getMessage(
      'moveDownLabel',
      'Move %1$s down, item %2$d of %3$d',
    ),
    remove: getMessage(
      'removeLabel',
      'Remove %1$s, item %2$d of %3$d',
    ),
    added: getMessage('addedAnnouncement', 'Added item %1$d of %2$d.'),
    moved: getMessage(
      'movedAnnouncement',
      'Moved %1$s to position %2$d of %3$d.',
    ),
    removedOne: getMessage(
      'removedOneAnnouncement',
      'Removed %1$s. One item remains.',
    ),
    removedMany: getMessage(
      'removedManyAnnouncement',
      'Removed %1$s. %2$d items remain.',
    ),
    removedAll: getMessage(
      'removedAllAnnouncement',
      'Removed %s. No items remain.',
    ),
    validation: getMessage(
      'validationAnnouncement',
      'Please correct the highlighted checklist item labels.',
    ),
  };

  const getRows = () =>
    Array.from(rowsContainer.querySelectorAll('.ediworman-template-item-row'));

  const getItemName = (row) => {
    const input = row.querySelector('.ediworman-template-item-label');
    const value = input instanceof HTMLInputElement ? input.value.trim() : '';
    return value || messages.untitledItem;
  };

  const announce = (message) => {
    liveRegion.textContent = '';
    window.setTimeout(() => {
      liveRegion.textContent = message;
    }, 0);
  };

  const clearRowError = (row) => {
    const input = row.querySelector('.ediworman-template-item-label');
    const error = row.querySelector('.ediworman-template-item-error');
    if (!input || !error) {
      return;
    }

    input.removeAttribute('aria-invalid');
    input.removeAttribute('aria-describedby');
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
    if (error.id) {
      input.setAttribute('aria-describedby', error.id);
    }
    error.hidden = false;
    error.textContent = message;
  };

  const refreshRows = () => {
    const rows = getRows();
    const total = rows.length;

    rows.forEach((row, index) => {
      const position = index + 1;
      const itemName = getItemName(row);
      const labelInput = row.querySelector('.ediworman-template-item-label');
      const labelLabel = row.querySelector('.ediworman-template-item-label-label');
      const descriptionInput = row.querySelector(
        '.ediworman-template-item-description',
      );
      const descriptionLabel = row.querySelector(
        '.ediworman-template-item-description-label',
      );
      const urlInput = row.querySelector('.ediworman-template-item-url');
      const urlLabel = row.querySelector('.ediworman-template-item-url-label');
      const requiredSelect = row.querySelector('.ediworman-template-item-required');
      const requiredLabel = row.querySelector('.ediworman-template-item-required-label');
      const error = row.querySelector('.ediworman-template-item-error');
      const actionGroup = row.querySelector('.button-group');
      const upButton = row.querySelector('.ediworman-template-item-up');
      const downButton = row.querySelector('.ediworman-template-item-down');
      const removeButton = row.querySelector('.ediworman-template-item-remove');

      if (labelInput && labelLabel) {
        const labelInputId = `ediworman-template-item-label-${index}`;
        labelInput.id = labelInputId;
        labelLabel.setAttribute('for', labelInputId);
        labelLabel.textContent = sprintf(messages.rowLabel, position, total);
      }

      if (descriptionInput && descriptionLabel) {
        const descriptionInputId = `ediworman-template-item-description-${index}`;
        descriptionInput.id = descriptionInputId;
        descriptionLabel.setAttribute('for', descriptionInputId);
        descriptionInput.setAttribute(
          'aria-label',
          sprintf(messages.descriptionLabel, itemName, position, total),
        );
      }

      if (urlInput && urlLabel) {
        const urlInputId = `ediworman-template-item-url-${index}`;
        urlInput.id = urlInputId;
        urlLabel.setAttribute('for', urlInputId);
        urlInput.setAttribute(
          'aria-label',
          sprintf(messages.urlLabel, itemName, position, total),
        );
      }

      if (requiredSelect && requiredLabel) {
        const requiredInputId = `ediworman-template-item-required-${index}`;
        requiredSelect.id = requiredInputId;
        requiredLabel.setAttribute('for', requiredInputId);
        requiredSelect.setAttribute(
          'aria-label',
          sprintf(messages.requiredLabel, itemName, position, total),
        );
      }

      if (error) {
        error.id = `ediworman-template-item-error-${index}`;
        if (labelInput && labelInput.getAttribute('aria-invalid') === 'true') {
          labelInput.setAttribute('aria-describedby', error.id);
        }
      }

      if (actionGroup) {
        actionGroup.setAttribute(
          'aria-label',
          sprintf(messages.rowActions, itemName, position, total),
        );
      }

      if (upButton) {
        const label = sprintf(messages.moveUp, itemName, position, total);
        upButton.disabled = index === 0;
        upButton.setAttribute('aria-label', label);
        upButton.setAttribute('title', label);
      }

      if (downButton) {
        const label = sprintf(messages.moveDown, itemName, position, total);
        downButton.disabled = index === total - 1;
        downButton.setAttribute('aria-label', label);
        downButton.setAttribute('title', label);
      }

      if (removeButton) {
        removeButton.setAttribute(
          'aria-label',
          sprintf(messages.remove, itemName, position, total),
        );
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

    announce(sprintf(messages.added, rows.length, rows.length));
  };

  addButton.addEventListener('click', addNewRow);

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
    const itemName = getItemName(row);

    if (upButton) {
      const previous = row.previousElementSibling;
      if (previous) {
        rowsContainer.insertBefore(row, previous);
        refreshRows();
        upButton.focus();
        const rows = getRows();
        announce(
          sprintf(messages.moved, itemName, rows.indexOf(row) + 1, rows.length),
        );
      }
      return;
    }

    if (downButton) {
      const next = row.nextElementSibling;
      if (next) {
        rowsContainer.insertBefore(next, row);
        refreshRows();
        downButton.focus();
        const rows = getRows();
        announce(
          sprintf(messages.moved, itemName, rows.indexOf(row) + 1, rows.length),
        );
      }
      return;
    }

    if (removeButton) {
      const nextRow = row.nextElementSibling;
      const previousRow = row.previousElementSibling;
      row.remove();
      refreshRows();

      const focusTarget =
        (nextRow && nextRow.querySelector('.ediworman-template-item-label')) ||
        (previousRow &&
          previousRow.querySelector('.ediworman-template-item-label')) ||
        addButton;
      focusTarget.focus();

      const remaining = getRows().length;
      if (remaining === 0) {
        announce(sprintf(messages.removedAll, itemName));
      } else if (remaining === 1) {
        announce(sprintf(messages.removedOne, itemName));
      } else {
        announce(sprintf(messages.removedMany, itemName, remaining));
      }
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
      refreshRows();
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
        setRowError(row, messages.emptyLabel);
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
      announce(messages.validation);
    }
  });

  refreshRows();
})();
