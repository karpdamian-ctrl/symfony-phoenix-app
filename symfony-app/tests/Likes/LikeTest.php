<?php

declare(strict_types=1);

namespace App\Tests\Likes;

use App\Entity\Like;
use App\Entity\Photo;
use App\Entity\User;
use PHPUnit\Framework\TestCase;

final class LikeTest extends TestCase
{
    public function testLikeHasNullIdAndCreatedAtByDefault(): void
    {
        $like = new Like();

        self::assertNull($like->getId());
        self::assertInstanceOf(\DateTimeInterface::class, $like->getCreatedAt());
    }

    public function testLikeStoresUserPhotoAndCreatedAt(): void
    {
        $user = (new User())
            ->setUsername('like_user')
            ->setEmail('like@example.com');

        $photo = (new Photo())
            ->setImageUrl('https://example.com/photo.jpg')
            ->setDescription('Like test photo')
            ->setUser($user);

        $createdAt = new \DateTimeImmutable('2026-04-08 12:00:00');

        $like = new Like();

        self::assertSame($like, $like->setUser($user));
        self::assertSame($like, $like->setPhoto($photo));
        self::assertSame($like, $like->setCreatedAt($createdAt));
        self::assertSame($user, $like->getUser());
        self::assertSame($photo, $like->getPhoto());
        self::assertSame($createdAt, $like->getCreatedAt());
    }
}
