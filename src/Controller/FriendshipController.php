<?php

namespace App\Controller;

use App\Entity\Friendship;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api', name: 'api_')]
class FriendshipController extends AbstractController
{
    #[Route('/friends', name: 'friends', methods: ['GET'])]
    public function getFriends(): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        if (!$user) {
            return $this->json(['error' => 'Unauthorized'], 401);
        }

        try {
            $sent = $user->getSentFriendships();
            $received = $user->getReceivedFriendships();

            $formatFriendship = function (Friendship $friendship) use ($user) {
                $isRequester = $friendship->getRequester() === $user;
                $otherUser = $isRequester ? $friendship->getAddressee() : $friendship->getRequester();

                return [
                    'id' => $friendship->getId(),
                    'status' => $friendship->getStatus(),
                    'direction' => $isRequester ? 'sent' : 'received',
                    'friend' => [
                        'userName' => $otherUser->getUserIdentifier(),
                        'profilePicture' => $otherUser->getProfilePicture() ?? '',
                        'status' => $otherUser->getStatus() ?? '',
                    ],
                ];
            };

            $friendships = [
                ...array_map($formatFriendship, $sent->toArray()),
                ...array_map($formatFriendship, $received->toArray()),
            ];

            return $this->json($friendships, 200);

        } catch (\Exception $e) {
            return $this->json(['error' => 'Failed to fetch friends list.'], 400);
        }
    }

    #[Route('/friends/request', name: 'send_request', methods: ['POST'])]
    public function sendRequest(Request $request, EntityManagerInterface $entityManager): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        if (!$user) {
            return $this->json(['error' => 'Unauthorized'], 401);
        }

        try {
            $data = json_decode($request->getContent(), true);
            $targetUserName = $data['userName'] ?? null;

            if (!$targetUserName) {
                return $this->json(['error' => 'User name is required.'], 400);
            }

            if ($user->getUserName() === $targetUserName) {
                return $this->json(['error' => 'You cannot send a friend request to yourself.'], 400);
            }

            $targetUser = $entityManager->getRepository(User::class)->findOneBy(['userName' => $targetUserName]);
            if (!$targetUser) {
                return $this->json(['error' => 'User not found.'], 404);
            }

            $friendshipRepository = $entityManager->getRepository(Friendship::class);

            $existing = $friendshipRepository->createQueryBuilder('f')
                ->where('(f.requester = :u1 AND f.addressee = :u2) OR (f.requester = :u2 AND f.addressee = :u1)')
                ->setParameter('u1', $user)
                ->setParameter('u2', $targetUser)
                ->getQuery()
                ->getOneOrNullResult();

            if ($existing) {
                if ($existing->getStatus() === 'accepted') {
                    return $this->json(['error' => 'You are already friends with this user.'], 400);
                }
                else if ($existing->getStatus() === 'pending') {
                    return $this->json(['error' => 'A friend request is already pending with this user.'], 400);
                }
            }

            $friendship = new Friendship();
            $friendship->setRequester($user);
            $friendship->setAddressee($targetUser);
            $friendship->setStatus('pending');
            $friendship->setCreatedAt(new \DateTimeImmutable());

            $entityManager->persist($friendship);
            $entityManager->flush();

            return $this->json(['message' => 'Friend request sent successfully!'], 201);
            
        } catch (\Exception $e) {
            return $this->json(['error' => 'An error occurred while sending the friend request.'], 400);
        }
    }

    #[Route('/friends/{id}/accept', name: 'accept', methods: ['PATCH'])]
    public function acceptRequest(int $id, EntityManagerInterface $entityManager): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        if (!$user) {
            return $this->json(['error' => 'Unauthorized'], 401);
        }

        try {
            $friendship = $entityManager->getRepository(Friendship::class)->find($id);
            if (!$friendship) {
                return $this->json(['error' => 'Friend request not found.'], 404);
            }

            if ($friendship->getAddressee() !== $user) {
                return $this->json(['error' => 'Unauthorized action.'], 403);
            }

            if ($friendship->getStatus() === 'accepted') {
                return $this->json(['error' => 'Friend request is already accepted.'], 400);
            }

            $friendship->setStatus('accepted');
            $entityManager->flush();

            return $this->json(['message' => 'Friend request accepted!'], 200);

        } catch (\Exception $e) {
            return $this->json(['error' => 'An error occurred while accepting the request.'], 400);
        }
    }

    #[Route('/friends/{id}', name: 'remove', methods: ['DELETE'])]
    public function removeFriendship(int $id, EntityManagerInterface $entityManager): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        if (!$user) {
            return $this->json(['error' => 'Unauthorized'], 401);
        }

        try {
            $friendship = $entityManager->getRepository(Friendship::class)->find($id);
            if (!$friendship) {
                return $this->json(['error' => 'Friendship or request not found.'], 404);
            }

            if ($friendship->getRequester() !== $user && $friendship->getAddressee() !== $user) {
                return $this->json(['error' => 'Unauthorized action.'], 403);
            }

            $entityManager->remove($friendship);
            $entityManager->flush();

            return $this->json(['message' => 'Friendship removed/rejected successfully.'], 200);

        } catch (\Exception $e) {
            return $this->json(['error' => 'An error occurred while removing the friendship.'], 400);
        }
    }
}