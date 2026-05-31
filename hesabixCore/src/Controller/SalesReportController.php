<?php

namespace App\Controller;

use App\Entity\HesabdariDoc;
use App\Entity\HesabdariRow;
use App\Entity\Person;
use App\Service\Access;
use App\Service\Jdate;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

class SalesReportController extends AbstractController
{
    #[Route('/api/acc/report/sales/period', name: 'api_report_sales_period', methods: ['POST'])]
    public function salesPeriod(Request $request, Access $access, EntityManagerInterface $em): JsonResponse
    {
        $acc = $access->hasRole('report');
        if (!$acc) throw $this->createAccessDeniedException();

        $p = json_decode($request->getContent(), true) ?? [];
        $from = $p['from'] ?? null;
        $to   = $p['to'] ?? null;
        $groupBy = $p['groupBy'] ?? 'month';

        $qb = $em->createQueryBuilder()
            ->select('d')
            ->from(HesabdariDoc::class, 'd')
            ->where('d.bid = :bid')->andWhere('d.year = :year')
            ->andWhere('d.type IN (:types)')
            ->setParameter('bid', $acc['bid'])
            ->setParameter('year', $acc['year'])
            ->setParameter('types', ['sell', 'rfbuy'])
            ->orderBy('d.date', 'ASC');

        if ($from) $qb->andWhere('d.date >= :from')->setParameter('from', $from);
        if ($to)   $qb->andWhere('d.date <= :to')->setParameter('to', $to);

        $docs = $qb->getQuery()->getResult();

        $groups = [];
        foreach ($docs as $doc) {
            $date = $doc->getDate();
            if (!$date) continue;
            $parts = explode('/', $date);
            $key = ($groupBy === 'month') ? ($parts[0] . '/' . $parts[1]) : $parts[0];
            if (!isset($groups[$key])) {
                $groups[$key] = ['period' => $key, 'sellCount' => 0, 'sellAmount' => 0, 'rfAmount' => 0, 'netAmount' => 0];
            }
            $amount = (float)($doc->getAmount() ?? 0);
            if ($doc->getType() === 'sell') {
                $groups[$key]['sellCount']++;
                $groups[$key]['sellAmount'] += $amount;
            } else {
                $groups[$key]['rfAmount'] += $amount;
            }
            $groups[$key]['netAmount'] = $groups[$key]['sellAmount'] - $groups[$key]['rfAmount'];
        }

        return $this->json(array_values($groups));
    }

    #[Route('/api/acc/report/sales/performance', name: 'api_report_sales_performance', methods: ['POST'])]
    public function salesPerformance(Request $request, Access $access, EntityManagerInterface $em): JsonResponse
    {
        $acc = $access->hasRole('report');
        if (!$acc) throw $this->createAccessDeniedException();

        $p = json_decode($request->getContent(), true) ?? [];
        $from = $p['from'] ?? null;
        $to   = $p['to'] ?? null;

        $qb = $em->createQueryBuilder()
            ->select('d')
            ->from(HesabdariDoc::class, 'd')
            ->leftJoin('d.submitter', 'u')
            ->where('d.bid = :bid')->andWhere('d.year = :year')
            ->andWhere('d.type = :type')
            ->setParameter('bid', $acc['bid'])
            ->setParameter('year', $acc['year'])
            ->setParameter('type', 'sell');

        if ($from) $qb->andWhere('d.date >= :from')->setParameter('from', $from);
        if ($to)   $qb->andWhere('d.date <= :to')->setParameter('to', $to);

        $docs = $qb->getQuery()->getResult();

        $byUser = [];
        foreach ($docs as $doc) {
            $user = $doc->getSubmitter();
            $uid = $user ? $user->getId() : 0;
            $uName = $user ? ($user->getName() ?? $user->getMobile()) : 'نامشخص';
            if (!isset($byUser[$uid])) {
                $byUser[$uid] = ['userId' => $uid, 'userName' => $uName, 'count' => 0, 'totalAmount' => 0];
            }
            $byUser[$uid]['count']++;
            $byUser[$uid]['totalAmount'] += (float)($doc->getAmount() ?? 0);
        }

        usort($byUser, fn($a, $b) => $b['totalAmount'] <=> $a['totalAmount']);
        return $this->json(array_values($byUser));
    }

