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
  const { __, sprintf } = wp.i18n;

  const UUID_PATTERN =
    /^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i;

  const getChecklistData = () => {
    const rawData = window.EDIWORMAN_CHECKLIST_DATA || {};
    const templateMode = rawData.templateMode === 'v2' ? 'v2' : 'legacy';
    const rawItems = Array.isArray(rawData.items) ? rawData.items : [];

    const items = rawItems
      .map((item) => {
        if (!item || typeof item !== 'object') {
          return null;
        }

        const label =
          typeof item.label === 'string' ? item.label.trim() : '';
        if (!label) {
          return null;
        }

        const required = item.required !== false;
        const rawId = typeof item.id === 'string' ? item.id.trim() : '';
        const id = rawId && UUID_PATTERN.test(rawId) ? rawId.toLowerCase() : '';

        if (templateMode === 'v2' && !id) {
          return null;
        }

        return {
          id,
          label,
          required,
        };
      })
      .filter(Boolean);

    return {
      templateMode,
      items,
    };
  };

  const getChecklistSummary = ({
    templateMode,
    items,
    checkedLabels,
    checkedItemIds,
  }) => {
    const checkedLabelsSet = new Set(checkedLabels);
    const checkedItemIdsSet = new Set(checkedItemIds);

    let totalItems = 0;
    let doneItems = 0;
    let requiredTotal = 0;
    let requiredDone = 0;

    items.forEach((item) => {
      const isRequired = templateMode === 'legacy' ? true : item.required !== false;
      const isChecked =
        templateMode === 'v2'
          ? !!item.id && checkedItemIdsSet.has(item.id)
          : checkedLabelsSet.has(item.label);

      totalItems += 1;
      if (isChecked) {
        doneItems += 1;
      }

      if (isRequired) {
        requiredTotal += 1;
        if (isChecked) {
          requiredDone += 1;
        }
      }
    });

    const missingRequired = Math.max(0, requiredTotal - requiredDone);
    const readinessBoolean = requiredTotal === 0 || missingRequired === 0;
    const optionalTotal = Math.max(0, totalItems - requiredTotal);
    const optionalDone = Math.max(0, doneItems - requiredDone);

    return {
      totalItems,
      doneItems,
      requiredTotal,
      requiredDone,
      missingRequired,
      readinessBoolean,
      optionalTotal,
      optionalDone,
    };
  };

  const useChecklist = () => {
    const checklistData = useMemo(() => getChecklistData(), []);
    const { templateMode, items } = checklistData;

    const meta = useSelect(
      (select) => select('core/editor').getEditedPostAttribute('meta') || {},
      [],
    );

    const checkedLabels =
      Array.isArray(meta._ediworman_checked_items)
        ? meta._ediworman_checked_items.filter((value) => typeof value === 'string')
        : [];

    const checkedItemIds =
      Array.isArray(meta._ediworman_checked_item_ids)
        ? meta._ediworman_checked_item_ids
            .filter((value) => typeof value === 'string')
            .map((value) => value.toLowerCase())
        : [];

    const { editPost } = useDispatch('core/editor');

    const summary = useMemo(
      () =>
        getChecklistSummary({
          templateMode,
          items,
          checkedLabels,
          checkedItemIds,
        }),
      [templateMode, items, checkedLabels, checkedItemIds],
    );

    const isChecked = (item) => {
      if (templateMode === 'v2') {
        return !!item.id && checkedItemIds.includes(item.id);
      }

      return checkedLabels.includes(item.label);
    };

    const toggleItem = (item) => {
      if (templateMode === 'v2') {
        if (!item.id) {
          return;
        }

        const nextCheckedIds = new Set(checkedItemIds);
        if (nextCheckedIds.has(item.id)) {
          nextCheckedIds.delete(item.id);
        } else {
          nextCheckedIds.add(item.id);
        }

        editPost({
          meta: {
            ...meta,
            _ediworman_checked_item_ids: Array.from(nextCheckedIds),
          },
        });
        return;
      }

      const nextCheckedLabels = new Set(checkedLabels);
      if (nextCheckedLabels.has(item.label)) {
        nextCheckedLabels.delete(item.label);
      } else {
        nextCheckedLabels.add(item.label);
      }

      editPost({
        meta: {
          ...meta,
          _ediworman_checked_items: Array.from(nextCheckedLabels),
        },
      });
    };

    return {
      templateMode,
      items,
      isChecked,
      toggleItem,
      ...summary,
    };
  };

  const usePostInfo = () => {
    const meta = useSelect(
      (select) => select('core/editor').getEditedPostAttribute('meta') || {},
      [],
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
      [],
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
      [userIdToShow],
    );

    let lastUpdatedTimeText = null;
    if (post && post.modified) {
      const dateObject = new Date(post.modified);
      if (!Number.isNaN(dateObject.getTime())) {
        lastUpdatedTimeText = dateObject.toLocaleString();
      }
    }

    if (!user || !user.name) {
      return { lastUpdatedText: null, lastUpdatedTimeText: null };
    }

    return {
      lastUpdatedText: sprintf(
        /* translators: %s: user display name */
        __('Last updated by %s', 'editorial-workflow-manager'),
        user.name,
      ),
      lastUpdatedTimeText,
    };
  };

  const SidebarContent = () => {
    const {
      items,
      isChecked,
      toggleItem,
      readinessBoolean,
      requiredDone,
      requiredTotal,
      missingRequired,
      optionalDone,
      optionalTotal,
    } = useChecklist();

    const { lastUpdatedText, lastUpdatedTimeText } = usePostInfo();

    if (!items.length) {
      return el(
        PanelBody,
        { title: __('Checklist', 'editorial-workflow-manager'), initialOpen: true },
        el(
          Notice,
          { status: 'info', isDismissible: false },
          __('No checklist template is configured for this post type.', 'editorial-workflow-manager'),
        ),
      );
    }

    const readinessLabel = readinessBoolean
      ? __('Ready', 'editorial-workflow-manager')
      : __('Incomplete', 'editorial-workflow-manager');

    const statusSummaryText =
      requiredTotal > 0
        ? sprintf(
            /* translators: 1: readiness status label, 2: required done count, 3: required total count */
            __('Status: %1$s. Required: %2$d/%3$d.', 'editorial-workflow-manager'),
            readinessLabel,
            requiredDone,
            requiredTotal,
          )
        : sprintf(
            /* translators: %s: checklist readiness status */
            __('Status: %s. No required items.', 'editorial-workflow-manager'),
            readinessLabel,
          );

    const missingRequiredText =
      !readinessBoolean && missingRequired > 0
        ? sprintf(
            /* translators: %d: missing required item count */
            __('Missing required: %d', 'editorial-workflow-manager'),
            missingRequired,
          )
        : '';

    const optionalProgressText =
      optionalTotal > 0
        ? sprintf(
            /* translators: 1: optional done count, 2: optional total count */
            __('Optional %1$d/%2$d', 'editorial-workflow-manager'),
            optionalDone,
            optionalTotal,
          )
        : '';

    return el(
      PanelBody,
      { title: __('Checklist', 'editorial-workflow-manager'), initialOpen: true },
		el(
			'div',
			{ style: { marginBottom: '5px' } },
			el(
				Notice,
          {
            status: readinessBoolean ? 'success' : 'warning',
            isDismissible: false,
          },
          el(
            'p',
            { role: 'status', 'aria-live': 'polite' },
            statusSummaryText,
          ),
          missingRequiredText && el('p', null, missingRequiredText),
        ),
      ),
      optionalProgressText &&
        el(
          'p',
          {
            style: {
              marginTop: '8px',
            },
          },
          optionalProgressText,
        ),
      items.map((item) =>
        el(CheckboxControl, {
          key: item.id || item.label,
          label:
            item.required
              ? item.label
              : sprintf(
                  /* translators: 1: item label, 2: optional marker */
                  __('%1$s (%2$s)', 'editorial-workflow-manager'),
                  item.label,
                  __('Optional', 'editorial-workflow-manager'),
                ),
          checked: isChecked(item),
          onChange: () => toggleItem(item),
        }),
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
            ? sprintf(
                /* translators: 1: last updated by text, 2: datetime string */
                __('%1$s on %2$s', 'editorial-workflow-manager'),
                lastUpdatedText,
                lastUpdatedTimeText,
              )
            : lastUpdatedText,
        ),
    );
  };

  const ChecklistStatusInfo = () => {
    const {
      items,
      readinessBoolean,
      requiredDone,
      requiredTotal,
      missingRequired,
      optionalDone,
      optionalTotal,
    } = useChecklist();

    if (!items.length) {
      return null;
    }

    const readinessLabel = readinessBoolean
      ? __('Ready', 'editorial-workflow-manager')
      : __('Incomplete', 'editorial-workflow-manager');

    const requiredProgressText = sprintf(
      /* translators: 1: required done count, 2: required total count */
      __('Required %1$d/%2$d', 'editorial-workflow-manager'),
      requiredDone,
      requiredTotal,
    );

    const missingRequiredText =
      missingRequired > 0
        ? sprintf(
            /* translators: %d: missing required item count */
            __('Missing required: %d', 'editorial-workflow-manager'),
            missingRequired,
          )
        : '';

    const optionalProgressText =
      optionalTotal > 0
        ? sprintf(
            /* translators: 1: optional done count, 2: optional total count */
            __('Optional %1$d/%2$d', 'editorial-workflow-manager'),
            optionalDone,
            optionalTotal,
          )
        : '';

    let text = '';
    if (missingRequired > 0 && optionalTotal > 0) {
      text = sprintf(
        /* translators: 1: readiness label, 2: required done count, 3: required total count, 4: missing required count, 5: optional done count, 6: optional total count */
        __('%1$s. Required: %2$d/%3$d. Missing required: %4$d. Optional: %5$d/%6$d.', 'editorial-workflow-manager'),
        readinessLabel,
        requiredDone,
        requiredTotal,
        missingRequired,
        optionalDone,
        optionalTotal,
      );
    } else if (missingRequired > 0) {
      text = sprintf(
        /* translators: 1: readiness label, 2: required done count, 3: required total count, 4: missing required count */
        __('%1$s. Required: %2$d/%3$d. Missing required: %4$d.', 'editorial-workflow-manager'),
        readinessLabel,
        requiredDone,
        requiredTotal,
        missingRequired,
      );
    } else if (optionalTotal > 0) {
      text = sprintf(
        /* translators: 1: readiness label, 2: required done count, 3: required total count, 4: optional done count, 5: optional total count */
        __('%1$s. Required: %2$d/%3$d. Optional: %4$d/%5$d.', 'editorial-workflow-manager'),
        readinessLabel,
        requiredDone,
        requiredTotal,
        optionalDone,
        optionalTotal,
      );
    } else {
      text = sprintf(
        /* translators: 1: readiness label, 2: required done count, 3: required total count */
        __('%1$s. Required: %2$d/%3$d.', 'editorial-workflow-manager'),
        readinessLabel,
        requiredDone,
        requiredTotal,
      );
    }

    return el(
      PluginPostStatusInfo,
      null,
      el(
        'span',
        {
          style: readinessBoolean
            ? { color: 'inherit' }
            : { color: '#d63638', fontWeight: '500' },
        },
        text,
      ),
    );
  };

  const ChecklistPrePublishPanel = () => {
    const { items, readinessBoolean, requiredDone, requiredTotal, missingRequired } =
      useChecklist();

    if (!items.length || readinessBoolean) {
      return null;
    }

    return el(
      PluginPrePublishPanel,
      {
        title: __('Editorial Checklist', 'editorial-workflow-manager'),
        initialOpen: true,
      },
      el(
        Notice,
        {
          status: 'warning',
          isDismissible: false,
        },
        sprintf(
          /* translators: 1: missing required count, 2: required done count, 3: required total count */
          __('Incomplete: %1$d required item(s) missing (%2$d/%3$d complete). You can still publish, but review required items first.', 'editorial-workflow-manager'),
          missingRequired,
          requiredDone,
          requiredTotal,
        ),
      ),
    );
  };

  const EditorialChecklistPlugin = () =>
    el(
      Fragment,
      null,
      el(
        PluginSidebarMoreMenuItem,
        { target: 'ediworman-checklist-sidebar' },
        __('Editorial Checklist', 'editorial-workflow-manager'),
      ),
      el(
        PluginSidebar,
        {
          name: 'ediworman-checklist-sidebar',
          title: __('Editorial Checklist', 'editorial-workflow-manager'),
          icon: 'yes-alt',
        },
        el(SidebarContent, null),
      ),
      el(ChecklistStatusInfo, null),
      el(ChecklistPrePublishPanel, null),
    );

  registerPlugin('ediworman-checklist-plugin', {
    render: EditorialChecklistPlugin,
  });
})();
