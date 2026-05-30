<?php

namespace App\Controller;

use App\Entity\Commodity;
use App\Entity\Storeroom;
use App\Entity\StoreroomCount;
use App\Entity\StoreroomCountItem;
use App\Service\Access;
use App\Service\Jdate;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

class StoreroomCountController extends AbstractController
{
    #[Route('/api/storeroom/count/start', name: 'app_storeroom_count_start', methods: ['POST'])]
    public function start(
        Request $request,
        Access $access,
        EntityManagerInterface $em,
        Jdate $jdate
    ): JsonResponse {
        $acc = $access->hasRole('store');
        if (!$acc)
            throw $this->createAccessDeniedException();

        $params = [];
        if ($content = $request->getContent()) {
            $params = json_decode($content, true);
        }

        if (empty($params['storeroomId']) || empty($params['date'])) {
            return $this->json(['result' => -1, 'message' => 'storeroomId and date are required']);
        }

        $storeroom = $em->getRepository(Storeroom::class)->findOneBy([
            'id' => $params['storeroomId'],
            'bid' => $acc['bid'],
        ]);
        if (!$storeroom) {
            return $this->json(['result' => -2, 'message' => 'Storeroom not found']);
        }

        $count = new StoreroomCount();
        $count->setBid($acc['bid']);
        $count->setStoreroom($storeroom);
        $count->setDate($params['date']);
        $count->setNote($params['note'] ?? null);
        $count->setStatus('open');
        $count->setCreatedBy($acc['user']);

        $em->persist($count);
        $em->flush();

        return $this->json(['result' => 1, 'id' => $count->getId()]);
    }

    #[Route('/api/storeroom/count/save-items/{id}', name: 'app_storeroom_count_save_items', methods: ['POST'])]
    public function saveItems(
        int $id,
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

        $count = $em->getRepository(StoreroomCount::class)->findOneBy([
            'id' => $id,
            'bid' => $acc['bid'],
        ]);
        if (!$count) {
            return $this->json(['result' => -1, 'message' => 'Count session not found']);
        }
        if ($count->getStatus() !== 'open') {
            return $this->json(['result' => -2, 'message' => 'Count session is not open']);
        }

        // Remove existing items
        foreach ($count->getItems() as $item) {
            $em->remove($item);
        }
        $em->flush();

        $items = $params['items'] ?? [];
        foreach ($items as $itemData) {
            if (empty($itemData['commodityId'])) continue;

            $commodity = $em->getRepository(Commodity::class)->findOneBy([
                'id' => $itemData['commodityId'],
                'bid' => $acc['bid'],
            ]);
            if (!$commodity) continue;

            $countItem = new StoreroomCountItem();
            $countItem->setCountSession($count);
            $countItem->setCommodity($commodity);
            $countItem->setLotNo($itemData['lotNo'] ?? null);
            $countItem->setExpiryDate($itemData['expiryDate'] ?? null);
            $countItem->setSystemQty($itemData['systemQty'] ?? '0');
            $countItem->setPhysicalQty($itemData['physicalQty'] ?? '0');
            $countItem->setNote($itemData['note'] ?? null);
            $em->persist($countItem);
        }

        $em->flush();

        return $this->json(['result' => 1]);
    }

    #[Route('/api/storeroom/count/approve/{id}', name: 'app_storeroom_count_approve', methods: ['POST'])]
    public function approve(
        int $id,
        Access $access,
        EntityManagerInterface $em,
        Jdate $jdate
    ): JsonResponse {
        $acc = $access->hasRole('store');
        if (!$acc)
            throw $this->createAccessDeniedException();

        $count = $em->getRepository(StoreroomCount::class)->findOneBy([
            'id' => $id,
            'bid' => $acc['bid'],
        ]);
        if (!$count) {
            return $this->json(['result' => -1, 'message' => 'Count session not found']);
        }

        $count->setStatus('approved');
        $count->setClosedDate($jdate->jdate('Y/m/d', time()));
        $em->flush();

        return $this->json(['result' => 1]);
    }

