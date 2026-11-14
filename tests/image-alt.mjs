import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { join } from 'node:path';
import { createContext, runInContext } from 'node:vm';

const wordpressRoot = process.argv[2];
assert.ok(wordpressRoot, 'Usage: node tests/image-alt.mjs <WordPress root>');
const readRepo = (path) => readFileSync(new URL(path, import.meta.url), 'utf8');
const context = createContext({ window: {} });
runInContext(readFileSync(join(wordpressRoot, 'wp-includes/js/dist/block-serialization-default-parser.js'), 'utf8'), context);
context.wp = context.window.wp;
runInContext(readRepo('../assets/js/ediworman-image-alt.js'), context);
const { collect, status } = context.window.EDIWORMAN_IMAGE_ALT;
const fixtures = JSON.parse(readRepo('./image-alt-fixtures.json'));
for (const fixture of fixtures) {
  assert.deepEqual(Array.from(collect(fixture.content, fixture.lightboxDefault), status), fixture.statuses, fixture.name);
}

let filter;
let schema = { ediwormanDecorativeImage: { type: 'boolean' } };
let globalLightbox;
const elements = {
  createElement: (type, props, ...children) => ({ type, props, children }),
  Fragment: 'Fragment',
};
Object.assign(context.wp, {
  element: elements,
  blockEditor: { InspectorControls: 'InspectorControls', useSettings: () => [globalLightbox] },
  blocks: { getBlockType: () => ({ attributes: schema }) },
  components: { Button: 'Button', PanelBody: 'PanelBody', ToggleControl: 'ToggleControl' },
  hooks: { addFilter: (_hook, _key, callback) => { filter = callback; } },
  i18n: { __: (text) => text },
});
runInContext(readRepo('../assets/js/ediworman-decorative-image.js'), context);
const wrap = filter('Image');
const changes = [];
const render = (attributes) => {
  const view = wrap({
    name: 'core/image', isSelected: true, attributes: { url: '/photo.jpg', ...attributes },
    setAttributes: (edits) => changes.push({ ...edits }),
  });
  const controls = view.children[1];
  return controls.type(controls.props)?.children[0].children.find(Boolean);
};
const switchOn = render({ alt: 'An informative image' });
assert.equal(switchOn.props.label, 'Mark as decorative');
switchOn.props.onChange(true);
assert.deepEqual(changes.pop(), { alt: '', ediwormanDecorativeImage: true });
render({ ediwormanDecorativeImage: true, alt: '' }).props.onChange(false);
assert.deepEqual(changes.pop(), { ediwormanDecorativeImage: false });
assert.equal(render({ href: '/details' }).props.disabled, true);
assert.equal(render({ linkDestination: 'media' }).props.disabled, true);
schema = { ...schema, lightbox: { type: 'object' } };
globalLightbox = { enabled: true };
assert.equal(render({}).props.disabled, true);
render({ lightbox: { enabled: false } }).props.onChange(true);
assert.deepEqual(JSON.parse(JSON.stringify(changes.pop())), {
  alt: '', ediwormanDecorativeImage: true, lightbox: { enabled: false },
});
schema = { ...schema, isDecorative: { type: 'boolean' } };
assert.equal(render({}), undefined);
const conversion = render({ ediwormanDecorativeImage: true, lightbox: { enabled: false } }).children[1];
conversion.props.onClick();
assert.deepEqual(JSON.parse(JSON.stringify(changes.pop())), {
  alt: '', isDecorative: true, ediwormanDecorativeImage: false, lightbox: { enabled: false },
});
const recover = render({ isDecorative: true });
assert.equal(recover.children[0], 'Turn off image expansion');
recover.props.onClick();
assert.deepEqual(JSON.parse(JSON.stringify(changes.pop())), { lightbox: { enabled: false } });
console.log(`Passed ${fixtures.length} image ALT fixtures and native/fallback editor control checks.`);
