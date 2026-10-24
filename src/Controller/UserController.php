<?php

namespace App\Controller;

use App\Entity\User;
use App\Entity\Message;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

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
    ): JsonResponse {
        /** @var User $user */
        $user = $this->getUser();

        if (!$user) {
            return $this->json(['error' => 'Unauthorized'], 401);
        }

        $data = json_decode($request->getContent(), true);
        $base64Image = $data['profilePicture'] ?? null;
        $remove = $data['remove'] ?? false;

        if ($remove) {
            $user->setProfilePicture(null);
            $entityManager->flush();

            return $this->json([
                'message' => 'Profile picture removed successfully!',
                'profilePicture' => ''
            ], 200);
        }

        if (!$base64Image) {
            return $this->json(['error' => 'No profile picture data provided.'], 400);
        }

        if (mb_strlen($base64Image) > 3000000) {
            return $this->json(['error' => 'File is too large. Maximum size is 2MB.'], 400);
        }

        if (!preg_match('/^data:image\/(jpeg|jpg|png|webp);base64,/', $base64Image, $matches)) {
            return $this->json(['error' => 'Invalid file type. Only JPG, PNG and WEBP are allowed.'], 400);
        }

        $base64Data = substr($base64Image, strpos($base64Image, ',') + 1);
        $decodedData = base64_decode($base64Data, true);

        if ($decodedData === false) {
            return $this->json(['error' => 'Invalid image encoding.'], 400);
        }

        $user->setProfilePicture($base64Image);
        $entityManager->flush();

        return $this->json([
            'message' => 'Profile picture updated successfully!',
            'profilePicture' => $base64Image
        ], 200);
    }

    #[Route('/account', name: 'delete_account', methods: ['DELETE'])]
    public function deleteAccount(
        EntityManagerInterface $entityManager,
        Request $request,
        TokenStorageInterface $tokenStorage
    ): JsonResponse {
        /** @var User $user */
        $user = $this->getUser();

        if (!$user) {
            return $this->json(['error' => 'Unauthorized'], 401);
        }

        $entityManager->remove($user);
        $entityManager->flush();

        $tokenStorage->setToken(null);

        $session = $request->getSession();
        if ($session->isStarted()) {
            $session->invalidate();
        }

        return $this->json([
            'message' => 'Account and all related data successfully deleted.'
        ], 200);
    }

    #[Route('/heartbeat', name: 'heartbeat', methods: ['POST'])]
    public function heartbeat(EntityManagerInterface $entityManager): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        if (!$user) {
            return $this->json(['error' => 'Unauthorized'], 401);
        }

        $user->setLastActiveAt(new \DateTimeImmutable());
        $entityManager->flush();

        return $this->json(['status' => 'ok'], 200);
    }

    #[Route('/statistics', name: 'statistics', methods: ['GET'])]
    public function getStatistics(EntityManagerInterface $entityManager): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        if (!$user) {
            return $this->json(['error' => 'Unauthorized'], 401);
        }

        $sentCount = $entityManager->createQueryBuilder()
            ->select('COUNT(m.id)')
            ->from(Message::class, 'm')
            ->where('m.sender = :user')
            ->setParameter('user', $user)
            ->getQuery()
            ->getSingleScalarResult();

        $receivedCount = $entityManager->createQueryBuilder()
            ->select('COUNT(m.id)')
            ->from(Message::class, 'm')
            ->join('m.conversation', 'c')
            ->join('c.participants', 'p')
            ->where('p = :user')
            ->andWhere('m.sender != :user')
            ->setParameter('user', $user)
            ->getQuery()
            ->getSingleScalarResult();

        $favoriteFriendQuery = $entityManager->createQueryBuilder()
            ->select('f.userName, f.profilePicture, f.status, COUNT(m.id) as msgCount')
            ->from(Message::class, 'm')
            ->join('m.conversation', 'c')
            ->join('c.participants', 'f')
            ->where(':user MEMBER OF c.participants')
            ->andWhere('f != :user')
            ->setParameter('user', $user)
            ->groupBy('f.id', 'f.userName', 'f.profilePicture', 'f.status')
            ->orderBy('msgCount', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        $today = new \DateTimeImmutable('today');
        $startDate = $today->modify('-13 days');

        $fetchTimelineData = function (bool $isSent) use ($entityManager, $user, $startDate) {
            $qb = $entityManager->createQueryBuilder()
                ->select("SUBSTRING(m.createdAt, 1, 10) as msgDate, COUNT(m.id) as cnt")
                ->from(Message::class, 'm')
                ->andWhere('m.createdAt >= :startDate')
                ->setParameter('startDate', $startDate);

            if ($isSent) {
                $qb->where('m.sender = :user')->setParameter('user', $user);
            } else {
                $qb->join('m.conversation', 'c')
                    ->join('c.participants', 'p')
                    ->where('p = :user')
                    ->andWhere('m.sender != :user')
                    ->setParameter('user', $user);
            }

            $results = $qb->groupBy('msgDate')->getQuery()->getResult();

            $map = [];
            foreach ($results as $row) {
                $map[$row['msgDate']] = (int) $row['cnt'];
            }
            return $map;
        };

        $sentTimelineMap = $fetchTimelineData(true);
        $receivedTimelineMap = $fetchTimelineData(false);

        $timelineQuery = [];
        $currentDate = $startDate;
        while ($currentDate <= $today) {
            $dateKey = $currentDate->format('Y-m-d');

            $timelineQuery[] = [
                'dateOfMessage' => $dateKey,
                'sentCount' => $sentTimelineMap[$dateKey] ?? 0,
                'receivedCount' => $receivedTimelineMap[$dateKey] ?? 0,
            ];

            $currentDate = $currentDate->modify('+1 day');
        }

        $activeDays = [...array_keys($sentTimelineMap), ...array_keys($receivedTimelineMap)];
        $activeDaysMap = array_fill_keys($activeDays, true);

        $yesterday = $today->modify('-1 day');
        $todayStr = $today->format('Y-m-d');
        $yesterdayStr = $yesterday->format('Y-m-d');

        $streak = 0;
        $currentCheckDate = null;

        if (isset($activeDaysMap[$todayStr])) {
            $currentCheckDate = $today;
        } elseif (isset($activeDaysMap[$yesterdayStr])) {
            $currentCheckDate = $yesterday;
        }

        while ($currentCheckDate !== null) {
            $dateKey = $currentCheckDate->format('Y-m-d');
            if (isset($activeDaysMap[$dateKey])) {
                $streak++;
                $currentCheckDate = $currentCheckDate->modify('-1 day');
            } else {
                break;
            }
        }

        return $this->json([
            'totalSent' => (int) $sentCount,
            'totalReceived' => (int) $receivedCount,
            'streak' => $streak,
            'favoriteFriend' => $favoriteFriendQuery ? [
                'userName' => $favoriteFriendQuery['userName'],
                'messageCount' => (int) $favoriteFriendQuery['msgCount'],
                'status' => $favoriteFriendQuery['status'],
                'profilePicture' => $favoriteFriendQuery['profilePicture'] ?? '',
            ] : null,
            'timeline' => $timelineQuery,
        ], 200);
    }
}
