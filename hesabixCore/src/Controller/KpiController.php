<?php
namespace App\Controller;

use App\Entity\ActivityLog;
use App\Entity\KpiTarget;
use App\Entity\Permission;
use App\Entity\SalesCenter;
use App\Entity\User;
use App\Entity\WeekPlan;
use App\Service\Access;
use App\Service\Jdate;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

class KpiController extends AbstractController
{
    private function calcKpi(EntityManagerInterface $em, $bid, $year, User $user, string $month, ?KpiTarget $target): array
    {
        [$y, $m] = explode('/', $month);
        $fromDate = "{$y}/{$m}/01";
        $nextM = (int)$m + 1;
        $nextY = (int)$y;
        if ($nextM > 12) { $nextM = 1; $nextY++; }
        $toDate = sprintf('%04d/%02d/01', $nextY, $nextM);

        $logs = $em->createQueryBuilder()->select('a.type, COUNT(a.id) as cnt')
            ->addSelect('SUM(CASE WHEN a.cashSale=true THEN 1 ELSE 0 END) as cashCnt')
            ->addSelect('SUM(CASE WHEN a.done=true THEN 1 ELSE 0 END) as doneCnt')
            ->from(ActivityLog::class, 'a')
            ->where('a.bid=:bid AND a.year=:year AND a.user=:user AND a.date>=:from AND a.date<:to')
            ->setParameters(['bid'=>$bid,'year'=>$year,'user'=>$user,'from'=>$fromDate,'to'=>$toDate])
            ->groupBy('a.type')->getQuery()->getScalarResult();

        $data = ['call'=>0,'visit'=>0,'sale'=>0,'mission'=>0,'cashCnt'=>0,'doneMission'=>0,'saleAmount'=>'0'];
        foreach ($logs as $row) {
            $data[$row['type']] = (int)$row['cnt'];
            if ($row['type'] === 'sale') { $data['cashCnt'] = (int)$row['cashCnt']; }
            if ($row['type'] === 'mission') { $data['doneMission'] = (int)$row['doneCnt']; }
        }

        // sales amount
        $saleAmt = $em->createQueryBuilder()->select('SUM(CAST(a.amount AS DECIMAL(20,2)))')
            ->from(ActivityLog::class,'a')
            ->where("a.bid=:bid AND a.year=:year AND a.user=:user AND a.type='sale' AND a.date>=:from AND a.date<:to")
            ->setParameters(['bid'=>$bid,'year'=>$year,'user'=>$user,'from'=>$fromDate,'to'=>$toDate])
            ->getQuery()->getSingleScalarResult();
        $data['saleAmount'] = $saleAmt ?? '0';

        // contract_closed centers this month (conversion rate)
        $contracted = $em->createQueryBuilder()->select('COUNT(c.id)')
            ->from(SalesCenter::class,'c')
            ->where('c.bid=:bid AND c.crmStatus=:s AND c.active=true')
            ->setParameters(['bid'=>$bid,'s'=>'contract_closed'])->getQuery()->getSingleScalarResult();
        $totalCenters = $em->getRepository(SalesCenter::class)->count(['bid'=>$bid,'active'=>true]);

        $t = $target;
        $daysInMonth = 25; // approx working days
        $weeksInMonth = 4;
        $callActual = $data['call'];
        $visitActual = $data['visit'];
        $callTarget = $t ? $t->getCallDailyTarget() * $daysInMonth : 250;
        $visitTarget = $t ? $t->getVisitWeeklyTarget() * $weeksInMonth : 20;
        $saleTarget = $t ? $t->getSaleMonthlyTarget() : 5;
        $saleAmtTarget = $t ? (float)$t->getSaleAmountTarget() : 0;
        $missionTarget = $t ? $t->getMissionMonthlyTarget() : 4;
        $cashPctTarget = $t ? $t->getCashPctTarget() : 30;

        $cashPctActual = $data['sale'] > 0 ? round($data['cashCnt'] / $data['sale'] * 100) : 0;
        $conversionRate = $totalCenters > 0 ? round((int)$contracted / $totalCenters * 100) : 0;

        return [
            'month' => $month,
            'user'  => ['id'=>$user->getId(),'mobile'=>$user->getMobile(),'name'=>$user->getName()],
            'kpis'  => [
                ['key'=>'call',        'label'=>'تماس روزانه',       'weight'=>15, 'actual'=>$callActual,              'target'=>$callTarget,    'unit'=>'عدد'],
                ['key'=>'visit',       'label'=>'ویزیت هفتگی',       'weight'=>15, 'actual'=>$visitActual,             'target'=>$visitTarget,   'unit'=>'عدد'],
                ['key'=>'sale',        'label'=>'تعداد فروش',        'weight'=>15, 'actual'=>$data['sale'],            'target'=>$saleTarget,    'unit'=>'عدد'],
                ['key'=>'saleAmount',  'label'=>'مبلغ فروش',         'weight'=>15, 'actual'=>round((float)$data['saleAmount']), 'target'=>$saleAmtTarget,'unit'=>'ریال'],
                ['key'=>'mission',     'label'=>'ماموریت ماهانه',    'weight'=>10, 'actual'=>$data['doneMission'],     'target'=>$missionTarget, 'unit'=>'عدد'],
                ['key'=>'cashPct',     'label'=>'نسبت فروش نقدی',    'weight'=>5,  'actual'=>$cashPctActual,           'target'=>$cashPctTarget, 'unit'=>'%'],
                ['key'=>'conversion',  'label'=>'نرخ تبدیل لید',     'weight'=>25, 'actual'=>$conversionRate,          'target'=>60,             'unit'=>'%'],
            ],
        ];
    }

