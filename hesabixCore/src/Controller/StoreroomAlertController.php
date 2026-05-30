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

class StoreroomAlertController extends AbstractController
{
    #[Route('/api/storeroom/alerts/list', name: 'app_storeroom_alerts_list', methods: ['GET'])]
    public function list(
        Access $access,
        EntityManagerInterface $em,
        Jdate $jdate
    ): JsonResponse {
        $acc = $access->hasRole('store');
        if (!$acc)
            throw $this->createAccessDeniedException();

        $bid = $acc['bid'];

        // Low stock commodities
        $commodities = $em->createQueryBuilder()
            ->select('c')
            ->from(Commodity::class, 'c')
            ->where('c.bid = :bid')
            ->andWhere('c.orderPoint IS NOT NULL')
            ->setParameter('bid', $bid)
            ->getQuery()
            ->getResult();

        $lowStock = [];
        foreach ($commodities as $commodity) {
            // Sum input items
            $inputQty = $em->createQueryBuilder()
                ->select('SUM(si.count)')
                ->from(StoreroomItem::class, 'si')
                ->where('si.bid = :bid')
                ->andWhere('si.commodity = :commodity')
                ->andWhere('si.type = :type')
                ->setParameter('bid', $bid)
                ->setParameter('commodity', $commodity)
                ->setParameter('type', 'input')
                ->getQuery()
                ->getSingleScalarResult() ?? 0;

            // Sum output items
            $outputQty = $em->createQueryBuilder()
                ->select('SUM(si.count)')
                ->from(StoreroomItem::class, 'si')
                ->where('si.bid = :bid')
                ->andWhere('si.commodity = :commodity')
                ->andWhere('si.type = :type')
                ->setParameter('bid', $bid)
                ->setParameter('commodity', $commodity)
                ->setParameter('type', 'output')
                ->getQuery()
                ->getSingleScalarResult() ?? 0;

            $currentStock = (float)$inputQty - (float)$outputQty;
            $orderPoint = (float)$commodity->getOrderPoint();

            if ($currentStock <= $orderPoint) {
                $lowStock[] = [
                    'commodityId' => $commodity->getId(),
                    'commodityName' => $commodity->getName(),
                    'currentStock' => (string)$currentStock,
                    'orderPoint' => $commodity->getOrderPoint(),
                    'deficit' => (string)($orderPoint - $currentStock),
                ];
            }
        }

        // Near expiry items
        $currentDateStr = $jdate->jdate('Y/m/d', time());

        $expiryItems = $em->createQueryBuilder()
            ->select('si')
            ->from(StoreroomItem::class, 'si')
            ->where('si.bid = :bid')
            ->andWhere('si.expiryDate IS NOT NULL')
            ->setParameter('bid', $bid)
            ->orderBy('si.expiryDate', 'ASC')
            ->getQuery()
            ->getResult();

        $nearExpiry = [];
        foreach ($expiryItems as $item) {
            $expiryDate = $item->getExpiryDate();
            if (!$expiryDate) continue;

            // Simple lexicographic comparison for jalali YYYY/MM/DD
            $urgency = 'future';
            if ($expiryDate < $currentDateStr) {
                $urgency = 'expired';
            } elseif ($expiryDate <= $this->addDaysToJalaliDate($currentDateStr, 30)) {
                $urgency = 'critical';
            } elseif ($expiryDate <= $this->addDaysToJalaliDate($currentDateStr, 90)) {
                $urgency = 'warning';
            } else {
                continue; // skip items expiring > 90 days from now
            }

            $nearExpiry[] = [
                'id' => $item->getId(),
                'commodityName' => $item->getCommodity() ? $item->getCommodity()->getName() : null,
                'lotNo' => $item->getLotNo(),
                'expiryDate' => $expiryDate,
                'qty' => $item->getCount(),
                'urgency' => $urgency,
            ];
        }

        return $this->json([
            'result' => 1,
            'lowStock' => $lowStock,
            'nearExpiry' => $nearExpiry,
        ]);
    }

    private function addDaysToJalaliDate(string $jalaliDate, int $days): string
    {
        // Convert jalali YYYY/MM/DD to approximate gregorian timestamp and back
        $parts = explode('/', $jalaliDate);
        if (count($parts) !== 3) return $jalaliDate;

        // Simple approximation: add days to a unix timestamp
        // Parse jalali date to approximate timestamp (rough conversion)
        $jYear = (int)$parts[0];
        $jMonth = (int)$parts[1];
        $jDay = (int)$parts[2];

        // Approximate gregorian year (jalali year + 621 or 622)
        $gYear = $jYear + 621;
        $timestamp = mktime(0, 0, 0, $jMonth, $jDay + $days, $gYear);

        // Convert back to jalali string approximately
        // Use the same offset back
        $newGYear = (int)date('Y', $timestamp);
        $newGMonth = (int)date('m', $timestamp);
        $newGDay = (int)date('d', $timestamp);

        $newJYear = $newGYear - 621;
        return sprintf('%04d/%02d/%02d', $newJYear, $newGMonth, $newGDay);
    }
}
