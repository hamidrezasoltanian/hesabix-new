<?php
namespace App\Controller;

use App\Entity\CrmChecklistItem;
use App\Entity\CrmChecklistLog;
use App\Service\Access;
use App\Service\Jdate;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

class CrmChecklistController extends AbstractController
{
    private static array $defaultItems = [
        ['ثبت گزارش تماس‌های روزانه', 'ارتباط با مشتری', false, 1, 1],
        ['بروزرسانی وضعیت مراکز در CRM', 'ارتباط با مشتری', false, 2, 1],
        ['پیگیری مراکز overdue', 'ارتباط با مشتری', false, 3, 1],
        ['ثبت ویزیت‌های انجام شده', 'ارتباط با مشتری', false, 4, 1],
        ['ثبت نتیجه مذاکرات', 'ارتباط با مشتری', false, 5, 1],
        ['بروزرسانی اطلاعات تماس مراکز', 'ارتباط با مشتری', false, 6, 1],
        ['ثبت فرصت‌های فروش جدید', 'برنامه‌ریزی فروش', false, 7, 1],
        ['پیگیری پیشنهادات ارسال‌شده', 'برنامه‌ریزی فروش', false, 8, 1],
        ['تهیه برنامه هفته آینده', 'برنامه‌ریزی فروش', false, 9, 1],
        ['بررسی اهداف هفتگی/ماهانه', 'برنامه‌ریزی فروش', false, 10, 1],
        ['ثبت فروش‌های روز', 'برنامه‌ریزی فروش', false, 11, 1],
        ['بررسی سررسید فاکتورها', 'مطالبات', false, 12, 1],
        ['پیگیری مطالبات معوق', 'مطالبات', false, 13, 1],
        ['ثبت پرداخت‌های دریافتی', 'مطالبات', false, 14, 1],
        ['بررسی موجودی انبار', 'عملکرد', false, 15, 1],
        ['پاسخ به ایمیل/پیام‌های کاری', 'عملکرد', false, 16, 1],
        ['بررسی KPI روزانه', 'عملکرد', false, 17, 1],
        ['ثبت فعالیت‌ها در سیستم', 'عملکرد', false, 18, 1],
        ['تکمیل چک‌لیست روزانه', 'عملکرد', false, 19, 1],
        ['بررسی عملکرد تیم فروش', 'مدیریت', true, 20, 2],
        ['تعیین اهداف ماهانه کارشناسان', 'مدیریت', true, 21, 2],
        ['مرور گزارش‌های هفتگی', 'مدیریت', true, 22, 2],
        ['ارزیابی KPI تیم', 'مدیریت', true, 23, 2],
        ['جلسه هفتگی با تیم', 'مدیریت', true, 24, 2],
        ['بررسی پیشرفت قراردادها', 'مدیریت', true, 25, 2],
        ['تخصیص مراکز به کارشناسان', 'مدیریت', true, 26, 2],
        ['تهیه گزارش ماهانه مدیریتی', 'مدیریت', true, 27, 2],
    ];

    #[Route('/api/acc/crm/checklist/seed', name: 'api_crm_checklist_seed', methods: ['POST'])]
    public function seed(Access $access, EntityManagerInterface $em): JsonResponse
    {
        $acc = $access->hasRole('join'); if (!$acc) throw $this->createAccessDeniedException();
        $existing = $em->getRepository(CrmChecklistItem::class)->count(['bid' => $acc['bid']]);
        if ($existing > 0) return $this->json(['result' => 0]);
        foreach (self::$defaultItems as [$name, $cat, $mgr, $order, $score]) {
            $item = new CrmChecklistItem();
            $item->setBid($acc['bid'])->setName($name)->setCategory($cat)
                ->setManagerOnly($mgr)->setDisplayOrder($order)->setScore($score);
            $em->persist($item);
        }
        $em->flush();
        return $this->json(['result' => 1]);
    }

