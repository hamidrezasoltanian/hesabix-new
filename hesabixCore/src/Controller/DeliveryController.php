<?php

namespace App\Controller;

use App\Entity\DeliveryRecord;
use App\Entity\StoreroomTicket;
use App\Service\Access;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

class DeliveryController extends AbstractController
{
    #[Route('/api/storeroom/delivery/list', name: 'app_storeroom_delivery_list', methods: ['GET'])]
    public function list(
        Access $access,
        EntityManagerInterface $em
    ): JsonResponse {
        $acc = $access->hasRole('store');
        if (!$acc)
            throw $this->createAccessDeniedException();

        $records = $em->createQueryBuilder()
            ->select('dr')
            ->from(DeliveryRecord::class, 'dr')
            ->where('dr.bid = :bid')
            ->andWhere('dr.year = :year')
            ->setParameter('bid', $acc['bid'])
            ->setParameter('year', $acc['year'])
            ->orderBy('dr.id', 'DESC')
            ->getQuery()
            ->getResult();

        $result = [];
        foreach ($records as $dr) {
            $ticket = $dr->getTicket();
            $result[] = [
                'id' => $dr->getId(),
                'status' => $dr->getStatus(),
                'courier' => $dr->getCourier(),
                'trackingCode' => $dr->getTrackingCode(),
                'recipientName' => $dr->getRecipientName(),
                'recipientTel' => $dr->getRecipientTel(),
                'sentAt' => $dr->getSentAt(),
                'deliveredAt' => $dr->getDeliveredAt(),
                'ticket' => $ticket ? [
                    'id' => $ticket->getId(),
                    'code' => $ticket->getCode(),
                    'date' => $ticket->getDate(),
                    'person' => $ticket->getPerson() ? $ticket->getPerson()->getNikename() : null,
                ] : null,
            ];
        }

        return $this->json(['result' => 1, 'items' => $result]);
    }

    #[Route('/api/storeroom/delivery/create', name: 'app_storeroom_delivery_create', methods: ['POST'])]
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

        $ticket = null;
        if (!empty($params['ticketId'])) {
            $ticket = $em->getRepository(StoreroomTicket::class)->findOneBy([
                'id' => $params['ticketId'],
                'bid' => $acc['bid'],
            ]);
        }

        $dr = new DeliveryRecord();
        $dr->setBid($acc['bid']);
        $dr->setYear($acc['year']);
        $dr->setTicket($ticket);
        $dr->setStatus('pending');
        $dr->setCourier($params['courier'] ?? null);
        $dr->setRecipientName($params['recipientName'] ?? null);
        $dr->setRecipientTel($params['recipientTel'] ?? null);
        $dr->setNotes($params['notes'] ?? null);

        $em->persist($dr);
        $em->flush();

        return $this->json(['result' => 1, 'id' => $dr->getId()]);
    }

    #[Route('/api/storeroom/delivery/update/{id}', name: 'app_storeroom_delivery_update', methods: ['POST'])]
    public function update(
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

        $dr = $em->getRepository(DeliveryRecord::class)->findOneBy([
            'id' => $id,
            'bid' => $acc['bid'],
        ]);
        if (!$dr) {
            return $this->json(['result' => -1, 'message' => 'Delivery record not found']);
        }

        if (!empty($params['status'])) $dr->setStatus($params['status']);
        if (array_key_exists('trackingCode', $params)) $dr->setTrackingCode($params['trackingCode']);
        if (array_key_exists('sentAt', $params)) $dr->setSentAt($params['sentAt']);
        if (array_key_exists('deliveredAt', $params)) $dr->setDeliveredAt($params['deliveredAt']);
        if (array_key_exists('notes', $params)) $dr->setNotes($params['notes']);
        if (array_key_exists('courier', $params)) $dr->setCourier($params['courier']);
        if (array_key_exists('recipientName', $params)) $dr->setRecipientName($params['recipientName']);
        if (array_key_exists('recipientTel', $params)) $dr->setRecipientTel($params['recipientTel']);

        $em->flush();

        return $this->json(['result' => 1]);
    }

    #[Route('/api/storeroom/delivery/delete/{id}', name: 'app_storeroom_delivery_delete', methods: ['DELETE'])]
    public function delete(
        int $id,
        Access $access,
        EntityManagerInterface $em
    ): JsonResponse {
        $acc = $access->hasRole('store');
        if (!$acc)
            throw $this->createAccessDeniedException();

        $dr = $em->getRepository(DeliveryRecord::class)->findOneBy([
            'id' => $id,
            'bid' => $acc['bid'],
        ]);
        if (!$dr) {
            return $this->json(['result' => -1, 'message' => 'Delivery record not found']);
        }

        $em->remove($dr);
        $em->flush();

        return $this->json(['result' => 1]);
    }
}
