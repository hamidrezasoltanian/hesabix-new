<?php

namespace App\Controller;

use App\Entity\Commodity;
use App\Entity\StoreroomRecall;
use App\Service\Access;
use App\Service\Jdate;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

class StoreroomRecallController extends AbstractController
{
    #[Route('/api/storeroom/recall/create', name: 'app_storeroom_recall_create', methods: ['POST'])]
    public function create(
        Request $request,
        Access $access,
        EntityManagerInterface $em
    ): JsonResponse {
        $acc = $access->hasRole('store');
        if (!$acc)
            throw $this->createAccessDeniedException();

        $params = [];
        if ($content = $request->getContent()) {
            $params = json_decode($content, true);
        }

        if (empty($params['commodityId']) || empty($params['startDate'])) {
            return $this->json(['result' => -1, 'message' => 'commodityId and startDate are required']);
        }

        $commodity = $em->getRepository(Commodity::class)->findOneBy([
            'id' => $params['commodityId'],
            'bid' => $acc['bid'],
        ]);
        if (!$commodity) {
            return $this->json(['result' => -2, 'message' => 'Commodity not found']);
        }

        $recall = new StoreroomRecall();
        $recall->setBid($acc['bid']);
        $recall->setYear($acc['year']);
        $recall->setCommodity($commodity);
        $recall->setLotNo($params['lotNo'] ?? null);
        $recall->setReason($params['reason'] ?? null);
        $recall->setPriority($params['priority'] ?? 'medium');
        $recall->setStatus('active');
        $recall->setStartDate($params['startDate']);
        $recall->setAffectedCount($params['affectedCount'] ?? null);
        $recall->setNotes($params['notes'] ?? null);

        $em->persist($recall);
        $em->flush();

        return $this->json(['result' => 1, 'id' => $recall->getId()]);
    }

    #[Route('/api/storeroom/recall/list', name: 'app_storeroom_recall_list', methods: ['GET'])]
    public function list(
        Access $access,
        EntityManagerInterface $em
    ): JsonResponse {
        $acc = $access->hasRole('store');
        if (!$acc)
            throw $this->createAccessDeniedException();

        $recalls = $em->createQueryBuilder()
            ->select('r')
            ->from(StoreroomRecall::class, 'r')
            ->where('r.bid = :bid')
            ->setParameter('bid', $acc['bid'])
            ->orderBy('r.id', 'DESC')
            ->getQuery()
            ->getResult();

        $result = [];
        foreach ($recalls as $recall) {
            $result[] = [
                'id' => $recall->getId(),
                'commodity' => $recall->getCommodity() ? [
                    'id' => $recall->getCommodity()->getId(),
                    'name' => $recall->getCommodity()->getName(),
                ] : null,
                'lotNo' => $recall->getLotNo(),
                'priority' => $recall->getPriority(),
                'status' => $recall->getStatus(),
                'startDate' => $recall->getStartDate(),
                'endDate' => $recall->getEndDate(),
                'affectedCount' => $recall->getAffectedCount(),
                'reason' => $recall->getReason(),
            ];
        }

        return $this->json(['result' => 1, 'items' => $result]);
    }

    #[Route('/api/storeroom/recall/resolve/{id}', name: 'app_storeroom_recall_resolve', methods: ['POST'])]
    public function resolve(
        int $id,
        Access $access,
        EntityManagerInterface $em,
        Jdate $jdate
    ): JsonResponse {
        $acc = $access->hasRole('store');
        if (!$acc)
            throw $this->createAccessDeniedException();

        $recall = $em->getRepository(StoreroomRecall::class)->findOneBy([
            'id' => $id,
            'bid' => $acc['bid'],
        ]);
        if (!$recall) {
            return $this->json(['result' => -1, 'message' => 'Recall not found']);
        }

        $recall->setStatus('resolved');
        $recall->setEndDate($jdate->jdate('Y/m/d', time()));
        $em->flush();

        return $this->json(['result' => 1]);
    }

    #[Route('/api/storeroom/recall/delete/{id}', name: 'app_storeroom_recall_delete', methods: ['DELETE'])]
    public function delete(
        int $id,
        Access $access,
        EntityManagerInterface $em
    ): JsonResponse {
        $acc = $access->hasRole('store');
        if (!$acc)
            throw $this->createAccessDeniedException();

        $recall = $em->getRepository(StoreroomRecall::class)->findOneBy([
            'id' => $id,
            'bid' => $acc['bid'],
        ]);
        if (!$recall) {
            return $this->json(['result' => -1, 'message' => 'Recall not found']);
        }

        $em->remove($recall);
        $em->flush();

        return $this->json(['result' => 1]);
    }
}
