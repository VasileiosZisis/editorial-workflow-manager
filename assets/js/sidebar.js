(function () {
  const { registerPlugin } = wp.plugins;
  const {
    PluginSidebar,
    PluginSidebarMoreMenuItem,
    PluginPostStatusInfo,
    PluginPrePublishPanel,
  } = wp.editor;
  const { PanelBody, CheckboxControl, Notice } = wp.components;
  const { Fragment, createElement: el, useMemo } = wp.element;
  const { useSelect, useDispatch } = wp.data;

  const useChecklist = () => {
    const items =
      window.EDIWORMAN_CHECKLIST_DATA &&
      Array.isArray(window.EDIWORMAN_CHECKLIST_DATA.items)
        ? window.EDIWORMAN_CHECKLIST_DATA.items
        : [];

    const meta = useSelect(
      (select) => select('core/editor').getEditedPostAttribute('meta') || {},
      []
    );

    const checkedItems =
      meta._ediworman_checked_items &&
      Array.isArray(meta._ediworman_checked_items)
        ? meta._ediworman_checked_items
        : [];

    const { editPost } = useDispatch('core/editor');

    const toggleItem = (label) => {
      const set = new Set(checkedItems);

      if (set.has(label)) {
        set.delete(label);
      } else {
        set.add(label);
      }

      editPost({
        meta: {
          ...meta,
          _ediworman_checked_items: Array.from(set),
        },
      });
    };

    const total = items.length;
    const completed = useMemo(
      () => items.filter((label) => checkedItems.includes(label)).length,
      [items, checkedItems]
    );
    const allDone = total > 0 && completed === total;

    return {
      items,
      checkedItems,
      total,
      completed,
      allDone,
      toggleItem,
    };
  };

  const usePostInfo = () => {
    const meta = useSelect(
      (select) => select('core/editor').getEditedPostAttribute('meta') || {},
      []
    );

    let lastEditorId = null;

    if (
      meta._ediworman_last_editor !== undefined &&
      meta._ediworman_last_editor !== null
    ) {
      const parsed = parseInt(meta._ediworman_last_editor, 10);
      if (!Number.isNaN(parsed) && parsed > 0) {
        lastEditorId = parsed;
      }
    }

    const post = useSelect(
      (select) => select('core/editor').getCurrentPost(),
      []
    );

    const fallbackAuthorId = post && post.author ? post.author : null;
    const userIdToShow = lastEditorId || fallbackAuthorId;

    const user = useSelect(
      (select) => {
        if (!userIdToShow) {
          return null;
        }
        return select('core').getUser(userIdToShow);
      },
      [userIdToShow]
    );

    // Build the "when" part from post.modified
    let lastUpdatedTimeText = null;
    if (post && post.modified) {
      const d = new Date(post.modified);
      if (!Number.isNaN(d.getTime())) {
        lastUpdatedTimeText = d.toLocaleString();
      }
    }

    if (!user || !user.name) {
      return { lastUpdatedText: null, lastUpdatedTimeText: null };
    }

    return {
      lastUpdatedText: `Last updated by ${user.name}`,
      lastUpdatedTimeText,
    };
  };

  const SidebarContent = () => {
    const { items, checkedItems, allDone, toggleItem } = useChecklist();

    const { lastUpdatedText, lastUpdatedTimeText } = usePostInfo();

    if (!items.length) {
      return el(
        PanelBody,
        { title: 'Checklist', initialOpen: true },
        el(
          Notice,
          { status: 'info', isDismissible: false },
          'No checklist template is configured for this post type.'
        )
      );
    }

    return el(
      PanelBody,
      { title: 'Checklist', initialOpen: true },
      el(
        'p',
        null,
        allDone
          ? '✅ All checklist items are complete.'
          : 'Tick each item as you complete it.'
      ),
      items.map((label) =>
        el(CheckboxControl, {
          key: label,
          label,
          checked: checkedItems.includes(label),
          onChange: () => toggleItem(label),
        })
      ),
      lastUpdatedText &&
        el(
          'p',
          {
            style: {
              marginTop: '12px',
              fontSize: '12px',
              opacity: 0.7,
            },
          },
          lastUpdatedTimeText
            ? `${lastUpdatedText} on ${lastUpdatedTimeText}`
            : lastUpdatedText
        )
    );
  };

  // Status line in "Status & visibility"
  const ChecklistStatusInfo = () => {
    const { items, total, completed, allDone } = useChecklist();

    if (!items.length) {
      // If no template, don't show anything.
      return null;
    }

    const text = allDone
      ? 'Checklist complete.'
      : `Checklist: ${completed} / ${total} items done`;

    return el(
      PluginPostStatusInfo,
      null,
      el(
        'span',
        {
          style: allDone
            ? { color: 'inherit' }
            : { color: '#d63638', fontWeight: '500' }, // subtle red when incomplete
        },
        text
      )
    );
  };

  // Non-blocking notice in the pre-publish panel
  const ChecklistPrePublishPanel = () => {
    const { items, total, completed, allDone } = useChecklist();

    // Only show when there is a template and the checklist is incomplete.
    if (!items.length || allDone) {
      return null;
    }

    return el(
      PluginPrePublishPanel,
      {
        title: 'Editorial Checklist',
        initialOpen: true,
      },
      el(
        Notice,
        {
          status: 'warning',
          isDismissible: false,
        },
        `Checklist incomplete: ${completed} / ${total} items done. You can still publish, but consider reviewing the checklist first.`
      )
    );
  };

  const EditorialChecklistPlugin = () =>
    el(
      Fragment,
      null,
      el(
        PluginSidebarMoreMenuItem,
        { target: 'ediworman-checklist-sidebar' },
        'Editorial Checklist'
      ),
      el(
        PluginSidebar,
        {
          name: 'ediworman-checklist-sidebar',
          title: 'Editorial Checklist',
          icon: 'yes-alt',
        },
        el(SidebarContent, null)
      ),
      el(ChecklistStatusInfo, null),
      el(ChecklistPrePublishPanel, null)
    );

  registerPlugin('ediworman-checklist-plugin', {
    render: EditorialChecklistPlugin,
  });
})();
