import assert from 'node:assert/strict';
import {execFileSync} from 'node:child_process';
import {fileURLToPath, pathToFileURL} from 'node:url';
import {resolve, dirname} from 'node:path';

const directory = dirname(fileURLToPath(import.meta.url));
const php = process.env.PHP_BINARY || 'php';
const fixture = resolve(directory, 'language-level1.php');
const cases = JSON.parse(execFileSync(php, [fixture, '--json'], {encoding: 'utf8'}));
const engine = execFileSync(php, [fixture, '--engine-root'], {encoding: 'utf8'}).trim();
globalThis.location = new URL('https://example.test/app');
globalThis.HTMLButtonElement = class {};
globalThis.addEventListener = () => {};
globalThis.document = {querySelector: () => null, addEventListener: () => {}};
globalThis.CustomEvent = class { constructor(type, options) { this.type = type; this.detail = options.detail; } };
const {execute} = await import(pathToFileURL(resolve(engine, 'src-js/actions-runtime.js')));
const {rootConfigs} = await import(pathToFileURL(resolve(engine, 'src-js/context.js')));

for (const sample of cases) {
  const root = {dispatchEvent: () => {}};
  const state = structuredClone(sample.initial);
  rootConfigs.set(root, {types: sample.types});
  const logs = [];
  const previous = console.log;
  console.log = (...values) => logs.push(...values);
  try { await execute(root, state, sample.action); }
  finally { console.log = previous; }
  assert.deepEqual(state, sample.expected);
  assert.deepEqual(logs, [sample.dump]);
  assert.deepEqual(Object.keys(state), Object.keys(sample.initial), 'Locals leaked into client state');
}
console.log('Level 1: Engine results and var_dump output match native PHP in both branches.');
