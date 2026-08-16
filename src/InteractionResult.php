<?php

declare(strict_types=1);

namespace AML\View;

final class InteractionResult
{
    /** @param list<string> $events */
    public function __construct(
        private string $html,
        private string $token,
        private array $events,
    ) {
    }

    public function html(): string
    {
        return $this->html;
    }

    public function token(): string
    {
        return $this->token;
    }

    /** @return list<string> */
    public function events(): array
    {
        return $this->events;
    }

    public function rootHtml(): string
    {
        $token = htmlspecialchars($this->token, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        return '<div data-aml-root data-aml-token="' . $token . '">' . $this->html . '</div>';
    }

    /** @return array{html: string, token: string} */
    public function json(): array
    {
        return ['html' => $this->html, 'token' => $this->token];
    }
}
