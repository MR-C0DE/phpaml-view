<?php
declare(strict_types=1);
namespace AML\View\Tests\LanguageCompletion;
trait CompletionStaticInitializers {
    public function staticInitializers(): void {$f=function(int $seed){static $value=$seed+1;return $value++;};$this->result=[$f(5),$f(99)];}
}
