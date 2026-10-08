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

    #[Route('/profile-picture', name: 'profile_picture', methods: ['POST'])]
    public function uploadProfilePicture(
        Request $request, 
        EntityManagerInterface $entityManager
    ): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        if (!$user) {
            return $this->json(['error' => 'Unauthorized'], 401);
        }

        $file = $request->files->get('profilePicture');
        $remove = $request->request->get('remove') === 'true';

        if (!$file && !$remove) {
            return $this->json(['error' => 'No file uploaded or actions specified.'], 400);
        }

        $oldPicture = $user->getProfilePicture();
        if ($oldPicture) {
            $oldFilePath = $this->getParameter('kernel.project_dir') . '/public' . $oldPicture;
            if (file_exists($oldFilePath)) {
                @unlink($oldFilePath);
            }
        }

        if ($remove) {
            $user->setProfilePicture(null);
            $entityManager->flush();

            return $this->json([
                'message' => 'Profile picture removed successfully!',
                'profilePicture' => ''
            ], 200);
        }

        $allowedMimeTypes = ['image/jpeg', 'image/png', 'image/webp'];
        if (!\in_array($file->getMimeType(), $allowedMimeTypes)) {
            return $this->json(['error' => 'Invalid file type. Only JPG, PNG and WEBP are allowed.'], 400);
        }

        $uploadsDirectory = $this->getParameter('kernel.project_dir') . '/public/uploads/avatars';

        if (!is_dir($uploadsDirectory)) {
            mkdir($uploadsDirectory, 0777, true);
        }

        $extension = $file->guessExtension() ?? 'jpg';
        $fileName = 'profile_picture_user_' . $user->getId() . '.' . $extension;

        try {
            $file->move($uploadsDirectory, $fileName);
        } catch (\Exception $e) {
            return $this->json(['error' => 'Failed to upload file.'], 500);
        }

        $relativePath = "/uploads/avatars/{$fileName}";
        $user->setProfilePicture($relativePath);
        $entityManager->flush();

        return $this->json([
            'message' => 'Profile picture updated successfully!',
            'profilePicture' => $relativePath
        ], 200);
    }
}