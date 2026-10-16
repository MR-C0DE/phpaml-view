<?php
declare(strict_types=1);
require __DIR__.'/bootstrap.php';require __DIR__.'/support/frontend-cases.php';require __DIR__.'/../examples/profil-unicode/page.php';
$cases=[];
foreach([[],['quantite'=>'5'],['email'=>''],['nom'=>"e\u{301} 🇨🇦 👍🏽",'quantite'=>'3'],['nom'=>'  '],['email'=>'invalide'],['quantite'=>'0'],['quantite'=>'21']]as$i=>$input)$cases[]=['name'=>'profil:'.$i,'method'=>'preparer','button'=>'Préparer','input'=>$input];
frontendCases($cases,static function($case){$page=new Exemples\ProfilUnicode\Pages\ProfilUnicodePage();foreach($case['input']as$key=>$value)$page->$key=$value;return$page;},'Profil Unicode');
