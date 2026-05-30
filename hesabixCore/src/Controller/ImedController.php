<?php

namespace App\Controller;

use App\Entity\StoreroomItem;
use App\Service\Access;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

class ImedController extends AbstractController
{
    #[Route('/api/storeroom/imed/list', name: 'app_storeroom_imed_list', methods: ['GET'])]
    public function list(
        Access $access,
        EntityManagerInterface $em
    ): JsonResponse {
        $acc = $access->hasRole('store');
        if (!$acc)
            throw $this->createAccessDeniedException();

        $items = $em->createQueryBuilder()
            ->select('si')
            ->from(StoreroomItem::class, 'si')
            ->where('si.bid = :bid')
            ->andWhere('(si.imedStatus IS NULL OR si.imedStatus != :registered)')
            ->setParameter('bid', $acc['bid'])
            ->setParameter('registered', 'registered')
            ->orderBy('si.id', 'DESC')
            ->getQuery()
            ->getResult();

        $result = [];
        foreach ($items as $item) {
            $result[] = [
                'id' => $item->getId(),
                'imedStatus' => $item->getImedStatus(),
                'imedRef' => $item->getImedRef(),
                'lotNo' => $item->getLotNo(),
                'count' => $item->getCount(),
                'type' => $item->getType(),
                'commodity' => $item->getCommodity() ? [
                    'id' => $item->getCommodity()->getId(),
                    'name' => $item->getCommodity()->getName(),
                    'code' => $item->getCommodity()->getCode(),
                ] : null,
                'ticket' => $item->getTicket() ? [
                    'id' => $item->getTicket()->getId(),
                    'code' => $item->getTicket()->getCode(),
                    'date' => $item->getTicket()->getDate(),
                ] : null,
            ];
        }

        return $this->json(['result' => 1, 'items' => $result]);
    }

    #[Route('/api/storeroom/imed/mark/{id}', name: 'app_storeroom_imed_mark', methods: ['POST'])]
    public function mark(
        int $id,
        Request $request,
        Access $access,
        EntityManagerInterface $em
    ): JsonResponse {
        $acc = $access->hasRole('store');
        if (!$acc)
            throw $this->createAccessDeniedException();

        $params = [];
        if ($content = $request->getContent()) {
            $params = json_decode($content, true);
        }

        $item = $em->getRepository(StoreroomItem::class)->findOneBy([
            'id' => $id,
            'bid' => $acc['bid'],
        ]);
        if (!$item) {
            return $this->json(['result' => -1, 'message' => 'Item not found']);
        }

        $item->setImedStatus('registered');
        $item->setImedRef($params['imedRef'] ?? null);
        $em->flush();

        return $this->json(['result' => 1]);
    }

    #[Route('/api/storeroom/imed/bulk-mark', name: 'app_storeroom_imed_bulk_mark', methods: ['POST'])]
    public function bulkMark(
        Request $request,
        Access $access,
        EntityManagerInterface $em
    ): JsonResponse {
        $acc = $access->hasRole('store');
        if (!$acc)
            throw $this->createAccessDeniedException();

        $params = [];
        if ($content = $request->getContent()) {
            $params = json_decode($content, true);
        }

        $ids = $params['ids'] ?? [];
        if (empty($ids) || !is_array($ids)) {
            return $this->json(['result' => -1, 'message' => 'ids array is required']);
        }

        $updated = 0;
        foreach ($ids as $id) {
            $item = $em->getRepository(StoreroomItem::class)->findOneBy([
                'id' => $id,
                'bid' => $acc['bid'],
            ]);
            if ($item) {
                $item->setImedStatus('registered');
                $updated++;
            }
        }

        $em->flush();

        return $this->json(['result' => 1, 'updated' => $updated]);
    }

    #[Route('/api/storeroom/imed/stats', name: 'app_storeroom_imed_stats', methods: ['GET'])]
    public function stats(
        Access $access,
        EntityManagerInterface $em
    ): JsonResponse {
        $acc = $access->hasRole('store');
        if (!$acc)
            throw $this->createAccessDeniedException();

        $bid = $acc['bid'];

        $notRegistered = $em->createQueryBuilder()
            ->select('COUNT(si.id)')
            ->from(StoreroomItem::class, 'si')
            ->where('si.bid = :bid')
            ->andWhere('si.imedStatus = :status OR si.imedStatus IS NULL')
            ->setParameter('bid', $bid)
            ->setParameter('status', 'not_registered')
            ->getQuery()
            ->getSingleScalarResult();

        $pending = $em->createQueryBuilder()
            ->select('COUNT(si.id)')
            ->from(StoreroomItem::class, 'si')
            ->where('si.bid = :bid')
            ->andWhere('si.imedStatus = :status')
            ->setParameter('bid', $bid)
            ->setParameter('status', 'pending')
            ->getQuery()
            ->getSingleScalarResult();

        $registered = $em->createQueryBuilder()
            ->select('COUNT(si.id)')
            ->from(StoreroomItem::class, 'si')
            ->where('si.bid = :bid')
            ->andWhere('si.imedStatus = :status')
            ->setParameter('bid', $bid)
            ->setParameter('status', 'registered')
            ->getQuery()
            ->getSingleScalarResult();

        return $this->json([
            'result' => 1,
            'stats' => [
                'not_registered' => (int)$notRegistered,
                'pending' => (int)$pending,
                'registered' => (int)$registered,
            ],
        ]);
    }
}
