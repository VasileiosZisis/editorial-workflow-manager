/* Offline counter. Keep its lexical and URL grammar aligned with the PHP helper. */
(function () {
  const normalizeComponent = (value) => value
    .replace(/[^\x00-\x7f]+/gu, (text) => encodeURIComponent(text))
    .replace(/%([0-9a-fA-F]{2})/g, (escape, hex) => {
      const character = String.fromCharCode(parseInt(hex, 16));
      return /^[A-Za-z0-9._~-]$/.test(character) ? character : escape.toUpperCase();
    });

  const normalizePath = (path) => {
    const result = [];
    normalizeComponent(path).split('/').slice(1).forEach((part) => {
      if (part === '..') result.pop();
      else if (part !== '.') result.push(part);
    });
    return '/' + result.join('/');
  };

  const absoluteUrl = (value) => {
    if (/[\x00-\x20\x7f<>\\]|%(?![0-9a-fA-F]{2})/.test(value)) return null;
    const match = value.match(/^(https?):\/\/(\[[0-9a-f:]+\]|[a-z0-9](?:[a-z0-9.-]*[a-z0-9])?)(?::([0-9]{1,5}))?(\/[^?#]*)?(\?[^#]*)?(?:#.*)?$/i);
    if (!match) return null;
    const host = match[2].toLowerCase();
    if (host[0] === '[') {
      const ip = host.slice(1, -1);
      const groups = ip.split(':').filter(Boolean);
      if (ip.includes(':::') || ip.split('::').length > 2 ||
          (ip.includes('::') ? groups.length >= 8 : groups.length !== 8) ||
          groups.some((group) => group.length > 4)) return null;
    } else if (/(?:^|\.)-|-(?:\.|$)|\.\./.test(host)) return null;
    let port = match[3] ? Number(match[3]) : 0;
    if (port > 65535 || (match[3] && port < 1)) return null;
    const scheme = match[1].toLowerCase();
    if ((scheme === 'http' && port === 80) || (scheme === 'https' && port === 443)) port = 0;
    return { scheme, host, port, path: normalizePath(match[4] || '/'), query: normalizeComponent(match[5] || '') };
  };

  const resolve = (rawHref, base) => {
    const href = rawHref.replace(/^[ \t\n\r\0\x0b\x0c]+|[ \t\n\r\0\x0b\x0c]+$/g, '');
    if (!href || href[0] === '#' || href[0] === '?') return null;
    if (/^[A-Za-z][A-Za-z0-9+.-]*:/.test(href)) return absoluteUrl(href);
    if (href.startsWith('//')) return absoluteUrl(base.scheme + ':' + href);
    const origin = base.scheme + '://' + base.host + (base.port ? ':' + base.port : '');
    const path = href[0] === '/' ? href : base.path.slice(0, base.path.lastIndexOf('/') + 1) + href;
    return absoluteUrl(origin + path);
  };

  const destinationKey = (url) => url.host + ':' + url.port + url.path.replace(/\/+$/, '') + url.query;

  const decodeAttribute = (value, entities) => value.replace(/&(?:#[xX][0-9a-fA-F]+|#[0-9]+|[A-Za-z][A-Za-z0-9]*);/g, (entity) => {
    if (Object.prototype.hasOwnProperty.call(entities, entity)) return entities[entity];
    if (!entity.startsWith('&#')) return entity;
    const hex = entity[2].toLowerCase() === 'x';
    const point = parseInt(entity.slice(hex ? 3 : 2, -1), hex ? 16 : 10);
    if (point < 1 || point > 0x10ffff || (point < 32 && ![9, 10].includes(point)) ||
        (point >= 0x7f && point <= 0x9f) || (point >= 0xd800 && point <= 0xdfff) ||
        (point >= 0xfdd0 && point <= 0xfdef) || (point & 0xffff) >= 0xfffe) return entity;
    return String.fromCodePoint(point);
  });

  const getHref = (attributes) => {
    const pattern = /[\x09\x0a\x0c\x0d\x20]+([^\x09\x0a\x0c\x0d\x20/=<>'"`]+)(?:[\x09\x0a\x0c\x0d\x20]*=[\x09\x0a\x0c\x0d\x20]*(?:"([^"]*)"|'([^']*)'|([^\x09\x0a\x0c\x0d\x20<>'"`=]+)))?/y;
    let match;
    while ((match = pattern.exec(attributes))) {
      if (match[1].toLowerCase() === 'href') return match[2] ?? match[3] ?? match[4] ?? '';
    }
    return null;
  };

  /** No DOM parsing: even provisional evaluation must never load resources. */
  const count = (content, homeUrl, postUrl = '', entities = {}) => {
    const home = absoluteUrl(homeUrl);
    if (!home) throw new Error('Invalid internal-link site URL.');
    const base = absoluteUrl(postUrl) || home;
    const self = destinationKey(base);
    const boundary = home.path.replace(/\/+$/, '');
    const destinations = new Set();
    content = String(content);
    const pattern = /<!--[\s\S]*?(?:-->|$)|\[\/?[A-Za-z_][A-Za-z0-9_:-]*(?=[\x09\x0a\x0c\x0d\x20/\]])(?:"[^"]*"|'[^']*'|[^\]'"])*\]|<\/?[A-Za-z](?:"[^"]*"|'[^']*'|[^'">])*>/g;
    let raw = '';
    let templateDepth = 0;
    let offset = 0;
    while (offset < content.length) {
      const nextPattern = raw ? new RegExp('</' + raw + '(?=[\\x09\\x0a\\x0c\\x0d\\x20/>])[^>]*>', 'gi') : pattern;
      nextPattern.lastIndex = offset;
      const next = nextPattern.exec(content);
      if (!next) break;
      const token = next[0];
      offset = next.index + token.length;
      const tag = token.match(/^<(\/?)([A-Za-z][A-Za-z0-9:-]*)(?=[\x09\x0a\x0c\x0d\x20/>])/);
      if (!tag) continue;
      const name = tag[2].toLowerCase();
      const closing = tag[1] === '/';
      if (raw) {
        if (closing && name === raw) raw = '';
        continue;
      }
      if (name === 'template') {
        templateDepth = Math.max(0, templateDepth + (closing ? -1 : 1));
        continue;
      }
      if (!closing && ['script', 'style', 'textarea', 'title'].includes(name)) {
        raw = name;
        continue;
      }
      if (templateDepth || closing || name !== 'a') continue;
      const href = getHref(token.slice(tag[0].length, -1));
      if (href === null) continue;
      const url = resolve(decodeAttribute(href, entities), base);
      if (!url || url.host !== home.host || url.port !== home.port ||
          (boundary && url.path !== boundary && !url.path.startsWith(boundary + '/'))) continue;
      const key = destinationKey(url);
      if (key !== self) destinations.add(key);
    }
    return destinations.size;
  };

  window.EDIWORMAN_INTERNAL_LINKS = Object.freeze({ count });
})();
