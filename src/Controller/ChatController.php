<?php

namespace App\Controller;

use App\Entity\Conversation;
use App\Entity\User;
use App\Entity\Message;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
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
            $direction = $message->getSender()->getId() === $user->getId() ? 'sent' : 'received';
            $messages[] = [
                'id' => $message->getId(),
                'content' => $message->getContent(),
                'createdAt' => $message->getCreatedAt()->format(\DateTime::ATOM),
                'direction' => $direction,
            ];
        }

        return $this->json([
            'conversationId' => $conversation->getId(),
            'messages' => $messages,
        ], 200);
    }

    #[Route('/conversation/{id}/message', name: 'send_message', methods: ['POST'])]
    public function sendMessage(
        Conversation $conversation,
        Request $request,
        EntityManagerInterface $entityManager
    ): JsonResponse {
        /** @var User $user */
        $user = $this->getUser();

        if (!$user) {
            return $this->json(['error' => 'Unauthorized'], 401);
        }

        if (!$conversation->getParticipants()->contains($user)) {
            return $this->json(['error' => 'Access denied'], 403);
        }

        $data = json_decode($request->getContent(), true);
        $content = trim($data['content'] ?? '');

        if ($content === '') {
            return $this->json(['error' => 'Message cannot be empty'], 400);
        }

        $message = new Message();
        $message->setContent($content);
        $message->setCreatedAt(new \DateTimeImmutable());
        $message->setSender($user);
        $message->setConversation($conversation);

        $entityManager->persist($message);
        $entityManager->flush();

        return $this->json([
            'id' => $message->getId(),
            'content' => $message->getContent(),
            'createdAt' => $message->getCreatedAt()->format(\DateTime::ATOM),
            'direction' => 'sent',
        ], 201);
    }

    #[Route('/conversation/{id}/poll', name: 'poll_conversation', methods: ['GET'])]
    public function pollMessages(
        Conversation $conversation,
        Request $request,
        EntityManagerInterface $entityManager
    ): JsonResponse {
        /** @var User $user */
        $user = $this->getUser();

        if (!$user) {
            return $this->json(['error' => 'Unauthorized'], 401);
        }

        if (!$conversation->getParticipants()->contains($user)) {
            return $this->json(['error' => 'Access denied'], 403);
        }

        $lastKnownId = (int) $request->query->get('after', 0);

        $newMessagesQuery = $entityManager->createQueryBuilder()
            ->select('m', 's')
            ->from(Message::class, 'm')
            ->join('m.sender', 's')
            ->where('m.conversation = :conversation')
            ->andWhere('m.id > :lastKnownId')
            ->setParameter('conversation', $conversation)
            ->setParameter('lastKnownId', $lastKnownId)
            ->orderBy('m.id', 'ASC')
            ->getQuery();

        $newMessages = $newMessagesQuery->getResult();

        $payload = [];
        foreach ($newMessages as $message) {
            $direction = $message->getSender()->getId() === $user->getId() ? 'sent' : 'received';
            $payload[] = [
                'id' => $message->getId(),
                'content' => $message->getContent(),
                'createdAt' => $message->getCreatedAt()->format(\DateTime::ATOM),
                'direction' => $direction,
            ];
        }

        return $this->json($payload, 200);
    }
}
