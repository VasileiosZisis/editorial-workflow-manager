(function () {
  const { registerPlugin } = wp.plugins;
  const { PluginSidebar, PluginSidebarMoreMenuItem, PluginPostStatusInfo } =
    wp.editor;
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

  const SidebarContent = () => {
    const { items, checkedItems, allDone, toggleItem } = useChecklist();

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
      )
    );
  };

  // This adds a little info line inside "Status & visibility"
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
      el(ChecklistStatusInfo, null)
    );

  registerPlugin('ediworman-checklist-plugin', {
    render: EditorialChecklistPlugin,
  });
})();
