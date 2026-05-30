<?php

namespace App\Controller;

use App\Entity\Commodity;
use App\Entity\Storeroom;
use App\Entity\StoreroomItem;
use App\Entity\StoreroomTicket;
use App\Service\Access;
use App\Service\Jdate;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

class StoreroomDashboardController extends AbstractController
{
    #[Route('/api/storeroom/dashboard/stats', name: 'app_storeroom_dashboard_stats', methods: ['GET'])]
    public function stats(
        Access $access,
        EntityManagerInterface $em,
        Jdate $jdate
    ): JsonResponse {
        $acc = $access->hasRole('store');
        if (!$acc)
            throw $this->createAccessDeniedException();

        $bid = $acc['bid'];

        // Count all storeroom items for this business
        $totalInventoryItems = $em->getRepository(StoreroomItem::class)->count(['bid' => $bid]);

        // Low stock: commodities where orderPoint is set
        $qb = $em->createQueryBuilder();
        $qb->select('c.id, c.name, c.orderPoint,
                     SUM(CASE WHEN si.type = \'input\' THEN CAST(si.count AS HIDDEN) ELSE 0 END) - SUM(CASE WHEN si.type = \'output\' THEN CAST(si.count AS HIDDEN) ELSE 0 END) as currentStock')
            ->from(Commodity::class, 'c')
            ->leftJoin(StoreroomItem::class, 'si', 'WITH', 'si.commodity = c AND si.bid = :bid')
            ->where('c.bid = :bid')
            ->andWhere('c.orderPoint IS NOT NULL')
            ->setParameter('bid', $bid)
            ->groupBy('c.id');

        $lowStockCount = 0;
        try {
            $commodities = $qb->getQuery()->getResult();
            foreach ($commodities as $c) {
                $currentStock = (float)($c['currentStock'] ?? 0);
                $orderPoint = (float)($c['orderPoint'] ?? 0);
                if ($currentStock <= $orderPoint) {
                    $lowStockCount++;
                }
            }
        } catch (\Exception $e) {
            $lowStockCount = 0;
        }

        // Expiry alerts: items with expiryDate not null
        $expiryAlerts30 = $em->createQueryBuilder()
            ->select('COUNT(si.id)')
            ->from(StoreroomItem::class, 'si')
            ->where('si.bid = :bid')
            ->andWhere('si.expiryDate IS NOT NULL')
            ->setParameter('bid', $bid)
            ->getQuery()
            ->getSingleScalarResult();

        // Today's transactions
        $today = $jdate->jdate('Y/m/d', time());
        $todayTransactions = $em->createQueryBuilder()
            ->select('COUNT(t.id)')
            ->from(StoreroomTicket::class, 't')
            ->where('t.bid = :bid')
            ->andWhere('t.date = :today')
            ->setParameter('bid', $bid)
            ->setParameter('today', $today)
            ->getQuery()
            ->getSingleScalarResult();

        // Storerooms list
        $storerooms = $em->getRepository(Storeroom::class)->findBy(['bid' => $bid, 'active' => true]);
        $storeroomList = [];
        foreach ($storerooms as $sr) {
            $itemCount = $em->getRepository(StoreroomItem::class)->count(['Storeroom' => $sr, 'bid' => $bid]);
            $storeroomList[] = [
                'id' => $sr->getId(),
                'name' => $sr->getName(),
                'itemCount' => $itemCount,
            ];
        }

        return $this->json([
            'result' => 1,
            'totalInventoryItems' => (int)$totalInventoryItems,
            'lowStockCount' => (int)$lowStockCount,
            'expiryAlerts30' => (int)$expiryAlerts30,
            'todayTransactions' => (int)$todayTransactions,
            'storerooms' => $storeroomList,
        ]);
    }

    #[Route('/api/storeroom/dashboard/recent', name: 'app_storeroom_dashboard_recent', methods: ['POST'])]
    public function recent(
        Request $request,
        Access $access,
        EntityManagerInterface $em
    ): JsonResponse {
        $acc = $access->hasRole('store');
        if (!$acc)
            throw $this->createAccessDeniedException();

        $bid = $acc['bid'];

        $tickets = $em->createQueryBuilder()
            ->select('t')
            ->from(StoreroomTicket::class, 't')
            ->where('t.bid = :bid')
            ->setParameter('bid', $bid)
            ->orderBy('t.id', 'DESC')
            ->setMaxResults(20)
            ->getQuery()
            ->getResult();

        $result = [];
        foreach ($tickets as $ticket) {
            $itemCount = $ticket->getStoreroomItems()->count();
            $result[] = [
                'id' => $ticket->getId(),
                'code' => $ticket->getCode(),
                'date' => $ticket->getDate(),
                'type' => $ticket->getType(),
                'typeString' => $ticket->getTypeString(),
                'storeroom' => $ticket->getStoreroom() ? $ticket->getStoreroom()->getName() : null,
                'person' => $ticket->getPerson() ? $ticket->getPerson()->getNikename() : null,
                'itemCount' => $itemCount,
                'submitter' => $ticket->getSubmitter() ? $ticket->getSubmitter()->getFullName() : null,
            ];
        }

        return $this->json(['result' => 1, 'items' => $result]);
    }
}
