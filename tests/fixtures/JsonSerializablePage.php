<?php
declare(strict_types=1);
namespace AML\View\Tests\JsonSerializable;
use AML\View\{Page, State, View};
use function AML\View\{Button, VStack, Text};
class Payload implements \JsonSerializable {
    public int $calls = 0;
    public function __construct(private mixed $value) {}
    public function jsonSerialize(): mixed { $this->calls++; return $this->value; }
}
class SelfPayload implements \JsonSerializable {
    public int $calls = 0;
    private string $secret = 'hidden';
    protected string $protected = 'hidden';
    public function jsonSerialize(): mixed { $this->calls++; return $this; }
    public function __get(string $name): mixed { throw new \RuntimeException('JSON must not call __get'); }
}
interface SerializableChild extends \JsonSerializable {}
class InheritedPayload extends Payload implements SerializableChild {}
enum PlainStatus { case Ready; }
enum Status: string implements \JsonSerializable { case Ready = 'ready'; public function jsonSerialize(): mixed { return ['status' => $this->value]; } }
enum SelfStatus implements \JsonSerializable { case Ready; public function jsonSerialize(): mixed { return $this; } }
class Growth implements \JsonSerializable { public function jsonSerialize(): mixed { return new self(); } }
final class JsonSerializablePage extends Page {
    #[State] public array $result = [];
    #[State] public int $counter = 0;
    public string $handler = 'values';
    public function body(): View { $method = $this->handler; return VStack(Button('Run')->click($this->$method(...))); }
    public function values(): void { foreach ([null, true, 1, 1.0, 'André', [1,2], ['a'=>3]] as $value) { $p = new Payload($value); $this->result[] = [json_encode($p, JSON_PRESERVE_ZERO_FRACTION), $p->calls]; } }
    public function selfValue(): void { $a = new SelfPayload(); $this->result = [json_encode($a), json_encode([$a,$a]), $a->calls]; }
    public function inheritance(): void { $a = new InheritedPayload(['yes'=>1]); $this->result = [json_encode($a), $a instanceof \JsonSerializable, $a instanceof SerializableChild]; }
    public function enums(): void { $this->result = [json_encode(Status::Ready), json_encode(SelfStatus::Ready), json_encode(new Payload(PlainStatus::Ready)), json_last_error()]; }
    public function flags(): void { $a = new Payload(['value' => 1.0, 'text' => '<André/"x">', 'num' => '12']); $this->result = [json_encode($a, JSON_PRETTY_PRINT | JSON_PRESERVE_ZERO_FRACTION | JSON_UNESCAPED_UNICODE), json_encode($a, JSON_FORCE_OBJECT | JSON_HEX_TAG | JSON_HEX_QUOT | JSON_NUMERIC_CHECK)]; }
    public function recursiveArray(): void { $a = new class implements \JsonSerializable { public int $calls = 0; public function jsonSerialize(): mixed { $this->calls++; return [$this]; } }; $this->result = [json_encode($a), json_last_error(), json_encode($a, JSON_PARTIAL_OUTPUT_ON_ERROR), json_last_error(), $a->calls]; }
    public function recursiveObjects(): void { $a = new class implements \JsonSerializable { public mixed $next = null; public function jsonSerialize(): mixed { return $this->next; } }; $b = clone $a; $a->next = $b; $b->next = $a; $this->result = [json_encode($a), json_last_error(), json_encode($a, JSON_PARTIAL_OUTPUT_ON_ERROR), json_last_error()]; }
    public function selfCycle(): void { $a = new class implements \JsonSerializable { public mixed $next = null; public function jsonSerialize(): mixed { return $this; } }; $a->next = $a; $this->result = [json_encode($a, JSON_PARTIAL_OUTPUT_ON_ERROR), json_last_error()]; }
    public function nestedEncode(): void { $a = new class implements \JsonSerializable { public int $calls = 0; public function jsonSerialize(): mixed { $this->calls++; return [json_encode($this), json_last_error()]; } }; $this->result = [json_encode($a), json_last_error(), $a->calls]; }
    public function errorContext(): void { $a = new class implements \JsonSerializable { public function jsonSerialize(): mixed { return json_last_error(); } }; json_decode('{'); $this->result = [json_encode($a, JSON_THROW_ON_ERROR), json_last_error(), json_encode($a), json_last_error()]; }
    public function thrown(): void { $a = new class implements \JsonSerializable { public function jsonSerialize(): mixed { throw new \JsonException('user exception', 7); } }; foreach ([0, JSON_THROW_ON_ERROR, JSON_PARTIAL_OUTPUT_ON_ERROR] as $flags) { json_decode('{'); try { json_encode($a, $flags); } catch (\JsonException $e) { $this->result[] = [$e->getMessage(), $e->getCode(), json_last_error()]; } } }
    public function rollback(): void { $this->counter = 1; $a = new class implements \JsonSerializable { public function jsonSerialize(): mixed { throw new \RuntimeException('serializer failure'); } }; $this->result = [json_encode($a, JSON_PARTIAL_OUTPUT_ON_ERROR)]; }
    public function siblingEffects(): void { $b = (object) ['x'=>1]; $a = new class($b) implements \JsonSerializable { public function __construct(private object $next) {} public function jsonSerialize(): mixed { $this->next->x = 2; echo "serialize\n"; return 'done'; } }; $this->result = [json_encode([$a,$b]), $b->x]; }
    public function callbacks(): void { $p = new Payload(['a'=>1]); $fn = 'json_encode'; $this->result = [$fn($p), array_map('json_encode', [$p,$p]), $p->calls]; }
    public function anonymousEval(): void { $code = '$a = new class implements \\JsonSerializable { public int $calls = 0; public function jsonSerialize(): mixed { $this->calls++; return ["value" => 2.0]; } }; return [json_encode($a, JSON_PRESERVE_ZERO_FRACTION), $a->calls, $a instanceof \\JsonSerializable];'; $this->result = eval($code); }
    public function depth(): void { $a = new Payload(3); $b = new Payload([3]); $this->result = [json_encode($a, 0, 0), json_last_error(), json_encode($b,0,0), json_last_error(), json_encode($b,0,1), json_last_error(), $a->calls, $b->calls]; }
    public function unsupportedReturns(): void { $this->result = [json_encode(new Payload(fn () => 1)), json_encode(new Payload(new \DateTimeImmutable('2024-01-02', new \DateTimeZone('UTC')))), json_encode(new Payload(log(0)), JSON_PARTIAL_OUTPUT_ON_ERROR | JSON_THROW_ON_ERROR), json_last_error()]; }

