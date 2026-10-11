<?php

namespace App\Controller;

use App\Entity\Conversation;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api', name: 'api_')]
class ChatController extends AbstractController
{
    #[Route('/conversation/{userName}', name: 'get_conversation', methods: ['GET'])]
    public function getOrCreateConversation(string $userName, EntityManagerInterface $entityManager): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        if (!$user) {
         return $this->json(['error' => 'Unauthorized'], 401);
        }

        $friend = $entityManager->getRepository(User::class)->findOneBy(['userName' => $userName]);

        if (!$friend) {
            return $this->json(['error' => 'User not found'], 404);
        }

        if ($user->getId() === $friend->getId()) {
            return $this->json(['error' => 'Cannot chat with yourself'], 400);
        }

        $conversation = $entityManager->getRepository(Conversation::class)
            ->createQueryBuilder('c')
            ->innerJoin('c.participants', 'u1')
            ->innerJoin('c.participants', 'u2')
            ->where('u1 = :user')
            ->andWhere('u2 = :friend')
            ->setParameter('user', $user)
            ->setParameter('friend', $friend)
            ->getQuery()
            ->getOneOrNullResult();

        if (!$conversation) {
            $conversation = new Conversation();
            $conversation->setCreatedAt(new \DateTimeImmutable());
            $conversation->addParticipant($user);
            $conversation->addParticipant($friend);

            $entityManager->persist($conversation);
            $entityManager->flush();
        }

        $messages = [];
        foreach ($conversation->getMessages() as $message) {
            $messages[] = [
                'id' => $message->getId(),
                'content' => $message->getContent(),
                'createdAt' => $message->getCreatedAt()->format(\DateTime::ATOM),
                'sender' => [
                    'userName' => $message->getSender()->getUserIdentifier(),
                ],
            ];
        }

        return $this->json([
            'conversationId' => $conversation->getId(),
            'messages' => $messages,
        ], 200);
    }
}
