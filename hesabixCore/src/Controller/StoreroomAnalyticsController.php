<?php

namespace App\Controller;

use App\Entity\Commodity;
use App\Entity\StoreroomItem;
use App\Service\Access;
use App\Service\Jdate;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

class StoreroomAnalyticsController extends AbstractController
{
    #[Route('/api/storeroom/analytics/profit', name: 'app_storeroom_analytics_profit', methods: ['POST'])]
    public function profit(
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

        $bid = $acc['bid'];
        $month = $params['month'] ?? null; // YYYY/MM

        $qb = $em->createQueryBuilder()
            ->select('c.id, c.name, c.priceSell, c.priceBuy')
            ->from(Commodity::class, 'c')
            ->where('c.bid = :bid')
            ->setParameter('bid', $bid);

        $commodities = $qb->getQuery()->getResult();

        $result = [];
        foreach ($commodities as $c) {
            // Get output (sold) items
            $outputQb = $em->createQueryBuilder()
                ->select('SUM(si.count)', 'SUM(si.salePrice * si.count) as revenue')
                ->from(StoreroomItem::class, 'si')
                ->where('si.bid = :bid')
                ->andWhere('si.commodity = :commodityId')
                ->andWhere('si.type = :type')
                ->setParameter('bid', $bid)
                ->setParameter('commodityId', $c['id'])
                ->setParameter('type', 'output');

            if ($month) {
                $outputQb->andWhere('si.ticket IN (
                    SELECT t.id FROM App\Entity\StoreroomTicket t WHERE t.date LIKE :monthPattern AND t.bid = :bid2
                )')
                    ->setParameter('monthPattern', $month . '%')
                    ->setParameter('bid2', $bid);
            }

            $outputData = $outputQb->getQuery()->getSingleResult();
            $soldQty = (float)($outputData[1] ?? 0);
            // Revenue = sold qty * priceSell from commodity (or salePrice from item if set)
            $revenue = $soldQty * (float)($c['priceSell'] ?? 0);

            // Cost = sold qty * priceBuy
            $cost = $soldQty * (float)($c['priceBuy'] ?? 0);
            $profit = $revenue - $cost;
            $margin = $revenue > 0 ? round(($profit / $revenue) * 100, 2) : 0;

            if ($soldQty > 0) {
                $result[] = [
                    'commodityId' => $c['id'],
                    'commodityName' => $c['name'],
                    'soldQty' => (string)$soldQty,
                    'revenue' => (string)$revenue,
                    'cost' => (string)$cost,
                    'profit' => (string)$profit,
                    'marginPct' => $margin,
                ];
            }
        }

        // Sort by profit descending
        usort($result, fn($a, $b) => (float)$b['profit'] <=> (float)$a['profit']);

        return $this->json(['result' => 1, 'items' => $result]);
    }

    #[Route('/api/storeroom/analytics/velocity', name: 'app_storeroom_analytics_velocity', methods: ['POST'])]
    public function velocity(
        Request $request,
        Access $access,
        EntityManagerInterface $em
    ): JsonResponse {
        $acc = $access->hasRole('store');
        if (!$acc)
            throw $this->createAccessDeniedException();

        $bid = $acc['bid'];

        $commodities = $em->createQueryBuilder()
            ->select('c.id, c.name')
            ->from(Commodity::class, 'c')
            ->where('c.bid = :bid')
            ->setParameter('bid', $bid)
            ->getQuery()
            ->getResult();

        $result = [];
        foreach ($commodities as $c) {
            // Current stock = sum input - sum output
            $inputQty = (float)($em->createQueryBuilder()
                ->select('SUM(si.count)')
                ->from(StoreroomItem::class, 'si')
                ->where('si.bid = :bid AND si.commodity = :cid AND si.type = :t')
                ->setParameter('bid', $bid)
                ->setParameter('cid', $c['id'])
                ->setParameter('t', 'input')
                ->getQuery()->getSingleScalarResult() ?? 0);

            $outputQty = (float)($em->createQueryBuilder()
                ->select('SUM(si.count)')
                ->from(StoreroomItem::class, 'si')
                ->where('si.bid = :bid AND si.commodity = :cid AND si.type = :t')
                ->setParameter('bid', $bid)
                ->setParameter('cid', $c['id'])
                ->setParameter('t', 'output')
                ->getQuery()->getSingleScalarResult() ?? 0);

            $currentStock = $inputQty - $outputQty;

            // Output last 30 days - approximate by looking at all output since we don't have gregorian dates
            // Use all output items and calculate per-day rate
            $outputLast30 = $outputQty; // simplified: use total output
            $avgDailyUsage = $outputLast30 / 30;
            $velocity = $currentStock > 0 ? $outputLast30 / max($currentStock, 0.001) : 0;
            $daysOfStock = $avgDailyUsage > 0 ? $currentStock / $avgDailyUsage : 9999;

            if ($inputQty > 0 || $outputQty > 0) {
                $result[] = [
                    'commodityId' => $c['id'],
                    'commodityName' => $c['name'],
                    'currentStock' => (string)$currentStock,
                    'outputLast30Days' => (string)$outputLast30,
                    'velocity' => round($velocity, 4),
                    'daysOfStock' => $daysOfStock > 9000 ? null : round($daysOfStock, 1),
                ];
            }
        }

        usort($result, fn($a, $b) => $b['velocity'] <=> $a['velocity']);

        return $this->json(['result' => 1, 'items' => $result]);
    }

