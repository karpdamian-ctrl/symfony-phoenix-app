<?php

declare(strict_types=1);

namespace App\Tests\Integration\Photo\Service;

use App\Entity\Photo;
use App\Entity\User;
use App\Photo\Service\PhoenixPhotoImportService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class PhoenixPhotoImportServiceIntegrationTest extends KernelTestCase
{
    private EntityManagerInterface $entityManager;
    private HttpClientInterface $httpClient;

    protected function setUp(): void
    {
        self::bootKernel();

        $container = static::getContainer();
        $this->entityManager = $container->get(EntityManagerInterface::class);
        $this->httpClient = $container->get(HttpClientInterface::class);

        $this->skipUnlessIntegrationEnabled();
        $this->skipUnlessPhoenixIsAvailable();

        $connection = $this->entityManager->getConnection();
        $platform = $connection->getDatabasePlatform();

        $connection->executeStatement('SET session_replication_role = replica');
        $connection->executeStatement($platform->getTruncateTableSQL('likes', true));
        $connection->executeStatement($platform->getTruncateTableSQL('photos', true));
        $connection->executeStatement($platform->getTruncateTableSQL('auth_tokens', true));
        $connection->executeStatement($platform->getTruncateTableSQL('users', true));
        $connection->executeStatement('SET session_replication_role = DEFAULT');
    }

    public function testImportsPhotosFromLivePhoenixServer(): void
    {
        $user = (new User())
            ->setUsername('integration_user')
            ->setEmail('integration@example.com')
            ->setPhoenixApiToken('test_token_user1_abc123');

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        $service = new PhoenixPhotoImportService(
            $this->httpClient,
            $this->entityManager,
            $this->resolvePhoenixBaseUrl()
        );

        $result = $service->import($user);

        self::assertFalse($result->isInvalidToken());
        self::assertSame(3, $result->getImportedCount());

        $this->entityManager->clear();
        $reloadedUser = $this->entityManager->getRepository(User::class)->find($user->getId());
        $photos = $this->entityManager->getRepository(Photo::class)->findBy([
            'user' => $reloadedUser,
        ], ['id' => 'ASC']);

        self::assertCount(3, $photos);
        self::assertSame(
            'https://images.unsplash.com/photo-1506905925346-21bda4d32df4',
            $photos[0]->getImageUrl()
        );
        self::assertSame('Rocky Mountains, Colorado', $photos[0]->getLocation());
        self::assertSame('Canon EOS R5', $photos[0]->getCamera());
        self::assertSame(
            'Mountain landscape at sunrise with beautiful golden hour lighting',
            $photos[0]->getDescription()
        );
        self::assertSame('2024-06-15T06:30:00+00:00', $photos[0]->getTakenAt()?->format(\DateTimeInterface::ATOM));
    }

    private function skipUnlessIntegrationEnabled(): void
    {
        if (getenv('RUN_PHOENIX_INTEGRATION') !== '1') {
            self::markTestSkipped(
                'Set RUN_PHOENIX_INTEGRATION=1 to run integration tests against a live Phoenix server.'
            );
        }
    }

    private function skipUnlessPhoenixIsAvailable(): void
    {
        try {
            $response = $this->httpClient->request('GET', $this->resolvePhoenixBaseUrl() . '/api/photos', [
                'headers' => [
                    'access-token' => 'test_token_user1_abc123',
                ],
                'timeout' => 2,
            ]);

            if ($response->getStatusCode() !== 200) {
                self::markTestSkipped('Live Phoenix server is available, but seed data/token is missing or invalid.');
            }
        } catch (ExceptionInterface|\Throwable) {
            self::markTestSkipped('Live Phoenix server is not reachable for integration tests.');
        }
    }

    private function resolvePhoenixBaseUrl(): string
    {
        $baseUrl = getenv('PHOENIX_BASE_URL') ?: 'http://localhost:4000';

        return rtrim($baseUrl, '/');
    }
}
