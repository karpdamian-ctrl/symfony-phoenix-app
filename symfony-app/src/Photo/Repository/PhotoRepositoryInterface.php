<?php

declare(strict_types=1);

namespace App\Photo\Repository;

use App\Entity\Photo;

interface PhotoRepositoryInterface
{
    /**
     * @return array<int, Photo>
     */
    public function findAllWithUsers(array $filters = []): array;
}