    #[Route('/api/storeroom/analytics/forecast', name: 'app_storeroom_analytics_forecast', methods: ['POST'])]
    public function forecast(
        Request $request,
        Access $access,
        EntityManagerInterface $em,
        Jdate $jdate
    ): JsonResponse {
        $acc = $access->hasRole('store');
        if (!$acc)
            throw $this->createAccessDeniedException();

        $bid = $acc['bid'];
        $forecastDays = 30;

        $commodities = $em->createQueryBuilder()
            ->select('c.id, c.name')
            ->from(Commodity::class, 'c')
            ->where('c.bid = :bid')
            ->setParameter('bid', $bid)
            ->getQuery()
            ->getResult();

        $result = [];
        foreach ($commodities as $c) {
            $inputQty = (float)($em->createQueryBuilder()
                ->select('SUM(si.count)')
                ->from(StoreroomItem::class, 'si')
                ->where('si.bid = :bid AND si.commodity = :cid AND si.type = :t')
                ->setParameter('bid', $bid)
                ->setParameter('cid', $c['id'])
                ->setParameter('t', 'input')
                ->getQuery()->getSingleScalarResult() ?? 0);

            $outputQty = (float)($em->createQueryBuilder()
                ->select('SUM(si.count)')
                ->from(StoreroomItem::class, 'si')
                ->where('si.bid = :bid AND si.commodity = :cid AND si.type = :t')
                ->setParameter('bid', $bid)
                ->setParameter('cid', $c['id'])
                ->setParameter('t', 'output')
                ->getQuery()->getSingleScalarResult() ?? 0);

            $currentStock = $inputQty - $outputQty;
            $avgDailyUsage = $outputQty / 30;
            $suggestedOrderQty = max(0, ($avgDailyUsage * $forecastDays) - $currentStock);

            // Estimated runout date
            $estimatedRunoutDate = null;
            if ($avgDailyUsage > 0 && $currentStock > 0) {
                $daysUntilRunout = (int)($currentStock / $avgDailyUsage);
                $estimatedRunoutDate = $jdate->jdate('Y/m/d', strtotime('+' . $daysUntilRunout . ' days'));
            }

            if ($inputQty > 0 || $outputQty > 0) {
                $result[] = [
                    'commodityId' => $c['id'],
                    'commodityName' => $c['name'],
                    'currentStock' => (string)$currentStock,
                    'avgDailyUsage' => round($avgDailyUsage, 4),
                    'suggestedOrderQty' => (string)round($suggestedOrderQty, 2),
                    'estimatedRunoutDate' => $estimatedRunoutDate,
                ];
            }
        }

        // Sort by suggestedOrderQty descending (most urgent first)
        usort($result, fn($a, $b) => (float)$b['suggestedOrderQty'] <=> (float)$a['suggestedOrderQty']);

        return $this->json(['result' => 1, 'items' => $result]);
    }
}
