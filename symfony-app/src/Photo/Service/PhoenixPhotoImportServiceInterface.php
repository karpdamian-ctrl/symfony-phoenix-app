<?php

declare(strict_types=1);

namespace App\Photo\Service;

use App\Entity\User;

interface PhoenixPhotoImportServiceInterface
{
    public function import(User $user): PhoenixPhotoImportResult;
}
