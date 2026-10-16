import assert from 'node:assert/strict';
import {execFileSync, spawnSync} from 'node:child_process';
import {fileURLToPath, pathToFileURL} from 'node:url';
import {resolve, dirname} from 'node:path';

const directory = dirname(fileURLToPath(import.meta.url));
const php = process.env.PHP_BINARY || 'php';
const samples = JSON.parse(execFileSync(php, [resolve(directory, 'language-level78.php'), '--json'], {encoding: 'utf8', maxBuffer: 32 * 1024 * 1024}));
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
        assert.ok(error.source.line > 0, sample.method + ': missing PHP line');
        assert.match(error.message, /\[PHP .*\.php:\d+\]/);
        assert.equal(error.name, sample.error.name, sample.method);
        return true;
      });
      assert.deepEqual(state, sample.initial, sample.method + ': failed action mutated state');
      assert.deepEqual(rootConfigs.get(root).valueTypes, sample.config.valueTypes, 'Rollback must restore PHP numeric kinds');
    } else {
      await execute(root, state, sample.action);
      assert.deepEqual(state, sample.expected, sample.method + ':' + JSON.stringify(sample.initial));
      if (Object.hasOwn(sample, 'output')) assert.equal(logs.join(''), sample.output, sample.method + ': echo');

      assert.equal(events.filter((event) => event.type === 'aml:warning').length, sample.warnings.length, sample.method + ': warning parity');
    }
  } finally {console.warn = originalWarn; console.log = originalLog;}
}
console.log(`Levels 7–8: ${samples.length} cross-runtime builtin scenarios passed.`);
const cli = resolve(directory, '../examples/niveaux-7-8/console.php');
for (const scenario of [
  {args: ['André'], input: '1\n2\n15\n0\n', status: 0, stdout: ['Bonjour André', '30\n'], stderr: ''},
  {args: [], input: 'André\n0\n', status: 0, stdout: ['Votre nom :'], stderr: ''},
  {args: [], input: '', status: 1, stdout: [], stderr: 'Aucune saisie reçue.'},
  {args: [' '], input: '', status: 2, stdout: [], stderr: 'Le nom est obligatoire.'},
  {args: ['André'], input: '2\n12abc\n0\n', status: 0, stdout: [], stderr: 'Saisie invalide.'},
]) {
  const result = spawnSync(php, [cli, ...scenario.args], {input: scenario.input, encoding: 'utf8', timeout: 5000});
  assert.equal(result.status, scenario.status, result.stderr);
  for (const output of scenario.stdout) assert.ok(result.stdout.includes(output));
  if (scenario.stderr === '') assert.equal(result.stderr, ''); else assert.ok(result.stderr.includes(scenario.stderr));
}
console.log('Level 7: 5 PHP CLI scenarios passed (streams, arguments, validation and exit status).');
