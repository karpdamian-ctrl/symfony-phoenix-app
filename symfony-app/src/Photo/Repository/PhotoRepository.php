<?php

declare(strict_types=1);

namespace App\Photo\Repository;

use App\Entity\Photo;
use App\Photo\Exception\InvalidPhotoFilterException;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Photo>
 */
class PhotoRepository extends ServiceEntityRepository implements PhotoRepositoryInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Photo::class);
    }

    /**
     * @return array<int, Photo>
     */
    public function findAllWithUsers(array $filters = []): array
    {
        $queryBuilder = $this->createQueryBuilder('p')
            ->leftJoin('p.user', 'u')
            ->addSelect('u')
            ->orderBy('p.id', 'ASC');

        if (($location = $this->normalizeFilterValue($filters['location'] ?? null)) !== null) {
            $queryBuilder
                ->andWhere('LOWER(p.location) LIKE LOWER(:location)')
                ->setParameter('location', '%' . $location . '%');
        }

        if (($camera = $this->normalizeFilterValue($filters['camera'] ?? null)) !== null) {
            $queryBuilder
                ->andWhere('LOWER(p.camera) LIKE LOWER(:camera)')
                ->setParameter('camera', '%' . $camera . '%');
        }

        if (($description = $this->normalizeFilterValue($filters['description'] ?? null)) !== null) {
            $queryBuilder
                ->andWhere('LOWER(p.description) LIKE LOWER(:description)')
                ->setParameter('description', '%' . $description . '%');
        }

        if (($username = $this->normalizeFilterValue($filters['username'] ?? null)) !== null) {
            $queryBuilder
                ->andWhere('LOWER(u.username) LIKE LOWER(:username)')
                ->setParameter('username', '%' . $username . '%');
        }

        $takenAtFrom = $this->normalizeTakenAtBoundary($filters['taken_at_from'] ?? null);
        $takenAtTo = $this->normalizeTakenAtBoundary($filters['taken_at_to'] ?? null, true);

        if ($takenAtFrom instanceof \DateTimeImmutable) {
            $queryBuilder
                ->andWhere('p.takenAt >= :takenAtFrom')
                ->setParameter('takenAtFrom', $takenAtFrom);
        }

        if ($takenAtTo instanceof \DateTimeImmutable) {
            $queryBuilder
                ->andWhere('p.takenAt <= :takenAtTo')
                ->setParameter('takenAtTo', $takenAtTo);
        }

        return $queryBuilder->getQuery()->getResult();
    }

    private function normalizeFilterValue(mixed $value): ?string
    {
        if (!\is_string($value)) {
            return null;
        }

        $trimmedValue = trim($value);

        return $trimmedValue === '' ? null : $trimmedValue;
    }

    private function normalizeTakenAtBoundary(mixed $value, bool $inclusiveUpperBound = false): ?\DateTimeImmutable
    {
        if (!\is_string($value) || trim($value) === '') {
            return null;
        }

        $normalizedValue = trim($value);
        $takenAt = \DateTimeImmutable::createFromFormat('!Y-m-d H:i', $normalizedValue);
        $hasTimeComponent = preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}$/', $normalizedValue) === 1;

        if (!$takenAt instanceof \DateTimeImmutable) {
            $takenAt = \DateTimeImmutable::createFromFormat('!Y-m-d', $normalizedValue);
            $hasTimeComponent = false;
        }

        if (!$takenAt instanceof \DateTimeImmutable) {
            throw new InvalidPhotoFilterException('Invalid taken_at filter format.');
        }

        if (!$inclusiveUpperBound) {
            return $takenAt;
        }

        if ($hasTimeComponent) {
            return $takenAt->modify('+59 seconds');
        }

        return $takenAt->setTime(23, 59, 59);
    }
}
