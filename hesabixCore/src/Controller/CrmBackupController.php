<?php
namespace App\Controller;

use App\Entity\ActivityLog;
use App\Entity\Business;
use App\Entity\CrmCalendarEvent;
use App\Entity\CrmChecklistItem;
use App\Entity\CrmChecklistLog;
use App\Entity\HesabdariDoc;
use App\Entity\HesabdariRow;
use App\Entity\HesabdariTable;
use App\Entity\KpiTarget;
use App\Entity\Person;
use App\Entity\SalesCenter;
use App\Entity\SalesCenterTag;
use App\Entity\WeekPlan;
use App\Service\Access;
use App\Service\Jdate;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class CrmBackupController extends AbstractController
{
    #[Route('/api/acc/backup/export', name: 'api_backup_export', methods: ['POST'])]
    public function export(Request $request, Access $access, Jdate $jdate, EntityManagerInterface $em): Response
    {
        $acc = $access->hasRole('join'); if (!$acc) throw $this->createAccessDeniedException();
        $bid = $acc['bid'];

        $p = json_decode($request->getContent(), true) ?? [];
        $modules = $p['modules'] ?? ['crm', 'persons', 'accounting_summary'];

        $data = [
            'version' => '3.0',
            'exportedAt' => $jdate->getToday(),
            'businessId' => $bid->getId(),
            'businessName' => $bid->getName(),
            'modules' => [],
        ];

        if (in_array('crm', $modules)) {
            $data['modules']['crm'] = $this->exportCrm($em, $bid);
        }
        if (in_array('persons', $modules)) {
            $data['modules']['persons'] = $this->exportPersons($em, $bid);
        }
        if (in_array('accounting_summary', $modules)) {
            $data['modules']['accounting_summary'] = $this->exportAccountingSummary($em, $bid, $acc['year']);
        }

        $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        $filename = 'backup_' . $bid->getId() . '_' . date('Ymd_His') . '.json';

        return new Response($json, 200, [
            'Content-Type' => 'application/json; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    #[Route('/api/acc/backup/import', name: 'api_backup_import', methods: ['POST'])]
    public function import(Request $request, Access $access, EntityManagerInterface $em): JsonResponse
    {
        $acc = $access->hasRole('join'); if (!$acc) throw $this->createAccessDeniedException();
        $file = $request->files->get('file');
        if (!$file) return $this->json(['result' => -1, 'msg' => 'فایل ارسال نشده']);

        $content = file_get_contents($file->getPathname());
        $data = json_decode($content, true);
        if (!$data || !isset($data['version'])) return $this->json(['result' => -2, 'msg' => 'فایل پشتیبان معتبر نیست']);

        $stats = ['crm_centers' => 0, 'crm_tags' => 0, 'crm_activities' => 0, 'persons' => 0];

        if (isset($data['modules']['crm'])) {
            $r = $this->importCrm($em, $acc['bid'], $data['modules']['crm']);
            $stats['crm_centers'] = $r['centers'];
            $stats['crm_tags'] = $r['tags'];
            $stats['crm_activities'] = $r['activities'];
        }
        if (isset($data['modules']['persons'])) {
            $stats['persons'] = $this->importPersons($em, $acc['bid'], $data['modules']['persons']);
        }

        $em->flush();
        return $this->json(['result' => 1, 'stats' => $stats]);
    }

    #[Route('/api/acc/backup/info', name: 'api_backup_info', methods: ['POST'])]
    public function info(Access $access, EntityManagerInterface $em): JsonResponse
    {
        $acc = $access->hasRole('join'); if (!$acc) throw $this->createAccessDeniedException();
        $bid = $acc['bid'];
        return $this->json([
            'centers' => $em->getRepository(SalesCenter::class)->count(['bid' => $bid, 'active' => true]),
            'tags' => $em->getRepository(SalesCenterTag::class)->count(['bid' => $bid]),
            'activities' => $em->getRepository(ActivityLog::class)->count(['bid' => $bid]),
            'weekPlans' => $em->getRepository(WeekPlan::class)->count(['bid' => $bid]),
            'calendarEvents' => $em->getRepository(CrmCalendarEvent::class)->count(['bid' => $bid]),
            'checklistItems' => $em->getRepository(CrmChecklistItem::class)->count(['bid' => $bid]),
            'persons' => $em->getRepository(Person::class)->count(['bid' => $bid]),
        ]);
    }

    private function exportCrm(EntityManagerInterface $em, Business $bid): array
    {
        $tags = $em->getRepository(SalesCenterTag::class)->findBy(['bid' => $bid]);
        $centers = $em->getRepository(SalesCenter::class)->findBy(['bid' => $bid]);
        $activities = $em->getRepository(ActivityLog::class)->findBy(['bid' => $bid]);
        $events = $em->getRepository(CrmCalendarEvent::class)->findBy(['bid' => $bid]);
        $kpiTargets = $em->getRepository(KpiTarget::class)->findBy(['bid' => $bid]);
        $checklistItems = $em->getRepository(CrmChecklistItem::class)->findBy(['bid' => $bid]);

        return [
            'tags' => array_map(fn($t) => ['id' => $t->getId(), 'name' => $t->getName(), 'color' => $t->getColor()], $tags),
            'centers' => array_map(function ($c) {
                return [
                    'id' => $c->getId(), 'name' => $c->getName(), 'province' => $c->getProvince(),
                    'city' => $c->getCity(), 'type' => $c->getType(), 'potential' => $c->getPotential(),
                    'lead' => $c->getLead(), 'crmStatus' => $c->getCrmStatus(), 'followupDate' => $c->getFollowupDate(),
                    'tel' => $c->getTel(), 'address' => $c->getAddress(), 'active' => $c->isActive(),
                    'tags' => array_map(fn($t) => $t->getName(), $c->getTags()->toArray()),
                ];
            }, $centers),
            'activities' => array_map(function ($a) {
                return [
                    'type' => $a->getType(), 'date' => $a->getDate(),
                    'amount' => $a->getAmount(), 'cashSale' => $a->isCashSale(),
                    'done' => $a->isDone(), 'note' => $a->getNote(),
                    'centerName' => $a->getCenter() ? $a->getCenter()->getName() : null,
                    'userMobile' => $a->getUser() ? $a->getUser()->getMobile() : null,
                ];
            }, $activities),
            'calendarEvents' => array_map(fn($e) => [
                'title' => $e->getTitle(), 'date' => $e->getDate(), 'endDate' => $e->getEndDate(),
                'color' => $e->getColor(), 'allDay' => $e->isAllDay(), 'des' => $e->getDes(),
            ], $events),
            'kpiTargets' => array_map(fn($t) => [
                'month' => $t->getMonth(), 'callDailyTarget' => $t->getCallDailyTarget(),
                'visitWeeklyTarget' => $t->getVisitWeeklyTarget(), 'saleMonthlyTarget' => $t->getSaleMonthlyTarget(),
                'saleAmountTarget' => $t->getSaleAmountTarget(), 'missionMonthlyTarget' => $t->getMissionMonthlyTarget(),
                'cashPctTarget' => $t->getCashPctTarget(),
            ], $kpiTargets),
            'checklistItems' => array_map(fn($i) => [
                'name' => $i->getName(), 'category' => $i->getCategory(),
                'managerOnly' => $i->isManagerOnly(), 'score' => $i->getScore(),
            ], $checklistItems),
        ];
    }

    private function exportPersons(EntityManagerInterface $em, Business $bid): array
    {
        $persons = $em->getRepository(Person::class)->findBy(['bid' => $bid]);
        return array_map(fn($p) => [
            'name' => $p->getName(), 'nikename' => $p->getNikename(),
            'tel' => $p->getTel(), 'mobile' => $p->getMobile(),
            'address' => $p->getAddress(), 'code' => $p->getCode(),
            'des' => $p->getDes(),
        ], $persons);
    }

    private function exportAccountingSummary(EntityManagerInterface $em, Business $bid, $year): array
    {
        $tables = $em->getRepository(HesabdariTable::class)->findBy(['bid' => $bid]);
        return [
            'accounts_count' => count($tables),
            'tables' => array_map(fn($t) => [
                'code' => $t->getCode(), 'name' => $t->getName(), 'type' => $t->getType(),
            ], array_slice($tables, 0, 100)),
        ];
    }

    private function importCrm(EntityManagerInterface $em, Business $bid, array $crmData): array
    {
        $tagMap = [];
        $tagCount = 0;
        foreach ($crmData['tags'] ?? [] as $td) {
            $tag = $em->getRepository(SalesCenterTag::class)->findOneBy(['bid' => $bid, 'name' => $td['name']]);
            if (!$tag) { $tag = new SalesCenterTag(); $tag->setBid($bid)->setName($td['name'])->setColor($td['color'] ?? '#607D8B'); $em->persist($tag); $tagCount++; }
            $tagMap[$td['name']] = $tag;
        }

        $centerCount = 0;
        foreach ($crmData['centers'] ?? [] as $cd) {
            $center = $em->getRepository(SalesCenter::class)->findOneBy(['bid' => $bid, 'name' => $cd['name']]);
            if (!$center) { $center = new SalesCenter(); $center->setBid($bid); $centerCount++; }
            $center->setName($cd['name'])->setProvince($cd['province'] ?? null)->setCity($cd['city'] ?? null)
                ->setType($cd['type'] ?? null)->setPotential($cd['potential'] ?? null)
                ->setLead($cd['lead'] ?? null)->setCrmStatus($cd['crmStatus'] ?? 'no_contact')
                ->setFollowupDate($cd['followupDate'] ?? null)->setTel($cd['tel'] ?? null)
                ->setAddress($cd['address'] ?? null)->setActive($cd['active'] ?? true);
            foreach ($center->getTags() as $t) $center->getTags()->removeElement($t);
            foreach ($cd['tags'] ?? [] as $tn) {
                if (isset($tagMap[$tn])) $center->getTags()->add($tagMap[$tn]);
            }
            $em->persist($center);
        }

        $actCount = 0;
        // Activities are informational only in import — skip to avoid duplicates
        return ['centers' => $centerCount, 'tags' => $tagCount, 'activities' => $actCount];
    }

    private function importPersons(EntityManagerInterface $em, Business $bid, array $persons): int
    {
        $count = 0;
        foreach ($persons as $pd) {
            $person = $em->getRepository(Person::class)->findOneBy(['bid' => $bid, 'name' => $pd['name']]);
            if (!$person) { $person = new Person(); $person->setBid($bid); $count++; }
            $person->setName($pd['name'] ?? '');
            if (!empty($pd['nikename'])) $person->setNikename($pd['nikename']);
            if (!empty($pd['tel'])) $person->setTel($pd['tel']);
            if (!empty($pd['mobile'])) $person->setMobile($pd['mobile']);
            if (!empty($pd['address'])) $person->setAddress($pd['address']);
            $em->persist($person);
        }
        return $count;
    }
}
