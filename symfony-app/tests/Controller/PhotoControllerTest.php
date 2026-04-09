<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\Like;
use App\Entity\Photo;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

final class PhotoControllerTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        self::ensureKernelShutdown();
        $this->client = static::createClient();
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

    public function testLikeLoggedIn(): void
    {
        [$user, $photo] = $this->createUserAndPhoto();

        $this->logInUser($this->client, $user->getId());
        $csrfToken = $this->fetchLikeCsrfToken($photo->getId());

        $this->client->request('POST', sprintf('/photo/%d/like', $photo->getId()), [], [], $this->jsonHeaders($csrfToken));

        self::assertResponseIsSuccessful();
        self::assertResponseFormatSame('json');
        self::assertSame([
            'status' => 'liked',
            'message' => 'Photo liked!',
            'photoId' => $photo->getId(),
            'liked' => true,
            'likeCounter' => 1,
        ], json_decode($this->client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR));
        self::assertSame(1, $this->countLikesForPhoto($photo->getId()));
        self::assertSame(1, $this->reloadPhoto($photo->getId())->getLikeCounter());
    }

    public function testLikeDuplicate(): void
    {
        [$user, $photo] = $this->createUserAndPhoto();

        $this->logInUser($this->client, $user->getId());
        $csrfToken = $this->fetchLikeCsrfToken($photo->getId());

        $this->client->request('POST', sprintf('/photo/%d/like', $photo->getId()), [], [], $this->jsonHeaders($csrfToken));
        $this->client->request('POST', sprintf('/photo/%d/like', $photo->getId()), [], [], $this->jsonHeaders($csrfToken));

        self::assertResponseIsSuccessful();
        self::assertResponseFormatSame('json');
        self::assertSame([
            'status' => 'noop',
            'message' => 'Photo already liked.',
            'photoId' => $photo->getId(),
            'liked' => true,
            'likeCounter' => 1,
        ], json_decode($this->client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR));
        self::assertSame(1, $this->countLikesForPhoto($photo->getId()));
        self::assertSame(1, $this->reloadPhoto($photo->getId())->getLikeCounter());
    }

    public function testLikeLoggedOut(): void
    {
        [, $photo] = $this->createUserAndPhoto();

        $this->client->request('POST', sprintf('/photo/%d/like', $photo->getId()));

        self::assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
        self::assertResponseFormatSame('json');
        self::assertSame([
            'status' => 'error',
            'message' => 'You must be logged in to like photos.',
        ], json_decode($this->client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR));
        self::assertSame(0, $this->countLikesForPhoto($photo->getId()));
        self::assertSame(0, $this->reloadPhoto($photo->getId())->getLikeCounter());
    }

    public function testUnlikeLoggedIn(): void
    {
        [$user, $photo] = $this->createUserAndPhoto();

        $this->logInUser($this->client, $user->getId());
        $csrfToken = $this->fetchLikeCsrfToken($photo->getId());

        $this->client->request('POST', sprintf('/photo/%d/like', $photo->getId()), [], [], $this->jsonHeaders($csrfToken));
        $this->client->request('POST', sprintf('/photo/%d/unlike', $photo->getId()), [], [], $this->jsonHeaders($csrfToken));

        self::assertResponseIsSuccessful();
        self::assertResponseFormatSame('json');
        self::assertSame([
            'status' => 'unliked',
            'message' => 'Photo unliked!',
            'photoId' => $photo->getId(),
            'liked' => false,
            'likeCounter' => 0,
        ], json_decode($this->client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR));
        self::assertSame(0, $this->countLikesForPhoto($photo->getId()));
        self::assertSame(0, $this->reloadPhoto($photo->getId())->getLikeCounter());
    }

    public function testUnlikeLoggedOut(): void
    {
        [, $photo] = $this->createUserAndPhoto();

        $this->client->request('POST', sprintf('/photo/%d/unlike', $photo->getId()));

        self::assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
        self::assertResponseFormatSame('json');
        self::assertSame([
            'status' => 'error',
            'message' => 'You must be logged in to unlike photos.',
        ], json_decode($this->client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR));
        self::assertSame(0, $this->countLikesForPhoto($photo->getId()));
        self::assertSame(0, $this->reloadPhoto($photo->getId())->getLikeCounter());
    }

    public function testTwoUsersCanLikePhoto(): void
    {
        [$owner, $photo] = $this->createUserAndPhoto();
        $secondUser = $this->createUser('second_user', 'second@example.com');

        $this->logInUser($this->client, $owner->getId());
        $ownerToken = $this->fetchLikeCsrfToken($photo->getId());
        $this->client->request('POST', sprintf('/photo/%d/like', $photo->getId()), [], [], $this->jsonHeaders($ownerToken));

        $this->logInUser($this->client, $secondUser->getId());
        $secondToken = $this->fetchLikeCsrfToken($photo->getId());
        $this->client->request('POST', sprintf('/photo/%d/like', $photo->getId()), [], [], $this->jsonHeaders($secondToken));

        self::assertResponseIsSuccessful();
        self::assertSame(2, $this->countLikesForPhoto($photo->getId()));
        self::assertSame(2, $this->reloadPhoto($photo->getId())->getLikeCounter());
    }

    public function testUnlikeOneOfTwoUsersDecrementsCounter(): void
    {
        [$owner, $photo] = $this->createUserAndPhoto();
        $secondUser = $this->createUser('second_user', 'second@example.com');

        $this->logInUser($this->client, $owner->getId());
        $ownerToken = $this->fetchLikeCsrfToken($photo->getId());
        $this->client->request('POST', sprintf('/photo/%d/like', $photo->getId()), [], [], $this->jsonHeaders($ownerToken));

        $this->logInUser($this->client, $secondUser->getId());
        $secondToken = $this->fetchLikeCsrfToken($photo->getId());
        $this->client->request('POST', sprintf('/photo/%d/like', $photo->getId()), [], [], $this->jsonHeaders($secondToken));
        $this->client->request('POST', sprintf('/photo/%d/unlike', $photo->getId()), [], [], $this->jsonHeaders($secondToken));

        self::assertResponseIsSuccessful();
        self::assertSame(1, $this->countLikesForPhoto($photo->getId()));
        self::assertSame(1, $this->reloadPhoto($photo->getId())->getLikeCounter());
    }

    public function testLikeOnlyPost(): void
    {
        [, $photo] = $this->createUserAndPhoto();

        $this->client->request('GET', sprintf('/photo/%d/like', $photo->getId()));

        self::assertResponseStatusCodeSame(Response::HTTP_METHOD_NOT_ALLOWED);
    }

    public function testUnlikeOnlyPost(): void
    {
        [, $photo] = $this->createUserAndPhoto();

        $this->client->request('GET', sprintf('/photo/%d/unlike', $photo->getId()));

        self::assertResponseStatusCodeSame(Response::HTTP_METHOD_NOT_ALLOWED);
    }

    public function testLikeRequiresCsrf(): void
    {
        [$user, $photo] = $this->createUserAndPhoto();

        $this->logInUser($this->client, $user->getId());
        $this->client->request('POST', sprintf('/photo/%d/like', $photo->getId()));

        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
        self::assertResponseFormatSame('json');
        self::assertSame([
            'status' => 'error',
            'message' => 'Invalid CSRF token.',
        ], json_decode($this->client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR));
    }

    public function testUnlikeRequiresCsrf(): void
    {
        [$user, $photo] = $this->createUserAndPhoto();

        $this->logInUser($this->client, $user->getId());
        $csrfToken = $this->fetchLikeCsrfToken($photo->getId());
        $this->client->request('POST', sprintf('/photo/%d/like', $photo->getId()), [], [], $this->jsonHeaders($csrfToken));
        $this->client->request('POST', sprintf('/photo/%d/unlike', $photo->getId()));

        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
        self::assertResponseFormatSame('json');
        self::assertSame([
            'status' => 'error',
            'message' => 'Invalid CSRF token.',
        ], json_decode($this->client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR));
    }

    public function testLikeRejectsInvalidCsrf(): void
    {
        [$user, $photo] = $this->createUserAndPhoto();

        $this->logInUser($this->client, $user->getId());
        $this->client->request('POST', sprintf('/photo/%d/like', $photo->getId()), [], [], $this->jsonHeaders('invalid-token'));

        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
        self::assertResponseFormatSame('json');
        self::assertSame([
            'status' => 'error',
            'message' => 'Invalid CSRF token.',
        ], json_decode($this->client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR));
    }

    public function testUnlikeRejectsInvalidCsrf(): void
    {
        [$user, $photo] = $this->createUserAndPhoto();

        $this->logInUser($this->client, $user->getId());
        $csrfToken = $this->fetchLikeCsrfToken($photo->getId());
        $this->client->request('POST', sprintf('/photo/%d/like', $photo->getId()), [], [], $this->jsonHeaders($csrfToken));
        $this->client->request('POST', sprintf('/photo/%d/unlike', $photo->getId()), [], [], $this->jsonHeaders('invalid-token'));

        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
        self::assertResponseFormatSame('json');
        self::assertSame([
            'status' => 'error',
            'message' => 'Invalid CSRF token.',
        ], json_decode($this->client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR));
    }

    /**
     * @return array{0: User, 1: Photo}
     */
    private function createUserAndPhoto(): array
    {
        $user = $this->createUser('test_user', 'test@example.com');

        $photo = new Photo();
        $photo
            ->setImageUrl('https://example.com/photo.jpg')
            ->setDescription('Test photo')
            ->setUser($user);

        $this->entityManager->persist($photo);
        $this->entityManager->flush();

        return [$user, $photo];
    }

    private function createUser(string $username, string $email): User
    {
        $user = new User();
        $user
            ->setUsername($username)
            ->setEmail($email);

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        return $user;
    }

    private function logInUser(KernelBrowser $client, int $userId): void
    {
        $session = $client->getContainer()->get('session.factory')->createSession();
        $session->start();
        $session->set('user_id', $userId);

        $session->save();

        $client->getCookieJar()->set(
            new \Symfony\Component\BrowserKit\Cookie($session->getName(), $session->getId())
        );
    }

    private function countLikesForPhoto(int $photoId): int
    {
        return $this->entityManager->getRepository(Like::class)->count(['photo' => $photoId]);
    }

    /**
     * @return array<string, string>
     */
    private function jsonHeaders(string $csrfToken): array
    {
        return [
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest',
            'HTTP_X_CSRF_TOKEN' => $csrfToken,
        ];
    }

    private function fetchLikeCsrfToken(int $photoId): string
    {
        $crawler = $this->client->request('GET', '/');
        $button = $crawler->filter(sprintf('.js-like-button[data-photo-id="%d"]', $photoId));

        self::assertCount(1, $button);

        return (string) $button->attr('data-csrf-token');
    }

    private function reloadPhoto(int $photoId): Photo
    {
        $this->entityManager->clear();

        $photo = $this->entityManager->getRepository(Photo::class)->find($photoId);
        self::assertInstanceOf(Photo::class, $photo);

        return $photo;
    }
}
