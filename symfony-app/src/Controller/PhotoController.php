<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Photo;
use App\Entity\User;
use App\Likes\DuplicateLikeException;
use App\Likes\LikeRepository;
use App\Likes\LikeService;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

class PhotoController extends AbstractController
{
    private const LIKE_CSRF_TOKEN_ID = 'photo_like';
    private const LIKE_CSRF_MESSAGE = 'You must be logged in to like photos.';
    private const UNLIKE_CSRF_MESSAGE = 'You must be logged in to unlike photos.';

    #[Route('/photo/{id}/like', name: 'photo_like', methods: ['POST'])]
    public function like(int $id, Request $request, EntityManagerInterface $em, ManagerRegistry $managerRegistry): JsonResponse
    {
        $likeRepository = new LikeRepository($managerRegistry);
        $likeService = new LikeService($likeRepository);

        $user = $this->resolveCurrentUser($request, $em);
        if (!$user instanceof User) {
            return $this->errorResponse(self::LIKE_CSRF_MESSAGE, JsonResponse::HTTP_UNAUTHORIZED);
        }

        if (!$this->hasValidCsrfToken($request)) {
            return $this->errorResponse('Invalid CSRF token.', JsonResponse::HTTP_FORBIDDEN);
        }

        $photo = $this->findPhoto($id, $em);

        if (!$photo) {
            return $this->errorResponse('Photo not found.', JsonResponse::HTTP_NOT_FOUND);
        }

        if ($likeRepository->hasUserLikedPhoto($user, $photo)) {
            return $this->likeStateResponse('noop', 'Photo already liked.', $photo, true);
        }

        try {
            $likeService->execute($user, $photo);
        } catch (DuplicateLikeException) {
            return $this->likeStateResponse('noop', 'Photo already liked.', $photo, true);
        }

        return $this->likeStateResponse('liked', 'Photo liked!', $photo, true);
    }

    #[Route('/photo/{id}/unlike', name: 'photo_unlike', methods: ['POST'])]
    public function unlike(int $id, Request $request, EntityManagerInterface $em, ManagerRegistry $managerRegistry): JsonResponse
    {
        $likeRepository = new LikeRepository($managerRegistry);

        $user = $this->resolveCurrentUser($request, $em);
        if (!$user instanceof User) {
            return $this->errorResponse(self::UNLIKE_CSRF_MESSAGE, JsonResponse::HTTP_UNAUTHORIZED);
        }

        if (!$this->hasValidCsrfToken($request)) {
            return $this->errorResponse('Invalid CSRF token.', JsonResponse::HTTP_FORBIDDEN);
        }

        $photo = $this->findPhoto($id, $em);

        if (!$photo) {
            return $this->errorResponse('Photo not found.', JsonResponse::HTTP_NOT_FOUND);
        }

        if (!$likeRepository->hasUserLikedPhoto($user, $photo)) {
            return $this->likeStateResponse('noop', 'Photo is not liked yet.', $photo, false);
        }

        $likeRepository->unlikePhoto($user, $photo);

        return $this->likeStateResponse('unliked', 'Photo unliked!', $photo, false);
    }

    private function resolveCurrentUser(Request $request, EntityManagerInterface $entityManager): ?User
    {
        $userId = $request->getSession()->get('user_id');
        if (!$userId) {
            return null;
        }

        $user = $entityManager->getRepository(User::class)->find($userId);

        return $user instanceof User ? $user : null;
    }

    private function hasValidCsrfToken(Request $request): bool
    {
        $csrfToken = (string) $request->headers->get('X-CSRF-TOKEN', '');

        return $this->isCsrfTokenValid(self::LIKE_CSRF_TOKEN_ID, $csrfToken);
    }

    private function findPhoto(int $id, EntityManagerInterface $entityManager): ?Photo
    {
        $photo = $entityManager->getRepository(Photo::class)->find($id);

        return $photo instanceof Photo ? $photo : null;
    }

    private function errorResponse(string $message, int $statusCode): JsonResponse
    {
        return $this->json([
            'status' => 'error',
            'message' => $message,
        ], $statusCode);
    }

    private function likeStateResponse(string $status, string $message, Photo $photo, bool $liked): JsonResponse
    {
        return $this->json([
            'status' => $status,
            'message' => $message,
            'photoId' => $photo->getId(),
            'liked' => $liked,
            'likeCounter' => $photo->getLikeCounter(),
        ]);
    }
}
