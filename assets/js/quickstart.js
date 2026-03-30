(function () {
  const root = document.querySelector('.ediworman-quickstart');
  if (!root) {
    return;
  }

  const data = window.EDIWORMAN_QUICKSTART_DATA || {};
  const checkboxes = Array.from(
    root.querySelectorAll('[data-post-type-checkbox]'),
  );
  const rows = Array.from(root.querySelectorAll('[data-post-type-row]'));
  const selects = Array.from(root.querySelectorAll('[data-template-select]'));
  const submitButton = root.querySelector(
    'button[form="ediworman-quickstart-save-form"]',
  );
  const emptySelectionNote = root.querySelector('[data-no-selection-message]');

  if (!checkboxes.length || !submitButton) {
    return;
  }

  const updateRowVisibility = () => {
    const selected = new Set(
      checkboxes
        .filter((checkbox) => checkbox.checked)
        .map((checkbox) => checkbox.getAttribute('data-post-type-checkbox')),
    );

    rows.forEach((row) => {
      const postType = row.getAttribute('data-post-type-row');
      row.hidden = !selected.has(postType);
    });

    const hasSelection = selected.size > 0;
    submitButton.disabled = !hasSelection;

    if (emptySelectionNote) {
      emptySelectionNote.hidden = hasSelection;
      if (!hasSelection && typeof data.noSelectionText === 'string') {
        emptySelectionNote.textContent = data.noSelectionText;
      }
    }
  };

  const updatePreview = (select) => {
    const postType = select.getAttribute('data-template-select');
    const preview = root.querySelector(
      `[data-template-preview-for="${postType}"]`,
    );
    const option = select.options[select.selectedIndex];

    if (!preview || !option) {
      return;
    }

    preview.textContent = option.getAttribute('data-preview') || '';
  };

  checkboxes.forEach((checkbox) => {
    checkbox.addEventListener('change', updateRowVisibility);
  });

  selects.forEach((select) => {
    select.addEventListener('change', () => updatePreview(select));
    updatePreview(select);
  });

  updateRowVisibility();
})();