    #[Route('/api/storeroom/count/cancel/{id}', name: 'app_storeroom_count_cancel', methods: ['POST'])]
    public function cancel(
        int $id,
        Access $access,
        EntityManagerInterface $em
    ): JsonResponse {
        $acc = $access->hasRole('store');
        if (!$acc)
            throw $this->createAccessDeniedException();

        $count = $em->getRepository(StoreroomCount::class)->findOneBy([
            'id' => $id,
            'bid' => $acc['bid'],
        ]);
        if (!$count) {
            return $this->json(['result' => -1, 'message' => 'Count session not found']);
        }

        $count->setStatus('cancelled');
        $em->flush();

        return $this->json(['result' => 1]);
    }

    #[Route('/api/storeroom/count/list', name: 'app_storeroom_count_list', methods: ['GET'])]
    public function list(
        Access $access,
        EntityManagerInterface $em
    ): JsonResponse {
        $acc = $access->hasRole('store');
        if (!$acc)
            throw $this->createAccessDeniedException();

        $counts = $em->createQueryBuilder()
            ->select('sc')
            ->from(StoreroomCount::class, 'sc')
            ->where('sc.bid = :bid')
            ->setParameter('bid', $acc['bid'])
            ->orderBy('sc.id', 'DESC')
            ->getQuery()
            ->getResult();

        $result = [];
        foreach ($counts as $sc) {
            $result[] = [
                'id' => $sc->getId(),
                'storeroom' => $sc->getStoreroom() ? $sc->getStoreroom()->getName() : null,
                'date' => $sc->getDate(),
                'status' => $sc->getStatus(),
                'closedDate' => $sc->getClosedDate(),
                'createdBy' => $sc->getCreatedBy() ? $sc->getCreatedBy()->getFullName() : null,
                'itemCount' => $sc->getItems()->count(),
            ];
        }

        return $this->json(['result' => 1, 'items' => $result]);
    }

    #[Route('/api/storeroom/count/info/{id}', name: 'app_storeroom_count_info', methods: ['GET'])]
    public function info(
        int $id,
        Access $access,
        EntityManagerInterface $em
    ): JsonResponse {
        $acc = $access->hasRole('store');
        if (!$acc)
            throw $this->createAccessDeniedException();

        $count = $em->getRepository(StoreroomCount::class)->findOneBy([
            'id' => $id,
            'bid' => $acc['bid'],
        ]);
        if (!$count) {
            return $this->json(['result' => -1, 'message' => 'Count session not found']);
        }

        $items = [];
        foreach ($count->getItems() as $item) {
            $systemQty = (float)$item->getSystemQty();
            $physicalQty = (float)$item->getPhysicalQty();
            $items[] = [
                'id' => $item->getId(),
                'commodity' => $item->getCommodity() ? [
                    'id' => $item->getCommodity()->getId(),
                    'name' => $item->getCommodity()->getName(),
                    'code' => $item->getCommodity()->getCode(),
                ] : null,
                'lotNo' => $item->getLotNo(),
                'expiryDate' => $item->getExpiryDate(),
                'systemQty' => $item->getSystemQty(),
                'physicalQty' => $item->getPhysicalQty(),
                'difference' => (string)($physicalQty - $systemQty),
                'note' => $item->getNote(),
            ];
        }

        return $this->json([
            'result' => 1,
            'count' => [
                'id' => $count->getId(),
                'storeroom' => $count->getStoreroom() ? $count->getStoreroom()->getName() : null,
                'date' => $count->getDate(),
                'status' => $count->getStatus(),
                'closedDate' => $count->getClosedDate(),
                'note' => $count->getNote(),
                'createdBy' => $count->getCreatedBy() ? $count->getCreatedBy()->getFullName() : null,
                'items' => $items,
            ],
        ]);
    }

    #[Route('/api/storeroom/count/delete/{id}', name: 'app_storeroom_count_delete', methods: ['DELETE'])]
    public function delete(
        int $id,
        Access $access,
        EntityManagerInterface $em
    ): JsonResponse {
        $acc = $access->hasRole('store');
        if (!$acc)
            throw $this->createAccessDeniedException();

        $count = $em->getRepository(StoreroomCount::class)->findOneBy([
            'id' => $id,
            'bid' => $acc['bid'],
        ]);
        if (!$count) {
            return $this->json(['result' => -1, 'message' => 'Count session not found']);
        }

        foreach ($count->getItems() as $item) {
            $em->remove($item);
        }
        $em->remove($count);
        $em->flush();

        return $this->json(['result' => 1]);
    }
}
