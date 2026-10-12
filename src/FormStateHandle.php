<?php

declare(strict_types=1);

namespace AML\View;

use AML\Engine\ClientInstruction;
use AML\Engine\StateRef;
use Closure;

/** Reactive state owned by one native form. */
final readonly class FormStateHandle
{
    public StateRef $dirty;
    public StateRef $touched;
    public StateRef $valid;
    public StateRef $invalid;
    public StateRef $submitting;
    public StateRef $submitted;
    public StateRef $error;

    public function __construct(private string $base)
    {
        $this->dirty = StateRef::to($base . '.dirty', false);
        $this->touched = StateRef::to($base . '.touched', false);
        $this->valid = StateRef::to($base . '.valid', true);
        $this->invalid = StateRef::to($base . '.invalid', false);
        $this->submitting = StateRef::to($base . '.submitting', false);
        $this->submitted = StateRef::to($base . '.submitted', false);
        $this->error = StateRef::to($base . '.error', '');
    }

    public function action(ClientInstruction|Closure $run): FormSubmitAction
    {
        return new FormSubmitAction(
            $run instanceof Closure ? FrontendClosureCompiler::compile($run) : $run,
            $this,
        );
    }

    public function reset(): FormResetAction
    {
        return new FormResetAction($this);
    }

    /** @return array<string, string> */
    public function manifest(): array
    {
        return [
            'dirty' => $this->dirty->name,
            'touched' => $this->touched->name,
            'valid' => $this->valid->name,
            'invalid' => $this->invalid->name,
            'submitting' => $this->submitting->name,
            'submitted' => $this->submitted->name,
            'error' => $this->error->name,
        ];
    }
}
