<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\Photo;
use App\Entity\User;
use App\Likes\DuplicateLikeException;
use App\Likes\LikeRepositoryInterface;
use App\Photo\Service\PhotoReactionService;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ObjectRepository;
use PHPUnit\Framework\TestCase;

final class PhotoReactionServiceTest extends TestCase
{
    public function testLikeReturnsNotFoundWhenPhotoDoesNotExist(): void
    {
        $photoRepository = $this->createMock(ObjectRepository::class);
        $photoRepository
            ->expects(self::once())
            ->method('find')
            ->with(99)
            ->willReturn(null);

        $entityManager = $this->mockEntityManagerForPhotoRepository($photoRepository);
        $likeRepository = $this->createMock(LikeRepositoryInterface::class);
        $service = new PhotoReactionService($entityManager, $likeRepository);

        $result = $service->like($this->createUser(), 99);

        self::assertTrue($result->isNotFound());
        self::assertSame('error', $result->getStatus());
        self::assertSame('photo.reaction.not_found', $result->getMessageKey());
    }

    public function testLikeReturnsAlreadyLikedWhenUserAlreadyLikedPhoto(): void
    {
        $photo = $this->createPhoto(10);
        $user = $this->createUser();
        $photoRepository = $this->createMock(ObjectRepository::class);
        $photoRepository->method('find')->with(10)->willReturn($photo);

        $entityManager = $this->mockEntityManagerForPhotoRepository($photoRepository);
        $likeRepository = $this->createMock(LikeRepositoryInterface::class);
        $likeRepository
            ->expects(self::once())
            ->method('hasUserLikedPhoto')
            ->with($user, $photo)
            ->willReturn(true);

        $likeRepository->expects(self::never())->method('createLike');
        $likeRepository->expects(self::never())->method('updatePhotoCounter');

        $service = new PhotoReactionService($entityManager, $likeRepository);

        $result = $service->like($user, 10);

        self::assertFalse($result->isNotFound());
        self::assertSame('noop', $result->getStatus());
        self::assertSame('photo.reaction.already_liked', $result->getMessageKey());
        self::assertTrue($result->isLiked());
        self::assertSame($photo, $result->getPhoto());
    }

    public function testLikeReturnsLikedWhenRepositoryCreatesLike(): void
    {
        $photo = $this->createPhoto(12);
        $user = $this->createUser();
        $photo->setLikeCounter(1);

        $photoRepository = $this->createMock(ObjectRepository::class);
        $photoRepository->method('find')->with(12)->willReturn($photo);

        $entityManager = $this->mockEntityManagerForPhotoRepository($photoRepository);
        $likeRepository = $this->createMock(LikeRepositoryInterface::class);
        $likeRepository
            ->expects(self::once())
            ->method('hasUserLikedPhoto')
            ->with($user, $photo)
            ->willReturn(false);
        $likeRepository
            ->expects(self::once())
            ->method('createLike')
            ->with($user, $photo)
            ->willReturn($this->createMock(\App\Entity\Like::class));
        $likeRepository
            ->expects(self::once())
            ->method('updatePhotoCounter')
            ->with($photo, 1)
            ->willReturnCallback(static function (Photo $photo, int $increment): void {
                self::assertSame(1, $increment);
                $photo->setLikeCounter($photo->getLikeCounter() + 1);
            });

        $service = new PhotoReactionService($entityManager, $likeRepository);

        $result = $service->like($user, 12);

        self::assertSame('liked', $result->getStatus());
        self::assertSame('photo.reaction.liked', $result->getMessageKey());
        self::assertTrue($result->isLiked());
        self::assertSame(2, $result->getPhoto()?->getLikeCounter());
    }

    public function testLikeReturnsAlreadyLikedWhenDuplicateLikeExceptionIsThrown(): void
    {
        $photo = $this->createPhoto(13);
        $user = $this->createUser();

        $photoRepository = $this->createMock(ObjectRepository::class);
        $photoRepository->method('find')->with(13)->willReturn($photo);

        $entityManager = $this->mockEntityManagerForPhotoRepository($photoRepository);
        $likeRepository = $this->createMock(LikeRepositoryInterface::class);
        $likeRepository
            ->expects(self::once())
            ->method('hasUserLikedPhoto')
            ->willReturn(false);

        $likeRepository
            ->expects(self::once())
            ->method('createLike')
            ->willThrowException(new DuplicateLikeException());
        $likeRepository->expects(self::never())->method('updatePhotoCounter');

        $service = new PhotoReactionService($entityManager, $likeRepository);

        $result = $service->like($user, 13);

        self::assertSame('noop', $result->getStatus());
        self::assertSame('photo.reaction.already_liked', $result->getMessageKey());
    }

