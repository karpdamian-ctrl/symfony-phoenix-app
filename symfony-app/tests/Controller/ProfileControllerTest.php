<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\Photo;
use App\Entity\User;
use App\Photo\Service\PhoenixPhotoImportResult;
use App\Photo\Service\PhoenixPhotoImportServiceInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\BrowserKit\Cookie;

final class ProfileControllerTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        self::ensureKernelShutdown();
        $this->client = static::createClient();
        $this->client->disableReboot();
        $this->entityManager = $this->client->getContainer()->get('doctrine')->getManager();

        $connection = $this->entityManager->getConnection();
        $platform = $connection->getDatabasePlatform();

        $connection->executeStatement('SET session_replication_role = replica');
        $connection->executeStatement($platform->getTruncateTableSQL('likes', true));
        $connection->executeStatement($platform->getTruncateTableSQL('photos', true));
        $connection->executeStatement($platform->getTruncateTableSQL('auth_tokens', true));
        $connection->executeStatement($platform->getTruncateTableSQL('users', true));
        $connection->executeStatement('SET session_replication_role = DEFAULT');
    }

    public function testGuestIsRedirectedToHome(): void
    {
        $this->client->request('GET', '/profile');

        self::assertResponseRedirects('/');
    }

    public function testLoggedInUserSeesOwnProfileData(): void
    {
        $user = $this->createUserWithProfile();
        $this->logInUser($user);

        $this->client->request('GET', '/profile');

        self::assertResponseIsSuccessful();
        $content = (string) $this->client->getResponse()->getContent();

        self::assertStringContainsString('John Doe', $content);
        self::assertStringContainsString('@profile_user', $content);
        self::assertStringContainsString('profile@example.com', $content);
        self::assertStringContainsString('31 years old', $content);
        self::assertStringContainsString('About Me', $content);
        self::assertStringContainsString('Landscape photographer', $content);
    }

    public function testProfileShowsPhotoCount(): void
    {
        $user = $this->createUserWithProfile();
        $this->createPhotoForUser($user, 'First photo');
        $this->createPhotoForUser($user, 'Second photo');
        $this->entityManager->clear();

        $this->logInUser($user);

        $crawler = $this->client->request('GET', '/profile');

        self::assertResponseIsSuccessful();
        self::assertStringContainsString('2', trim((string) $crawler->filter('.stat-number')->text()));
        self::assertStringContainsString('Photos', (string) $this->client->getResponse()->getContent());
    }

    public function testUserCanSavePhoenixApiToken(): void
    {
        $user = $this->createUserWithProfile();
        $this->logInUser($user);

        $crawler = $this->client->request('GET', '/profile');
        $csrfToken = (string) $crawler
            ->filter('form[action="/profile/phoenix-token"] input[name="_token"]')
            ->attr('value');

        $this->client->request('POST', '/profile/phoenix-token', [
            '_token' => $csrfToken,
            'phoenix_api_token' => 'phoenix-test-token',
        ]);

        self::assertResponseRedirects('/profile');

        $this->entityManager->clear();
        $updatedUser = $this->entityManager->getRepository(User::class)->find($user->getId());

        self::assertInstanceOf(User::class, $updatedUser);
        self::assertSame('phoenix-test-token', $updatedUser->getPhoenixApiToken());
    }

    public function testUserSeesValidationMessageWhenPhoenixApiTokenIsTooLong(): void
    {
        $user = $this->createUserWithProfile();
        $this->logInUser($user);

        $crawler = $this->client->request('GET', '/profile');
        $csrfToken = (string) $crawler
            ->filter('form[action="/profile/phoenix-token"] input[name="_token"]')
            ->attr('value');

        $this->client->request('POST', '/profile/phoenix-token', [
            '_token' => $csrfToken,
            'phoenix_api_token' => str_repeat('a', 256),
        ]);

        self::assertResponseRedirects('/profile');
        $this->client->followRedirect();

        self::assertResponseIsSuccessful();
        self::assertStringContainsString('Phoenix API token is too long.', (string) $this->client->getResponse()->getContent());

        $this->entityManager->clear();
        $updatedUser = $this->entityManager->getRepository(User::class)->find($user->getId());

        self::assertInstanceOf(User::class, $updatedUser);
        self::assertNull($updatedUser->getPhoenixApiToken());
    }

    public function testUserSeesValidationMessageWhenPhoenixApiTokenIsEmpty(): void
    {
        $user = $this->createUserWithProfile()->setPhoenixApiToken('existing-token');
        $this->entityManager->flush();
        $this->logInUser($user);

        $crawler = $this->client->request('GET', '/profile');
        $csrfToken = (string) $crawler
            ->filter('form[action="/profile/phoenix-token"] input[name="_token"]')
            ->attr('value');

        $this->client->request('POST', '/profile/phoenix-token', [
            '_token' => $csrfToken,
            'phoenix_api_token' => '   ',
        ]);

        self::assertResponseRedirects('/profile');
        $this->client->followRedirect();

        self::assertResponseIsSuccessful();
        self::assertStringContainsString('Phoenix API token cannot be empty.', (string) $this->client->getResponse()->getContent());

        $this->entityManager->clear();
        $updatedUser = $this->entityManager->getRepository(User::class)->find($user->getId());

        self::assertInstanceOf(User::class, $updatedUser);
        self::assertSame('existing-token', $updatedUser->getPhoenixApiToken());
    }

    public function testImportPhotosRequiresSavedToken(): void
    {
        $user = $this->createUserWithProfile();
        $this->logInUser($user);

        $this->replaceImportService(new class () implements PhoenixPhotoImportServiceInterface {
            public function import(User $user): PhoenixPhotoImportResult
            {
                throw new \LogicException('Import service should not be called when token is missing.');
            }
        });

        $crawler = $this->client->request('GET', '/profile');
        $csrfToken = (string) $crawler
            ->filter('form[action="/profile/import-photos"] input[name="_token"]')
            ->attr('value');

        $this->client->request('POST', '/profile/import-photos', [
            '_token' => $csrfToken,
        ]);

        self::assertResponseRedirects('/profile');
        $this->client->followRedirect();

        self::assertResponseIsSuccessful();
        self::assertStringContainsString('Provide and save a Phoenix API token before importing photos.', (string) $this->client->getResponse()->getContent());
    }

    public function testImportPhotosShowsInvalidTokenMessage(): void
    {
        $user = $this->createUserWithProfile()->setPhoenixApiToken('invalid-token');
        $this->entityManager->flush();
        $this->logInUser($user);

        $this->replaceImportService(new class () implements PhoenixPhotoImportServiceInterface {
            public function import(User $user): PhoenixPhotoImportResult
            {
                return PhoenixPhotoImportResult::invalidToken();
            }
        });

        $crawler = $this->client->request('GET', '/profile');
        $csrfToken = (string) $crawler
            ->filter('form[action="/profile/import-photos"] input[name="_token"]')
            ->attr('value');

        $this->client->request('POST', '/profile/import-photos', [
            '_token' => $csrfToken,
        ]);

        self::assertResponseRedirects('/profile');
        $this->client->followRedirect();

        self::assertResponseIsSuccessful();
        self::assertStringContainsString('Invalid Phoenix API token.', (string) $this->client->getResponse()->getContent());
    }

    public function testImportPhotosShowsGenericFailureMessageWhenImportFails(): void
    {
        $user = $this->createUserWithProfile()->setPhoenixApiToken('valid-token');
        $this->entityManager->flush();
        $this->logInUser($user);

        $this->replaceImportService(new class () implements PhoenixPhotoImportServiceInterface {
            public function import(User $user): PhoenixPhotoImportResult
            {
                throw new \RuntimeException('Phoenix is unavailable.');
            }
        });

        $crawler = $this->client->request('GET', '/profile');
        $csrfToken = (string) $crawler
            ->filter('form[action="/profile/import-photos"] input[name="_token"]')
            ->attr('value');

        $this->client->request('POST', '/profile/import-photos', [
            '_token' => $csrfToken,
        ]);

        self::assertResponseRedirects('/profile');
        $this->client->followRedirect();

        self::assertResponseIsSuccessful();
        self::assertStringContainsString('Photos could not be imported right now.', (string) $this->client->getResponse()->getContent());
    }

    public function testImportPhotosShowsRateLimitMessageWhenPhoenixLimitIsReached(): void
    {
        $user = $this->createUserWithProfile()->setPhoenixApiToken('valid-token');
        $this->entityManager->flush();
        $this->logInUser($user);

        $this->replaceImportService(new class () implements PhoenixPhotoImportServiceInterface {
            public function import(User $user): PhoenixPhotoImportResult
            {
                throw new \App\Photo\Service\PhoenixPhotoImportRateLimitException(
                    'profile.import.rate_limited_user',
                    'Rate limited.'
                );
            }
        });

        $crawler = $this->client->request('GET', '/profile');
        $csrfToken = (string) $crawler
            ->filter('form[action="/profile/import-photos"] input[name="_token"]')
            ->attr('value');

        $this->client->request('POST', '/profile/import-photos', [
            '_token' => $csrfToken,
        ]);

        self::assertResponseRedirects('/profile');
        $this->client->followRedirect();

        self::assertResponseIsSuccessful();
        self::assertStringContainsString('Import limit reached for your account. Try again in 10 minutes.', (string) $this->client->getResponse()->getContent());
    }

    public function testImportPhotosShowsSuccessMessage(): void
    {
        $user = $this->createUserWithProfile()->setPhoenixApiToken('valid-token');
        $this->entityManager->flush();
        $this->logInUser($user);

        $this->replaceImportService(new class () implements PhoenixPhotoImportServiceInterface {
            public function import(User $user): PhoenixPhotoImportResult
            {
                return PhoenixPhotoImportResult::success(1);
            }
        });

        $crawler = $this->client->request('GET', '/profile');
        $csrfToken = (string) $crawler
            ->filter('form[action="/profile/import-photos"] input[name="_token"]')
            ->attr('value');

        $this->client->request('POST', '/profile/import-photos', [
            '_token' => $csrfToken,
        ]);

        self::assertResponseRedirects('/profile');
        $this->client->followRedirect();

        self::assertResponseIsSuccessful();
        self::assertStringContainsString('1 photos imported from Phoenix API.', (string) $this->client->getResponse()->getContent());
    }

    private function createUserWithProfile(): User
    {
        $user = new User();
        $user
            ->setUsername('profile_user')
            ->setEmail('profile@example.com')
            ->setName('John')
            ->setLastName('Doe')
            ->setAge(31)
            ->setBio('Landscape photographer');

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        return $user;
    }

    private function createPhotoForUser(User $user, string $description): void
    {
        $photo = new Photo();
        $photo
            ->setImageUrl('https://example.com/' . md5($description) . '.jpg')
            ->setDescription($description)
            ->setUser($user);

        $this->entityManager->persist($photo);
        $this->entityManager->flush();
    }

    private function replaceImportService(PhoenixPhotoImportServiceInterface $service): void
    {
        static::getContainer()->set(PhoenixPhotoImportServiceInterface::class, $service);
    }

    private function logInUser(User $user): void
    {
        $session = $this->client->getContainer()->get('session.factory')->createSession();
        $session->start();
        $session->set('user_id', $user->getId());
        $session->set('username', $user->getUsername());
        $session->save();

        $this->client->getCookieJar()->set(new Cookie($session->getName(), $session->getId()));
    }
}
