import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { spawnSync } from 'node:child_process';
import vm from 'node:vm';

const root = new URL('../', import.meta.url);
const fixtures = JSON.parse(readFileSync(new URL('internal-links-fixtures.json', import.meta.url), 'utf8'));
const php = spawnSync(process.env.EDIWORMAN_PHP_BINARY || 'php', ['tests/internal-links.php', '--json'], { cwd: root, encoding: 'utf8' });
assert.equal(php.status, 0, php.stderr || php.stdout);
const server = JSON.parse(php.stdout);
// Deliberately provide no DOM, fetch, browser URL parser, or WordPress runtime.
const context = vm.createContext({ window: {} });
vm.runInContext(readFileSync(new URL('assets/js/ediworman-internal-links.js', root), 'utf8'), context);
fixtures.forEach((fixture, index) => {
  const run = () => context.window.EDIWORMAN_INTERNAL_LINKS.count(fixture.content, fixture.home ?? 'https://example.test/site/', fixture.post ?? 'https://example.test/site/posts/current/', server.entities);
  if (fixture.error) assert.throws(run, undefined, fixture.name);
  else {
    assert.equal(run(), fixture.expected, fixture.name);
    assert.equal(run(), server.counts[index], fixture.name + ' PHP/editor parity');
  }
});
console.log(`${fixtures.length} PHP/editor counting fixtures passed with no DOM or network APIs.`);
