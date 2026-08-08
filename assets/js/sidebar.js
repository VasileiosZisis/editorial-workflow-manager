(function () {
  const { registerPlugin } = wp.plugins;
  const {
    PluginSidebar,
    PluginSidebarMoreMenuItem,
    PluginPostStatusInfo,
    PluginPrePublishPanel,
  } = wp.editor;
  const { PanelBody, CheckboxControl, Notice, Button } = wp.components;
  const { Fragment, createElement: el, useMemo, useState } = wp.element;
  const { useSelect, useDispatch } = wp.data;
  const { __, sprintf } = wp.i18n;
  const { parse: parseBlocks } = wp.blocks;

  const UUID_PATTERN =
    /^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i;

  const getChecklistData = () => {
    const rawData = window.EDIWORMAN_CHECKLIST_DATA || {};
    const templateMode = rawData.templateMode === 'v2' ? 'v2' : 'legacy';
    const rawItems = Array.isArray(rawData.items) ? rawData.items : [];
    const supportedAutomaticRequirements = new Set([
      'featured_image',
      'excerpt',
      'minimum_word_count',
      'taxonomy_presence',
      'image_alt_text',
    ]);

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
        const description =
          typeof item.description === 'string' ? item.description.trim() : '';
        const url = typeof item.url === 'string' ? item.url.trim() : '';

        if (templateMode === 'v2' && !id) {
          return null;
        }

        return {
          id,
          label,
          description,
          url,
          required,
        };
      })
      .filter(Boolean);

    const automaticRequirements = Array.isArray(rawData.automaticRequirements)
      ? rawData.automaticRequirements
          .map((rule) => {
            if (
              !rule ||
              typeof rule !== 'object' ||
              typeof rule.key !== 'string' ||
              !supportedAutomaticRequirements.has(rule.key) ||
              typeof rule.label !== 'string' ||
              !rule.label.trim()
            ) {
              return null;
            }

            const normalizedRule = {
              key: rule.key,
              label: rule.label.trim(),
            };

            if (rule.key === 'minimum_word_count') {
              const minimum = parseInt(rule.minimum, 10);
              normalizedRule.minimum =
                !Number.isNaN(minimum) && minimum > 0 ? minimum : 300;
            }

            return normalizedRule;
          })
          .filter(Boolean)
      : [];

    const taxonomyRestBases = Array.isArray(rawData.taxonomyRestBases)
      ? rawData.taxonomyRestBases.filter(
          (restBase) =>
            typeof restBase === 'string' && /^[a-z0-9_-]+$/.test(restBase),
        )
      : [];

    return {
      templateMode,
      items,
      automaticRequirements,
      taxonomyRestBases,
    };
  };

  const checklistData = getChecklistData();
  const automaticRuleKeys = new Set(
    checklistData.automaticRequirements.map((rule) => rule.key),
  );

  const getFeedbackData = () => {
    const rawData = window.EDIWORMAN_CHECKLIST_DATA || {};
    const feedback =
      rawData.feedback && typeof rawData.feedback === 'object'
        ? rawData.feedback
        : {};

    return {
      eligible: feedback.eligible === true,
      reviewUrl:
        typeof feedback.reviewUrl === 'string' ? feedback.reviewUrl : '',
      ajaxUrl: typeof feedback.ajaxUrl === 'string' ? feedback.ajaxUrl : '',
      ajaxAction:
        typeof feedback.ajaxAction === 'string' ? feedback.ajaxAction : '',
      nonce: typeof feedback.nonce === 'string' ? feedback.nonce : '',
    };
  };

  const feedbackData = getFeedbackData();

  const getImagesFromHtml = (html) => {
    if (typeof html !== 'string' || !html) {
      return [];
    }

    const imageTags = html.match(/<img\b[^>]*>/gi) || [];

    return imageTags.map((imageTag) => {
      const idMatch = imageTag.match(/\bwp-image-(\d+)\b/i);
      const altMatch = imageTag.match(/\balt\s*=\s*(["'])([\s\S]*?)\1/i);
      return {
        id: idMatch ? parseInt(idMatch[1], 10) : 0,
        alt: altMatch ? altMatch[2] : '',
      };
    });
  };

  const collectImagesFromBlocks = (blocks, images) => {
    if (!Array.isArray(blocks)) {
      return;
    }

    blocks.forEach((block) => {
      if (!block || typeof block !== 'object') {
        return;
      }

      if (block.name === 'core/image') {
        const attributes = block.attributes || {};
        const htmlImage = getImagesFromHtml(block.originalContent || '')[0] || {
          id: 0,
          alt: '',
        };
        images.push({
          id: Number.isInteger(attributes.id) ? attributes.id : htmlImage.id,
          alt:
            typeof attributes.alt === 'string' ? attributes.alt : htmlImage.alt,
        });
        return;
      }

      images.push(...getImagesFromHtml(block.originalContent || ''));

      if (Array.isArray(block.innerBlocks) && block.innerBlocks.length) {
        collectImagesFromBlocks(block.innerBlocks, images);
      }
    });
  };

  const getContentImages = (content) => {
    const images = [];

    try {
      collectImagesFromBlocks(parseBlocks(content || ''), images);
    } catch (error) {
      return getImagesFromHtml(content || '');
    }

    return images.map((image, index) => ({
      ...image,
      source: sprintf(
        /* translators: %d: image position in post content */
        __('Content image %d', 'editorial-workflow-manager'),
        index + 1,
      ),
    }));
  };

  const countWords = (content) => {
    const text = String(content || '')
      .replace(/\[[^\]]*\]/g, ' ')
      .replace(/<[^>]*>/g, ' ')
      .replace(/&(?:#\d+|#x[a-f0-9]+|[a-z][a-z0-9]+);/gi, ' ');
    const matches = text.match(/[\p{L}\p{N}]+(?:[\u2019'-][\p{L}\p{N}]+)*/gu);
    return matches ? matches.length : 0;
  };

  const evaluateAutomaticRequirements = (state) => {
    const { automaticRequirements } = checklistData;
    const imageSummary = state.images.reduce(
      (summary, image) => {
        const mediaAlt = image.id > 0 ? state.mediaAlts[image.id] || '' : '';
        const hasAlt = !!String(image.alt || mediaAlt).trim();
        return {
          total: summary.total + 1,
          missing: summary.missing + (hasAlt ? 0 : 1),
          missingLabels: hasAlt
            ? summary.missingLabels
            : [...summary.missingLabels, image.source],
        };
      },
      { total: 0, missing: 0, missingLabels: [] },
    );

    return automaticRequirements.map((rule) => {
      let passed = false;
      let message = '';

      switch (rule.key) {
        case 'featured_image':
          passed = state.featuredMediaId > 0;
          message = passed
            ? __('Featured image detected.', 'editorial-workflow-manager')
            : __('Add a featured image.', 'editorial-workflow-manager');
          break;
        case 'excerpt':
          passed = !!state.excerpt.replace(/<[^>]*>/g, '').trim();
          message = passed
            ? __('Excerpt detected.', 'editorial-workflow-manager')
            : __('Add a manual excerpt.', 'editorial-workflow-manager');
          break;
        case 'minimum_word_count': {
          const wordCount = countWords(state.content);
          passed = wordCount >= rule.minimum;
          message = sprintf(
            /* translators: 1: current word count, 2: required minimum word count */
            __('%1$d of %2$d required words.', 'editorial-workflow-manager'),
            wordCount,
            rule.minimum,
          );
          break;
        }
        case 'taxonomy_presence':
          passed = Object.values(state.taxonomyTerms).some(
            (terms) => Array.isArray(terms) && terms.length > 0,
          );
          message = passed
            ? __('Category or tag detected.', 'editorial-workflow-manager')
            : __('Assign at least one category or tag.', 'editorial-workflow-manager');
          break;
        case 'image_alt_text':
          passed = imageSummary.missing === 0;
          message = passed
            ? sprintf(
                /* translators: %d: number of images checked */
                __('%d image(s) checked.', 'editorial-workflow-manager'),
                imageSummary.total,
              )
            : sprintf(
                /* translators: %s: comma-separated image locations missing alternative text */
                __('Add alternative text to: %s.', 'editorial-workflow-manager'),
                imageSummary.missingLabels.join(', '),
              );
          break;
      }

      return { ...rule, passed, message };
    });
  };

  const getChecklistSummary = ({
    templateMode,
    items,
    checkedLabelsSet,
    checkedItemIdsSet,
    automaticResults,
  }) => {
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

    const optionalTotal = Math.max(0, totalItems - requiredTotal);
    const optionalDone = Math.max(0, doneItems - requiredDone);

    automaticResults.forEach((result) => {
      totalItems += 1;
      requiredTotal += 1;
      if (result.passed) {
        doneItems += 1;
        requiredDone += 1;
      }
    });

    const missingRequired = Math.max(0, requiredTotal - requiredDone);
    const readinessBoolean = requiredTotal === 0 || missingRequired === 0;

    return {
      totalItems,
      doneItems,
      requiredTotal,
      requiredDone,
      missingRequired,
      readinessBoolean,
      optionalTotal,
      optionalDone,
      automaticResults,
      hasRequirements: totalItems > 0,
    };
  };

  const useEditorState = () =>
    useSelect((select) => {
      const editor = select('core/editor');
      const hasAutomaticRequirements = automaticRuleKeys.size > 0;
      const needsContent =
        automaticRuleKeys.has('minimum_word_count') ||
        automaticRuleKeys.has('image_alt_text');
      const rawContent = needsContent
        ? editor.getEditedPostAttribute('content')
        : '';
      const content =
        typeof rawContent === 'string'
          ? rawContent
          : rawContent && typeof rawContent.raw === 'string'
            ? rawContent.raw
            : '';
      const featuredMediaId = parseInt(
        hasAutomaticRequirements &&
          (automaticRuleKeys.has('featured_image') ||
            automaticRuleKeys.has('image_alt_text'))
          ? editor.getEditedPostAttribute('featured_media')
          : 0,
        10,
      );
      const images = automaticRuleKeys.has('image_alt_text')
        ? getContentImages(content)
        : [];

      if (!Number.isNaN(featuredMediaId) && featuredMediaId > 0) {
        images.unshift({
          id: featuredMediaId,
          alt: '',
          source: __('Featured image', 'editorial-workflow-manager'),
        });
      }

      const mediaAlts = {};
      if (automaticRuleKeys.has('image_alt_text')) {
        const core = select('core');
        images.forEach((image) => {
          if (!image.id || mediaAlts[image.id] !== undefined) {
            return;
          }

          const media = core.getMedia(image.id);
          mediaAlts[image.id] =
            media && typeof media.alt_text === 'string' ? media.alt_text : '';
        });
      }

      const taxonomyTerms = {};
      if (automaticRuleKeys.has('taxonomy_presence')) {
        checklistData.taxonomyRestBases.forEach((restBase) => {
          const terms = editor.getEditedPostAttribute(restBase);
          taxonomyTerms[restBase] = Array.isArray(terms) ? terms : [];
        });
      }

      const rawExcerpt = automaticRuleKeys.has('excerpt')
        ? editor.getEditedPostAttribute('excerpt')
        : '';
      const excerpt =
        typeof rawExcerpt === 'string'
          ? rawExcerpt
          : rawExcerpt && typeof rawExcerpt.raw === 'string'
            ? rawExcerpt.raw
            : '';

      return {
        meta: editor.getEditedPostAttribute('meta') || {},
        post: editor.getCurrentPost(),
        automaticState: {
          content,
          excerpt,
          featuredMediaId:
            !Number.isNaN(featuredMediaId) && featuredMediaId > 0
              ? featuredMediaId
              : 0,
          images,
          mediaAlts,
          taxonomyTerms,
        },
      };
    }, []);

  const useChecklist = (meta, automaticState) => {
    const { templateMode, items } = checklistData;

    const rawCheckedLabels = meta._ediworman_checked_items;
    const rawCheckedItemIds = meta._ediworman_checked_item_ids;

    const checkedLabels = useMemo(
      () =>
        Array.isArray(rawCheckedLabels)
          ? rawCheckedLabels.filter((value) => typeof value === 'string')
          : [],
      [rawCheckedLabels],
    );

    const checkedItemIds = useMemo(
      () =>
        Array.isArray(rawCheckedItemIds)
          ? rawCheckedItemIds
              .filter((value) => typeof value === 'string')
              .map((value) => value.toLowerCase())
          : [],
      [rawCheckedItemIds],
    );

    const checkedLabelsSet = useMemo(
      () => new Set(checkedLabels),
      [checkedLabels],
    );
    const checkedItemIdsSet = useMemo(
      () => new Set(checkedItemIds),
      [checkedItemIds],
    );

    const { editPost } = useDispatch('core/editor');

    const automaticResults = useMemo(
      () => evaluateAutomaticRequirements(automaticState),
      [automaticState],
    );

    const summary = useMemo(
      () =>
        getChecklistSummary({
          templateMode,
          items,
          checkedLabelsSet,
          checkedItemIdsSet,
          automaticResults,
        }),
      [
        templateMode,
        items,
        checkedLabelsSet,
        checkedItemIdsSet,
        automaticResults,
      ],
    );

    const isChecked = (item) => {
      if (templateMode === 'v2') {
        return !!item.id && checkedItemIdsSet.has(item.id);
      }

      return checkedLabelsSet.has(item.label);
    };

    const toggleItem = (item) => {
      if (templateMode === 'v2') {
        if (!item.id) {
          return;
        }

        const nextCheckedIds = new Set(checkedItemIdsSet);
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

      const nextCheckedLabels = new Set(checkedLabelsSet);
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

  const usePostInfo = (meta, post) => {
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

  const ReviewPrompt = () => {
    const [isVisible, setIsVisible] = useState(feedbackData.eligible);
    const [isBusy, setIsBusy] = useState(false);
    const [errorMessage, setErrorMessage] = useState('');

    if (!isVisible) {
      return null;
    }

    const updatePreference = async (promptAction) => {
      if (
        isBusy ||
        !feedbackData.ajaxUrl ||
        !feedbackData.ajaxAction ||
        !feedbackData.nonce
      ) {
        return;
      }

      setIsBusy(true);
      setErrorMessage('');

      const requestBody = new URLSearchParams({
        action: feedbackData.ajaxAction,
        nonce: feedbackData.nonce,
        prompt_action: promptAction,
      });

      try {
        const response = await window.fetch(feedbackData.ajaxUrl, {
          method: 'POST',
          credentials: 'same-origin',
          headers: {
            'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
          },
          body: requestBody.toString(),
        });
        const result = await response.json();

        if (!response.ok || !result || result.success !== true) {
          throw new Error('review-prompt-update-failed');
        }

        setIsVisible(false);
      } catch (error) {
        setErrorMessage(
          __('The review preference could not be saved. Please try again.', 'editorial-workflow-manager'),
        );
        setIsBusy(false);
      }
    };

    return el(
      Notice,
      {
        status: 'info',
        isDismissible: false,
        className: 'ediworman-review-prompt',
      },
      el(
        'p',
        null,
        el(
          'strong',
          null,
          __('Enjoying Editorial Workflow Manager?', 'editorial-workflow-manager'),
        ),
      ),
      el(
        'p',
        null,
        __('You have completed several editorial checklists. A WordPress.org review would help other teams discover the plugin.', 'editorial-workflow-manager'),
      ),
      errorMessage &&
        el(
          'p',
          { role: 'alert', className: 'ediworman-review-prompt__error' },
          errorMessage,
        ),
      el(
        'div',
        { className: 'ediworman-review-prompt__actions' },
        feedbackData.reviewUrl &&
          el(
            Button,
            {
              variant: 'primary',
              href: feedbackData.reviewUrl,
              target: '_blank',
              rel: 'noopener noreferrer',
              'aria-label': __('Leave a review (opens in a new tab)', 'editorial-workflow-manager'),
              onClick: () => setIsVisible(false),
            },
            __('Leave a review', 'editorial-workflow-manager'),
          ),
        el(
          Button,
          {
            variant: 'secondary',
            disabled: isBusy,
            onClick: () => updatePreference('snooze'),
          },
          __('Maybe later', 'editorial-workflow-manager'),
        ),
        el(
          Button,
          {
            variant: 'tertiary',
            disabled: isBusy,
            onClick: () => updatePreference('dismiss'),
          },
          __('Do not ask again', 'editorial-workflow-manager'),
        ),
      ),
    );
  };

  const SidebarContent = ({ checklist, meta, post }) => {
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
      automaticResults,
      hasRequirements,
    } = checklist;

    const { lastUpdatedText, lastUpdatedTimeText } = usePostInfo(meta, post);

    if (!hasRequirements) {
      return el(
        PanelBody,
        { title: __('Checklist', 'editorial-workflow-manager'), initialOpen: true },
        el(
          Notice,
          { status: 'info', isDismissible: false },
          __('No checklist requirements are configured for this post type.', 'editorial-workflow-manager'),
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
        { className: 'ediworman-checklist-status' },
        el(
          Notice,
          {
            status: readinessBoolean ? 'success' : 'warning',
            isDismissible: false,
            className: 'ediworman-checklist-status-summary',
          },
          el(
            'div',
            {
              role: 'status',
              'aria-live': 'polite',
              'aria-atomic': 'true',
            },
            el('p', null, statusSummaryText),
            missingRequiredText && el('p', null, missingRequiredText),
          ),
        ),
      ),
      optionalProgressText &&
        el(
          'p',
          { className: 'ediworman-checklist-optional-progress' },
          optionalProgressText,
        ),
      el(ReviewPrompt),
      items.length > 0 &&
        el(
          'fieldset',
          { className: 'ediworman-checklist-items' },
          el(
            'legend',
            { className: 'screen-reader-text' },
            __('Editorial checklist items', 'editorial-workflow-manager'),
          ),
          items.map((item) => {
          const itemKey = item.id || item.label;
          const label = item.required
            ? item.label
            : sprintf(
                /* translators: 1: item label, 2: optional marker */
                __('%1$s (%2$s)', 'editorial-workflow-manager'),
                item.label,
                __('Optional', 'editorial-workflow-manager'),
              );
          const hasDetails = !!item.description || !!item.url;

          return el(
            'div',
            { className: 'ediworman-checklist-item', key: itemKey },
            el(CheckboxControl, {
              label,
              checked: isChecked(item),
              onChange: () => toggleItem(item),
            }),
            hasDetails &&
              el(
                'details',
                { className: 'ediworman-checklist-item__details' },
                el(
                  'summary',
                  {
                    className: 'ediworman-checklist-item__summary',
                    'aria-label': sprintf(
                      /* translators: %s: checklist item label */
                      __('Details for %s', 'editorial-workflow-manager'),
                      item.label,
                    ),
                  },
                  __('Details', 'editorial-workflow-manager'),
                ),
                item.description &&
                  el(
                    'p',
                    { className: 'ediworman-checklist-item__description' },
                    item.description,
                  ),
                item.url &&
                  el(
                    'p',
                    { className: 'ediworman-checklist-item__reference' },
                    el(
                      'a',
                      {
                        className: 'ediworman-checklist-item__reference-link',
                        href: item.url,
                        target: '_blank',
                        rel: 'noopener noreferrer',
                        'aria-label': sprintf(
                          /* translators: %s: checklist item label */
                          __('Reference for %s (opens in a new tab)', 'editorial-workflow-manager'),
                          item.label,
                        ),
                      },
                      __('Reference', 'editorial-workflow-manager'),
                    ),
                  ),
              ),
          );
          }),
        ),
      automaticResults.length > 0 &&
        el(
          'fieldset',
          { className: 'ediworman-automatic-requirements' },
          el(
            'legend',
            { className: 'ediworman-automatic-requirements__legend' },
            __('Automatic requirements', 'editorial-workflow-manager'),
          ),
          automaticResults.map((result) =>
            el(
              'div',
              {
                className: result.passed
                  ? 'ediworman-automatic-requirement is-passed'
                  : 'ediworman-automatic-requirement is-failed',
                key: result.key,
              },
              el(
                'span',
                {
                  className: 'ediworman-automatic-requirement__icon',
                  'aria-hidden': 'true',
                },
                result.passed ? '\u2713' : '!',
              ),
              el(
                'div',
                { className: 'ediworman-automatic-requirement__content' },
                el('strong', null, result.label),
                el(
                  'span',
                  { className: 'screen-reader-text' },
                  result.passed
                    ? __('Passed.', 'editorial-workflow-manager')
                    : __('Needs attention.', 'editorial-workflow-manager'),
                ),
                el('p', null, result.message),
              ),
            ),
          ),
        ),
      lastUpdatedText &&
        el(
          'p',
          { className: 'ediworman-checklist-last-updated' },
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

  const ChecklistStatusInfo = ({ checklist }) => {
    const {
      hasRequirements,
      readinessBoolean,
      requiredDone,
      requiredTotal,
      missingRequired,
      optionalDone,
      optionalTotal,
    } = checklist;

    if (!hasRequirements) {
      return null;
    }

    const readinessLabel = readinessBoolean
      ? __('Ready', 'editorial-workflow-manager')
      : __('Incomplete', 'editorial-workflow-manager');

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
          className: readinessBoolean
            ? 'ediworman-checklist-post-status is-ready'
            : 'ediworman-checklist-post-status is-incomplete',
        },
        text,
      ),
    );
  };

  const ChecklistPrePublishPanel = ({ checklist }) => {
    const {
      hasRequirements,
      readinessBoolean,
      requiredDone,
      requiredTotal,
      missingRequired,
    } = checklist;

    if (!hasRequirements || readinessBoolean) {
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
        el(
          'p',
          {
            role: 'status',
            'aria-live': 'polite',
            'aria-atomic': 'true',
          },
          sprintf(
            /* translators: 1: missing required count, 2: required done count, 3: required total count */
            __('Incomplete: %1$d required item(s) missing (%2$d/%3$d complete). You can still publish, but review required items first.', 'editorial-workflow-manager'),
            missingRequired,
            requiredDone,
            requiredTotal,
          ),
        ),
      ),
    );
  };

  const EditorialChecklistPlugin = () => {
    const { meta, post, automaticState } = useEditorState();
    const checklist = useChecklist(meta, automaticState);

    return el(
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
          className: 'ediworman-checklist-sidebar',
        },
        el(SidebarContent, { checklist, meta, post }),
      ),
      el(ChecklistStatusInfo, { checklist }),
      el(ChecklistPrePublishPanel, { checklist }),
    );
  };

  registerPlugin('ediworman-checklist-plugin', {
    render: EditorialChecklistPlugin,
  });
})();
