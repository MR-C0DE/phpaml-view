import assert from 'node:assert/strict';
import {execFileSync} from 'node:child_process';
import {fileURLToPath, pathToFileURL} from 'node:url';
import {resolve, dirname} from 'node:path';
const directory = dirname(fileURLToPath(import.meta.url)), php = process.env.PHP_BINARY || 'php';
const engine = execFileSync(php, [resolve(directory, 'language-level1.php'), '--engine-root'], {encoding: 'utf8'}).trim();
globalThis.location = new URL('https://example.test/app'); globalThis.HTMLButtonElement = class {};
globalThis.addEventListener = () => {}; globalThis.document = {querySelector: () => null, addEventListener: () => {}};
globalThis.CustomEvent = class {constructor(type, options) {this.type = type; this.detail = options.detail;}};
const {execute} = await import(pathToFileURL(resolve(engine, 'src-js/actions-runtime.js')));
const {rootConfigs} = await import(pathToFileURL(resolve(engine, 'src-js/context.js')));
const samples = JSON.parse(execFileSync(php, [resolve(directory, 'bcmath.php'), '--json'], {encoding: 'utf8', maxBuffer: 256 * 1024 * 1024}));
const failures = [];
for (const sample of samples) {
  const root = {dispatchEvent: () => {}, querySelector: () => null, querySelectorAll: () => []}, state = structuredClone(sample.initial);
  rootConfigs.set(root, structuredClone(sample.config));
  const logs = [], original = console.log, oldWarn = console.warn; console.log = (value) => logs.push(value); console.warn = () => {};
  try {
    if (sample.error) {await assert.rejects(execute(root, state, sample.action), (error) => {assert.equal(error.name, sample.error.name, sample.name); assert.ok(error.source?.file); return true;}); assert.deepEqual(state, sample.initial);}
    else {await execute(root, state, sample.action); assert.deepEqual(state.result, sample.expected.result); if (sample.name.startsWith('scales:')) { await execute(root, state, sample.action); assert.deepEqual(state.result, sample.expected.result); }}
    assert.equal(logs.join(''), sample.output, sample.name + ': console');
  } catch (error) {failures.push(sample.name + ' ' + JSON.stringify(sample.initial.arguments).slice(0, 160) + ': ' + error.message.slice(0, 400));}
  finally {console.log = original; console.warn = oldWarn;}
}
for (const failure of failures.slice(0, 60)) console.error(failure);
console.log(`BCMath: ${samples.length - failures.length}/${samples.length} PHP/Engine comparisons passed.`);
if (failures.length) process.exitCode = 1;
