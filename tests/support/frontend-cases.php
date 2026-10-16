<?php
declare(strict_types=1);

/** Compare a rendered event against native PHP, then export it for Engine/browser tests. */
function frontendCases(array $cases, callable $factory, string $label): void
{
    $samples = [];
    $attribute = static function (string $html, string $name): array {
        preg_match('/' . $name . '="([^"]+)"/', $html, $match);
        return json_decode(html_entity_decode($match[1], ENT_QUOTES, 'UTF-8'), true, 512, JSON_THROW_ON_ERROR);
    };
    foreach ($cases as $index => $case) {
        $page = $factory($case); $method = $case['method'] ?? 'run'; $name = $case['name'] ?? $method . ':' . $index;
        $html = (new AML\View\Renderer())->render($page);
        $initial = $attribute($html,'data-aml-state'); $config = $attribute($html,'data-aml-state-config'); $action = $attribute($html,'data-aml-client-click');
        $warnings = []; $error = null;
        set_error_handler(static function ($level, $message) use (&$warnings): bool { $warnings[] = $message; return true; }); ob_start();
        try { if (isset($case['guard'])) $error = ['name'=>$case['guard'], 'engineName'=>$case['guard'] === RangeException::class ? 'RangeError' : $case['guard']]; else $page->$method(); }
        catch (Throwable $failure) { $error = ['name'=>$failure::class,'message'=>$failure->getMessage()]; }
        finally { $output = ob_get_clean(); restore_error_handler(); }
        $expected = []; foreach ($initial as $key => $_) $expected[$key] = $page->$key;
        $test = new AML\View\Testing\TestView($html); $failure = null; $viewWarnings = [];
        set_error_handler(static function ($level,$message) use (&$viewWarnings): bool { $viewWarnings[] = $message; return true; });
        try { $test->click($case['button']??'Run'); } catch (Throwable $caught) { $failure=$caught; } finally { restore_error_handler(); }
        if (($failure !== null) !== ($error !== null) || $failure && $failure::class !== $error['name']) throw new RuntimeException($name . ': ' . ($failure ? $failure::class . ' ' . $failure->getMessage() : 'unexpected success') . ' expected ' . json_encode($error), previous:$failure);
        try { foreach ($error ? $initial : $expected as $key=>$value) $test->assertState($key,$value); }
        catch (Throwable $caught) { throw new RuntimeException($name . ': ' . $caught->getMessage(), previous:$caught); }
        if (count($warnings)!==count($viewWarnings)) throw new RuntimeException($name . ': warning mismatch '.json_encode([$warnings,$viewWarnings]));
        if (implode('',$test->consoleOutput())!==$output) throw new RuntimeException($name . ': console mismatch');
        $browser = $case['browser'] ?? isset($case['method']);
        $sampleHtml=$browser?$html:null;
        $samples[] = ['button'=>$case['button']??'Run','html'=>$sampleHtml,...compact('name','method','initial','config','action','expected','error','warnings','output','browser')];
    }
    if (in_array('--json',$GLOBALS['argv'],true)) echo json_encode($samples, JSON_THROW_ON_ERROR|JSON_PRESERVE_ZERO_FRACTION|JSON_UNESCAPED_UNICODE);
    else echo $label . ': ' . count($samples) . " PHP/ViewTest scenarios passed.\n";
}
