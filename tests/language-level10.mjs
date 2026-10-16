import assert from 'node:assert/strict';
import {execFileSync} from 'node:child_process';
import {fileURLToPath, pathToFileURL} from 'node:url';
import {resolve, dirname} from 'node:path';

const directory = dirname(fileURLToPath(import.meta.url));
const php = process.env.PHP_BINARY || 'php';
const engine = execFileSync(php, [resolve(directory, 'language-level1.php'), '--engine-root'], {encoding: 'utf8'}).trim();
globalThis.location = new URL('https://example.test/app');
globalThis.HTMLButtonElement = class {};
globalThis.addEventListener = () => {};
globalThis.document = {querySelector: () => null, addEventListener: () => {}};
globalThis.CustomEvent = class {constructor(type, options) {this.type = type; this.detail = options.detail;}};
const {execute} = await import(pathToFileURL(resolve(engine, 'src-js/actions-runtime.js')));
const {rootConfigs} = await import(pathToFileURL(resolve(engine, 'src-js/context.js')));
let count = 0;
for (const mode of ['1', '-1']) {
  const samples = JSON.parse(execFileSync(php, ['-d', 'zend.assertions=' + mode, resolve(directory, 'language-level10.php'), '--json'], {encoding: 'utf8', maxBuffer: 8 * 1024 * 1024}));
  for (const sample of samples) {
    const root = {dispatchEvent: () => {}, querySelector: () => null, querySelectorAll: () => []};
    const state = structuredClone(sample.initial); rootConfigs.set(root, structuredClone(sample.config));
    const logs = [], previous = console.log; console.log = (message) => logs.push(message);
    try {
      if (sample.error) {
        await assert.rejects(execute(root, state, sample.action), (error) => {
          assert.equal(error.name, sample.error.name, sample.method);
          assert.equal(error.phpMessage, sample.error.message, sample.method);
          assert.ok(error.source?.file && error.source.line > 0);
          return true;
        });
        assert.deepEqual(state, sample.initial, sample.method + ': rollback');
        assert.deepEqual(rootConfigs.get(root).valueTypes, sample.config.valueTypes, sample.method + ': kinds rollback');
      } else {
        await execute(root, state, sample.action);
        assert.deepEqual(state, sample.expected, sample.method + ', assertions=' + mode);
      }
      assert.equal(logs.join(''), sample.output, sample.method + ': echo'); count++;
    } finally { console.log = previous; }
  }
}
console.log(`Level 10: ${count} native PHP/Engine exception scenarios passed, assertions enabled/disabled.`);
