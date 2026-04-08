<?php

declare(strict_types=1);

namespace App\Photo\Repository;

use App\Entity\Photo;

interface PhotoRepositoryInterface
{
    /**
     * @param array<string, string|null> $filters
     * @return array<int, Photo>
     */
    public function findAllWithUsers(array $filters = []): array;
}
