<?php

declare(strict_types=1);

namespace AML\View;

final readonly class RedirectView implements View
{
    public function __construct(private string $destination, private bool $replace = true)
    {
        if ($destination === '' || preg_match('/[\x00-\x1F\x7F]/', $destination) === 1) {
            throw new \InvalidArgumentException('Redirect destination must be a safe non-empty URL.');
        }
        if (preg_match('/^([a-z][a-z0-9+.-]*):/i', $destination, $match) === 1
            && !in_array(strtolower($match[1]), ['http', 'https'], true)) {
            throw new \InvalidArgumentException('Redirect only supports relative, HTTP, and HTTPS URLs.');
        }
    }

    public function render(RenderContext $context): string
    {
        $json = htmlspecialchars(json_encode(['destination' => $this->destination, 'replace' => $this->replace], JSON_THROW_ON_ERROR), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        return '<template data-aml-redirect="' . $json . '"></template>';
    }
}
