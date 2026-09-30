<?php

namespace App\Controller;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api', name: 'api_')]
class UserController extends AbstractController
{
    #[Route('/me', name: 'me', methods: ['GET'])]
    public function getCurrentUser(): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        if (!$user) {
            return $this->json(['error' => 'Unauthorized'], 401);
        }

        return $this->json([
            'userName' => $user->getUserIdentifier(),
            'status' => $user->getStatus() ?? 'Hey there, I am using Dieter-Chat',
            'profilePicture' => $user->getProfilePicture() ?? '',
        ]);
    }

    #[Route('/status', name: 'update_status', methods: ['PATCH'])]
    public function updateStatus(Request $request, EntityManagerInterface $entityManager): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        if (!$user) {
            return $this->json(['error' => 'Unauthorized'], 401);
        }

        $data = json_decode($request->getContent(), true);
        $newStatus = $data['status'] ?? null;

        if ($newStatus === null) {
            return $this->json(['error' => 'Status cannot be null.'], 400);
        }

        if (\strlen($newStatus) > 255) {
            return $this->json(['error' => 'Status is too long (max 255 characters).'], 400);
        }

        $user->setStatus($newStatus);
        $entityManager->flush();

        return $this->json([
            'message' => 'Status successfully updated!',
            'status' => $user->getStatus()
        ], 200);
    }
}