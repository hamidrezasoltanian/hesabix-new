<?php

namespace App\Controller;

use App\Entity\ActivityLog;
use App\Entity\SalesCenter;
use App\Service\Access;
use App\Service\Jdate;
use App\Service\Log;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

class ActivityLogController extends AbstractController
{
    private function toArray(ActivityLog $a): array
    {
        return [
            'id'       => $a->getId(),
            'type'     => $a->getType(),
            'date'     => $a->getDate(),
            'amount'   => $a->getAmount(),
            'cashSale' => $a->isCashSale(),
            'done'     => $a->isDone(),
            'note'     => $a->getNote(),
            'createdAt'=> $a->getCreatedAt(),
            'center'   => $a->getCenter() ? ['id' => $a->getCenter()->getId(), 'name' => $a->getCenter()->getName()] : null,
            'user'     => ['id' => $a->getUser()->getId(), 'mobile' => $a->getUser()->getMobile()],
        ];
    }

    #[Route('/api/acc/activitylog/add', name: 'api_activitylog_add', methods: ['POST'])]
    public function add(Request $request, Access $access, Jdate $jdate, Log $log, EntityManagerInterface $em): JsonResponse
    {
        $acc = $access->hasRole('join');
        if (!$acc) throw $this->createAccessDeniedException();
        $p = json_decode($request->getContent(), true) ?? [];

        $allowed = ['call', 'visit', 'sale', 'mission'];
        if (empty($p['type']) || !in_array($p['type'], $allowed)) return $this->json(['result' => -1, 'msg' => 'نوع فعالیت نامعتبر است']);
        if (empty($p['date'])) return $this->json(['result' => -1, 'msg' => 'تاریخ الزامی است']);

        $activity = new ActivityLog();
        $activity->setBid($acc['bid']);
        $activity->setYear($acc['year']);
        $activity->setUser($this->getUser());
        $activity->setType($p['type']);
        $activity->setDate($p['date']);
        $activity->setNote($p['note'] ?? null);
        $activity->setCreatedAt($jdate->getToday());

        if (!empty($p['centerId'])) {
            $center = $em->getRepository(SalesCenter::class)->findOneBy(['id' => $p['centerId'], 'bid' => $acc['bid']]);
            $activity->setCenter($center ?: null);
        }

        if ($p['type'] === 'sale') {
            $activity->setAmount($p['amount'] ?? null);
            $activity->setCashSale(($p['cashSale'] ?? false) == true);
        }

        if ($p['type'] === 'mission') {
            $activity->setDone(($p['done'] ?? false) == true);
        }

        $em->persist($activity);
        $em->flush();

        $centerName = $activity->getCenter() ? ' برای ' . $activity->getCenter()->getName() : '';
        $log->insert('لاگ فعالیت', $p['type'] . $centerName . ' ثبت شد.', $this->getUser(), $acc['bid']);
        return $this->json(['result' => 1, 'id' => $activity->getId()]);
    }

    #[Route('/api/acc/activitylog/list', name: 'api_activitylog_list', methods: ['POST'])]
    public function list(Request $request, Access $access, EntityManagerInterface $em): JsonResponse
    {
        $acc = $access->hasRole('join');
        if (!$acc) throw $this->createAccessDeniedException();
        $p = json_decode($request->getContent(), true) ?? [];

        $qb = $em->createQueryBuilder()
            ->select('a')->from(ActivityLog::class, 'a')
            ->where('a.bid = :bid AND a.year = :year')
            ->setParameter('bid', $acc['bid'])
            ->setParameter('year', $acc['year'])
            ->orderBy('a.date', 'DESC')
            ->addOrderBy('a.id', 'DESC');

        if (!empty($p['type']))     $qb->andWhere('a.type = :type')->setParameter('type', $p['type']);
        if (!empty($p['centerId'])) $qb->andWhere('a.center = :cid')->setParameter('cid', (int)$p['centerId']);
        if (!empty($p['userId']))   $qb->andWhere('a.user = :uid')->setParameter('uid', (int)$p['userId']);
        if (!empty($p['from']))     $qb->andWhere('a.date >= :from')->setParameter('from', $p['from']);
        if (!empty($p['to']))       $qb->andWhere('a.date <= :to')->setParameter('to', $p['to']);

        $items = $qb->setMaxResults(200)->getQuery()->getResult();
        return $this->json(array_map(fn($a) => $this->toArray($a), $items));
    }

    #[Route('/api/acc/activitylog/del/{id}', name: 'api_activitylog_del', methods: ['POST'])]
    public function del(int $id, Access $access, EntityManagerInterface $em): JsonResponse
    {
        $acc = $access->hasRole('join');
        if (!$acc) throw $this->createAccessDeniedException();
        $activity = $em->getRepository(ActivityLog::class)->findOneBy(['id' => $id, 'bid' => $acc['bid'], 'year' => $acc['year']]);
        if (!$activity) throw $this->createNotFoundException();
        $em->remove($activity);
        $em->flush();
        return $this->json(['result' => 1]);
    }

    #[Route('/api/acc/activitylog/summary', name: 'api_activitylog_summary', methods: ['POST'])]
    public function summary(Request $request, Access $access, EntityManagerInterface $em): JsonResponse
    {
        $acc = $access->hasRole('join');
        if (!$acc) throw $this->createAccessDeniedException();
        $p = json_decode($request->getContent(), true) ?? [];

        $fromDate = $p['from'] ?? null;
        $toDate   = $p['to']   ?? null;

        $qb = $em->createQueryBuilder()
            ->select('a.type, COUNT(a.id) as cnt, SUM(CASE WHEN a.done = true THEN 1 ELSE 0 END) as doneCnt, SUM(CASE WHEN a.cashSale = true THEN 1 ELSE 0 END) as cashCnt')
            ->addSelect('a.user')
            ->from(ActivityLog::class, 'a')
            ->where('a.bid = :bid AND a.year = :year')
            ->setParameter('bid', $acc['bid'])
            ->setParameter('year', $acc['year'])
            ->groupBy('a.type, a.user');

        if ($fromDate) $qb->andWhere('a.date >= :from')->setParameter('from', $fromDate);
        if ($toDate)   $qb->andWhere('a.date <= :to')->setParameter('to', $toDate);
        if (!empty($p['userId'])) $qb->andWhere('a.user = :uid')->setParameter('uid', (int)$p['userId']);

        $rows = $qb->getQuery()->getScalarResult();

        // Sales amount sum
        $amountQb = $em->createQueryBuilder()
            ->select('SUM(CAST(a.amount AS DECIMAL(20,2))) as total')
            ->from(ActivityLog::class, 'a')
            ->where("a.bid = :bid AND a.year = :year AND a.type = 'sale'")
            ->setParameter('bid', $acc['bid'])->setParameter('year', $acc['year']);
        if ($fromDate) $amountQb->andWhere('a.date >= :from')->setParameter('from', $fromDate);
        if ($toDate)   $amountQb->andWhere('a.date <= :to')->setParameter('to', $toDate);
        if (!empty($p['userId'])) $amountQb->andWhere('a.user = :uid')->setParameter('uid', (int)$p['userId']);
        $totalSales = $amountQb->getQuery()->getSingleScalarResult();

        return $this->json([
            'rows'       => $rows,
            'totalSales' => $totalSales,
        ]);
    }
}
