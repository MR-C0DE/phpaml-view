<?php
declare(strict_types=1);
namespace AML\View\Testing;

trait CommonBuiltinEvaluation
{
    private function commonHashBuiltin(string $name, array $arguments, bool $strict): mixed
    {
        [$args] = $this->bindInterpretedBuiltin($name, $arguments, $strict);
        if ($name === 'hash_algos' || $name === 'hash_hmac_algos') return \AML\Engine\BuiltinFunctions::HASH_ALGORITHMS;
        if (!in_array(strtolower($args[0]), \AML\Engine\BuiltinFunctions::HASH_ALGORITHMS, true)) throw new \ValueError('Unsupported frontend hash algorithm: '.$args[0]);
        if ($args[$name === 'hash' ? 2 : 3]) throw new \ValueError('Frontend hashes return hexadecimal text; binary output is not supported');
        $result = $name(...$args);
        $this->validateBuiltinResult($result);
        return $result;
    }
}
