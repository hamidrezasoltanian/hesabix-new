<?php

namespace App\Controller;

use App\Entity\DealActivity;
use App\Entity\Person;
use App\Entity\SalesCenter;
use App\Entity\SalesDeal;
use App\Entity\User;
use App\Entity\VisitReport;
use App\Entity\WeekPlan;
use App\Service\Access;
use App\Service\Jdate;
use App\Service\Log;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

class SalesDealController extends AbstractController
{
    private const STAGES = [
        'planning',
        'visited',
        'pre_invoice',
        'approved',
        'dispatched',
        'invoiced',
        'collected',
    ];

    // ─── Deals ──────────────────────────────────────────────────────────

    #[Route('/api/acc/salesdeal/list', name: 'api_salesdeal_list', methods: ['POST'])]
    public function list(Request $request, Access $access, EntityManagerInterface $em): JsonResponse
    {
        $acc = $access->hasRole('join');
        if (!$acc) throw $this->createAccessDeniedException();
        $p = json_decode($request->getContent(), true) ?? [];

        $qb = $em->createQueryBuilder()
            ->select('d')
            ->from(SalesDeal::class, 'd')
            ->where('d.bid = :bid AND d.year = :year')
            ->setParameter('bid', $acc['bid'])
            ->setParameter('year', $acc['year'])
            ->orderBy('d.createdAt', 'DESC');

        if (!empty($p['stage'])) {
            $qb->andWhere('d.stage = :stage')->setParameter('stage', $p['stage']);
        }
        if (!empty($p['centerId'])) {
            $qb->andWhere('d.center = :center')->setParameter('center', $p['centerId']);
        }
        if (!empty($p['ownerId'])) {
            $qb->andWhere('d.owner = :owner')->setParameter('owner', $p['ownerId']);
        }

        return $this->json(array_map(fn($d) => $this->dealToArray($d), $qb->getQuery()->getResult()));
    }

    #[Route('/api/acc/salesdeal/get/{id}', name: 'api_salesdeal_get')]
    public function get(int $id, Access $access, EntityManagerInterface $em): JsonResponse
    {
        $acc = $access->hasRole('join');
        if (!$acc) throw $this->createAccessDeniedException();

        $deal = $em->getRepository(SalesDeal::class)->findOneBy(['id' => $id, 'bid' => $acc['bid']]);
        if (!$deal) throw $this->createNotFoundException();

        return $this->json($this->dealToArray($deal));
    }

    #[Route('/api/acc/salesdeal/mod', name: 'api_salesdeal_mod', methods: ['POST'])]
    public function mod(Request $request, Access $access, Jdate $jdate, Log $log, EntityManagerInterface $em): JsonResponse
    {
        $acc = $access->hasRole('join');
        if (!$acc) throw $this->createAccessDeniedException();
        $p = json_decode($request->getContent(), true) ?? [];

        if (empty($p['title']) || empty($p['centerId']))
            return $this->json(['result' => -1]);

        $center = $em->getRepository(SalesCenter::class)->findOneBy(['id' => $p['centerId'], 'bid' => $acc['bid']]);
        if (!$center) throw $this->createNotFoundException('مرکز یافت نشد');

        $deal = !empty($p['id'])
            ? $em->getRepository(SalesDeal::class)->findOneBy(['id' => $p['id'], 'bid' => $acc['bid']])
            : new SalesDeal();
        if (!$deal) throw $this->createNotFoundException();

        $isNew = !$deal->getId();

        $deal->setBid($acc['bid']);
        $deal->setYear($acc['year']);
        $deal->setCenter($center);
        $deal->setTitle($p['title']);
        $deal->setDes($p['des'] ?? null);
        $deal->setAmount(!empty($p['amount']) ? (string)$p['amount'] : null);
        $deal->setDueDate($p['dueDate'] ?? null);

        if (!empty($p['personId'])) {
            $person = $em->getRepository(Person::class)->findOneBy(['id' => $p['personId'], 'bid' => $acc['bid']]);
            $deal->setPerson($person);
        } elseif ($isNew && $center->getPerson()) {
            $deal->setPerson($center->getPerson());
        }

        if (array_key_exists('ownerId', $p)) {
            $deal->setOwner(!empty($p['ownerId']) ? $em->getRepository(User::class)->find($p['ownerId']) : null);
        }
        if (array_key_exists('preInvoiceId', $p)) $deal->setPreInvoiceId($p['preInvoiceId'] ? (int)$p['preInvoiceId'] : null);
        if (array_key_exists('storeroomTicketId', $p)) $deal->setStoreroomTicketId($p['storeroomTicketId'] ? (int)$p['storeroomTicketId'] : null);
        if (array_key_exists('sellDocId', $p)) $deal->setSellDocId($p['sellDocId'] ? (int)$p['sellDocId'] : null);
        if (array_key_exists('priority', $p)) $deal->setPriority($p['priority'] ?: null);

        if ($isNew) {
            $deal->setSubmitter($this->getUser());
            $deal->setStage($p['stage'] ?? 'planning');
            $deal->setCreatedAt($jdate->getToday());
        }

        $em->persist($deal);
        $em->flush();
        $log->insert('فروش', ($isNew ? 'فرصت جدید: ' : 'ویرایش فرصت: ') . $deal->getTitle(), $this->getUser(), $acc['bid']);
        return $this->json(['result' => 1, 'id' => $deal->getId()]);
    }

