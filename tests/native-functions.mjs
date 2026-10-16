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
const {arrayEntries, arrayKind} = await import(pathToFileURL(resolve(engine, 'src-js/core/php-arrays.js')));
const {phpType} = await import(pathToFileURL(resolve(engine, 'src-js/core/php-values.js')));
const samples = JSON.parse(execFileSync(php, [resolve(directory, 'native-functions.php'), '--json'], {encoding: 'utf8', maxBuffer: 32 * 1024 * 1024}));
const describe = (value, kind = phpType(value)) => kind === 'array' ? {type: 'array', entries: arrayEntries(value).map(([key, item]) => ({key, item: describe(item, arrayKind(value, key))}))} : {type: kind, value};
const compare = (actual, expected, path) => {
  assert.equal(actual.type, expected.type, path + ': type');
  if (actual.type === 'array') {assert.deepEqual(actual.entries.map((entry) => entry.key), expected.entries.map((entry) => entry.key), path + ': keys'); actual.entries.forEach((entry, i) => compare(entry.item, expected.entries[i].item, path + '.' + entry.key));}
  else if (actual.type === 'double') assert.ok(Object.is(actual.value, expected.value) || Math.abs(actual.value - expected.value) <= Math.max(1, Math.abs(expected.value)) * 1e-13, path + ': ' + actual.value + ' !== ' + expected.value);
  else assert.deepEqual(actual.value, expected.value, path);
};
const failures = [];
for (const sample of samples) {
  const root = {dispatchEvent: () => {}, querySelector: () => null, querySelectorAll: () => []}, state = structuredClone(sample.initial);
  rootConfigs.set(root, structuredClone(sample.config));
  const logs = [], original = console.log, oldWarn = console.warn; console.log = (value) => logs.push(value); console.warn = () => {};
  try {
    if (sample.error) {await assert.rejects(execute(root, state, sample.action), (error) => {assert.equal(error.name, sample.error.name, sample.name); assert.ok(error.source?.file); return true;}); assert.deepEqual(state, sample.initial);}
    else {await execute(root, state, sample.action); compare(describe(state.result), sample.typed, sample.name);}
    assert.equal(logs.join(''), sample.output, sample.name + ': console');
  } catch (error) {failures.push(sample.name + ': ' + error.message);}
  finally {console.log = original; console.warn = oldWarn;}
}
for (const failure of failures) console.error(failure);
console.log(`Native library: ${samples.length - failures.length}/${samples.length} PHP/Engine comparisons passed.`);
if (failures.length) process.exitCode = 1;
