<?php
namespace App\Controller;

use App\Entity\CrmCalendarEvent;
use App\Entity\User;
use App\Service\Access;
use App\Service\Jdate;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

class CrmCalendarController extends AbstractController
{
    private function toArr(CrmCalendarEvent $e): array {
        return [
            'id' => $e->getId(), 'title' => $e->getTitle(),
            'date' => $e->getDate(), 'endDate' => $e->getEndDate(),
            'color' => $e->getColor(), 'allDay' => $e->isAllDay(), 'des' => $e->getDes(),
            'user' => ['id' => $e->getUser()->getId(), 'mobile' => $e->getUser()->getMobile()],
        ];
    }

    #[Route('/api/acc/crm/calendar/list', name: 'api_crm_calendar_list', methods: ['POST'])]
    public function list(Request $request, Access $access, EntityManagerInterface $em): JsonResponse
    {
        $acc = $access->hasRole('join'); if (!$acc) throw $this->createAccessDeniedException();
        $p = json_decode($request->getContent(), true) ?? [];

        $qb = $em->createQueryBuilder()->select('e')->from(CrmCalendarEvent::class, 'e')
            ->where('e.bid = :bid')->setParameter('bid', $acc['bid']);
        if (!empty($p['from'])) $qb->andWhere('e.date >= :from')->setParameter('from', $p['from']);
        if (!empty($p['to']))   $qb->andWhere('e.date <= :to')->setParameter('to', $p['to']);
        $events = $qb->orderBy('e.date', 'ASC')->getQuery()->getResult();
        return $this->json(array_map(fn($e) => $this->toArr($e), $events));
    }

    #[Route('/api/acc/crm/calendar/mod', name: 'api_crm_calendar_mod', methods: ['POST'])]
    public function mod(Request $request, Access $access, Jdate $jdate, EntityManagerInterface $em): JsonResponse
    {
        $acc = $access->hasRole('join'); if (!$acc) throw $this->createAccessDeniedException();
        $p = json_decode($request->getContent(), true) ?? [];
        if (empty($p['title']) || empty($p['date'])) return $this->json(['result' => -1]);

        $event = !empty($p['id'])
            ? $em->getRepository(CrmCalendarEvent::class)->findOneBy(['id' => $p['id'], 'bid' => $acc['bid']])
            : new CrmCalendarEvent();
        if (!$event) throw $this->createNotFoundException();

        $event->setBid($acc['bid'])->setUser($this->getUser())
            ->setTitle(trim($p['title']))->setDate($p['date'])
            ->setEndDate($p['endDate'] ?? null)->setColor($p['color'] ?? '#1976D2')
            ->setAllDay(($p['allDay'] ?? false) == true)->setDes($p['des'] ?? null);
        $em->persist($event); $em->flush();
        return $this->json(['result' => 1, 'id' => $event->getId()]);
    }

    #[Route('/api/acc/crm/calendar/del/{id}', name: 'api_crm_calendar_del', methods: ['POST'])]
    public function del(int $id, Access $access, EntityManagerInterface $em): JsonResponse
    {
        $acc = $access->hasRole('join'); if (!$acc) throw $this->createAccessDeniedException();
        $event = $em->getRepository(CrmCalendarEvent::class)->findOneBy(['id' => $id, 'bid' => $acc['bid']]);
        if (!$event) throw $this->createNotFoundException();
        $em->remove($event); $em->flush();
        return $this->json(['result' => 1]);
    }

    #[Route('/api/acc/crm/users', name: 'api_crm_users', methods: ['GET'])]
    public function users(Access $access, EntityManagerInterface $em): JsonResponse
    {
        $acc = $access->hasRole('join'); if (!$acc) throw $this->createAccessDeniedException();
        $perms = $em->createQueryBuilder()
            ->select('u.id, u.mobile, u.fullName AS name')->from('App\Entity\Permission', 'p')
            ->join('p.user', 'u')
            ->where('p.bid = :bid')->setParameter('bid', $acc['bid'])
            ->getQuery()->getScalarResult();
        return $this->json($perms);
    }
}