    #[Route('/api/acc/report/receivables/aging', name: 'api_report_receivables_aging', methods: ['POST'])]
    public function receivablesAging(Request $request, Access $access, Jdate $jdate, EntityManagerInterface $em): JsonResponse
    {
        $acc = $access->hasRole('report');
        if (!$acc) throw $this->createAccessDeniedException();

        $today = $jdate->GetTodayDate();

        // Sum bd (debit) and bs (credit) per person to get net balance
        $rows = $em->createQueryBuilder()
            ->select('p.id as personId, p.nikename as personName, r.bd, r.bs, d.date, d.type')
            ->from(HesabdariRow::class, 'r')
            ->join('r.doc', 'd')
            ->join('r.person', 'p')
            ->where('r.bid = :bid')->andWhere('r.year = :year')
            ->andWhere('d.type IN (:types)')
            ->setParameter('bid', $acc['bid'])
            ->setParameter('year', $acc['year'])
            ->setParameter('types', ['sell', 'getpay', 'rfbuy'])
            ->orderBy('d.date', 'ASC')
            ->getQuery()->getScalarResult();

        $persons = [];
        foreach ($rows as $row) {
            $pid = $row['personId'];
            if (!isset($persons[$pid])) {
                $persons[$pid] = ['personId' => $pid, 'personName' => $row['personName'], 'balance' => 0.0, 'docs' => []];
            }
            $bd = (float)($row['bd'] ?? 0);
            $bs = (float)($row['bs'] ?? 0);
            $persons[$pid]['balance'] += $bd - $bs;
            $persons[$pid]['docs'][] = ['date' => $row['date'], 'type' => $row['type'], 'bd' => $bd, 'bs' => $bs];
        }

        $result = [];
        foreach ($persons as $pid => $data) {
            if ($data['balance'] <= 0) continue;

            // Find earliest unpaid doc date to estimate age
            $earliestDate = null;
            foreach ($data['docs'] as $doc) {
                if ($doc['date'] && (!$earliestDate || $doc['date'] < $earliestDate)) {
                    $earliestDate = $doc['date'];
                }
            }

            $ageDays = 0;
            if ($earliestDate) {
                [$jy, $jm, $jd] = array_map('intval', explode('/', $earliestDate));
                $ts = $jdate->jallaliToUnixTime($earliestDate, false);
                $ageDays = max(0, (int)round((time() - $ts) / 86400));
            }

            $bucket = match(true) {
                $ageDays <= 30 => '0-30 روز',
                $ageDays <= 60 => '31-60 روز',
                $ageDays <= 90 => '61-90 روز',
                default        => 'بیش از 90 روز',
            };

            $result[] = [
                'personId' => $pid,
                'personName' => $data['personName'],
                'balance' => $data['balance'],
                'ageDays' => $ageDays,
                'bucket' => $bucket,
            ];
        }

        usort($result, fn($a, $b) => $b['balance'] <=> $a['balance']);
        return $this->json($result);
    }

    #[Route('/api/acc/report/receivables/ranking', name: 'api_report_receivables_ranking', methods: ['POST'])]
    public function receivablesRanking(Request $request, Access $access, EntityManagerInterface $em): JsonResponse
    {
        $acc = $access->hasRole('report');
        if (!$acc) throw $this->createAccessDeniedException();

        $p = json_decode($request->getContent(), true) ?? [];
        $from = $p['from'] ?? null;
        $to   = $p['to'] ?? null;
        $limit = min((int)($p['limit'] ?? 20), 100);

        $qb = $em->createQueryBuilder()
            ->select('p.id as personId, p.nikename as personName, p.mobile as personMobile, SUM(d.amount) as totalSell, COUNT(d.id) as docCount')
            ->from(HesabdariDoc::class, 'd')
            ->join('d.hesabdariRows', 'r')
            ->join('r.person', 'p')
            ->where('d.bid = :bid')->andWhere('d.year = :year')
            ->andWhere('d.type = :type')
            ->setParameter('bid', $acc['bid'])
            ->setParameter('year', $acc['year'])
            ->setParameter('type', 'sell')
            ->groupBy('p.id')
            ->orderBy('totalSell', 'DESC')
            ->setMaxResults($limit);

        if ($from) $qb->andWhere('d.date >= :from')->setParameter('from', $from);
        if ($to)   $qb->andWhere('d.date <= :to')->setParameter('to', $to);

        $rows = $qb->getQuery()->getScalarResult();

        return $this->json($rows);
    }
}