    public function testLikeReturnsAlreadyLikedWhenUniqueConstraintViolationIsThrown(): void
    {
        $photo = $this->createPhoto(14);
        $user = $this->createUser();

        $photoRepository = $this->createMock(ObjectRepository::class);
        $photoRepository->method('find')->with(14)->willReturn($photo);

        $entityManager = $this->mockEntityManagerForPhotoRepository($photoRepository);
        $likeRepository = $this->createMock(LikeRepositoryInterface::class);
        $likeRepository
            ->expects(self::once())
            ->method('hasUserLikedPhoto')
            ->willReturn(false);
        $likeRepository
            ->expects(self::once())
            ->method('createLike')
            ->willThrowException($this->createUniqueConstraintViolationException());
        $likeRepository->expects(self::never())->method('updatePhotoCounter');

        $service = new PhotoReactionService($entityManager, $likeRepository);

        $result = $service->like($user, 14);

        self::assertSame('noop', $result->getStatus());
        self::assertSame('photo.reaction.already_liked', $result->getMessageKey());
    }

    public function testUnlikeReturnsNotFoundWhenPhotoDoesNotExist(): void
    {
        $photoRepository = $this->createMock(ObjectRepository::class);
        $photoRepository
            ->expects(self::once())
            ->method('find')
            ->with(77)
            ->willReturn(null);

        $entityManager = $this->mockEntityManagerForPhotoRepository($photoRepository);
        $likeRepository = $this->createMock(LikeRepositoryInterface::class);
        $service = new PhotoReactionService($entityManager, $likeRepository);

        $result = $service->unlike($this->createUser(), 77);

        self::assertTrue($result->isNotFound());
        self::assertSame('photo.reaction.not_found', $result->getMessageKey());
    }

    public function testUnlikeReturnsNoopWhenPhotoIsNotLikedYet(): void
    {
        $photo = $this->createPhoto(20);
        $user = $this->createUser();
        $photoRepository = $this->createMock(ObjectRepository::class);
        $photoRepository->method('find')->with(20)->willReturn($photo);

        $entityManager = $this->mockEntityManagerForPhotoRepository($photoRepository);
        $likeRepository = $this->createMock(LikeRepositoryInterface::class);
        $likeRepository
            ->expects(self::once())
            ->method('hasUserLikedPhoto')
            ->with($user, $photo)
            ->willReturn(false);
        $likeRepository->expects(self::never())->method('unlikePhoto');

        $likeRepository->expects(self::never())->method('createLike');
        $likeRepository->expects(self::never())->method('updatePhotoCounter');
        $service = new PhotoReactionService($entityManager, $likeRepository);

        $result = $service->unlike($user, 20);

        self::assertSame('noop', $result->getStatus());
        self::assertSame('photo.reaction.not_liked_yet', $result->getMessageKey());
        self::assertFalse($result->isLiked());
    }

    public function testUnlikeReturnsUnlikedWhenRepositoryRemovesLike(): void
    {
        $photo = $this->createPhoto(21);
        $user = $this->createUser();
        $photo->setLikeCounter(2);

        $photoRepository = $this->createMock(ObjectRepository::class);
        $photoRepository->method('find')->with(21)->willReturn($photo);

        $entityManager = $this->mockEntityManagerForPhotoRepository($photoRepository);
        $likeRepository = $this->createMock(LikeRepositoryInterface::class);
        $likeRepository
            ->expects(self::once())
            ->method('hasUserLikedPhoto')
            ->with($user, $photo)
            ->willReturn(true);
        $likeRepository
            ->expects(self::once())
            ->method('unlikePhoto')
            ->with($user, $photo)
            ->willReturnCallback(static function (User $user, Photo $photo): void {
                $photo->setLikeCounter($photo->getLikeCounter() - 1);
            });

        $likeRepository->expects(self::never())->method('createLike');
        $likeRepository->expects(self::never())->method('updatePhotoCounter');
        $service = new PhotoReactionService($entityManager, $likeRepository);

        $result = $service->unlike($user, 21);

        self::assertSame('unliked', $result->getStatus());
        self::assertSame('photo.reaction.unliked', $result->getMessageKey());
        self::assertFalse($result->isLiked());
        self::assertSame(1, $result->getPhoto()?->getLikeCounter());
    }

    /**
     * @param ObjectRepository<Photo> $photoRepository
     */
    private function mockEntityManagerForPhotoRepository(ObjectRepository $photoRepository): EntityManagerInterface
    {
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager
            ->expects(self::once())
            ->method('getRepository')
            ->with(Photo::class)
            ->willReturn($photoRepository);

        return $entityManager;
    }

    private function createUser(): User
    {
        return (new User())
            ->setUsername('test_user')
            ->setEmail('test@example.com');
    }

    private function createPhoto(int $id): Photo
    {
        $photo = (new Photo())
            ->setImageUrl('https://example.com/photo.jpg')
            ->setDescription('Test photo')
            ->setUser($this->createUser());

        $reflectionProperty = new \ReflectionProperty(Photo::class, 'id');
        $reflectionProperty->setAccessible(true);
        $reflectionProperty->setValue($photo, $id);

        return $photo;
    }

    private function createUniqueConstraintViolationException(): UniqueConstraintViolationException
    {
        /** @var UniqueConstraintViolationException $exception */
        $exception = (new \ReflectionClass(UniqueConstraintViolationException::class))
            ->newInstanceWithoutConstructor();

        return $exception;
    }
}