    #[Route('/api/acc/salesdeal/stage/{id}', name: 'api_salesdeal_stage', methods: ['POST'])]
    public function changeStage(int $id, Request $request, Access $access, Jdate $jdate, Log $log, EntityManagerInterface $em): JsonResponse
    {
        $acc = $access->hasRole('join');
        if (!$acc) throw $this->createAccessDeniedException();
        $p = json_decode($request->getContent(), true) ?? [];

        $deal = $em->getRepository(SalesDeal::class)->findOneBy(['id' => $id, 'bid' => $acc['bid']]);
        if (!$deal) throw $this->createNotFoundException();

        $newStage = $p['stage'] ?? null;
        if (!$newStage || !in_array($newStage, self::STAGES))
            return $this->json(['result' => -1, 'msg' => 'مرحله نامعتبر']);

        $oldStage = $deal->getStage();
        $deal->setStage($newStage);
        if (in_array($newStage, ['invoiced', 'collected'])) {
            $deal->setClosedAt($jdate->getToday());
        }
        $activity = new DealActivity();
        $activity->setDeal($deal)->setBid($acc['bid'])->setUser($this->getUser())
            ->setType('status_change')->setContent($oldStage . ' → ' . $newStage)->setDate($jdate->getToday());
        $em->persist($activity);
        $em->flush();
        $log->insert('فروش', 'تغییر مرحله فرصت «' . $deal->getTitle() . '» به ' . $newStage, $this->getUser(), $acc['bid']);
        return $this->json(['result' => 1, 'stage' => $deal->getStage()]);
    }

    #[Route('/api/acc/salesdeal/approve/{id}', name: 'api_salesdeal_approve', methods: ['POST'])]
    public function approve(int $id, Access $access, Jdate $jdate, Log $log, EntityManagerInterface $em): JsonResponse
    {
        $acc = $access->hasRole('join');
        if (!$acc) throw $this->createAccessDeniedException();

        $deal = $em->getRepository(SalesDeal::class)->findOneBy(['id' => $id, 'bid' => $acc['bid']]);
        if (!$deal) throw $this->createNotFoundException();

        $deal->setApprovedBy($this->getUser());
        $deal->setApprovedAt($jdate->getToday());
        $deal->setStage('approved');
        $em->flush();
        $log->insert('فروش', 'تأیید مدیر برای فرصت: ' . $deal->getTitle(), $this->getUser(), $acc['bid']);
        return $this->json(['result' => 1]);
    }

    #[Route('/api/acc/salesdeal/del/{id}', name: 'api_salesdeal_del', methods: ['POST'])]
    public function del(int $id, Access $access, Log $log, EntityManagerInterface $em): JsonResponse
    {
        $acc = $access->hasRole('join');
        if (!$acc) throw $this->createAccessDeniedException();

        $deal = $em->getRepository(SalesDeal::class)->findOneBy(['id' => $id, 'bid' => $acc['bid']]);
        if (!$deal) throw $this->createNotFoundException();

        $em->remove($deal);
        $em->flush();
        return $this->json(['result' => 1]);
    }

    #[Route('/api/acc/salesdeal/collections', name: 'api_salesdeal_collections', methods: ['POST'])]
    public function collections(Request $request, Access $access, EntityManagerInterface $em): JsonResponse
    {
        $acc = $access->hasRole('join');
        if (!$acc) throw $this->createAccessDeniedException();
        $p = json_decode($request->getContent(), true) ?? [];

        $qb = $em->createQueryBuilder()
            ->select('d')
            ->from(SalesDeal::class, 'd')
            ->where('d.bid = :bid AND d.year = :year')
            ->andWhere('d.stage IN (:stages)')
            ->setParameter('bid', $acc['bid'])
            ->setParameter('year', $acc['year'])
            ->setParameter('stages', ['invoiced', 'collected'])
            ->orderBy('d.dueDate', 'ASC');

        $deals = $qb->getQuery()->getResult();

        $today = (new \DateTime())->format('Y/m/d');
        $grouped = ['overdue' => [], 'today' => [], 'upcoming' => [], 'later' => []];
        foreach ($deals as $d) {
            $due = $d->getDueDate();
            if (!$due) { $grouped['later'][] = $this->dealToArray($d); continue; }
            if ($due < $today) $grouped['overdue'][] = $this->dealToArray($d);
            elseif ($due === $today) $grouped['today'][] = $this->dealToArray($d);
            else $grouped['upcoming'][] = $this->dealToArray($d);
        }

        return $this->json($grouped);
    }

