<?php

declare(strict_types=1);

namespace App\Photo\Service;

use App\Entity\Photo;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class PhoenixPhotoImportService implements PhoenixPhotoImportServiceInterface
{
    private const REQUESTED_FIELDS = [
        'camera',
        'location',
        'description',
        'taken_at',
    ];

    public function __construct(
        private HttpClientInterface $httpClient,
        private EntityManagerInterface $entityManager,
        private string $phoenixBaseUrl
    ) {
    }

    public function import(User $user): PhoenixPhotoImportResult
    {
        $token = $user->getPhoenixApiToken();
        if ($token === null) {
            return PhoenixPhotoImportResult::invalidToken();
        }

        try {
            $response = $this->httpClient->request('GET', $this->buildPhotosUrl(), [
                'headers' => [
                    'access-token' => $token,
                ],
            ]);
        } catch (ExceptionInterface $exception) {
            throw new \RuntimeException('Failed to fetch photos from Phoenix API.', previous: $exception);
        }

        if ($response->getStatusCode() === 401) {
            return PhoenixPhotoImportResult::invalidToken();
        }

        if ($response->getStatusCode() !== 200) {
            throw new \RuntimeException(sprintf('Unexpected Phoenix API status: %d', $response->getStatusCode()));
        }

        /** @var array{photos?: list<array{
         *     id?: int,
         *     photo_url?: string,
         *     description?: string,
         *     location?: string,
         *     camera?: string,
         *     taken_at?: string
         * }>} $payload */
        $payload = $response->toArray(false);
        $photoRepository = $this->entityManager->getRepository(Photo::class);
        $importedCount = 0;

        foreach ($payload['photos'] ?? [] as $item) {
            $photoUrl = $item['photo_url'] ?? null;
            if (!is_string($photoUrl) || $photoUrl === '') {
                continue;
            }

            $existingPhoto = $photoRepository->findOneBy([
                'user' => $user,
                'imageUrl' => $photoUrl,
            ]);

            if ($existingPhoto instanceof Photo) {
                continue;
            }

            $photo = (new Photo())
                ->setUser($user)
                ->setImageUrl($photoUrl)
                ->setDescription($this->stringOrDefault($item['description'] ?? null, 'Imported from Phoenix API'))
                ->setLocation($this->stringOrNull($item['location'] ?? null))
                ->setCamera($this->stringOrNull($item['camera'] ?? null))
                ->setTakenAt($this->dateTimeOrNull($item['taken_at'] ?? null));

            $this->entityManager->persist($photo);
            $importedCount++;
        }

        if ($importedCount > 0) {
            $this->entityManager->flush();
        }

        return PhoenixPhotoImportResult::success($importedCount);
    }

    private function buildPhotosUrl(): string
    {
        return sprintf(
            '%s/api/photos?fields=%s',
            rtrim($this->phoenixBaseUrl, '/'),
            implode(',', self::REQUESTED_FIELDS)
        );
    }

    private function stringOrNull(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? $value : null;
    }

    private function stringOrDefault(mixed $value, string $default): string
    {
        return is_string($value) && $value !== '' ? $value : $default;
    }

    private function dateTimeOrNull(mixed $value): ?\DateTimeImmutable
    {
        if (!is_string($value) || $value === '') {
            return null;
        }

        try {
            return new \DateTimeImmutable($value);
        } catch (\Exception) {
            return null;
        }
    }
}
