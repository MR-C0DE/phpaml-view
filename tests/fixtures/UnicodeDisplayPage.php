<?php
declare(strict_types=1);
namespace AML\View\Tests\UnicodeDisplay;
use AML\View\{Page,State,View};
use function AML\View\{Button,VStack,Text};
final class UnicodeDisplayPage extends Page
{
    #[State] public string $functionName='grapheme_strlen';
    #[State] public array $arguments=[];
    #[State] public mixed $result=null;
    #[State] public string $message="e\u{301} 👨‍👩‍👧‍👦";
    public string $handler='run';
    public function body(): View {return VStack(Text(grapheme_substr($this->message,0,3)),Text(grapheme_strlen($this->message)),Text(\Normalizer::normalize($this->message)),Button('Run')->onClick($this->{$this->handler}(...)));}
    public function run(): void {$f=$this->functionName;$this->result=$f(...$this->arguments);}
    public function extract(): void {
        $out=[];
        foreach([-100,-1,0,1,2,3,4,6,28,32,100] as $offset){foreach([GRAPHEME_EXTR_COUNT,GRAPHEME_EXTR_MAXBYTES,GRAPHEME_EXTR_MAXCHARS] as $type){$next=99;$out[]=[grapheme_extract("aé👨‍👩‍👧‍👦e\u{301}z",4,$type,$offset,$next),$next];}}
        $this->result=$out;
    }
    public function extractionErrors(): void {$out=[];foreach([-1,9]as$type){$next=99;try{grapheme_extract('abc',1,$type,0,$next);}catch(\ValueError $e){$out[]=$next;}}$this->result=$out;}
    public function composition(): void {$this->message="🇨🇦 👍🏽 e\u{301}";$f=grapheme_strlen(...);$this->result=[array_map($f,['é','👨‍👩‍👧‍👦',"e\u{301}"]),normalizer_normalize('Straße Ａ',\Normalizer::NFKC_CF),grapheme_str_split($this->message,2)];}
    public function dynamicEval(): void {$code='return [grapheme_strlen("👨‍👩‍👧‍👦"), grapheme_substr("é🌍",1),normalizer_normalize("e\\u{301}",16)];';$this->result=eval($code);}
    public function intlFormats(): void {
        $out=[];
        foreach(['fr_CA','fr_FR','en_US','de_DE','ar_EG','ja_JP']as$locale){foreach([\NumberFormatter::DECIMAL,\NumberFormatter::CURRENCY,\NumberFormatter::CURRENCY_ACCOUNTING,\NumberFormatter::PERCENT]as$style){
            $fmt=new \NumberFormatter($locale,$style);
            $out[]=[$fmt->format(1234.56),$fmt->format(-1.25),$fmt->formatCurrency(1234.56,'CAD'),$fmt->formatCurrency(1234.56,'JPY'),$fmt->getTextAttribute(\NumberFormatter::CURRENCY_CODE)];
        }}
        $this->result=$out;
    }
    public function intlAttributes(): void {
        $fmt=\NumberFormatter::create('fr_CA',\NumberFormatter::DECIMAL);$out=[];
        foreach([0,1,2,3,4,5,6]as$mode){$fmt->setAttribute(\NumberFormatter::FRACTION_DIGITS,1);$fmt->setAttribute(\NumberFormatter::ROUNDING_MODE,$mode);$out[]=[$fmt->format(1.25),$fmt->format(-1.25),$fmt->getAttribute(\NumberFormatter::FRACTION_DIGITS)];}
        $copy=clone $fmt;$copy->setAttribute(\NumberFormatter::GROUPING_USED,0);$out[]=[$copy->format(1234.56),$fmt->format(1234.56)];
        $this->result=$out;
    }
    public function intlFunctions(): void {
        $fmt=numfmt_create('en_US',\NumberFormatter::CURRENCY);numfmt_set_text_attribute($fmt,\NumberFormatter::CURRENCY_CODE,'CAD');numfmt_set_attribute($fmt,\NumberFormatter::FRACTION_DIGITS,3);
        $this->result=[numfmt_format($fmt,1234.5),numfmt_format_currency($fmt,1234.5,'EUR'),numfmt_get_attribute($fmt,\NumberFormatter::MAX_FRACTION_DIGITS),numfmt_get_text_attribute($fmt,\NumberFormatter::CURRENCY_CODE),\Normalizer::isNormalized("e\u{301}"),\Normalizer::getRawDecomposition('Ǻ')];
    }
    public function intlEval(): void {$code='$fmt=new NumberFormatter("fr_CA",NumberFormatter::CURRENCY); return [$fmt->formatCurrency(12.5,"CAD"),Normalizer::normalize("e\\u{301}",Normalizer::NFC)];';$this->result=eval($code);}
    public function literalEval(): void {$this->result=eval('$next=0; return [grapheme_extract("é🌍",1,GRAPHEME_EXTR_COUNT,0,$next),$next];');}
}