    // ─── Visit Reports ───────────────────────────────────────────────────

    #[Route('/api/acc/visitreport/mod', name: 'api_visitreport_mod', methods: ['POST'])]
    public function reportMod(Request $request, Access $access, Jdate $jdate, Log $log, EntityManagerInterface $em): JsonResponse
    {
        $acc = $access->hasRole('join');
        if (!$acc) throw $this->createAccessDeniedException();
        $p = json_decode($request->getContent(), true) ?? [];

        if (empty($p['centerId']))
            return $this->json(['result' => -1]);

        $center = $em->getRepository(SalesCenter::class)->findOneBy(['id' => $p['centerId'], 'bid' => $acc['bid']]);
        if (!$center) throw $this->createNotFoundException();

        $report = !empty($p['id'])
            ? $em->getRepository(VisitReport::class)->findOneBy(['id' => $p['id'], 'bid' => $acc['bid']])
            : new VisitReport();
        if (!$report) throw $this->createNotFoundException();

        $isNew = !$report->getId();
        $report->setBid($acc['bid']);
        $report->setCenter($center);
        $report->setDate($p['date'] ?? $jdate->getToday());
        $report->setResult($p['result'] ?? 'follow_up');
        $report->setDes($p['des'] ?? null);
        $report->setNextAction($p['nextAction'] ?? null);
        $report->setNextDate($p['nextDate'] ?? null);

        if (!empty($p['dealId'])) {
            $deal = $em->getRepository(SalesDeal::class)->findOneBy(['id' => $p['dealId'], 'bid' => $acc['bid']]);
            $report->setDeal($deal);
        }
        if (!empty($p['weekPlanId'])) {
            $report->setWeekPlan($em->getRepository(WeekPlan::class)->find($p['weekPlanId']));
        }
        if ($isNew) {
            $report->setSubmitter($this->getUser());
        }

        $em->persist($report);
        $em->flush();
        $log->insert('فروش', 'گزارش بازدید ثبت شد: ' . $center->getName(), $this->getUser(), $acc['bid']);

        // if result=deal and no dealId, optionally auto-create deal
        if ($isNew && $p['result'] === 'deal' && empty($p['dealId']) && !empty($p['dealTitle'])) {
            $deal = new SalesDeal();
            $deal->setBid($acc['bid']);
            $deal->setYear($acc['year']);
            $deal->setCenter($center);
            $deal->setTitle($p['dealTitle']);
            $deal->setSubmitter($this->getUser());
            $deal->setStage('visited');
            $deal->setCreatedAt($jdate->getToday());
            if ($center->getPerson()) $deal->setPerson($center->getPerson());
            $em->persist($deal);
            $em->flush();
            $report->setDeal($deal);
            $em->flush();
            return $this->json(['result' => 1, 'id' => $report->getId(), 'dealId' => $deal->getId()]);
        }

        return $this->json(['result' => 1, 'id' => $report->getId()]);
    }

    #[Route('/api/acc/visitreport/del/{id}', name: 'api_visitreport_del', methods: ['POST'])]
    public function reportDel(int $id, Access $access, EntityManagerInterface $em): JsonResponse
    {
        $acc = $access->hasRole('join');
        if (!$acc) throw $this->createAccessDeniedException();

        $report = $em->getRepository(VisitReport::class)->findOneBy(['id' => $id, 'bid' => $acc['bid']]);
        if (!$report) throw $this->createNotFoundException();

        $em->remove($report);
        $em->flush();
        return $this->json(['result' => 1]);
    }

    #[Route('/api/acc/visitreport/list/{centerId}', name: 'api_visitreport_list')]
    public function reportList(int $centerId, Access $access, EntityManagerInterface $em): JsonResponse
    {
        $acc = $access->hasRole('join');
        if (!$acc) throw $this->createAccessDeniedException();

        $center = $em->getRepository(SalesCenter::class)->findOneBy(['id' => $centerId, 'bid' => $acc['bid']]);
        if (!$center) throw $this->createNotFoundException();

        $reports = $em->getRepository(VisitReport::class)->findBy(
            ['center' => $center, 'bid' => $acc['bid']],
            ['date' => 'DESC']
        );
        return $this->json(array_map(fn($r) => $this->reportToArray($r), $reports));
    }

