<?php

declare(strict_types=1);

namespace App\Home\Controller;

use App\Home\Dto\PhotoFilterDto;
use App\Likes\LikeRepositoryInterface;
use App\Photo\Exception\InvalidPhotoFilterException;
use App\Photo\Repository\PhotoRepositoryInterface;
use App\Shared\Controller\AppController;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\ConstraintViolationListInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class HomeController extends AppController
{
    /**
     * @Route("/", name="home")
     */
    public function index(
        Request $request,
        EntityManagerInterface $em,
        PhotoRepositoryInterface $photoRepository,
        LikeRepositoryInterface $likeRepository,
        ValidatorInterface $validator
    ): Response {
        $filterDto = PhotoFilterDto::fromRequest($request);
        $filters = $filterDto->toRepositoryFilters();
        $violations = $validator->validate($filterDto);

        if (\count($violations) > 0) {
            $this->addFilterValidationErrors($violations);
            $photos = [];
        } else {
            try {
                $photos = $photoRepository->findAllWithUsers($filters);
            } catch (InvalidPhotoFilterException) {
                $this->addFlash('error', $this->translate('photo.filters.invalid_date'));
                $photos = [];
            }
        }

        $currentUser = $this->resolveCurrentUser($request, $em, true);
        $userLikes = [];

        if ($currentUser) {
            $photoIds = array_map(
                static fn (\App\Entity\Photo $photo): ?int => $photo->getId(),
                $photos
            );
            $likedPhotoIds = $likeRepository->getLikedPhotoIdsForUser($currentUser, $photoIds);
            $likedPhotoIdLookup = array_fill_keys($likedPhotoIds, true);

            foreach ($photos as $photo) {
                $photoId = $photo->getId();
                if ($photoId === null) {
                    continue;
                }

                $userLikes[$photoId] = isset($likedPhotoIdLookup[$photoId]);
            }
        }

        return $this->render('home/index.html.twig', [
            'photos' => $photos,
            'filters' => $filters,
            'currentUser' => $currentUser,
            'userLikes' => $userLikes,
        ]);
    }

    private function addFilterValidationErrors(ConstraintViolationListInterface $violations): void
    {
        $errorTranslationKeys = [];

        foreach ($violations as $violation) {
            $constraint = $violation->getConstraint();
            $propertyPath = $violation->getPropertyPath();

            if (\in_array($propertyPath, ['takenAtFrom', 'takenAtTo'], true)) {
                $errorTranslationKeys['photo.filters.invalid_date'] = true;
                continue;
            }

            if ($constraint instanceof Length) {
                $errorTranslationKeys['photo.filters.invalid_length'] = true;
                continue;
            }

            $errorTranslationKeys['photo.filters.invalid_value'] = true;
        }

        foreach (array_keys($errorTranslationKeys) as $errorTranslationKey) {
            $this->addFlash('error', $this->translate($errorTranslationKey));
        }
    }
}
