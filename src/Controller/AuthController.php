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

        $username = $data['username'] ?? '';
        $password = $data['password'] ?? '';

        $usernameLen = \strlen($username);
        if ($usernameLen < 5 || $usernameLen > 63) {
            return $this->json(['error' => 'Username must be between 5 and 63 characters long.'], 400);
        }

        if (!preg_match('/^[a-zA-Z0-9]+$/', $username)) {
            return $this->json(['error' => 'Username can only contain Latin letters and numbers.'], 400);
        }

        $passwordLen = \strlen($password);
        if ($passwordLen < 8 || $passwordLen > 63) {
            return $this->json(['error' => 'Password must be between 8 and 63 characters long.'], 400);
        }

        $existingUser = $entityManager->getRepository(User::class)->findOneBy(['username' => $username]);
        if ($existingUser) {
            return $this->json(['error' => 'This username is already taken.'], 400);
        }

        $user = new User();
        $user->setUsername($data['username']);
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

        $username = $data['username'] ?? '';
        $password = $data['password'] ?? '';

        $user = $entityManager->getRepository(User::class)->findOneBy(['username' => $username]);

        if (!$user || !$passwordHasher->isPasswordValid($user, $password)) {
            return $this->json(['error' => 'Invalid credentials.'], 401);
        }

        return $this->json([
            'message' => 'Logged in successfully!',
            'username' => $user->getUserIdentifier()
        ], 200);
    }
}