    // ─── Center Timeline ─────────────────────────────────────────────────

    #[Route('/api/acc/salescenter/timeline/{id}', name: 'api_salescenter_timeline')]
    public function centerTimeline(int $id, Access $access, EntityManagerInterface $em): JsonResponse
    {
        $acc = $access->hasRole('join');
        if (!$acc) throw $this->createAccessDeniedException();

        $center = $em->getRepository(SalesCenter::class)->findOneBy(['id' => $id, 'bid' => $acc['bid']]);
        if (!$center) throw $this->createNotFoundException();

        $deals   = $em->getRepository(SalesDeal::class)->findBy(['center' => $center, 'bid' => $acc['bid']], ['createdAt' => 'DESC']);
        $reports = $em->getRepository(VisitReport::class)->findBy(['center' => $center, 'bid' => $acc['bid']], ['date' => 'DESC']);
        $plans   = $em->getRepository(WeekPlan::class)->findBy(['center' => $center, 'bid' => $acc['bid']], ['scheduledDate' => 'DESC']);

        return $this->json([
            'center'  => $this->centerToArray($center),
            'deals'   => array_map(fn($d) => $this->dealToArray($d), $deals),
            'reports' => array_map(fn($r) => $this->reportToArray($r), $reports),
            'plans'   => array_map(fn($p) => $this->planToArray($p), $plans),
        ]);
    }

    // ─── helpers ─────────────────────────────────────────────────────────

    private function dealToArray(SalesDeal $d): array
    {
        return [
            'id'               => $d->getId(),
            'title'            => $d->getTitle(),
            'stage'            => $d->getStage(),
            'amount'           => $d->getAmount(),
            'dueDate'          => $d->getDueDate(),
            'des'              => $d->getDes(),
            'createdAt'        => $d->getCreatedAt(),
            'closedAt'         => $d->getClosedAt(),
            'approvedAt'       => $d->getApprovedAt(),
            'preInvoiceId'     => $d->getPreInvoiceId(),
            'storeroomTicketId'=> $d->getStoreroomTicketId(),
            'sellDocId'        => $d->getSellDocId(),
            'center'           => ['id' => $d->getCenter()->getId(), 'name' => $d->getCenter()->getName()],
            'person'           => $d->getPerson() ? ['id' => $d->getPerson()->getId(), 'name' => $d->getPerson()->getName()] : null,
            'owner'            => $d->getOwner() ? ['id' => $d->getOwner()->getId(), 'mobile' => $d->getOwner()->getMobile()] : null,
            'submitter'        => $d->getSubmitter() ? ['id' => $d->getSubmitter()->getId(), 'mobile' => $d->getSubmitter()->getMobile()] : null,
            'approvedBy'       => $d->getApprovedBy() ? ['id' => $d->getApprovedBy()->getId(), 'mobile' => $d->getApprovedBy()->getMobile()] : null,
            'priority'         => $d->getPriority(),
        ];
    }

    private function reportToArray(VisitReport $r): array
    {
        return [
            'id'         => $r->getId(),
            'date'       => $r->getDate(),
            'result'     => $r->getResult(),
            'des'        => $r->getDes(),
            'nextAction' => $r->getNextAction(),
            'nextDate'   => $r->getNextDate(),
            'center'     => ['id' => $r->getCenter()->getId(), 'name' => $r->getCenter()->getName()],
            'deal'       => $r->getDeal() ? ['id' => $r->getDeal()->getId(), 'title' => $r->getDeal()->getTitle()] : null,
            'submitter'  => $r->getSubmitter() ? ['id' => $r->getSubmitter()->getId(), 'mobile' => $r->getSubmitter()->getMobile()] : null,
        ];
    }

    private function centerToArray(SalesCenter $c): array
    {
        return [
            'id'        => $c->getId(),
            'name'      => $c->getName(),
            'province'  => $c->getProvince(),
            'city'      => $c->getCity(),
            'type'      => $c->getType(),
            'potential' => $c->getPotential(),
            'tel'       => $c->getTel(),
            'address'   => $c->getAddress(),
            'active'    => $c->isActive(),
            'person'    => $c->getPerson() ? ['id' => $c->getPerson()->getId(), 'name' => $c->getPerson()->getName()] : null,
        ];
    }

    private function planToArray(WeekPlan $p): array
    {
        return [
            'id'            => $p->getId(),
            'scheduledDate' => $p->getScheduledDate(),
            'actionType'    => $p->getActionType(),
            'done'          => $p->isDone(),
            'doneDate'      => $p->getDoneDate(),
            'des'           => $p->getDes(),
        ];
    }
}
