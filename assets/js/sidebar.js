// We use the wp.* globals that WordPress provides in the editor.

(function () {
  const { registerPlugin } = wp.plugins;
  const { PluginSidebar, PluginSidebarMoreMenuItem } = wp.editPost;
  const { PanelBody } = wp.components;
  const { Fragment, createElement: el } = wp.element;

  const SidebarContent = () => {
    return el(
      PanelBody,
      { title: 'Checklist', initialOpen: true },
      el('p', null, 'Checklist will go here.')
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
