<?php

declare(strict_types=1);

namespace AML\View\Tests\Exceptions;

use AML\View\{Page, State, View, Console};
use function AML\View\Button;
use InvalidArgumentException as InvalidAge;
use RuntimeException;
use Throwable;
use AML\View\Tests\Exceptions\AgeException as LogicException;

final class LanguageExceptions extends Page
{
    public string $handler = 'simple';
    #[State] public int $age = -1;
    #[State] public int $count = 0;
    #[State] public array $result = [];
    #[State] public string $message = '';

    public function body(): View { return Button('Run')->onClick($this->{$this->handler}(...)); }
    public function simple(): void
    {
        try {
            if ($this->age < 0) { throw new InvalidAge('Âge invalide'); }
        } catch (InvalidAge $error) { $this->message = $error->getMessage(); echo $error->getMessage() . PHP_EOL; }
        finally { $this->count++; }
    }
    public function inherited(): void
    {
        try { $this->count = 2; $this->age = validateAge($this->age); }
        catch (\RuntimeException $error) { $this->message = 'wrong'; }
        catch (AgeException $error) { $this->result = [get_class($error), $error->getMessage(), $error->getCode(), $error instanceof \Exception, $error instanceof \Error, ! $error instanceof \RuntimeException]; $this->count++; }
        finally { $this->count *= 2; }
    }
    public function unionCatch(): void
    {
        try { throw new \LengthException('length'); }
        catch (\RuntimeException|\LengthException $error) { $this->message = $error->getMessage(); }
        try { throw new \Error('no binding'); } catch (\Error) { $this->count++; }
    }
    public function identityAndRethrow(): void
    {
        $first = new RuntimeException(message: 'original', code: 31);
        $copy = identity($first);
        try { try { throw $copy; } catch (Throwable $error) { $this->result[] = $error === $first; throw $error; } }
        catch (Throwable $error) { $this->result[] = $error === $first; $this->result[] = gettype($error); $this->result[] = $error->getMessage(); }
    }
    public function previous(): void
    {
        try {
            try { throw new \DomainException('cause', 2); }
            catch (\Exception $error) { throw new RuntimeException(previous: $error, message: 'wrapper', code: 3); }
        } catch (Throwable $error) { $this->result = [$error->getMessage(), $error->getPrevious()->getMessage(), $error->getPrevious()->getCode(), $error->getPrevious()->getPrevious()?->getMessage()]; }
    }
    public function finallyThrows(): void
    {
        try {
            try { throw new RuntimeException('first'); }
            finally { throw new \LogicException('last', previous: new \Exception('middle')); }
        } catch (Throwable $error) { $this->result = [$error->getMessage(), $error->getPrevious()->getMessage(), $error->getPrevious()->getPrevious()->getMessage()]; }
    }
    public function finallyAfterCatchThrows(): void
    {
        try {
            try { throw new \Exception('first'); }
            catch (\Exception) { throw new \Exception('second'); }
            finally { throw new \Exception('third'); }
        } catch (Throwable $error) { $this->result = [$error->getMessage(), $error->getPrevious()->getMessage()]; }
    }
    public function finallyCycle(): void
    {
        $first = new RuntimeException('first');
        $second = new RuntimeException('second', previous: $first);
        try { try { throw $second; } finally { throw $first; } }
        catch (Throwable $error) { $this->result = [$error === $first, $error->getPrevious() === null]; }
    }
    public function finallyReturn(): void
    {
        $this->result = [returnsFromFinally(), returnsBeforeFinally(), recovered()];
        try { $this->count = 1; return; } finally { $this->count = 2; }
        $this->count = 99;
    }
    public function suppressed(): void
    {
        try { throw new RuntimeException('suppressed'); } finally { $this->count = 3; return; }
    }
    public function loopControl(): void
    {
        for ($i = 0; $i < 4; $i++) {
            try { if ($i === 0) { continue; } if ($i === 2) { break; } $this->count += 10; }
            finally { $this->count++; }
        }
        for ($i = 0; $i < 2; $i++) {
            for ($j = 0; $j < 2; $j++) { try { continue 2; } finally { $this->count++; } }
        }
    }
    public function nativeErrors(): void
    {
        try { $this->count = 'invalid'; } catch (\TypeError $error) { $this->result[] = get_class($error); }
        try { typed('invalid'); } catch (\TypeError $error) { $this->result[] = get_class($error); }
        try { $a = 1 / $this->count; } catch (\Exception) { $this->message = 'wrong'; } catch (\ArithmeticError $error) { $this->result[] = get_class($error); }
        try { $value = match ($this->count) { 1 => 'one' }; } catch (\UnhandledMatchError $error) { $this->result[] = get_class($error); }
        try { random_int(2, 1); } catch (\ValueError $error) { $this->result[] = get_class($error); }
    }
    public function throwExpression(): void
    {
        $value = 'value'; $this->message = $value ?? throw new RuntimeException('unused');
        try { $value = null; $this->message = $value ?? throw new RuntimeException('required'); }
        catch (Throwable $error) { $this->result[] = $error->getMessage(); }
        $fn = fn () => throw new \DomainException('arrow');
        try { $fn(); } catch (\LogicException $error) { $this->result[] = $error->getMessage(); }
    }
    public function badThrow(): void
    {
        try { throw 'invalid'; } catch (\Error $error) { $this->message = $error->getMessage(); }
    }
    public function constructorArgs(): void
    {
        $args = ['message' => 'spread', 'code' => 12];
        $error = new RuntimeException(...$args);
        $this->result = [$error->getMessage(), $error->getCode(), $error->getPrevious()];
        try { $error = new RuntimeException(code: 'bad'); } catch (\TypeError) { $this->count++; }
        $error = new \ErrorException('warning', 9, E_USER_WARNING, 'chosen.php', 42);
        $this->result[] = $error->getSeverity(); $this->result[] = $error->getCode();
        $this->result[] = $error->getFile(); $this->result[] = $error->getLine();
    }
    public function catchThrows(): void
    {
        try {
            try { throw new \DomainException('first'); }
            catch (\DomainException $error) { $this->count++; throw new RuntimeException('second'); }
            catch (RuntimeException) { $this->count = 99; }
        } catch (RuntimeException $error) { $this->message = $error->getMessage(); }
    }
    public function emptyTry(): void { try {} catch (Throwable) { $this->count = 99; } finally { $this->count = 1; } }
    public function exceptionClosure(): void
    {
        $error = new NegativeAge('captured');
        $fn = fn (): Throwable => $error;
        try { throw $fn(); } catch (Throwable $caught) { $this->result = [$caught === $error, $caught->getMessage()]; }
    }
    public function badPrevious(): void
    {
        try { $error = new RuntimeException(previous: 'bad'); }
        catch (\TypeError) { $this->count++; }
    }
    public function unknownCatch(): void
    {
        try { throw new RuntimeException('known'); }
        catch (UnknownException) { $this->count = 99; }
        catch (Throwable) { $this->count = 1; }
    }
    public function assertion(): void
    {
        $this->result[] = assert(true, 'success');
        try { assert(false, 'Invariant invalide'); } catch (\AssertionError $error) { $this->result[] = $error->getMessage(); }
        try { assert(assertion: false, description: new NegativeAge('custom assertion', 7)); }
        catch (AgeException $error) { $this->result[] = [$error->getMessage(), $error->getCode()]; }
        assert(++$this->count > 0, ++$this->count > 0 ? 'yes' : 'no');
    }
    public function assertionString(): void { $this->result[] = assert('false', 'strings are values'); }
    public function weakArguments(): void { $this->result = [weakException(), weakAssertion()]; }
    public function aliasedContracts(): void
    {
        $fn = fn (LogicException $error): LogicException => $error;
        $this->result[] = $fn(new AgeException('correct'))->getMessage();
        try { $fn(new \LogicException('wrong')); } catch (\TypeError) { $this->count++; }
    }
    public function nestedLocals(): void
    {
        $value = 'before';
        try { $value = 'during'; throw new RuntimeException('error'); }
        catch (Throwable $error) { $value .= ':' . $error->getMessage(); }
        finally { $value .= ':finally'; }
        $this->message = $value;
    }
    public function unmatched(): void
    {
        try { $this->count = 9; throw new \Error('unmatched'); }
        catch (\Exception) { $this->count = 99; }
        finally { echo "finally\n"; $this->count++; }
    }
    public function uncaughtService(): void { $this->count = 9; middle(); }
    public function invalidState(): void { $this->result = [new RuntimeException('must remain local')]; }
    public function unsupported(): void { throw new UnsupportedException(); }
    public function serverLog(): void { error_log('server'); }
    public function trace(): void
    {
        try { middle(); }
        catch (Throwable $error) { $this->result = [$error->getFile(), $error->getLine(), $error->getTrace(), $error->getTraceAsString(), $error->getMessage()]; }
    }
}
