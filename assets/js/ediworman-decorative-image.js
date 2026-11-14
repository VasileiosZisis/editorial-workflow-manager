/* global wp */
(function () {
  const { createElement: el, Fragment } = wp.element;
  const { InspectorControls } = wp.blockEditor;
  const { Button, PanelBody, ToggleControl } = wp.components;
  const { __ } = wp.i18n;
  const marker = 'ediwormanDecorativeImage';
  const linkedDestinations = ['media', 'attachment', 'custom'];

  const useLightboxSetting = wp.blockEditor.useSettings
    ? () => wp.blockEditor.useSettings('lightbox')[0]
    : wp.blockEditor.useSetting
      ? () => wp.blockEditor.useSetting('lightbox')
      : () => undefined;

  const DecorativeControls = ({ attributes, setAttributes, name }) => {
    const schema = wp.blocks.getBlockType(name)?.attributes || {};
    const native = schema.isDecorative?.type === 'boolean';
    const marked = attributes[marker] === true;
    const globalLightbox = useLightboxSetting();
    const linked = Boolean(attributes.href) || linkedDestinations.includes(attributes.linkDestination);
    const lightbox = Boolean(schema.lightbox) && (attributes.lightbox ?? globalLightbox)?.enabled === true;
    const recoverLightbox = native && attributes.isDecorative === true && lightbox;
    if (native && !marked && !recoverLightbox) return null;

    const interactive = linked || lightbox;
    const mark = (convert) => {
      if (interactive) return;
      const edits = { alt: '' };
      if (convert) {
        edits.isDecorative = true;
        edits[marker] = false;
      } else {
        edits[marker] = true;
      }
      if (schema.lightbox) edits.lightbox = { enabled: false };
      setAttributes(edits);
    };
    const help = interactive
      ? __('Remove the image link or turn off image expansion before marking it as decorative.', 'editorial-workflow-manager')
      : __('Use for images that add no information. Marking this image clears its alternative text.', 'editorial-workflow-manager');

    return el(
      InspectorControls,
      null,
      el(
        PanelBody,
        { title: __('Decorative image', 'editorial-workflow-manager'), initialOpen: true },
        recoverLightbox && el(
          Button,
          { variant: 'secondary', onClick: () => setAttributes({ lightbox: { enabled: false } }) },
          __('Turn off image expansion', 'editorial-workflow-manager'),
        ),
        native && marked
          ? el(
            Fragment,
            null,
            el('p', null, interactive ? help : __('This image was marked decorative in an older editor.', 'editorial-workflow-manager')),
            el(Button, { variant: 'secondary', disabled: interactive, onClick: () => mark(true) }, __('Use WordPress decorative setting', 'editorial-workflow-manager')),
            el(Button, { variant: 'tertiary', onClick: () => setAttributes({ [marker]: false }) }, __('Clear older decorative setting', 'editorial-workflow-manager')),
          )
          : !native && el(ToggleControl, {
            label: __('Mark as decorative', 'editorial-workflow-manager'),
            help,
            checked: marked,
            disabled: interactive && !marked,
            onChange: (value) => value ? mark(false) : setAttributes({ [marker]: false }),
          }),
      ),
    );
  };

  wp.hooks.addFilter('editor.BlockEdit', 'ediworman/decorative-image', (BlockEdit) => (props) => {
    if (props.name !== 'core/image' || !props.isSelected || !props.attributes?.url) {
      return el(BlockEdit, props);
    }
    return el(Fragment, null, el(BlockEdit, props), el(DecorativeControls, props));
  });
})();
