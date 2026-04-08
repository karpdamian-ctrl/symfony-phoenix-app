<?php

declare(strict_types=1);

namespace App\Likes;

use App\Entity\Photo;
use App\Entity\User;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;

class LikeService
{
    public function __construct(
        private LikeRepositoryInterface $likeRepository
    ) {
    }

    public function execute(User $user, Photo $photo): void
    {
        try {
            $this->likeRepository->createLike($user, $photo);
            $this->likeRepository->updatePhotoCounter($photo, 1);
        } catch (UniqueConstraintViolationException $e) {
            throw new DuplicateLikeException('Photo already liked.', 0, $e);
        } catch (\Throwable $e) {
            throw new \Exception('Something went wrong while liking the photo');
        }
    }
}