    public function stateEffect(): void { $fn = function () { $this->counter++; return $this->counter; }; $a = new class($fn) implements \JsonSerializable { public function __construct(private mixed $fn) {} public function jsonSerialize(): mixed { return ($this->fn)(); } }; $this->result = [json_encode($a), json_encode($a)]; }
    public function staticEffect(): void { $a = new class implements \JsonSerializable { public static int $calls = 0; public function jsonSerialize(): mixed { return ++self::$calls; } }; $this->result = [json_encode([$a,$a]), json_encode($a)]; }
    public function throwThenRetry(): void { $a = new class implements \JsonSerializable { public int $calls = 0; public function jsonSerialize(): mixed { $this->calls++; if ($this->calls === 1) { throw new \RuntimeException('first'); } return $this->calls; } }; $root = (object) ['a'=>$a]; try { json_encode($root); } catch (\RuntimeException $e) { $this->result[] = $e->getMessage(); } $this->result[] = json_encode($root); }
    public function nestedOuterObject(): void { $root = (object) ['a'=>null]; $a = new class($root) implements \JsonSerializable { public function __construct(private object $root) {} public function jsonSerialize(): mixed { return [json_encode($this->root), json_last_error()]; } }; $root->a = $a; $this->result = [json_encode($root), json_last_error()]; }
    public function growthBoundary(): void { $this->result = [json_encode(new Growth())]; }
    public function nestedThrown(): void { $a = new class implements \JsonSerializable { public function jsonSerialize(): mixed { return json_decode('{', flags: JSON_THROW_ON_ERROR); } }; foreach ([0,JSON_PARTIAL_OUTPUT_ON_ERROR] as $flags) { try { json_encode($a,$flags); } catch (\JsonException $e) { $this->result[] = [$e->getCode(),json_last_error()]; } } }
    public function validateType(): void { $a = new class implements \JsonSerializable { public function jsonSerialize(): int { return 'text'; } }; $this->result = [json_encode($a)]; }
}

class DisplayCounter implements \JsonSerializable {
    public static int $calls = 0;
    public function __construct(private int $counter) {}
    public function jsonSerialize(): mixed { return ++self::$calls; }
}
final class JsonSerializableDisplayPage extends Page {
    #[State] public int $counter = 0;
    public function body(): View { return Text(fn () => json_encode(new DisplayCounter($this->counter))); }
}