    #[Route('/api/acc/crm/checklist/today', name: 'api_crm_checklist_today', methods: ['POST'])]
    public function today(Request $request, Access $access, Jdate $jdate, EntityManagerInterface $em): JsonResponse
    {
        $acc = $access->hasRole('join'); if (!$acc) throw $this->createAccessDeniedException();
        $p = json_decode($request->getContent(), true) ?? [];
        $date = $p['date'] ?? $jdate->getToday();
        $isManager = $p['isManager'] ?? false;

        $items = $em->getRepository(CrmChecklistItem::class)
            ->findBy(['bid' => $acc['bid']], ['displayOrder' => 'ASC']);

        $logs = $em->createQueryBuilder()->select('l')->from(CrmChecklistLog::class, 'l')
            ->where('l.bid = :bid AND l.user = :user AND l.date = :date')
            ->setParameter('bid', $acc['bid'])->setParameter('user', $this->getUser())
            ->setParameter('date', $date)->getQuery()->getResult();
        $logMap = [];
        foreach ($logs as $log) $logMap[$log->getItem()->getId()] = $log;

        $result = [];
        $totalScore = 0;
        $maxScore = 0;
        foreach ($items as $item) {
            if ($item->isManagerOnly() && !$isManager) continue;
            $log = $logMap[$item->getId()] ?? null;
            $done = $log ? $log->isDone() : false;
            $maxScore += $item->getScore();
            if ($done) $totalScore += $item->getScore();
            $result[] = [
                'id' => $item->getId(), 'name' => $item->getName(),
                'category' => $item->getCategory(), 'managerOnly' => $item->isManagerOnly(),
                'score' => $item->getScore(), 'done' => $done,
                'note' => $log ? $log->getNote() : null,
            ];
        }
        return $this->json(['items' => $result, 'score' => $totalScore, 'maxScore' => $maxScore,
            'pct' => $maxScore > 0 ? round($totalScore / $maxScore * 100) : 0, 'date' => $date]);
    }

    #[Route('/api/acc/crm/checklist/toggle', name: 'api_crm_checklist_toggle', methods: ['POST'])]
    public function toggle(Request $request, Access $access, Jdate $jdate, EntityManagerInterface $em): JsonResponse
    {
        $acc = $access->hasRole('join'); if (!$acc) throw $this->createAccessDeniedException();
        $p = json_decode($request->getContent(), true) ?? [];
        if (empty($p['itemId'])) return $this->json(['result' => -1]);
        $date = $p['date'] ?? $jdate->getToday();

        $item = $em->getRepository(CrmChecklistItem::class)->findOneBy(['id' => $p['itemId'], 'bid' => $acc['bid']]);
        if (!$item) throw $this->createNotFoundException();

        $log = $em->getRepository(CrmChecklistLog::class)->findOneBy([
            'bid' => $acc['bid'], 'user' => $this->getUser(), 'date' => $date, 'item' => $item]);
        if (!$log) {
            $log = new CrmChecklistLog();
            $log->setBid($acc['bid'])->setUser($this->getUser())->setDate($date)->setItem($item);
        }
        $log->setDone(!$log->isDone());
        if (!empty($p['note'])) $log->setNote($p['note']);
        $em->persist($log); $em->flush();
        return $this->json(['result' => 1, 'done' => $log->isDone()]);
    }

    #[Route('/api/acc/crm/checklist/history', name: 'api_crm_checklist_history', methods: ['POST'])]
    public function history(Request $request, Access $access, Jdate $jdate, EntityManagerInterface $em): JsonResponse
    {
        $acc = $access->hasRole('join'); if (!$acc) throw $this->createAccessDeniedException();
        $p = json_decode($request->getContent(), true) ?? [];
        $from = $p['from'] ?? null; $to = $p['to'] ?? null;

        $qb = $em->createQueryBuilder()->select('l.date, COUNT(l.id) as total, SUM(CASE WHEN l.done=true THEN 1 ELSE 0 END) as done')
            ->from(CrmChecklistLog::class, 'l')
            ->where('l.bid = :bid AND l.user = :user')->setParameter('bid', $acc['bid'])->setParameter('user', $this->getUser())
            ->groupBy('l.date')->orderBy('l.date', 'DESC');
        if ($from) $qb->andWhere('l.date >= :from')->setParameter('from', $from);
        if ($to)   $qb->andWhere('l.date <= :to')->setParameter('to', $to);
        return $this->json($qb->setMaxResults(30)->getQuery()->getScalarResult());
    }
}
