<?php

declare(strict_types=1);

namespace App\Likes;

use App\Entity\Like;
use App\Entity\Photo;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Like>
 */
final class LikeRepository extends ServiceEntityRepository implements LikeRepositoryInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Like::class);
    }

    #[\Override]
    public function unlikePhoto(User $user, Photo $photo): void
    {
        $em = $this->getEntityManager();

        $like = $em->createQueryBuilder()
            ->select('l')
            ->from(Like::class, 'l')
            ->where('l.user = :user')
            ->andWhere('l.photo = :photo')
            ->setParameter('user', $user)
            ->setParameter('photo', $photo)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        if ($like) {
            $em->remove($like);
            $em->flush();

            $photo->setLikeCounter($photo->getLikeCounter() - 1);
            $em->persist($photo);

            $em->flush();
        }
    }

    #[\Override]
    public function hasUserLikedPhoto(User $user, Photo $photo): bool
    {
        $likes = $this->createQueryBuilder('l')
            ->select('l.id')
            ->where('l.user = :user')
            ->andWhere('l.photo = :photo')
            ->setParameter('user', $user)
            ->setParameter('photo', $photo)
            ->getQuery()
            ->getArrayResult();

        return count($likes) > 0;
    }

    #[\Override]
    public function createLike(User $user, Photo $photo): Like
    {
        $like = new Like();
        $like->setUser($user);
        $like->setPhoto($photo);

        $em = $this->getEntityManager();
        $em->persist($like);
        $em->flush();

        return $like;
    }

    #[\Override]
    public function updatePhotoCounter(Photo $photo, int $increment): void
    {
        $em = $this->getEntityManager();
        $photo->setLikeCounter($photo->getLikeCounter() + $increment);
        $em->persist($photo);
        $em->flush();
    }
}
