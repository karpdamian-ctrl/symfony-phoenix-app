<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\Like;
use App\Entity\Photo;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\BrowserKit\Cookie;

final class HomeControllerTest extends WebTestCase
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

    public function testGuestSeesPhotosAndDisabledLikeButtons(): void
    {
        [, $photo] = $this->createUserAndPhoto();

        $crawler = $this->client->request('GET', '/');

        self::assertResponseIsSuccessful();
        self::assertStringContainsString('Test photo', (string) $this->client->getResponse()->getContent());
        self::assertCount(1, $crawler->filter(sprintf('img[src="%s"]', $photo->getImageUrl())));
        self::assertCount(1, $crawler->filter('.like-button.disabled'));
        self::assertCount(0, $crawler->filter('.js-like-button'));
    }

    public function testLoggedInUserSeesInteractiveLikeButton(): void
    {
        [$user, $photo] = $this->createUserAndPhoto();
        $this->logInUser($user);

        $crawler = $this->client->request('GET', '/');

        self::assertResponseIsSuccessful();
        self::assertCount(1, $crawler->filter(sprintf('.js-like-button[data-photo-id="%d"]', $photo->getId())));
        self::assertSame('false', $crawler->filter('.js-like-button')->attr('data-liked'));
        self::assertSame('🤍', trim((string) $crawler->filter('.js-like-icon')->text()));
    }

    public function testLoggedInUserSeesLikedPhotoState(): void
    {
        [$user, $photo] = $this->createUserAndPhoto();

        $like = new Like();
        $like->setUser($user);
        $like->setPhoto($photo);
        $photo->setLikeCounter(1);

        $this->entityManager->persist($like);
        $this->entityManager->persist($photo);
        $this->entityManager->flush();

        $this->logInUser($user);

        $crawler = $this->client->request('GET', '/');

        self::assertResponseIsSuccessful();
        self::assertSame('true', $crawler->filter('.js-like-button')->attr('data-liked'));
        self::assertStringContainsString('liked', (string) $crawler->filter('.js-like-button')->attr('class'));
        self::assertSame('❤️', trim((string) $crawler->filter('.js-like-icon')->text()));
        self::assertSame('1', trim((string) $crawler->filter('.js-like-counter')->text()));
    }

    public function testEmptyStateIsVisibleWhenThereAreNoPhotos(): void
    {
        $crawler = $this->client->request('GET', '/');

        self::assertResponseIsSuccessful();
        self::assertStringContainsString('No photos yet', (string) $this->client->getResponse()->getContent());
        self::assertStringContainsString('Be the first to share your photography!', (string) $this->client->getResponse()->getContent());
        self::assertCount(1, $crawler->filter('.empty-state'));
    }

    public function testPhotosCanBeFilteredBySupportedFieldsIncludingTakenAtRange(): void
    {
        $matchingUser = (new User())
            ->setUsername('match_author')
            ->setEmail('match@example.com');

        $otherUser = (new User())
            ->setUsername('other_author')
            ->setEmail('other@example.com');

        $matchingPhoto = (new Photo())
            ->setImageUrl('https://example.com/matching-photo.jpg')
            ->setLocation('Warsaw Old Town')
            ->setCamera('Canon EOS R5')
            ->setDescription('Night skyline over the river')
            ->setTakenAt(new \DateTimeImmutable('2026-04-08 20:15:00'))
            ->setUser($matchingUser);

        $otherPhoto = (new Photo())
            ->setImageUrl('https://example.com/other-photo.jpg')
            ->setLocation('Krakow Market Square')
            ->setCamera('Sony A7 III')
            ->setDescription('Sunny afternoon in the city center')
            ->setTakenAt(new \DateTimeImmutable('2026-04-07 09:00:00'))
            ->setUser($otherUser);

        $this->entityManager->persist($matchingUser);
        $this->entityManager->persist($otherUser);
        $this->entityManager->persist($matchingPhoto);
        $this->entityManager->persist($otherPhoto);
        $this->entityManager->flush();

        $crawler = $this->client->request('GET', '/', [
            'location' => 'warsaw',
            'camera' => 'canon',
            'description' => 'skyline',
            'taken_at_from' => '2026-04-08 20:00',
            'taken_at_to' => '2026-04-08 20:30',
            'username' => 'match',
        ]);

        self::assertResponseIsSuccessful();
        self::assertCount(1, $crawler->filter(sprintf('img[src="%s"]', $matchingPhoto->getImageUrl())));
        self::assertCount(0, $crawler->filter(sprintf('img[src="%s"]', $otherPhoto->getImageUrl())));
        self::assertSame('warsaw', $crawler->filter('#filter-location')->attr('value'));
        self::assertSame('canon', $crawler->filter('#filter-camera')->attr('value'));
        self::assertSame('skyline', $crawler->filter('#filter-description')->attr('value'));
        self::assertSame('2026-04-08 20:00', $crawler->filter('#filter-taken-at-from')->attr('value'));
        self::assertSame('2026-04-08 20:30', $crawler->filter('#filter-taken-at-to')->attr('value'));
        self::assertSame('match', $crawler->filter('#filter-username')->attr('value'));
    }

    public function testPhotosCanBeFilteredByTakenAtLowerBoundaryOnly(): void
    {
        $user = (new User())
            ->setUsername('boundary_author')
            ->setEmail('boundary@example.com');

        $olderPhoto = (new Photo())
            ->setImageUrl('https://example.com/older-photo.jpg')
            ->setDescription('Older photo')
            ->setTakenAt(new \DateTimeImmutable('2026-04-08 20:14:00'))
            ->setUser($user);

        $newerPhoto = (new Photo())
            ->setImageUrl('https://example.com/newer-photo.jpg')
            ->setDescription('Newer photo')
            ->setTakenAt(new \DateTimeImmutable('2026-04-08 20:15:00'))
            ->setUser($user);

        $this->entityManager->persist($user);
        $this->entityManager->persist($olderPhoto);
        $this->entityManager->persist($newerPhoto);
        $this->entityManager->flush();

        $crawler = $this->client->request('GET', '/', [
            'taken_at_from' => '2026-04-08 20:15',
        ]);

        self::assertResponseIsSuccessful();
        self::assertCount(0, $crawler->filter(sprintf('img[src="%s"]', $olderPhoto->getImageUrl())));
        self::assertCount(1, $crawler->filter(sprintf('img[src="%s"]', $newerPhoto->getImageUrl())));
        self::assertSame('2026-04-08 20:15', $crawler->filter('#filter-taken-at-from')->attr('value'));
        self::assertSame('', (string) $crawler->filter('#filter-taken-at-to')->attr('value'));
    }

    public function testPhotosCanBeFilteredByTakenAtUpperBoundaryOnly(): void
    {
        $user = (new User())
            ->setUsername('upper_boundary_author')
            ->setEmail('upper-boundary@example.com');

        $olderPhoto = (new Photo())
            ->setImageUrl('https://example.com/upper-older-photo.jpg')
            ->setDescription('Older upper boundary photo')
            ->setTakenAt(new \DateTimeImmutable('2026-04-08 20:15:00'))
            ->setUser($user);

        $newerPhoto = (new Photo())
            ->setImageUrl('https://example.com/upper-newer-photo.jpg')
            ->setDescription('Newer upper boundary photo')
            ->setTakenAt(new \DateTimeImmutable('2026-04-08 20:16:00'))
            ->setUser($user);

        $this->entityManager->persist($user);
        $this->entityManager->persist($olderPhoto);
        $this->entityManager->persist($newerPhoto);
        $this->entityManager->flush();

        $crawler = $this->client->request('GET', '/', [
            'taken_at_to' => '2026-04-08 20:15',
        ]);

        self::assertResponseIsSuccessful();
        self::assertCount(1, $crawler->filter(sprintf('img[src="%s"]', $olderPhoto->getImageUrl())));
        self::assertCount(0, $crawler->filter(sprintf('img[src="%s"]', $newerPhoto->getImageUrl())));
        self::assertSame('', (string) $crawler->filter('#filter-taken-at-from')->attr('value'));
        self::assertSame('2026-04-08 20:15', $crawler->filter('#filter-taken-at-to')->attr('value'));
    }

    public function testPhotosCanBeFilteredByTakenAtRangeWithNoMatchesWhenFromIsAfterTo(): void
    {
        [, $photo] = $this->createUserAndPhoto();

        $crawler = $this->client->request('GET', '/', [
            'taken_at_from' => '2026-04-09 10:00',
            'taken_at_to' => '2026-04-08 10:00',
        ]);

        self::assertResponseIsSuccessful();
        self::assertCount(0, $crawler->filter(sprintf('img[src="%s"]', $photo->getImageUrl())));
        self::assertCount(1, $crawler->filter('.empty-state'));
        self::assertSame('2026-04-09 10:00', $crawler->filter('#filter-taken-at-from')->attr('value'));
        self::assertSame('2026-04-08 10:00', $crawler->filter('#filter-taken-at-to')->attr('value'));
    }

    public function testPhotosCanBeFilteredByUsername(): void
    {
        $matchingUser = (new User())
            ->setUsername('emma_wilson')
            ->setEmail('emma@example.com')
            ->setName('Emma')
            ->setLastName('Wilson');

        $otherUser = (new User())
            ->setUsername('anna_nowak')
            ->setEmail('other-emma@example.com')
            ->setName('Anna')
            ->setLastName('Nowak');

        $matchingPhoto = (new Photo())
            ->setImageUrl('https://example.com/emma-wilson-photo.jpg')
            ->setDescription('Emma photo')
            ->setUser($matchingUser);

        $otherPhoto = (new Photo())
            ->setImageUrl('https://example.com/other-emma-photo.jpg')
            ->setDescription('Other photo')
            ->setUser($otherUser);

        $this->entityManager->persist($matchingUser);
        $this->entityManager->persist($otherUser);
        $this->entityManager->persist($matchingPhoto);
        $this->entityManager->persist($otherPhoto);
        $this->entityManager->flush();

        $crawler = $this->client->request('GET', '/', [
            'username' => 'emma_wil',
        ]);

        self::assertResponseIsSuccessful();
        self::assertCount(1, $crawler->filter(sprintf('img[src="%s"]', $matchingPhoto->getImageUrl())));
        self::assertCount(0, $crawler->filter(sprintf('img[src="%s"]', $otherPhoto->getImageUrl())));
        self::assertSame('emma_wil', $crawler->filter('#filter-username')->attr('value'));
    }

    public function testPhotosCanBeFilteredByUsernameCaseInsensitively(): void
    {
        $matchingUser = (new User())
            ->setUsername('MixedCaseAuthor')
            ->setEmail('mixed@example.com');

        $otherUser = (new User())
            ->setUsername('different_author')
            ->setEmail('different@example.com');

        $matchingPhoto = (new Photo())
            ->setImageUrl('https://example.com/mixed-case-photo.jpg')
            ->setDescription('Mixed case photo')
            ->setUser($matchingUser);

        $otherPhoto = (new Photo())
            ->setImageUrl('https://example.com/different-case-photo.jpg')
            ->setDescription('Different case photo')
            ->setUser($otherUser);

        $this->entityManager->persist($matchingUser);
        $this->entityManager->persist($otherUser);
        $this->entityManager->persist($matchingPhoto);
        $this->entityManager->persist($otherPhoto);
        $this->entityManager->flush();

        $crawler = $this->client->request('GET', '/', [
            'username' => 'mixedcase',
        ]);

        self::assertResponseIsSuccessful();
        self::assertCount(1, $crawler->filter(sprintf('img[src="%s"]', $matchingPhoto->getImageUrl())));
        self::assertCount(0, $crawler->filter(sprintf('img[src="%s"]', $otherPhoto->getImageUrl())));
        self::assertSame('mixedcase', $crawler->filter('#filter-username')->attr('value'));
    }

    public function testPhotosCanBeFilteredByLocationOnly(): void
    {
        [$matchingPhoto, $otherPhoto] = $this->createPhotosForSingleTextFilter(
            'location',
            'Warsaw Riverside',
            'Gdansk Harbor'
        );

        $crawler = $this->client->request('GET', '/', [
            'location' => 'warsaw',
        ]);

        self::assertResponseIsSuccessful();
        self::assertCount(1, $crawler->filter(sprintf('img[src="%s"]', $matchingPhoto->getImageUrl())));
        self::assertCount(0, $crawler->filter(sprintf('img[src="%s"]', $otherPhoto->getImageUrl())));
        self::assertSame('warsaw', $crawler->filter('#filter-location')->attr('value'));
    }

    public function testPhotosCanBeFilteredByCameraOnly(): void
    {
        [$matchingPhoto, $otherPhoto] = $this->createPhotosForSingleTextFilter(
            'camera',
            'Canon EOS R6',
            'Sony A7 IV'
        );

        $crawler = $this->client->request('GET', '/', [
            'camera' => 'canon',
        ]);

        self::assertResponseIsSuccessful();
        self::assertCount(1, $crawler->filter(sprintf('img[src="%s"]', $matchingPhoto->getImageUrl())));
        self::assertCount(0, $crawler->filter(sprintf('img[src="%s"]', $otherPhoto->getImageUrl())));
        self::assertSame('canon', $crawler->filter('#filter-camera')->attr('value'));
    }

    public function testPhotosCanBeFilteredByDescriptionOnly(): void
    {
        [$matchingPhoto, $otherPhoto] = $this->createPhotosForSingleTextFilter(
            'description',
            'Golden hour skyline',
            'Forest trail in morning fog'
        );

        $crawler = $this->client->request('GET', '/', [
            'description' => 'skyline',
        ]);

        self::assertResponseIsSuccessful();
        self::assertCount(1, $crawler->filter(sprintf('img[src="%s"]', $matchingPhoto->getImageUrl())));
        self::assertCount(0, $crawler->filter(sprintf('img[src="%s"]', $otherPhoto->getImageUrl())));
        self::assertSame('skyline', $crawler->filter('#filter-description')->attr('value'));
    }

    public function testWhitespaceOnlyFiltersDoNotRestrictResults(): void
    {
        [, $firstPhoto] = $this->createUserAndPhoto();

        $otherUser = (new User())
            ->setUsername('whitespace_user')
            ->setEmail('whitespace@example.com');

        $otherPhoto = (new Photo())
            ->setImageUrl('https://example.com/whitespace-photo.jpg')
            ->setDescription('Whitespace photo')
            ->setUser($otherUser);

        $this->entityManager->persist($otherUser);
        $this->entityManager->persist($otherPhoto);
        $this->entityManager->flush();

        $crawler = $this->client->request('GET', '/', [
            'location' => '   ',
            'camera' => '   ',
            'description' => '   ',
            'username' => '   ',
        ]);

        self::assertResponseIsSuccessful();
        self::assertCount(1, $crawler->filter(sprintf('img[src="%s"]', $firstPhoto->getImageUrl())));
        self::assertCount(1, $crawler->filter(sprintf('img[src="%s"]', $otherPhoto->getImageUrl())));
        self::assertSame('', (string) $crawler->filter('#filter-location')->attr('value'));
        self::assertSame('', (string) $crawler->filter('#filter-camera')->attr('value'));
        self::assertSame('', (string) $crawler->filter('#filter-description')->attr('value'));
        self::assertSame('', (string) $crawler->filter('#filter-username')->attr('value'));
    }

    public function testInvalidTakenAtFormatShowsErrorAndNoPhotos(): void
    {
        [, $photo] = $this->createUserAndPhoto();

        $crawler = $this->client->request('GET', '/', [
            'taken_at_from' => '2026',
        ]);

        self::assertResponseIsSuccessful();
        self::assertStringContainsString('Invalid date format. Use YYYY-MM-DD or YYYY-MM-DD HH:mm.', (string) $this->client->getResponse()->getContent());
        self::assertCount(0, $crawler->filter(sprintf('img[src="%s"]', $photo->getImageUrl())));
        self::assertCount(1, $crawler->filter('.empty-state'));
        self::assertSame('2026', $crawler->filter('#filter-taken-at-from')->attr('value'));
    }

    /**
     * @return array{0: User, 1: Photo}
     */
    private function createUserAndPhoto(): array
    {
        $user = new User();
        $user
            ->setUsername('home_user')
            ->setEmail('home@example.com');

        $photo = new Photo();
        $photo
            ->setImageUrl('https://example.com/home-photo.jpg')
            ->setDescription('Test photo')
            ->setUser($user);

        $this->entityManager->persist($user);
        $this->entityManager->persist($photo);
        $this->entityManager->flush();

        return [$user, $photo];
    }

    /**
     * @return array{0: Photo, 1: Photo}
     */
    private function createPhotosForSingleTextFilter(string $field, string $matchingValue, string $otherValue): array
    {
        $matchingUser = (new User())
            ->setUsername(sprintf('%s_match_user', $field))
            ->setEmail(sprintf('%s_match@example.com', $field));

        $otherUser = (new User())
            ->setUsername(sprintf('%s_other_user', $field))
            ->setEmail(sprintf('%s_other@example.com', $field));

        $matchingPhoto = (new Photo())
            ->setImageUrl(sprintf('https://example.com/%s-matching-photo.jpg', $field))
            ->setDescription('Matching single filter photo')
            ->setUser($matchingUser);

        $otherPhoto = (new Photo())
            ->setImageUrl(sprintf('https://example.com/%s-other-photo.jpg', $field))
            ->setDescription('Other single filter photo')
            ->setUser($otherUser);

        match ($field) {
            'location' => $matchingPhoto->setLocation($matchingValue),
            'camera' => $matchingPhoto->setCamera($matchingValue),
            'description' => $matchingPhoto->setDescription($matchingValue),
            default => throw new \InvalidArgumentException('Unsupported filter field.'),
        };

        match ($field) {
            'location' => $otherPhoto->setLocation($otherValue),
            'camera' => $otherPhoto->setCamera($otherValue),
            'description' => $otherPhoto->setDescription($otherValue),
            default => throw new \InvalidArgumentException('Unsupported filter field.'),
        };

        $this->entityManager->persist($matchingUser);
        $this->entityManager->persist($otherUser);
        $this->entityManager->persist($matchingPhoto);
        $this->entityManager->persist($otherPhoto);
        $this->entityManager->flush();

        return [$matchingPhoto, $otherPhoto];
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
