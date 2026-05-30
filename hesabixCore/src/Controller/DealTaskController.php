<?php

namespace App\Controller;

use App\Entity\DealActivity;
use App\Entity\DealTask;
use App\Entity\SalesDeal;
use App\Service\Access;
use App\Service\Jdate;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

class DealTaskController extends AbstractController
{
    // ─── Tasks ───────────────────────────────────────────────────────────

    #[Route('/api/acc/dealtask/list/{dealId}', name: 'api_dealtask_list')]
    public function list(int $dealId, Access $access, EntityManagerInterface $em): JsonResponse
    {
        $acc = $access->hasRole('join');
        if (!$acc) throw $this->createAccessDeniedException();

        $deal = $em->getRepository(SalesDeal::class)->findOneBy(['id' => $dealId, 'bid' => $acc['bid']]);
        if (!$deal) throw $this->createNotFoundException();

        $tasks = $em->getRepository(DealTask::class)->findBy(
            ['deal' => $deal, 'bid' => $acc['bid']],
            ['displayOrder' => 'ASC', 'createdAt' => 'ASC']
        );

        return $this->json(array_map(fn($t) => $this->taskToArray($t), $tasks));
    }

    #[Route('/api/acc/dealtask/mod', name: 'api_dealtask_mod', methods: ['POST'])]
    public function mod(Request $request, Access $access, Jdate $jdate, EntityManagerInterface $em): JsonResponse
    {
        $acc = $access->hasRole('join');
        if (!$acc) throw $this->createAccessDeniedException();
        $p = json_decode($request->getContent(), true) ?? [];

        if (empty($p['title']) || empty($p['dealId']))
            return $this->json(['result' => -1]);

        $deal = $em->getRepository(SalesDeal::class)->findOneBy(['id' => $p['dealId'], 'bid' => $acc['bid']]);
        if (!$deal) throw $this->createNotFoundException();

        $task = !empty($p['id'])
            ? $em->getRepository(DealTask::class)->findOneBy(['id' => $p['id'], 'bid' => $acc['bid']])
            : new DealTask();
        if (!$task) throw $this->createNotFoundException();

        $isNew = !$task->getId();
        $task->setDeal($deal);
        $task->setBid($acc['bid']);
        $task->setTitle(trim($p['title']));
        if (isset($p['displayOrder'])) $task->setDisplayOrder((int)$p['displayOrder']);
        if ($isNew) $task->setCreatedAt($jdate->getToday());

        $em->persist($task);
        $em->flush();
        return $this->json(['result' => 1, 'id' => $task->getId()]);
    }

    #[Route('/api/acc/dealtask/toggle/{id}', name: 'api_dealtask_toggle', methods: ['POST'])]
    public function toggle(int $id, Access $access, Jdate $jdate, EntityManagerInterface $em): JsonResponse
    {
        $acc = $access->hasRole('join');
        if (!$acc) throw $this->createAccessDeniedException();

        $task = $em->getRepository(DealTask::class)->findOneBy(['id' => $id, 'bid' => $acc['bid']]);
        if (!$task) throw $this->createNotFoundException();

        $task->setDone(!$task->isDone());
        $task->setDoneAt($task->isDone() ? $jdate->getToday() : null);
        $task->setDoneBy($task->isDone() ? $this->getUser() : null);
        $em->flush();

        return $this->json(['result' => 1, 'done' => $task->isDone()]);
    }

    #[Route('/api/acc/dealtask/del/{id}', name: 'api_dealtask_del', methods: ['POST'])]
    public function del(int $id, Access $access, EntityManagerInterface $em): JsonResponse
    {
        $acc = $access->hasRole('join');
        if (!$acc) throw $this->createAccessDeniedException();

        $task = $em->getRepository(DealTask::class)->findOneBy(['id' => $id, 'bid' => $acc['bid']]);
        if (!$task) throw $this->createNotFoundException();

        $em->remove($task);
        $em->flush();
        return $this->json(['result' => 1]);
    }

    // ─── Activity / Comments ─────────────────────────────────────────────

    #[Route('/api/acc/dealactivity/list/{dealId}', name: 'api_dealactivity_list')]
    public function activityList(int $dealId, Access $access, EntityManagerInterface $em): JsonResponse
    {
        $acc = $access->hasRole('join');
        if (!$acc) throw $this->createAccessDeniedException();

        $deal = $em->getRepository(SalesDeal::class)->findOneBy(['id' => $dealId, 'bid' => $acc['bid']]);
        if (!$deal) throw $this->createNotFoundException();

        $activities = $em->getRepository(DealActivity::class)->findBy(
            ['deal' => $deal, 'bid' => $acc['bid']],
            ['id' => 'DESC']
        );

        return $this->json(array_map(fn($a) => $this->activityToArray($a), $activities));
    }

    #[Route('/api/acc/dealactivity/add', name: 'api_dealactivity_add', methods: ['POST'])]
    public function activityAdd(Request $request, Access $access, Jdate $jdate, EntityManagerInterface $em): JsonResponse
    {
        $acc = $access->hasRole('join');
        if (!$acc) throw $this->createAccessDeniedException();
        $p = json_decode($request->getContent(), true) ?? [];

        if (empty($p['dealId']) || empty($p['content']))
            return $this->json(['result' => -1]);

        $deal = $em->getRepository(SalesDeal::class)->findOneBy(['id' => $p['dealId'], 'bid' => $acc['bid']]);
        if (!$deal) throw $this->createNotFoundException();

        $activity = new DealActivity();
        $activity->setDeal($deal);
        $activity->setBid($acc['bid']);
        $activity->setUser($this->getUser());
        $activity->setType('comment');
        $activity->setContent(trim($p['content']));
        $activity->setDate($jdate->getToday());

        $em->persist($activity);
        $em->flush();
        return $this->json(['result' => 1, 'id' => $activity->getId()]);
    }

    // ─── helpers ─────────────────────────────────────────────────────────

    private function taskToArray(DealTask $t): array
    {
        return [
            'id'           => $t->getId(),
            'title'        => $t->getTitle(),
            'done'         => $t->isDone(),
            'doneAt'       => $t->getDoneAt(),
            'displayOrder' => $t->getDisplayOrder(),
            'createdAt'    => $t->getCreatedAt(),
            'doneBy'       => $t->getDoneBy() ? ['id' => $t->getDoneBy()->getId(), 'mobile' => $t->getDoneBy()->getMobile()] : null,
        ];
    }

    private function activityToArray(DealActivity $a): array
    {
        return [
            'id'      => $a->getId(),
            'type'    => $a->getType(),
            'content' => $a->getContent(),
            'date'    => $a->getDate(),
            'user'    => ['id' => $a->getUser()->getId(), 'mobile' => $a->getUser()->getMobile()],
        ];
    }
}
