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
    private const DEFAULT_PHOENIX_DATABASE_URL = 'postgres://postgres:postgres@phoenix-db:5432/phoenix_api';

    private EntityManagerInterface $entityManager;
    private HttpClientInterface $httpClient;
    private string $phoenixApiToken;

    protected function setUp(): void
    {
        self::bootKernel();

        $container = static::getContainer();
        $this->entityManager = $container->get(EntityManagerInterface::class);
        $this->httpClient = $container->get(HttpClientInterface::class);

        $this->skipUnlessIntegrationEnabled();
        $this->phoenixApiToken = $this->resolvePhoenixApiToken();
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
            ->setPhoenixApiToken($this->phoenixApiToken);

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

    public function testReturnsInvalidTokenForLivePhoenixServerWhenTokenIsWrong(): void
    {
        $user = (new User())
            ->setUsername('integration_invalid_token_user')
            ->setEmail('integration-invalid@example.com')
            ->setPhoenixApiToken('invalid-live-token');

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        $service = new PhoenixPhotoImportService(
            $this->httpClient,
            $this->entityManager,
            $this->resolvePhoenixBaseUrl()
        );

        $result = $service->import($user);

        self::assertTrue($result->isInvalidToken());
        self::assertSame(0, $result->getImportedCount());

        $this->entityManager->clear();
        $reloadedUser = $this->entityManager->getRepository(User::class)->find($user->getId());
        $photos = $this->entityManager->getRepository(Photo::class)->findBy([
            'user' => $reloadedUser,
        ]);

        self::assertCount(0, $photos);
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
                    'access-token' => $this->phoenixApiToken,
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

    private function resolvePhoenixApiToken(): string
    {
        $databaseUrl = \getenv('PHOENIX_DATABASE_URL') ?: self::DEFAULT_PHOENIX_DATABASE_URL;
        $parsedUrl = \parse_url($databaseUrl);

        if ($parsedUrl === false) {
            self::markTestSkipped('Invalid PHOENIX_DATABASE_URL format.');
        }

        $host = $parsedUrl['host'] ?? null;
        $port = $parsedUrl['port'] ?? 5432;
        $user = $parsedUrl['user'] ?? null;
        $password = $parsedUrl['pass'] ?? null;
        $databaseName = isset($parsedUrl['path']) ? \ltrim($parsedUrl['path'], '/') : null;

        if (
            !\is_string($host) || $host === ''
            || !\is_string($user) || $user === ''
            || !\is_string($databaseName) || $databaseName === ''
        ) {
            self::markTestSkipped('Incomplete PHOENIX_DATABASE_URL for resolving integration token.');
        }

        try {
            $pdo = new \PDO(
                \sprintf('pgsql:host=%s;port=%d;dbname=%s', $host, $port, $databaseName),
                $user,
                $password ?: ''
            );
            $statement = $pdo->query('SELECT api_token FROM users ORDER BY id ASC LIMIT 1');
            $token = $statement?->fetchColumn();

            if (!\is_string($token) || $token === '') {
                self::markTestSkipped('No Phoenix API token found in users table.');
            }

            return $token;
        } catch (\Throwable) {
            self::markTestSkipped('Phoenix database is not reachable for integration token lookup.');
        }

        throw new \RuntimeException('Unreachable state while resolving Phoenix API token.');
    }
}
