<?php

declare(strict_types=1);

namespace AML\View;

final class FileNonceStore implements NonceStore
{
    public function __construct(private string $directory)
    {
    }

    public function consume(string $nonce, int $expiresAt): bool
    {
        if (!is_dir($this->directory) && !mkdir($this->directory, 0700, true) && !is_dir($this->directory)) {
            throw new \RuntimeException('AML View cannot create its interaction nonce directory.');
        }

        $path = $this->directory . DIRECTORY_SEPARATOR . hash('sha256', $nonce);
        $handle = @fopen($path, 'x');
        if ($handle === false) {
            return false;
        }

        fwrite($handle, (string) $expiresAt);
        fclose($handle);
        @chmod($path, 0600);
        $this->removeExpired(time());
        return true;
    }

    private function removeExpired(int $now): void
    {
        if (random_int(1, 100) !== 1) {
            return;
        }

        foreach (glob($this->directory . DIRECTORY_SEPARATOR . '*') ?: [] as $path) {
            $expiresAt = (int) @file_get_contents($path);
            if ($expiresAt > 0 && $expiresAt < $now) {
                @unlink($path);
            }
        }
    }
}
