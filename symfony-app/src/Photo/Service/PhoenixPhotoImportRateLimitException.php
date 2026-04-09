<?php

declare(strict_types=1);

namespace App\Photo\Service;

final class PhoenixPhotoImportRateLimitException extends \RuntimeException
{
    public function __construct(
        private readonly string $translationKey,
        string $message = 'Phoenix API import rate limit exceeded.'
    ) {
        parent::__construct($message);
    }

    public function getTranslationKey(): string
    {
        return $this->translationKey;
    }
}
