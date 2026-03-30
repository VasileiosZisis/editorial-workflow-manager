(function () {
  const tourData = window.EDIWORMAN_EDITOR_TOUR_DATA || {};
  if (!tourData.isActive) {
    return;
  }

  const { registerPlugin } = wp.plugins;
  const { Guide } = wp.components;
  const { createElement: el, useEffect, useState } = wp.element;
  const { __ } = wp.i18n;

  let hasDismissed = false;

  const openSidebar = () => {
    if (
      !wp.data ||
      !wp.data.dispatch ||
      typeof wp.data.dispatch !== 'function'
    ) {
      return;
    }

    try {
      wp.data
        .dispatch('core/edit-post')
        .openGeneralSidebar(tourData.sidebarTarget);
    } catch (error) {
      // Ignore editor-store failures and keep the editor usable.
    }
  };

  const dismissTour = () => {
    if (hasDismissed || !tourData.ajaxUrl || !tourData.nonce) {
      return;
    }

    hasDismissed = true;

    const requestBody = new window.URLSearchParams({
      action: tourData.dismissAction,
      nonce: tourData.nonce,
    });

    window
      .fetch(tourData.ajaxUrl, {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
          'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
        },
        body: requestBody.toString(),
      })
      .catch(() => {});
  };

  const Tour = () => {
    const [isOpen, setIsOpen] = useState(true);

    useEffect(() => {
      openSidebar();

      const timeoutId = window.setTimeout(openSidebar, 250);
      document.documentElement.classList.add('ediworman-tour-active');

      return () => {
        window.clearTimeout(timeoutId);
        document.documentElement.classList.remove('ediworman-tour-active');
      };
    }, []);

    if (!isOpen) {
      return null;
    }

    return el(Guide, {
      className: 'ediworman-editor-tour-guide',
      onFinish: () => {
        setIsOpen(false);
        dismissTour();
      },
      pages: [
        {
          content: el(
            'div',
            null,
            el(
              'h2',
              null,
              __('Editorial Checklist', 'editorial-workflow-manager'),
            ),
            el(
              'p',
              null,
              __(
                'This sidebar is where authors and editors track the checklist for the current post.',
                'editorial-workflow-manager',
              ),
            ),
          ),
        },
        {
          content: el(
            'div',
            null,
            el(
              'h2',
              null,
              __('Required items matter most', 'editorial-workflow-manager'),
            ),
            el(
              'p',
              null,
              __(
                'The Ready or Incomplete status is based on required checklist items, while optional items stay visible without blocking readiness.',
                'editorial-workflow-manager',
              ),
            ),
          ),
        },
      ],
    });
  };

  registerPlugin('ediworman-checklist-tour', {
    render: Tour,
  });
})();
