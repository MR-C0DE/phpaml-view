<?php
declare(strict_types=1);
require __DIR__.'/bootstrap.php';
require __DIR__.'/support/frontend-cases.php';
require __DIR__.'/fixtures/'.(PHP_VERSION_ID>=80300?'CompletionStatic83.php':'CompletionStatic82.php');
if(PHP_VERSION_ID>=80400)require __DIR__.'/fixtures/CompletionHooks84.php';
require __DIR__.'/fixtures/LanguageCompletionPage.php';
$methods=['statements','instanceOf','staticProperties','staticInitializers','variableVariables','symbols','extractReferences','conversions','dynamicEval','randoms','regexCallbacks','destructureRefs','propertyRefs','cyclicRefs','generatorRefs','regexAdvanced','namedEval','namespaceEval','enumEval','hooks','cloneClosures','datesMore','intlMore','unicodeValidation','splLists','reflectionMore','serialization','regexMore','regexBudget','namespaceBlocks','namespaceGroup','formatterMore','rngMore','serializedCycle','serializationErrors','reflectionErrors','propertyClone','propertyRefError','splClone','dateFormats','isoPeriod','foreachDestructure','adaptedTypes','hookParameter','reflectionInitialization'];
$methods=array_values(array_filter($methods,static fn($method)=>(PHP_VERSION_ID>=80300||!in_array($method,['staticInitializers','isoPeriod'],true))&&(PHP_VERSION_ID>=80400||!in_array($method,['hooks','reflectionMore','hookParameter','reflectionInitialization'],true))));
frontendCases(array_map(static fn($method)=>['method'=>$method],$methods),static function($case){$p=new AML\View\Tests\LanguageCompletion\LanguageCompletionPage();$p->handler=$case['method'];return $p;},'Language completion');
