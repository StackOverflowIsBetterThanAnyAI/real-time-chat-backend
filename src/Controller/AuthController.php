<?php

namespace App\Controller;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[Route('/api', name: 'api_')]
class AuthController extends AbstractController
{
    #[Route('/register', name: 'register', methods: ['POST'])]
    public function register(
        Request $request, 
        UserPasswordHasherInterface $passwordHasher, 
        EntityManagerInterface $entityManager
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);

        $userName = $data['userName'] ?? '';
        $password = $data['password'] ?? '';

        $userNameLen = \strlen($userName);
        if ($userNameLen < 5 || $userNameLen > 63) {
            return $this->json(['error' => 'User name must be between 5 and 63 characters long.'], 400);
        }

        if (!preg_match('/^[a-zA-Z0-9]+$/', $userName)) {
            return $this->json(['error' => 'User name can only contain Latin letters and numbers.'], 400);
        }

        $passwordLen = \strlen($password);
        if ($passwordLen < 8 || $passwordLen > 63) {
            return $this->json(['error' => 'Password must be between 8 and 63 characters long.'], 400);
        }

        $existingUser = $entityManager->getRepository(User::class)->findOneBy(['userName' => $userName]);
        if ($existingUser) {
            return $this->json(['error' => 'This user name is already taken.'], 400);
        }

        $user = new User();
        $user->setUserName($data['userName']);
        $user->setCreatedAt(new \DateTimeImmutable());

        $hashedPassword = $passwordHasher->hashPassword(
            $user,
            $data['password']
        );
        $user->setPassword($hashedPassword);

        $entityManager->persist($user);
        $entityManager->flush();

        return $this->json(['message' => 'User successfully registered!'], 201);
    }

    #[Route('/login', name: 'login', methods: ['POST'])]
    public function login(Request $request, UserPasswordHasherInterface $passwordHasher, EntityManagerInterface $entityManager): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        $userName = $data['userName'] ?? '';
        $password = $data['password'] ?? '';

        $user = $entityManager->getRepository(User::class)->findOneBy(['userName' => $userName]);

        if (!$user || !$passwordHasher->isPasswordValid($user, $password)) {
            return $this->json(['error' => 'Invalid credentials.'], 401);
        }

        return $this->json([
            'message' => 'Logged in successfully!',
            'userName' => $user->getUserIdentifier()
        ], 200);
    }
}

