import assert from 'node:assert/strict';
import {execFileSync} from 'node:child_process';
import {fileURLToPath, pathToFileURL} from 'node:url';
import {resolve, dirname} from 'node:path';

const directory = dirname(fileURLToPath(import.meta.url));
const php = process.env.PHP_BINARY || 'php';
const samples = JSON.parse(execFileSync(php, [resolve(directory, 'language-level2.php'), '--json'], {encoding: 'utf8', maxBuffer: 32 * 1024 * 1024}));
const engine = execFileSync(php, [resolve(directory, 'language-level1.php'), '--engine-root'], {encoding: 'utf8'}).trim();
globalThis.location = new URL('https://example.test/app');
globalThis.HTMLButtonElement = class {};
globalThis.addEventListener = () => {};
globalThis.document = {querySelector: () => null, addEventListener: () => {}};
globalThis.CustomEvent = class {constructor(type, options) {this.type = type; this.detail = options.detail;}};
const {execute} = await import(pathToFileURL(resolve(engine, 'src-js/actions-runtime.js')));
const {rootConfigs} = await import(pathToFileURL(resolve(engine, 'src-js/context.js')));
for (const sample of samples) {
  const events = [];
  const root = {dispatchEvent: (event) => events.push(event), querySelector: () => null, querySelectorAll: () => []};
  const state = structuredClone(sample.initial);
  rootConfigs.set(root, structuredClone(sample.config));
  const logs = [];
  const originalLog = console.log;
  console.log = (message) => logs.push(message);
  const originalWarn = console.warn;
  console.warn = () => {};
  try {
    if (sample.error) {
      await assert.rejects(execute(root, state, sample.action), (error) => {
        assert.ok(error.source, sample.method + ': missing PHP origin');
        assert.equal(error.source.line, sample.error.line, sample.method + ': incorrect PHP line');
        assert.match(error.message, /\[PHP .*\.php:\d+\]/);
        assert.equal(error.name, sample.error.name, sample.method);
        return true;
      });
      assert.deepEqual(state, sample.initial, sample.method + ': failed action mutated state');
      assert.deepEqual(rootConfigs.get(root).valueTypes, sample.config.valueTypes, 'Rollback must restore PHP numeric kinds');
    } else {
      await execute(root, state, sample.action);
      assert.deepEqual(state, sample.expected, sample.method);
      assert.deepEqual(logs, sample.dump ? [sample.dump] : [], sample.method + ': diagnostic evaluation');
      assert.equal(events.filter((event) => event.type === 'aml:warning').length, sample.warnings.length, sample.method + ': warning parity');
    }
  } finally {console.warn = originalWarn; console.log = originalLog;}
}
console.log(`Level 2: ${samples.length} native PHP scenarios match Engine types, conversions, diagnostics and rollback.`);
