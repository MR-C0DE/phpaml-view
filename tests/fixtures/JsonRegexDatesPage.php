<?php
declare(strict_types=1);
namespace AML\View\Tests\JsonRegexDates;
use AML\View\{Page, State, View};
use function AML\View\{VStack, Button, Text};

enum Status: string { case Ready = 'ready'; }
enum UnitStatus { case Done; }

final class JsonRegexDatesPage extends Page
{
    #[State] public array $result = [];
    #[State] public int $counter = 0;
    public string $handler = 'jsonEncode';
    public function body(): View { $method = $this->handler; return VStack(Button('Run')->onClick($this->{$method}(...))); }
    public function jsonEncode(): void { $this->result = [json_encode(['nom' => 'André', 'url' => '/a', 'prix' => 12.0]), json_encode(['<"&\'' => 'é/'], JSON_HEX_TAG | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_HEX_APOS), json_encode([1.0, 1e-8, -0.0], JSON_PRESERVE_ZERO_FRACTION), json_encode(['x' => [1, 2]], JSON_PRETTY_PRINT), json_encode([1, 2], JSON_FORCE_OBJECT), json_encode(['+12', '1.5'], JSON_NUMERIC_CHECK)]; }
    public function jsonDecode(): void { $a = json_decode('{"n":1.0,"nested":{"ok":true},"items":[1,2]}', true, flags: JSON_THROW_ON_ERROR); $this->result = [$a, gettype($a['n']), json_decode('92233720368547758080', flags: JSON_BIGINT_AS_STRING), json_decode('{"x":1,"x":2}', true)]; }
    public function jsonObject(): void { $a = json_decode('{"name":"André","data":{"age":30}}'); $a->name = 'Marie'; $a->extra = true; $this->result = [$a->name, $a->data->age, get_class($a), property_exists($a, 'extra'), json_encode($a), get_object_vars($a->data)]; }
    public function jsonErrors(): void { $a = json_decode('{'); $first = [json_last_error(), json_last_error_msg()]; try { json_decode('{', flags: JSON_THROW_ON_ERROR); } catch (\JsonException $error) { $this->result = [$a, $first, $error->getCode(), $error->getMessage(), json_last_error()]; } $this->result[] = json_decode('null'); $this->result[] = json_last_error(); }
    public function jsonDepth(): void { $a = json_decode('[[1]]', true, 2); $this->result = [$a, json_last_error(), json_encode([1], depth: 1), json_encode([[1]], depth: 1), json_last_error()]; }
    public function jsonPartial(): void { $this->result = [json_encode([sqrt(-1), fdiv(1, 0)], JSON_PARTIAL_OUTPUT_ON_ERROR), json_last_error(), json_encode(['é', "\u{2028}"], JSON_UNESCAPED_UNICODE), json_encode(["\u{2028}"], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_LINE_TERMINATORS)]; }
    public function jsonValidate(): void { $this->result = [json_validate('{"a":1}'), json_validate('{'), json_last_error(), json_validate('[[1]]', 2), json_last_error(), json_validate('true'), json_validate('9007199254740992'), json_validate('{"constructor":1}')]; }
    public function jsonSurrogate(): void { $this->result = [json_decode('"\uD800"'), json_last_error(), json_last_error_msg(), json_decode('"\uD83D\uDE00"')]; }
    public function regexMatch(): void { $matches = []; $ok = preg_match('/^(?<letters>[A-Z]{2})([0-9]{3})$/', 'AB123', $matches, PREG_OFFSET_CAPTURE); $this->result = [$ok, $matches, preg_match('/^[A-Z]{2}[0-9]{3}$/', 'abc')]; }
    public function regexUnicode(): void { $matches = []; $ok = preg_match('/(é)/u', 'AéZ', $matches, PREG_OFFSET_CAPTURE, 1); $this->result = [$ok, $matches, preg_match('/./u', 'é', $matches, 0, 1), preg_last_error()]; }
    public function regexOptional(): void { $matches = []; preg_match('/(a)(b)?(c)?/', 'a', $matches); $this->result = [$matches]; preg_match('/(a)(b)?(c)?/', 'a', $matches, PREG_UNMATCHED_AS_NULL | PREG_OFFSET_CAPTURE); $this->result[] = $matches; }
    public function regexAll(): void { $matches = []; $a = preg_match_all('/(?<word>[a-z]+)/', 'ab cd', $matches); $this->result = [$a, $matches]; preg_match_all('/([a-z]+)/', 'ab cd', $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE); $this->result[] = $matches; }
    public function regexReplace(): void { $count = 0; $this->result = [preg_replace('/\s+/u', ' ', " ab  cd\tEF ", count: $count), $count, preg_replace('/([a-z])([0-9])/', '$2:$1', 'a1 b2'), preg_replace(['/a/', '/b/'], ['b', 'c'], 'ab'), preg_filter('/[0-9]+/', '#', ['a', 'b12', 'c3'])]; }
    public function regexCallback(): void { $count = 0; $this->result = [preg_replace_callback('/[a-z]+/', fn (array $m): string => strtoupper($m[0]), 'ab 12 cd', count: $count), $count]; }
    public function regexSplit(): void { $this->result = [preg_split('/[,;]+/', 'a,,b;c'), preg_split('/([,;])/', 'a,b;c', flags: PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_OFFSET_CAPTURE), preg_split('/\s+/', ' a b ', flags: PREG_SPLIT_NO_EMPTY), preg_split('/,/', 'a,b,c', 2)]; }
    public function regexGrep(): void { $this->result = [preg_grep('/^[0-9]+$/', ['one' => '12', 'two' => 'ab']), preg_grep('/^[0-9]+$/', ['12', 'ab'], PREG_GREP_INVERT), preg_quote('a.b#c/d', '/')]; }
    public function regexInvalid(): void { $this->result = [preg_match('/[/', 'a'), preg_last_error(), preg_last_error_msg()]; }
    public function regexBoundary(): void { $this->result = [preg_match('/a$/', "a\n"), preg_match('/a$/D', "a\n"), preg_match('/[a-z]+/i', 'ABC'), preg_match('/a . b/xs', "a\nb")]; }
    public function jsonEnums(): void { $this->result = [json_encode(Status::Ready), json_encode(UnitStatus::Done), json_last_error(), json_encode([UnitStatus::Done], JSON_PARTIAL_OUTPUT_ON_ERROR), json_last_error()]; }
    public function jsonDates(): void { $this->result = [json_encode(new \DateTimeImmutable('2026-10-15T12:00:00+00:00')), json_encode(new \DateTimeZone('America/Toronto'))]; }
    public function dateImmutable(): void { $a = new \DateTimeImmutable('2026-10-15T12:30:45.123456+00:00'); $b = $a->modify('+2 days'); $this->result = [$a->format('Y-m-d H:i:s.u P'), $b->format('Y-m-d'), $a->getTimestamp(), $a->getOffset(), $a instanceof \DateTimeInterface, $a->getTimezone()->getName()]; }
    public function dateMutable(): void { $a = new \DateTime('2026-01-31 10:00:00', new \DateTimeZone('UTC')); $b = $a->modify('+1 month'); $c = clone $a; $c->setTime(12, 30, 5, 10); $this->result = [$a === $b, $a->format('Y-m-d H:i:s'), $c->format('Y-m-d H:i:s.u'), $a->setDate(2024, 2, 29)->format('Y-m-d')]; }
    public function dateZone(): void { $a = new \DateTimeImmutable('2026-07-01 12:00:00', new \DateTimeZone('America/Toronto')); $b = $a->setTimezone(new \DateTimeZone('Europe/Paris')); $this->result = [$a->format('Y-m-d H:i:s e P I'), $b->format('Y-m-d H:i:s e P I'), $a->getTimestamp() === $b->getTimestamp(), $a->getOffset()]; }
    public function dateFormat(): void { $a = new \DateTimeImmutable('2024-02-29T13:05:06.123456+02:30'); $this->result = [$a->format('d D j l N S w z W F m M n t L o Y y a A g G h H i s u v e O P p T Z c r U')]; }
    public function dateProcedural(): void { $a = date_create_immutable('2026-10-15T12:00:00+00:00'); $this->result = [date_format($a, 'Y-m-d'), date_timestamp_get($a), timezone_name_get(date_timezone_get($a)), timezone_offset_get(timezone_open('America/Toronto'), $a), gmdate('Y-m-d H:i:s T', 0), checkdate(2, 29, 2024), checkdate(2, 29, 2023)]; }
    public function dateDefaults(): void { date_default_timezone_set('America/Toronto'); $a = new \DateTimeImmutable('2026-01-15 12:00:00'); $this->result = [date_default_timezone_get(), $a->format('Y-m-d H:i:s P'), date('Y-m-d H:i:s P', $a->getTimestamp()), strtotime('+2 days', $a->getTimestamp()), mktime(12, 0, 0, 1, 15, 2026), gmmktime(12, 0, 0, 1, 15, 2026)]; }
    public function dateGetDate(): void { date_default_timezone_set('UTC'); $this->result = [getdate(0), getdate(1709251200)]; }
    public function dateConvert(): void { $a = new \DateTime('2026-10-15'); $b = \DateTimeImmutable::createFromMutable($a); $c = \DateTime::createFromImmutable($b); $this->result = [get_class($b), get_class($c), $a === $c, $b->setTimestamp(0)->format('Y-m-d H:i:s'), $b->format('Y-m-d'), $a->format(\DateTime::ATOM)]; }
    public function dateDst(): void { $a = new \DateTimeImmutable('2026-03-07 12:00:00', new \DateTimeZone('America/Toronto')); $b = $a->modify('+1 day'); $this->result = [$a->format('Y-m-d H:i:s P'), $b->format('Y-m-d H:i:s P'), $b->getTimestamp() - $a->getTimestamp()]; }
    public function dateCallable(): void { $a = new \DateTimeImmutable('2026-10-15'); $format = $a->format(...); $this->result = [$format('Y-m-d')]; }
    public function jsonTypes(): void { $a = json_decode('[0,-0,1.0,1e0,1.5,true,null,"x"]', true); foreach ($a as $value) { $this->result[] = [gettype($value), $value]; } }
    public function jsonRoundtrip(): void { $a = json_decode('{"b":2,"2":3,"a":1}'); unset($a->b); $a->b = 4; $this->result = [json_encode($a), isset($a->b), empty($a->b), property_exists($a, 'b'), get_object_vars($a)]; }
    public function regexAllOptional(): void { $matches = []; preg_match_all('/(a)(b)?/', 'a ab', $matches); $this->result = [$matches]; preg_match_all('/(a)(b)?/', 'a ab', $matches, PREG_PATTERN_ORDER | PREG_UNMATCHED_AS_NULL); $this->result[] = $matches; }
    public function regexZeroWidth(): void { $matches = []; preg_match_all('/b*/', 'abc', $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE); $this->result = [$matches, preg_split('//u', 'aé', flags: PREG_SPLIT_NO_EMPTY), preg_replace('/^/', 'X', 'abc')]; }
    public function regexExtended(): void { $matches = []; $this->result = [preg_match('/ ^ (?P<word> [a-z]+ ) $ # end /x', 'abc', $matches), $matches]; }
    public function regexUnicodeClasses(): void { $this->result = [preg_match('/\s/u', "\u{00a0}"), preg_match('/\w/u', 'é'), preg_match('/\d/u', '٣'), preg_match('/\bé\b/u', 'é'), preg_match('/./u', "\r"), preg_match('/^b/m', "a\rb"), preg_match('/^b/m', "a\nb"), preg_quote('a#b', '#'), preg_replace('/(é)/', '$1/$1', 'été')]; }
    public function dateEpoch(): void { $a = new \DateTimeImmutable('@-1.5'); $this->result = [$a->format('Y-m-d H:i:s.u e'), $a->getTimestamp(), (new \DateTimeImmutable('2026-01-01'))->format('o-W-N'), (new \DateTimeImmutable('2021-01-01'))->format('o-W-N')]; }
    public function dateError(): void { try { $unused = new \DateTimeImmutable('2026-13-01'); } catch (\Exception $error) { $this->result = [get_class($error)]; } }
    public function dateDump(): void { var_dump(new \DateTimeImmutable('2026-10-15T12:00:00+00:00'), new \DateTimeZone('America/Toronto')); }
    public function jsonCallback(): void { $a = json_decode('{"n":2}'); $this->result = [array_map('json_encode', [$a]), json_encode(fn () => 2)]; }
    public function evalData(): void { $code = '$a = json_decode("{\"n\":2}", true, flags: JSON_THROW_ON_ERROR); $d = date_create_immutable("2026-10-15"); return [$a["n"], $d->format("Y-m-d"), preg_match("/^[0-9]+$/", "12")];'; $this->result = eval($code); }
    public function frontendGuards(): void {
        foreach (['/(?i:a)/', '/[[:alpha:]]/'] as $pattern) {
            try { preg_match($pattern, 'aaaa'); $this->result[] = 'accepted'; } catch (\ValueError $error) { $this->result[] = 'rejected'; }
        }
    }
}
