(function () {
  'use strict';

  const form = document.getElementById('ediworman-publication-announcement-form');
  const data = window.EDIWORMAN_PUBLICATION_ANNOUNCEMENT;
  if (!form || !data || typeof window.fetch !== 'function' || typeof window.AbortController !== 'function') {
    return;
  }

  const notice = document.getElementById('ediworman-publication-announcement');
  const button = form.querySelector('button[type="submit"]');
  const status = document.getElementById('ediworman-publication-announcement-status');

  form.addEventListener('submit', async function (event) {
    event.preventDefault();
    if (button.disabled) {
      return;
    }

    button.disabled = true;
    notice.setAttribute('aria-busy', 'true');
    status.textContent = '';
    const controller = new AbortController();
    const timeout = window.setTimeout(() => controller.abort(), 15000);

    try {
      const response = await window.fetch(data.ajaxUrl, {
        method: 'POST',
        credentials: 'same-origin',
        body: new URLSearchParams(new FormData(form)),
        signal: controller.signal,
      });
      const result = await response.json();
      if (!response.ok || result.success !== true) {
        throw new Error('ediworman_dismissal_failed');
      }

      const focusTarget = document.querySelector('select[id^="ediworman-publication-"]');
      notice.remove();
      if (focusTarget) {
        focusTarget.focus();
      }
    } catch (error) {
      status.textContent = data.error;
    } finally {
      window.clearTimeout(timeout);
      button.disabled = false;
      notice.removeAttribute('aria-busy');
    }
  });
})();
