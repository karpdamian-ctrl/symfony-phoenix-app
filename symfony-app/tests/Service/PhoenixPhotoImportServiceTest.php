<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\Photo;
use App\Entity\User;
use App\Photo\Service\PhoenixPhotoImportRateLimitException;
use App\Photo\Service\PhoenixPhotoImportService;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ObjectRepository;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class PhoenixPhotoImportServiceTest extends TestCase
{
    public function testImportReturnsInvalidTokenWhenUserHasNoToken(): void
    {
        $user = (new User())
            ->setUsername('profile_user')
            ->setEmail('profile@example.com');

        $httpClient = new MockHttpClient([]);
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::never())->method('getRepository');

        $service = new PhoenixPhotoImportService($httpClient, $entityManager, 'http://phoenix.test');

        $result = $service->import($user);

        self::assertTrue($result->isInvalidToken());
        self::assertSame(0, $result->getImportedCount());
    }

    public function testImportReturnsInvalidTokenWhenPhoenixRejectsToken(): void
    {
        $user = (new User())
            ->setUsername('profile_user')
            ->setEmail('profile@example.com')
            ->setPhoenixApiToken('invalid-token');

        $httpClient = new MockHttpClient([
            new MockResponse('', ['http_code' => 401]),
        ]);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::never())->method('persist');
        $entityManager->expects(self::never())->method('flush');

        $service = new PhoenixPhotoImportService($httpClient, $entityManager, 'http://phoenix.test');

        $result = $service->import($user);

        self::assertTrue($result->isInvalidToken());
        self::assertSame(0, $result->getImportedCount());
    }

    public function testImportPersistsOnlyNewPhotos(): void
    {
        $user = (new User())
            ->setUsername('profile_user')
            ->setEmail('profile@example.com')
            ->setPhoenixApiToken('valid-token');

        $existingPhoto = (new Photo())
            ->setUser($user)
            ->setImageUrl('https://partner.example/existing.jpg');

        $repository = $this->createMock(ObjectRepository::class);
        $repository
            ->expects(self::exactly(2))
            ->method('findOneBy')
            ->willReturnCallback(static function (array $criteria) use ($existingPhoto): ?Photo {
                return $criteria['imageUrl'] === 'https://partner.example/existing.jpg' ? $existingPhoto : null;
            });

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager
            ->expects(self::once())
            ->method('getRepository')
            ->with(Photo::class)
            ->willReturn($repository);
        $entityManager
            ->expects(self::once())
            ->method('persist')
            ->with(self::callback(static function (Photo $photo) use ($user): bool {
                return $photo->getUser() === $user
                    && $photo->getImageUrl() === 'https://partner.example/new.jpg'
                    && $photo->getDescription() === 'Imported from Phoenix'
                    && $photo->getLocation() === 'Warsaw'
                    && $photo->getCamera() === 'Canon EOS R5'
                    && $photo->getTakenAt()?->format(\DateTimeInterface::ATOM) === '2026-04-08T20:15:00+00:00';
            }));
        $entityManager
            ->expects(self::once())
            ->method('flush');

        $httpClient = new MockHttpClient(static function (string $method, string $url, array $options): MockResponse {
            TestCase::assertSame('GET', $method);
            TestCase::assertSame(
                'http://phoenix.test/api/photos?fields=camera,location,description,taken_at',
                $url
            );
            TestCase::assertContains('access-token: valid-token', $options['normalized_headers']['access-token'] ?? []);

            return new MockResponse(json_encode([
                'photos' => [
                    [
                        'id' => 1,
                        'photo_url' => 'https://partner.example/new.jpg',
                        'description' => 'Imported from Phoenix',
                        'location' => 'Warsaw',
                        'camera' => 'Canon EOS R5',
                        'taken_at' => '2026-04-08T20:15:00Z',
                    ],
                    ['id' => 2, 'photo_url' => 'https://partner.example/existing.jpg'],
                ],
            ], JSON_THROW_ON_ERROR), ['http_code' => 200]);
        });

        $service = new PhoenixPhotoImportService($httpClient, $entityManager, 'http://phoenix.test');

        $result = $service->import($user);

        self::assertFalse($result->isInvalidToken());
        self::assertSame(1, $result->getImportedCount());
    }

    public function testImportThrowsRuntimeExceptionWhenHttpClientFails(): void
    {
        $user = (new User())
            ->setUsername('profile_user')
            ->setEmail('profile@example.com')
            ->setPhoenixApiToken('valid-token');

        $httpClient = $this->createMock(HttpClientInterface::class);
        $httpClient
            ->expects(self::once())
            ->method('request')
            ->willThrowException(new class ('HTTP failure') extends \RuntimeException implements ExceptionInterface {
            });

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::never())->method('getRepository');
        $entityManager->expects(self::never())->method('persist');
        $entityManager->expects(self::never())->method('flush');

        $service = new PhoenixPhotoImportService($httpClient, $entityManager, 'http://phoenix.test');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Failed to fetch photos from Phoenix API.');

        $service->import($user);
    }

    public function testImportThrowsRuntimeExceptionWhenPhoenixReturnsUnexpectedStatus(): void
    {
        $user = (new User())
            ->setUsername('profile_user')
            ->setEmail('profile@example.com')
            ->setPhoenixApiToken('valid-token');

        $httpClient = new MockHttpClient([
            new MockResponse('', ['http_code' => 500]),
        ]);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::never())->method('getRepository');
        $entityManager->expects(self::never())->method('persist');
        $entityManager->expects(self::never())->method('flush');

        $service = new PhoenixPhotoImportService($httpClient, $entityManager, 'http://phoenix.test');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Unexpected Phoenix API status: 500');

        $service->import($user);
    }

    public function testImportThrowsUserRateLimitExceptionWhenPhoenixReturnsUserRateLimit(): void
    {
        $user = (new User())
            ->setUsername('profile_user')
            ->setEmail('profile@example.com')
            ->setPhoenixApiToken('valid-token');

        $httpClient = new MockHttpClient([
            new MockResponse(
                json_encode(['errors' => ['detail' => 'Photo import user rate limit exceeded']], JSON_THROW_ON_ERROR),
                ['http_code' => 429]
            ),
        ]);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::never())->method('getRepository');
        $entityManager->expects(self::never())->method('persist');
        $entityManager->expects(self::never())->method('flush');

        $service = new PhoenixPhotoImportService($httpClient, $entityManager, 'http://phoenix.test');

        try {
            $service->import($user);
            self::fail('Expected PhoenixPhotoImportRateLimitException to be thrown.');
        } catch (PhoenixPhotoImportRateLimitException $exception) {
            self::assertSame('profile.import.rate_limited_user', $exception->getTranslationKey());
        }
    }

    public function testImportThrowsGlobalRateLimitExceptionWhenPhoenixReturnsGlobalRateLimit(): void
    {
        $user = (new User())
            ->setUsername('profile_user')
            ->setEmail('profile@example.com')
            ->setPhoenixApiToken('valid-token');

        $httpClient = new MockHttpClient([
            new MockResponse(
                json_encode(['errors' => ['detail' => 'Photo import global rate limit exceeded']], JSON_THROW_ON_ERROR),
                ['http_code' => 429]
            ),
        ]);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::never())->method('getRepository');
        $entityManager->expects(self::never())->method('persist');
        $entityManager->expects(self::never())->method('flush');

        $service = new PhoenixPhotoImportService($httpClient, $entityManager, 'http://phoenix.test');

        try {
            $service->import($user);
            self::fail('Expected PhoenixPhotoImportRateLimitException to be thrown.');
        } catch (PhoenixPhotoImportRateLimitException $exception) {
            self::assertSame('profile.import.rate_limited_global', $exception->getTranslationKey());
        }
    }

    public function testImportSkipsInvalidPayloadItemsAndDoesNotFlushWhenNothingNewWasImported(): void
    {
        $user = (new User())
            ->setUsername('profile_user')
            ->setEmail('profile@example.com')
            ->setPhoenixApiToken('valid-token');

        $repository = $this->createMock(ObjectRepository::class);
        $repository
            ->expects(self::once())
            ->method('findOneBy')
            ->with([
                'user' => $user,
                'imageUrl' => 'https://partner.example/existing.jpg',
            ])
            ->willReturn(new Photo());

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager
            ->expects(self::once())
            ->method('getRepository')
            ->with(Photo::class)
            ->willReturn($repository);
        $entityManager->expects(self::never())->method('persist');
        $entityManager->expects(self::never())->method('flush');

        $httpClient = new MockHttpClient([
            new MockResponse(json_encode([
                'photos' => [
                    ['id' => 1],
                    ['id' => 2, 'photo_url' => ''],
                    ['id' => 3, 'photo_url' => 'https://partner.example/existing.jpg'],
                ],
            ], JSON_THROW_ON_ERROR), ['http_code' => 200]),
        ]);

        $service = new PhoenixPhotoImportService($httpClient, $entityManager, 'http://phoenix.test');

        $result = $service->import($user);

        self::assertFalse($result->isInvalidToken());
        self::assertSame(0, $result->getImportedCount());
    }

    public function testImportFallsBackForInvalidOptionalFields(): void
    {
        $user = (new User())
            ->setUsername('profile_user')
            ->setEmail('profile@example.com')
            ->setPhoenixApiToken('valid-token');

        $repository = $this->createMock(ObjectRepository::class);
        $repository
            ->expects(self::once())
            ->method('findOneBy')
            ->with([
                'user' => $user,
                'imageUrl' => 'https://partner.example/new.jpg',
            ])
            ->willReturn(null);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager
            ->expects(self::once())
            ->method('getRepository')
            ->with(Photo::class)
            ->willReturn($repository);
        $entityManager
            ->expects(self::once())
            ->method('persist')
            ->with(self::callback(static function (Photo $photo) use ($user): bool {
                return $photo->getUser() === $user
                    && $photo->getImageUrl() === 'https://partner.example/new.jpg'
                    && $photo->getDescription() === 'Imported from Phoenix API'
                    && $photo->getLocation() === null
                    && $photo->getCamera() === null
                    && $photo->getTakenAt() === null;
            }));
        $entityManager->expects(self::once())->method('flush');

        $httpClient = new MockHttpClient([
            new MockResponse(json_encode([
                'photos' => [
                    [
                        'photo_url' => 'https://partner.example/new.jpg',
                        'description' => '',
                        'location' => '',
                        'camera' => '',
                        'taken_at' => 'not-a-date',
                    ],
                ],
            ], JSON_THROW_ON_ERROR), ['http_code' => 200]),
        ]);

        $service = new PhoenixPhotoImportService($httpClient, $entityManager, 'http://phoenix.test');

        $result = $service->import($user);

        self::assertSame(1, $result->getImportedCount());
        self::assertFalse($result->isInvalidToken());
    }
}