    #[Route('/api/acc/kpi/dashboard', name: 'api_kpi_dashboard', methods: ['POST'])]
    public function dashboard(Request $request, Access $access, Jdate $jdate, EntityManagerInterface $em): JsonResponse
    {
        $acc = $access->hasRole('join'); if (!$acc) throw $this->createAccessDeniedException();
        $p = json_decode($request->getContent(), true) ?? [];
        $month = $p['month'] ?? substr($jdate->getToday(), 0, 7);
        $targetUser = !empty($p['userId'])
            ? $em->getRepository(User::class)->find($p['userId'])
            : $this->getUser();
        if (!$targetUser) throw $this->createNotFoundException();
        $target = $em->getRepository(KpiTarget::class)->findOneBy(['bid'=>$acc['bid'],'user'=>$targetUser,'month'=>$month]);
        return $this->json($this->calcKpi($em, $acc['bid'], $acc['year'], $targetUser, $month, $target));
    }

    #[Route('/api/acc/kpi/team', name: 'api_kpi_team', methods: ['POST'])]
    public function team(Request $request, Access $access, Jdate $jdate, EntityManagerInterface $em): JsonResponse
    {
        $acc = $access->hasRole('join'); if (!$acc) throw $this->createAccessDeniedException();
        $p = json_decode($request->getContent(), true) ?? [];
        $month = $p['month'] ?? substr($jdate->getToday(), 0, 7);

        $perms = $em->createQueryBuilder()->select('IDENTITY(p.user) as uid')->from(Permission::class,'p')
            ->where('p.bid=:bid')->setParameter('bid',$acc['bid'])->getQuery()->getScalarResult();
        $result = [];
        foreach ($perms as $row) {
            $user = $em->getRepository(User::class)->find($row['uid']);
            if (!$user) continue;
            $target = $em->getRepository(KpiTarget::class)->findOneBy(['bid'=>$acc['bid'],'user'=>$user,'month'=>$month]);
            $result[] = $this->calcKpi($em, $acc['bid'], $acc['year'], $user, $month, $target);
        }
        return $this->json($result);
    }

    #[Route('/api/acc/kpi/targets/save', name: 'api_kpi_targets_save', methods: ['POST'])]
    public function saveTargets(Request $request, Access $access, EntityManagerInterface $em): JsonResponse
    {
        $acc = $access->hasRole('join'); if (!$acc) throw $this->createAccessDeniedException();
        $p = json_decode($request->getContent(), true) ?? [];
        if (empty($p['month'])) return $this->json(['result'=>-1]);
        $targetUser = !empty($p['userId'])
            ? $em->getRepository(User::class)->find($p['userId'])
            : $this->getUser();

        $t = $em->getRepository(KpiTarget::class)->findOneBy(['bid'=>$acc['bid'],'user'=>$targetUser,'month'=>$p['month']]);
        if (!$t) { $t = new KpiTarget(); $t->setBid($acc['bid'])->setUser($targetUser)->setMonth($p['month']); }
        if (isset($p['callDailyTarget']))      $t->setCallDailyTarget((int)$p['callDailyTarget']);
        if (isset($p['visitWeeklyTarget']))    $t->setVisitWeeklyTarget((int)$p['visitWeeklyTarget']);
        if (isset($p['saleMonthlyTarget']))    $t->setSaleMonthlyTarget((int)$p['saleMonthlyTarget']);
        if (isset($p['saleAmountTarget']))     $t->setSaleAmountTarget((string)$p['saleAmountTarget']);
        if (isset($p['retentionPctTarget']))   $t->setRetentionPctTarget((int)$p['retentionPctTarget']);
        if (isset($p['missionMonthlyTarget'])) $t->setMissionMonthlyTarget((int)$p['missionMonthlyTarget']);
        if (isset($p['cashPctTarget']))        $t->setCashPctTarget((int)$p['cashPctTarget']);
        $em->persist($t); $em->flush();
        return $this->json(['result'=>1]);
    }

    #[Route('/api/acc/kpi/targets/get', name: 'api_kpi_targets_get', methods: ['POST'])]
    public function getTargets(Request $request, Access $access, EntityManagerInterface $em): JsonResponse
    {
        $acc = $access->hasRole('join'); if (!$acc) throw $this->createAccessDeniedException();
        $p = json_decode($request->getContent(), true) ?? [];
        $targetUser = !empty($p['userId']) ? $em->getRepository(User::class)->find($p['userId']) : $this->getUser();
        $t = $em->getRepository(KpiTarget::class)->findOneBy(['bid'=>$acc['bid'],'user'=>$targetUser,'month'=>($p['month']??'')]);
        if (!$t) return $this->json(['callDailyTarget'=>10,'visitWeeklyTarget'=>5,'saleMonthlyTarget'=>2,'saleAmountTarget'=>'0','retentionPctTarget'=>80,'missionMonthlyTarget'=>2,'cashPctTarget'=>30]);
        return $this->json(['callDailyTarget'=>$t->getCallDailyTarget(),'visitWeeklyTarget'=>$t->getVisitWeeklyTarget(),'saleMonthlyTarget'=>$t->getSaleMonthlyTarget(),'saleAmountTarget'=>$t->getSaleAmountTarget(),'retentionPctTarget'=>$t->getRetentionPctTarget(),'missionMonthlyTarget'=>$t->getMissionMonthlyTarget(),'cashPctTarget'=>$t->getCashPctTarget()]);
    }
}
