import assert from 'node:assert/strict';
import {execFileSync} from 'node:child_process';
import {fileURLToPath, pathToFileURL} from 'node:url';
import {resolve, dirname} from 'node:path';
export async function frontendCases(fixture) {
  const path = fileURLToPath(fixture), php = process.env.PHP_BINARY || 'php';
  const engine = execFileSync(php, [resolve(dirname(path),'language-level1.php'),'--engine-root'],{encoding:'utf8'}).trim();
  const samples = JSON.parse(execFileSync(php,[path,'--json'],{encoding:'utf8',maxBuffer:128*1024*1024}));
  globalThis.location = new URL('https://example.test/app'); globalThis.HTMLButtonElement = class {};
  globalThis.addEventListener = () => {}; globalThis.document = {querySelector:()=>null,addEventListener:()=>{}};
  globalThis.CustomEvent = class {constructor(type,options){this.type=type;this.detail=options.detail;}};
  const {execute} = await import(pathToFileURL(resolve(engine,'src-js/actions-runtime.js')));
  const {rootConfigs} = await import(pathToFileURL(resolve(engine,'src-js/context.js')));
  const {installEvalCompiler} = await import(pathToFileURL(resolve(engine,'src-js/core/php-eval.js')));
  const {compileEval} = await import(pathToFileURL(resolve(engine,'src-js/eval/compiler.js'))); installEvalCompiler(compileEval);
  const failures=[];
  for (const sample of samples) {
    const events=[],logs=[],root={dispatchEvent:(e)=>events.push(e),querySelector:()=>null,querySelectorAll:()=>[]};
    const state=structuredClone(sample.initial); rootConfigs.set(root,structuredClone(sample.config));
    const oldLog=console.log,oldWarn=console.warn;console.log=(s)=>logs.push(s);console.warn=()=>{};
    try {
      if(sample.error){await assert.rejects(execute(root,state,sample.action),(error)=>{assert.equal(error.name,sample.error.engineName || sample.error.name);assert.ok(error.source?.file);return true;});assert.deepEqual(state,sample.initial);}
      else {await execute(root,state,sample.action);assert.deepEqual(state,sample.expected);}
      assert.equal(logs.join(''),sample.output);assert.equal(events.filter((e)=>e.type==='aml:warning').length,sample.warnings.length);
    } catch(error){failures.push(sample.name+': '+error.stack);} finally{console.log=oldLog;console.warn=oldWarn;}
  }
  for(const failure of failures.slice(0,30))console.error(failure);
  console.log(`${path}: ${samples.length-failures.length}/${samples.length} Engine comparisons passed.`);
  if(failures.length)process.exitCode=1;
}
