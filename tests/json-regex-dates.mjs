import assert from 'node:assert/strict';
import {readFileSync} from 'node:fs';
import {execFileSync} from 'node:child_process';
import {fileURLToPath, pathToFileURL} from 'node:url';
import {resolve, dirname} from 'node:path';

const directory = dirname(fileURLToPath(import.meta.url));
const php = process.env.PHP_BINARY || 'php';
const samples = JSON.parse(execFileSync(php, [resolve(directory, 'json-regex-dates.php'), '--json'], {encoding: 'utf8', maxBuffer: 32 * 1024 * 1024}));
const engine = execFileSync(php, [resolve(directory, 'language-level1.php'), '--engine-root'], {encoding: 'utf8'}).trim();
globalThis.location = new URL('https://example.test/app');
globalThis.HTMLButtonElement = class {};
globalThis.addEventListener = () => {};
globalThis.document = {querySelector: () => null, addEventListener: () => {}};
globalThis.CustomEvent = class {constructor(type, options) {this.type = type; this.detail = options.detail;}};
const normalize = (text) => text.replace(/(object\([^\n]*?\))#\d+/g, '$1').replace(/object\(class@anonymous.*?\)(?:#\d+)?/gs, 'object(anonymous)');
const {execute} = await import(pathToFileURL(resolve(engine, 'src-js/actions-runtime.js')));
await import(pathToFileURL(execFileSync(php, [resolve(directory, 'language-level1.php'), '--eval-asset'], {encoding: 'utf8'}).trim()));
const {installEvalCompiler} = await import(pathToFileURL(resolve(engine, 'src-js/core/php-eval.js')));
installEvalCompiler(globalThis.AMLPhpEval.compile);
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
    if (sample.invalidState) {
      await assert.rejects(execute(root, state, sample.action), /service objects must remain local/);
      assert.deepEqual(state, sample.initial);
    } else if (sample.limitCode) {
      await assert.rejects(execute(root, state, sample.action), (error) => error.code === sample.limitCode && !!error.source);
      assert.deepEqual(state, sample.initial, sample.method + ': state was not restored');
    } else if (sample.error) {
      await assert.rejects(execute(root, state, sample.action), (error) => {
        assert.ok(error.source, sample.method + ': missing PHP origin');
        assert.ok(error.source.line > 0, sample.method + ': missing PHP line');
        assert.match(error.message, /\[PHP .*\.php:\d+\]/);
        assert.equal(error.name, sample.error.name, sample.method);
        return true;
      });
      assert.deepEqual(state, sample.initial, sample.method + ': failed action mutated state');
      assert.deepEqual(rootConfigs.get(root).valueTypes, sample.config.valueTypes, 'Rollback must restore PHP numeric kinds');
    } else {
      for (let i = 0; i < (sample.repetitions || 1); i++) await execute(root, state, sample.action);
      assert.deepEqual(state, sample.expected, sample.method);
      assert.equal(normalize(logs.join('')), sample.output, sample.method + ': echo output');
      assert.equal(events.filter((event) => event.type === 'aml:warning').length, sample.warnings.length, sample.method + ': warning parity');
    }
  } finally {console.warn = originalWarn; console.log = originalLog;}
}
console.log(`JSON/regex/dates: ${samples.length} cross-runtime scenarios passed.`);
