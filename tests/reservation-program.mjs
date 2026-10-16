import assert from 'node:assert/strict';
import {execFileSync} from 'node:child_process';
import {fileURLToPath, pathToFileURL} from 'node:url';
import {resolve, dirname} from 'node:path';

const directory = dirname(fileURLToPath(import.meta.url));
const php = process.env.PHP_BINARY || 'php';
const samples = JSON.parse(execFileSync(php, [resolve(directory, 'reservation-program.php'), '--json'], {encoding: 'utf8', maxBuffer: 32 * 1024 * 1024}));
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
    const root = {dispatchEvent: event => events.push(event), querySelector: () => null, querySelectorAll: () => []};
    const state = structuredClone(sample.initial);
    rootConfigs.set(root, structuredClone(sample.config));
    const logs = [], originalLog = console.log;
    console.log = message => logs.push(message);
    try {
        await execute(root, state, sample.action);
        await execute(root, state, sample.action);
        assert.deepEqual(state, sample.expected, sample.variant);
        assert.equal(logs.join(''), sample.output, sample.variant + ': console');
        assert.deepEqual(events.filter(event => event.type === 'aml:warning' || event.type === 'aml:error'), [], sample.variant);
    } finally { console.log = originalLog; }
}
console.log(`Reservation program: ${samples.length} cross-runtime scenarios passed.`);
