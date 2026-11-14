/* global wp */
(function () {
  const rawElements = ['script', 'style', 'textarea', 'title', 'iframe', 'xmp'];
  const marker = 'ediwormanDecorativeImage';
  const linkedDestinations = ['media', 'attachment', 'custom'];
  const whitespaceEntities = /&(?:nbsp;|nbsp(?![a-zA-Z0-9=])|(?:Tab|NewLine|NonBreakingSpace|ensp|emsp|emsp13|emsp14|numsp|puncsp|thinsp|ThinSpace|hairsp|VeryThinSpace|MediumSpace|ThickSpace);)/g;
  const nonblank = /[^\p{Z}\x09-\x0d\x85\uFEFF]/u;

  const readAttributes = (markup) => {
    const pattern = /[\x09\x0a\x0c\x0d\x20/]*([^\x09\x0a\x0c\x0d\x20/=<>"']+)(?:[\x09\x0a\x0c\x0d\x20]*=[\x09\x0a\x0c\x0d\x20]*(?:"([^"]*)"|'([^']*)'|([^\x09\x0a\x0c\x0d\x20<>]+)))?/y;
    const attributes = Object.create(null);
    let match;
    while ((match = pattern.exec(markup))) {
      const name = match[1].toLowerCase();
      if (!(name in attributes)) {
        attributes[name] = match[2] ?? match[3] ?? match[4] ?? null;
      }
    }
    return attributes;
  };

  const scan = (html, state, images) => {
    const tags = /<!--[\s\S]*?(?:-->|$)|<\/?[a-z][a-z0-9:-]*(?=[\x09\x0a\x0c\x0d\x20/>])(?:"[^"]*"|'[^']*'|[^'"<>])*>/gi;
    let offset = 0;
    while (offset < html.length) {
      const next = state.raw ? new RegExp(`</${state.raw}(?=[\\x09\\x0a\\x0c\\x0d\\x20/>])[^>]*>`, 'gi') : tags;
      next.lastIndex = offset;
      const match = next.exec(html);
      if (!match) break;

      const tag = match[0];
      offset = next.lastIndex;
      if (state.raw) {
        state.raw = '';
        continue;
      }
      const parts = tag.match(/^<(\/?)([a-z][a-z0-9:-]*)/i);
      if (!parts) continue;
      const name = parts[2].toLowerCase();
      const closing = parts[1] === '/';
      if (name === 'template') {
        state.templateDepth = Math.max(0, state.templateDepth + (closing ? -1 : 1));
      } else if (!closing && rawElements.includes(name)) {
        state.raw = name;
      } else if (!state.templateDepth && name === 'a') {
        state.anchor = !closing && 'href' in readAttributes(tag.slice(parts[0].length, -1));
      } else if (!state.templateDepth && name === 'img' && !closing) {
        const attributes = readAttributes(tag.slice(parts[0].length, -1));
        const attachment = (attributes.class || '').match(/(?:^|[\x09\x0a\x0c\x0d\x20])wp-image-(\d+)(?=$|[\x09\x0a\x0c\x0d\x20])/);
        images.push({
          id: attachment ? Number(attachment[1]) : 0,
          alt: attributes.alt ?? null,
          marked: false,
          interactive: state.anchor,
        });
      }
    }
  };

  const walk = (blocks, state, images) => {
    blocks.forEach((block) => {
      const isImage = block.blockName === 'core/image';
      const attrs = block.attrs || {};
      const first = images.length;
      let child = 0;
      (block.innerContent || [block.innerHTML || '']).forEach((fragment) => {
        if (typeof fragment === 'string') {
          scan(fragment, state, images);
        } else if (block.innerBlocks?.[child]) {
          walk([block.innerBlocks[child]], state, images);
          child += 1;
        }
      });
      if (isImage && !block.innerBlocks?.length && images.length === first + 1) {
        const image = images[first];
        image.marked = attrs.isDecorative === true || attrs[marker] === true;
        const lightbox = attrs.lightbox == null ? image.marked && state.lightboxDefault : attrs.lightbox.enabled === true;
        image.interactive = image.interactive || lightbox ||
          linkedDestinations.includes(attrs.linkDestination);
        if (Number.isInteger(attrs.id) && attrs.id > 0) {
          image.id = attrs.id;
        }
      }
    });
  };

  const collect = (content, lightboxDefault = false) => {
    const images = [];
    walk(wp.blockSerializationDefaultParser.parse(content), { anchor: false, raw: '', templateDepth: 0, lightboxDefault }, images);
    return images;
  };

  const hasAlt = (alt, encoded = true) => {
    if (typeof alt !== 'string') return false;
    if (!encoded) return nonblank.test(alt);
    const text = alt.replace(whitespaceEntities, ' ').replace(/&#(?:([0-9]+)|[xX]([0-9a-fA-F]+));?/g, (reference, decimal, hex) => {
      const point = hex ? parseInt(hex, 16) : Number(decimal);
      if ((point >= 9 && point <= 13) || (point >= 8192 && point <= 8202) ||
          [32, 160, 5760, 8232, 8233, 8239, 8287, 12288, 65279].includes(point)) {
        return ' ';
      }
      return reference;
    });
    return nonblank.test(text);
  };

  const status = (image) => {
    if (image.marked) return image.alt === '' && !image.interactive ? 'decorative' : 'invalid';
    return hasAlt(image.alt) ? 'pass' : 'missing';
  };

  window.EDIWORMAN_IMAGE_ALT = { collect, hasAlt, status };
})();
