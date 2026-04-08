<?php

declare(strict_types=1);

namespace App\Home\Controller;

use App\Likes\LikeRepositoryInterface;
use App\Photo\Exception\InvalidPhotoFilterException;
use App\Photo\Repository\PhotoRepositoryInterface;
use App\Shared\Controller\AppController;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class HomeController extends AppController
{
    /**
     * @Route("/", name="home")
     */
    public function index(
        Request $request,
        EntityManagerInterface $em,
        PhotoRepositoryInterface $photoRepository,
        LikeRepositoryInterface $likeRepository
    ): Response {
        $filters = [
            'location' => $this->normalizeFilterValue($request->query->get('location')),
            'camera' => $this->normalizeFilterValue($request->query->get('camera')),
            'description' => $this->normalizeFilterValue($request->query->get('description')),
            'taken_at_from' => $this->normalizeFilterValue($request->query->get('taken_at_from')),
            'taken_at_to' => $this->normalizeFilterValue($request->query->get('taken_at_to')),
            'username' => $this->normalizeFilterValue($request->query->get('username')),
        ];

        try {
            $photos = $photoRepository->findAllWithUsers($filters);
        } catch (InvalidPhotoFilterException) {
            $this->addFlash('error', $this->translate('photo.filters.invalid_date'));
            $photos = [];
        }

        $currentUser = $this->resolveCurrentUser($request, $em, true);
        $userLikes = [];

        if ($currentUser) {
            foreach ($photos as $photo) {
                $userLikes[$photo->getId()] = $likeRepository->hasUserLikedPhoto($currentUser, $photo);
            }
        }

        return $this->render('home/index.html.twig', [
            'photos' => $photos,
            'filters' => $filters,
            'currentUser' => $currentUser,
            'userLikes' => $userLikes,
        ]);
    }

    private function normalizeFilterValue(mixed $value): ?string
    {
        if (!\is_string($value)) {
            return null;
        }

        $trimmedValue = trim($value);

        return $trimmedValue === '' ? null : $trimmedValue;
    }
}
