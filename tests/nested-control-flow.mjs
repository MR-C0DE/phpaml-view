import assert from 'node:assert/strict';
import {execFileSync} from 'node:child_process';
import {readFileSync, writeFileSync} from 'node:fs';
import {fileURLToPath, pathToFileURL} from 'node:url';
import {resolve, dirname} from 'node:path';

const directory = dirname(fileURLToPath(import.meta.url));
const php = process.env.PHP_BINARY || 'php';
const report = JSON.parse(process.env.PHPAML_NESTED_SAMPLES ? readFileSync(process.env.PHPAML_NESTED_SAMPLES, 'utf8') : execFileSync(php, [resolve(directory, 'nested-control-flow.php'), '--json'], {encoding: 'utf8', maxBuffer: 16 * 1024 * 1024}));
const engine = execFileSync(php, [resolve(directory, 'language-level1.php'), '--engine-root'], {encoding: 'utf8'}).trim();
globalThis.location = new URL('https://example.test/app');
globalThis.HTMLButtonElement = class {};
globalThis.addEventListener = () => {};
globalThis.document = {querySelector: () => null, addEventListener: () => {}};
globalThis.CustomEvent = class {constructor(type, options) {this.type = type; this.detail = options.detail;}};
const {execute} = await import(pathToFileURL(resolve(engine, 'src-js/actions-runtime.js')));
const {rootConfigs} = await import(pathToFileURL(resolve(engine, 'src-js/context.js')));
const results = [];
assert.equal(report.diagnostics.length, 0, 'Default nesting probes must compile');
for (const sample of report.cases) {
  const root = {dispatchEvent: () => {}, querySelector: () => null, querySelectorAll: () => []};
  const state = structuredClone(sample.initial);
  rootConfigs.set(root, structuredClone(sample.config));
  try {
    assert.ok(sample.viewPass, JSON.stringify(sample.viewError));
    if (sample.limit) {
      await assert.rejects(execute(root, state, sample.action), error => error.code === 'PHP_LOOP_LIMIT' && error.limitKind === sample.limitKind && error.message.includes(sample.limitMessage) && /\[PHP .+\.php:\d+\]/.test(error.message) && error.source.line > 0);
      assert.deepEqual(state, sample.initial, 'Loop limit must restore State');
    } else {
      await execute(root, state, sample.action);
      assert.deepEqual(state, sample.expected, 'Engine output must match native PHP');
    }
    await execute(root, state, sample.recovery);
    assert.equal(state.counter, 42, 'Subsequent events must remain usable');
    results.push({name: sample.name, status: 'passed'});
  } catch (error) {results.push({name: sample.name, status: 'failed', error: error.message});}
}
if (process.env.PHPAML_NESTED_CORE_REPORT) writeFileSync(process.env.PHPAML_NESTED_CORE_REPORT, JSON.stringify({results}, null, 2) + '\n');
for (const result of results.filter(r => r.status !== 'passed')) console.error(result.name + ': ' + result.error);
console.log(`Nested control flow: ${results.filter(r => r.status === 'passed').length}/${results.length} native PHP/Core/Engine parity checks passed, including recovery after loop limits.`);
if (results.some(r => r.status !== 'passed')) process.exitCode = 1;
