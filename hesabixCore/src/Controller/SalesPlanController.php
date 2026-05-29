<?php

namespace App\Controller;

use App\Entity\SalesCenter;
use App\Entity\User;
use App\Entity\WeekPlan;
use App\Service\Access;
use App\Service\Jdate;
use App\Service\Log;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

class SalesPlanController extends AbstractController
{
    // ─── مراکز فروش ───────────────────────────────────────────────────

    #[Route('/api/acc/salescenter/list', name: 'api_salescenter_list')]
    public function list(Access $access, EntityManagerInterface $em): JsonResponse
    {
        $acc = $access->hasRole('join');
        if (!$acc) throw $this->createAccessDeniedException();

        $centers = $em->getRepository(SalesCenter::class)->findBy(['bid' => $acc['bid'], 'active' => true]);
        return $this->json(array_map(fn($c) => $this->centerToArray($c), $centers));
    }

    #[Route('/api/acc/salescenter/mod', name: 'api_salescenter_mod')]
    public function mod(Request $request, Access $access, Log $log, EntityManagerInterface $em): JsonResponse
    {
        $acc = $access->hasRole('join');
        if (!$acc) throw $this->createAccessDeniedException();
        $p = json_decode($request->getContent(), true) ?? [];

        if (empty($p['name'])) return $this->json(['result' => -1]);

        $center = !empty($p['id'])
            ? $em->getRepository(SalesCenter::class)->findOneBy(['id' => $p['id'], 'bid' => $acc['bid']])
            : new SalesCenter();

        if (!$center) throw $this->createNotFoundException();

        $center->setBid($acc['bid']);
        $center->setName($p['name']);
        $center->setProvince($p['province'] ?? null);
        $center->setCity($p['city'] ?? null);
        $center->setType($p['type'] ?? 'customer');
        $center->setPotential(!empty($p['potential']) ? (int)$p['potential'] : null);
        $center->setTel($p['tel'] ?? null);
        $center->setAddress($p['address'] ?? null);
        $center->setActive(($p['active'] ?? true) == true);

        if (!empty($p['ownerId'])) {
            $owner = $em->getRepository(User::class)->find($p['ownerId']);
            $center->setOwner($owner);
        } else {
            $center->setOwner(null);
        }

        $em->persist($center);
        $em->flush();
        $log->insert('برنامه‌ریزی فروش', 'مرکز ' . $center->getName() . ' ذخیره شد.', $this->getUser(), $acc['bid']);
        return $this->json(['result' => 1, 'id' => $center->getId()]);
    }

    #[Route('/api/acc/salescenter/del/{id}', name: 'api_salescenter_del')]
    public function del(int $id, Access $access, Log $log, EntityManagerInterface $em): JsonResponse
    {
        $acc = $access->hasRole('join');
        if (!$acc) throw $this->createAccessDeniedException();

        $center = $em->getRepository(SalesCenter::class)->findOneBy(['id' => $id, 'bid' => $acc['bid']]);
        if (!$center) throw $this->createNotFoundException();

        $center->setActive(false);
        $em->flush();
        $log->insert('برنامه‌ریزی فروش', 'مرکز ' . $center->getName() . ' غیرفعال شد.', $this->getUser(), $acc['bid']);
        return $this->json(['result' => 1]);
    }

    // ─── برنامه هفتگی ──────────────────────────────────────────────────

    #[Route('/api/acc/weekplan/week', name: 'api_weekplan_week')]
    public function weekData(Request $request, Access $access, Jdate $jdate, EntityManagerInterface $em): JsonResponse
    {
        $acc = $access->hasRole('join');
        if (!$acc) throw $this->createAccessDeniedException();
        $p = json_decode($request->getContent(), true) ?? [];

        $weekStr = $p['weekStr'] ?? $jdate->getWeekStart();
        $weekEnd = $this->addDays($weekStr, 6);

        $plans = $em->getRepository(WeekPlan::class)->findBy([
            'bid' => $acc['bid'],
            'year' => $acc['year'],
            'weekStr' => $weekStr,
        ]);

        $days = [];
        for ($i = 0; $i < 7; $i++) {
            $days[$this->addDays($weekStr, $i)] = [];
        }

        foreach ($plans as $plan) {
            $d = $plan->getScheduledDate();
            if (!isset($days[$d])) $days[$d] = [];
            $days[$d][] = $this->planToArray($plan);
        }

        $centers = $em->getRepository(SalesCenter::class)->findBy(['bid' => $acc['bid'], 'active' => true]);

        return $this->json([
            'weekStr' => $weekStr,
            'weekEnd' => $weekEnd,
            'days' => $days,
            'centers' => array_map(fn($c) => $this->centerToArray($c), $centers),
            'prevWeek' => $this->addDays($weekStr, -7),
            'nextWeek' => $this->addDays($weekStr, 7),
        ]);
    }

