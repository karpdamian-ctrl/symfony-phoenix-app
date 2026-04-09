<?php

declare(strict_types=1);

namespace App\Profile\Controller;

use App\Photo\Service\PhoenixPhotoImportRateLimitException;
use App\Photo\Service\PhoenixPhotoImportServiceInterface;
use App\Shared\Controller\AppController;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class ProfileController extends AppController
{
    private const MAX_PHOENIX_TOKEN_LENGTH = 255;

    #[Route('/profile', name: 'profile')]
    public function profile(Request $request, EntityManagerInterface $em): Response
    {
        $user = $this->resolveCurrentUser($request, $em, true);
        if (!$user) {
            return $this->redirectToRoute('home');
        }

        return $this->render('profile/index.html.twig', [
            'user' => $user,
        ]);
    }

    #[Route('/profile/phoenix-token', name: 'profile_save_phoenix_token', methods: ['POST'])]
    public function savePhoenixToken(Request $request, EntityManagerInterface $em): Response
    {
        $user = $this->resolveCurrentUser($request, $em, true);
        if (!$user) {
            return $this->redirectToRoute('home');
        }

        if (!$this->hasValidCsrfToken($request, 'profile_save_phoenix_token')) {
            $this->addFlash('error', $this->translate('security.csrf.invalid'));

            return $this->redirectToRoute('profile');
        }

        $token = trim((string) $request->request->get('phoenix_api_token', ''));

        if (mb_strlen($token) > self::MAX_PHOENIX_TOKEN_LENGTH) {
            $this->addFlash('error', $this->translate('profile.import.token_too_long'));

            return $this->redirectToRoute('profile');
        }

        if ($token === '') {
            $this->addFlash('error', $this->translate('profile.import.token_empty'));

            return $this->redirectToRoute('profile');
        }

        $user->setPhoenixApiToken($token);
        $em->flush();

        $this->addFlash('success', $this->translate('profile.import.token_saved'));

        return $this->redirectToRoute('profile');
    }

    #[Route('/profile/import-photos', name: 'profile_import_photos', methods: ['POST'])]
    public function importPhotos(
        Request $request,
        EntityManagerInterface $em,
        PhoenixPhotoImportServiceInterface $phoenixPhotoImportService
    ): Response {
        $user = $this->resolveCurrentUser($request, $em, true);
        if (!$user) {
            return $this->redirectToRoute('home');
        }

        if (!$this->hasValidCsrfToken($request, 'profile_import_photos')) {
            $this->addFlash('error', $this->translate('security.csrf.invalid'));

            return $this->redirectToRoute('profile');
        }

        if ($user->getPhoenixApiToken() === null) {
            $this->addFlash('error', $this->translate('profile.import.token_missing'));

            return $this->redirectToRoute('profile');
        }

        try {
            $result = $phoenixPhotoImportService->import($user);
        } catch (PhoenixPhotoImportRateLimitException $exception) {
            $this->addFlash('error', $this->translate($exception->getTranslationKey()));

            return $this->redirectToRoute('profile');
        } catch (\RuntimeException) {
            $this->addFlash('error', $this->translate('profile.import.failed'));

            return $this->redirectToRoute('profile');
        }

        if ($result->isInvalidToken()) {
            $this->addFlash('error', $this->translate('profile.import.invalid_token'));

            return $this->redirectToRoute('profile');
        }

        if ($result->getImportedCount() === 0) {
            $this->addFlash('info', $this->translate('profile.import.no_new_photos'));

            return $this->redirectToRoute('profile');
        }

        $this->addFlash('success', $this->translate('profile.import.success', [
            '%count%' => $result->getImportedCount(),
        ]));

        return $this->redirectToRoute('profile');
    }
}
