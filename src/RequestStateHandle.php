<?php

declare(strict_types=1);

namespace AML\View;

use AML\Engine\ApiAction;
use AML\Engine\ClientInstruction;
use AML\Engine\StateRef;

/** One API request with its reactive result and execution status. */
final class RequestStateHandle implements ClientInstruction
{
    public readonly StateRef $data;
    public readonly StateRef $pending;
    public readonly StateRef $success;
    public readonly StateRef $error;
    private ApiAction $request;
    private ?ClientInstruction $successAction = null;
    private ?ClientInstruction $errorAction = null;
    private ?ClientInstruction $finallyAction = null;

    public function __construct(
        ApiAction $request,
        string $base,
        mixed $initial = [],
        ?string $select = null,
    ) {
        $this->data = StateRef::to($base . '.data', $initial);
        $this->pending = StateRef::to($base . '.pending', false);
        $this->success = StateRef::to($base . '.success', false);
        $this->error = StateRef::to($base . '.error', '');
        $this->request = $request->storeIn($this->data->name, $select);
    }

    public function onSuccess(ClientInstruction|\Closure $action): self
    {
        $clone = clone $this;
        $clone->successAction = $action instanceof \Closure ? FrontendClosureCompiler::compile($action) : $action;
        return $clone;
    }

    /** Promise-style alias for onSuccess(). */
    public function then(ClientInstruction|\Closure $action): self
    {
        return $this->onSuccess($action);
    }

    public function onError(ClientInstruction|\Closure $action): self
    {
        $clone = clone $this;
        $clone->errorAction = $action instanceof \Closure ? FrontendClosureCompiler::compile($action) : $action;
        return $clone;
    }

    /** Promise-style alias for onError(). */
    public function catch(ClientInstruction|\Closure $action): self
    {
        return $this->onError($action);
    }

    public function always(ClientInstruction|\Closure $action): self
    {
        $clone = clone $this;
        $clone->finallyAction = $action instanceof \Closure ? FrontendClosureCompiler::compile($action) : $action;
        return $clone;
    }

    /** Promise-style alias for always(). */
    public function finally(ClientInstruction|\Closure $action): self
    {
        return $this->always($action);
    }

    public function json(): string
    {
        return json_encode([
            'type' => 'action-state',
            'action' => json_decode($this->request->json(), true, 512, JSON_THROW_ON_ERROR),
            'pending' => $this->pending->name,
            'success' => $this->success->name,
            'error' => $this->error->name,
            'onSuccess' => $this->payload($this->successAction),
            'onError' => $this->payload($this->errorAction),
            'onFinally' => $this->payload($this->finallyAction),
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    /** @return array<string, mixed>|null */
    private function payload(?ClientInstruction $action): ?array
    {
        return $action === null ? null : json_decode($action->json(), true, 512, JSON_THROW_ON_ERROR);
    }
}
