<?php
declare(strict_types=1);
require __DIR__.'/bootstrap.php';require __DIR__.'/support/frontend-cases.php';require __DIR__.'/fixtures/DynamicClassesPage.php';
$methods=['frozenTarget','unknownStatic','skippedLocals','generators','adaptedClasses','nullCallBoundary','nullStatic','construct','arrayTarget','expressionTarget','objectTarget','staticCalls','lateBinding','staticProperties','firstClass','leadingSlash','unknownClass','badClass','privateMethod','nonStatic','constructorType','nullProperties','nullMethods','liveChain','innerNull','innerNullCall','repeatedNullsafe','independentArgument','nullDynamicName','fallback','quietLive','once','dynamicEval','literalEval'];
foreach (['$a?->foo(...);', '$a?->foo->bar(...);', '$a?->foo()::bar(...);', '$a?->foo->bar = 1;', '++$a?->foo->bar;', '$b =& $a?->foo();', 'unset($a?->foo->bar);'] as $code) {
    $program=AML\View\FrontendClosureCompiler::compileEvalProgram('$a = null; '.$code,['source'=>['file'=>'diagnostics.php','line'=>1]]);
    if(!isset($program['error']))throw new RuntimeException('Expected nullsafe diagnostic: '.$code);
}
frontendCases(array_map(static fn($method)=>['method'=>$method],$methods),static function($case){$p=new AML\View\Tests\DynamicClasses\DynamicClassesPage();$p->handler=$case['method'];return$p;},'Dynamic classes/nullsafe');
