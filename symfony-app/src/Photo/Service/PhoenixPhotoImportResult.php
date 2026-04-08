<?php

declare(strict_types=1);

namespace App\Photo\Service;

final class PhoenixPhotoImportResult
{
    private function __construct(
        private int $importedCount,
        private bool $invalidToken = false
    ) {
    }

    public static function success(int $importedCount): self
    {
        return new self($importedCount);
    }

    public static function invalidToken(): self
    {
        return new self(0, true);
    }

    public function getImportedCount(): int
    {
        return $this->importedCount;
    }

    public function isInvalidToken(): bool
    {
        return $this->invalidToken;
    }
}
