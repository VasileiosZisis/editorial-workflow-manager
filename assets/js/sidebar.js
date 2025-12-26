(function () {
  const { registerPlugin } = wp.plugins;
  const { PluginSidebar, PluginSidebarMoreMenuItem } = wp.editPost;
  const { PanelBody, CheckboxControl, Notice } = wp.components;
  const { Fragment, createElement: el, useMemo } = wp.element;
  const { useSelect, useDispatch } = wp.data;

  const SidebarContent = () => {
    const items =
      window.EWM_CHECKLIST_DATA &&
      Array.isArray(window.EWM_CHECKLIST_DATA.items)
        ? window.EWM_CHECKLIST_DATA.items
        : [];

    // Get post meta from the editor store.
    const meta = useSelect(
      (select) => select('core/editor').getEditedPostAttribute('meta') || {},
      []
    );

    const checkedItems =
      meta._ewm_checked_items && Array.isArray(meta._ewm_checked_items)
        ? meta._ewm_checked_items
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
          _ewm_checked_items: Array.from(set),
        },
      });
    };

    const allDone = useMemo(() => {
      if (!items.length) {
        return false;
      }
      return items.every((label) => checkedItems.includes(label));
    }, [items, checkedItems]);

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

  const EditorialChecklistSidebar = () =>
    el(
      Fragment,
      null,
      el(
        PluginSidebarMoreMenuItem,
        { target: 'ewm-checklist-sidebar' },
        'Editorial Checklist'
      ),
      el(
        PluginSidebar,
        {
          name: 'ewm-checklist-sidebar',
          title: 'Editorial Checklist',
          icon: 'yes-alt',
        },
        el(SidebarContent, null)
      )
    );

  registerPlugin('ewm-checklist-plugin', {
    render: EditorialChecklistSidebar,
  });
})();
