(function () {
  const button = document.getElementById('ediworman-recalculate-all');
  const status = document.getElementById('ediworman-recalculation-status');
  const data = window.EDIWORMAN_MANAGER_VISIBILITY || {};

  if (
    !button ||
    !status ||
    typeof data.ajaxUrl !== 'string' ||
    typeof data.action !== 'string' ||
    typeof data.nonce !== 'string' ||
    typeof data.postType !== 'string'
  ) {
    return;
  }

  let cursor = 0;
  let processed = 0;
  let skipped = 0;
  let running = false;

  const formatCounts = (template) =>
    String(template || '')
      .replace('%1$d', String(processed))
      .replace('%2$d', String(skipped));

  const announce = (message) => {
    status.textContent = message;
  };

  const runBatch = async () => {
    const requestBody = new URLSearchParams({
      action: data.action,
      nonce: data.nonce,
      post_type: data.postType,
      cursor: String(cursor),
    });

    try {
      const response = await window.fetch(data.ajaxUrl, {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
          'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
        },
        body: requestBody.toString(),
      });
      const result = await response.json();

      if (!response.ok || !result || result.success !== true || !result.data) {
        throw new Error('ediworman-recalculation-failed');
      }

      const nextCursor = Number.parseInt(result.data.nextCursor, 10);
      processed += Number.parseInt(result.data.processed, 10) || 0;
      skipped += Number.parseInt(result.data.skipped, 10) || 0;

      if (result.data.done === true) {
        button.setAttribute('aria-busy', 'false');
        announce(data.i18n && data.i18n.complete ? data.i18n.complete : '');
        window.setTimeout(() => window.location.reload(), 700);
        return;
      }

      if (!Number.isFinite(nextCursor) || nextCursor <= cursor) {
        throw new Error('ediworman-recalculation-cursor-stalled');
      }

      cursor = nextCursor;
      announce(
        formatCounts(data.i18n && data.i18n.running ? data.i18n.running : ''),
      );
      await runBatch();
    } catch (error) {
      running = false;
      button.disabled = false;
      button.setAttribute('aria-busy', 'false');
      if (data.i18n && data.i18n.resume) {
        button.textContent = data.i18n.resume;
      }
      announce(
        formatCounts(data.i18n && data.i18n.error ? data.i18n.error : ''),
      );
    }
  };

  button.addEventListener('click', () => {
    if (running) {
      return;
    }

    running = true;
    button.disabled = true;
    button.setAttribute('aria-busy', 'true');
    announce(
      formatCounts(data.i18n && data.i18n.running ? data.i18n.running : ''),
    );
    runBatch();
  });
})();
