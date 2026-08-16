<?php

declare(strict_types=1);

namespace AML\View;

use Closure;

final class InteractionKernel
{
    /** @var array<string, Closure(): View> */
    private array $factories = [];

    private TokenSigner $signer;
    private NonceStore $nonces;
    private string $audience;

    public function __construct(string $secret, ?NonceStore $nonces = null, string $audience = 'public')
    {
        $this->signer = new TokenSigner($secret);
        $this->nonces = $nonces ?? new FileNonceStore(sys_get_temp_dir() . '/aml-view-nonces/' . hash('sha256', $secret));
        $this->audience = hash('sha256', $audience);
    }

    /** @param Closure(): View $factory */
    public function register(string $alias, Closure $factory): self
    {
        if (!preg_match('/^[a-z][a-z0-9._-]*$/', $alias)) {
            throw new \InvalidArgumentException("Invalid AML View component alias: {$alias}");
        }
        $this->factories[$alias] = $factory;
        return $this;
    }

    public function mount(string $alias): InteractionResult
    {
        return $this->run($alias, [], null);
    }

    /** @param array<string, mixed> $data */
    public function dispatch(string $token, string $eventId, array $data = []): InteractionResult
    {
        if (strlen($token) > 131072 || strlen($eventId) > 128 || strlen(json_encode($data, JSON_THROW_ON_ERROR)) > 65536) {
            throw new \LengthException('AML View interaction payload is too large.');
        }
        $payload = $this->signer->verify($token);
        $alias = $payload['component'] ?? null;
        $state = $payload['state'] ?? null;
        $nonce = $payload['nonce'] ?? null;
        $expiresAt = $payload['expires_at'] ?? null;
        if (!is_string($alias) || !is_array($state) || !is_string($nonce) || !is_int($expiresAt)) {
            throw new \UnexpectedValueException('Invalid AML View interaction payload.');
        }
        if (!hash_equals($this->audience, (string) ($payload['audience'] ?? ''))) {
            throw new \UnexpectedValueException('AML View interaction audience mismatch.');
        }
        if (!$this->nonces->consume($nonce, $expiresAt)) {
            throw new \UnexpectedValueException('AML View interaction token was already used.');
        }

        return $this->run($alias, $state, $eventId, $data);
    }

    /** @param array<string, mixed> $state */
    private function run(string $alias, array $state, ?string $eventId, array $data = []): InteractionResult
    {
        $factory = $this->factories[$alias] ?? null;
        if ($factory === null) {
            throw new \OutOfBoundsException("Unknown AML View component: {$alias}");
        }

        $cycle = new StateCycle($state);
        Runtime::enter($cycle);
        try {
            $view = $factory();
            $rendered = (new Renderer())->interactive($view);
        } finally {
            Runtime::leave();
        }

        if ($eventId !== null) {
            $rendered->dispatch($eventId, $data);
        }

        $snapshot = $cycle->snapshot();
        $newToken = $this->signer->sign(['component' => $alias, 'state' => $snapshot, 'audience' => $this->audience]);
        return new InteractionResult($rendered->html(), $newToken, $rendered->eventIds());
    }
}