    #[Route('/api/acc/weekplan/add', name: 'api_weekplan_add')]
    public function add(Request $request, Access $access, Jdate $jdate, Log $log, EntityManagerInterface $em): JsonResponse
    {
        $acc = $access->hasRole('join');
        if (!$acc) throw $this->createAccessDeniedException();
        $p = json_decode($request->getContent(), true) ?? [];

        if (empty($p['centerId']) || empty($p['scheduledDate']))
            return $this->json(['result' => -1]);

        $center = $em->getRepository(SalesCenter::class)->findOneBy(['id' => $p['centerId'], 'bid' => $acc['bid']]);
        if (!$center) throw $this->createNotFoundException('مرکز یافت نشد');

        $weekStr = $this->getWeekStart($p['scheduledDate']);

        $plan = new WeekPlan();
        $plan->setBid($acc['bid']);
        $plan->setYear($acc['year']);
        $plan->setCenter($center);
        $plan->setSubmitter($this->getUser());
        $plan->setWeekStr($weekStr);
        $plan->setScheduledDate($p['scheduledDate']);
        $plan->setActionType($p['actionType'] ?? 'visit');
        $plan->setDone(false);
        $plan->setDes($p['des'] ?? null);

        if (!empty($p['ownerId'])) {
            $owner = $em->getRepository(User::class)->find($p['ownerId']);
            $plan->setOwner($owner);
        }

        $em->persist($plan);
        $em->flush();
        $log->insert('برنامه‌ریزی فروش', 'برنامه ویزیت برای ' . $center->getName() . ' افزوده شد.', $this->getUser(), $acc['bid']);
        return $this->json(['result' => 1, 'id' => $plan->getId()]);
    }

    #[Route('/api/acc/weekplan/done/{id}', name: 'api_weekplan_done')]
    public function markDone(int $id, Request $request, Access $access, Jdate $jdate, EntityManagerInterface $em): JsonResponse
    {
        $acc = $access->hasRole('join');
        if (!$acc) throw $this->createAccessDeniedException();
        $p = json_decode($request->getContent(), true) ?? [];

        $plan = $em->getRepository(WeekPlan::class)->findOneBy(['id' => $id, 'bid' => $acc['bid']]);
        if (!$plan) throw $this->createNotFoundException();

        $plan->setDone(!$plan->isDone());
        $plan->setDoneDate($plan->isDone() ? $jdate->getToday() : null);
        $plan->setDes($p['des'] ?? $plan->getDes());
        $em->flush();

        return $this->json(['result' => 1, 'done' => $plan->isDone()]);
    }

    #[Route('/api/acc/weekplan/del/{id}', name: 'api_weekplan_del')]
    public function delPlan(int $id, Access $access, Log $log, EntityManagerInterface $em): JsonResponse
    {
        $acc = $access->hasRole('join');
        if (!$acc) throw $this->createAccessDeniedException();

        $plan = $em->getRepository(WeekPlan::class)->findOneBy(['id' => $id, 'bid' => $acc['bid']]);
        if (!$plan) throw $this->createNotFoundException();

        $em->remove($plan);
        $em->flush();
        return $this->json(['result' => 1]);
    }

    #[Route('/api/acc/weekplan/stats', name: 'api_weekplan_stats')]
    public function stats(Request $request, Access $access, EntityManagerInterface $em): JsonResponse
    {
        $acc = $access->hasRole('join');
        if (!$acc) throw $this->createAccessDeniedException();
        $p = json_decode($request->getContent(), true) ?? [];

        $weekStr = $p['weekStr'] ?? null;
        $criteria = ['bid' => $acc['bid'], 'year' => $acc['year']];
        if ($weekStr) $criteria['weekStr'] = $weekStr;

        $plans = $em->getRepository(WeekPlan::class)->findBy($criteria);
        $total = count($plans);
        $done  = count(array_filter($plans, fn($pl) => $pl->isDone()));

        return $this->json([
            'total'    => $total,
            'done'     => $done,
            'pending'  => $total - $done,
            'percent'  => $total > 0 ? round($done / $total * 100) : 0,
        ]);
    }

    // ─── helpers ──────────────────────────────────────────────────────

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
            'owner'     => $c->getOwner() ? ['id' => $c->getOwner()->getId(), 'mobile' => $c->getOwner()->getMobile()] : null,
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
            'center'        => $this->centerToArray($p->getCenter()),
            'owner'         => $p->getOwner() ? ['id' => $p->getOwner()->getId(), 'mobile' => $p->getOwner()->getMobile()] : null,
        ];
    }

    private function addDays(string $jalaliDate, int $days): string
    {
        $parts = explode('/', $jalaliDate);
        [$y, $m, $d] = [(int)$parts[0], (int)$parts[1], (int)$parts[2]];
        $ts = mktime(0, 0, 0, $m, $d + $days, $y);
        // simple Jalali arithmetic — works for ±30 days within same year
        $newD = (int)date('d', $ts);
        $newM = (int)date('m', $ts);
        $newY = (int)date('Y', $ts);
        // We need Jalali result; use the day offset approach
        $gregorianBase = \DateTime::createFromFormat('Y/m/d', $jalaliDate);
        if (!$gregorianBase) {
            // fallback: assume input is already Gregorian-like
            return sprintf('%04d/%02d/%02d', $newY, $newM, $newD);
        }
        $gregorianBase->modify(($days >= 0 ? '+' : '') . $days . ' days');
        return $gregorianBase->format('Y/m/d');
    }

    private function getWeekStart(string $jalaliDate): string
    {
        $parts = explode('/', $jalaliDate);
        [$y, $m, $d] = [(int)$parts[0], (int)$parts[1], (int)$parts[2]];
        $dt = \DateTime::createFromFormat('Y/m/d', $jalaliDate);
        if (!$dt) return $jalaliDate;
        // Saturday = day 6 in PHP (0=Sun,6=Sat); find previous Saturday
        $dow = (int)$dt->format('w'); // 0=Sun ... 6=Sat
        $toSaturday = ($dow === 6) ? 0 : -(($dow + 1) % 7 + 1 - 1);
        // diff to reach last Saturday
        $offset = -( ($dow + 1) % 7 );
        $dt->modify($offset . ' days');
        return $dt->format('Y/m/d');
    }
}
