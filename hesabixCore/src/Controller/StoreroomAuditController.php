<?php

namespace App\Controller;

use App\Entity\Log;
use App\Service\Access;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

class StoreroomAuditController extends AbstractController
{
    #[Route('/api/storeroom/audit/list', name: 'app_storeroom_audit_list', methods: ['POST'])]
    public function list(
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

        $qb = $em->createQueryBuilder()
            ->select('l')
            ->from(Log::class, 'l')
            ->where('l.bid = :bid')
            ->setParameter('bid', $acc['bid'])
            ->orderBy('l.id', 'DESC')
            ->setMaxResults(100);

        // Filter by user mobile if provided
        if (!empty($params['userMobile'])) {
            $qb->leftJoin('l.user', 'u')
                ->andWhere('u.mobile = :mobile')
                ->setParameter('mobile', $params['userMobile']);
        }

        // Filter by action type (part field)
        if (!empty($params['actionType'])) {
            $qb->andWhere('l.part LIKE :actionType')
                ->setParameter('actionType', '%' . $params['actionType'] . '%');
        }

        // Filter by date range
        if (!empty($params['fromDate'])) {
            $qb->andWhere('l.dateSubmit >= :fromDate')
                ->setParameter('fromDate', $params['fromDate']);
        }
        if (!empty($params['toDate'])) {
            $qb->andWhere('l.dateSubmit <= :toDate')
                ->setParameter('toDate', $params['toDate']);
        }

        $logs = $qb->getQuery()->getResult();

        $result = [];
        foreach ($logs as $log) {
            $result[] = [
                'id' => $log->getId(),
                'user' => $log->getUser() ? [
                    'id' => $log->getUser()->getId(),
                    'fullName' => $log->getUser()->getFullName(),
                ] : null,
                'date' => $log->getDateSubmit(),
                'part' => $log->getPart(),
                'des' => $log->getDes(),
                'ipaddress' => $log->getIpaddress(),
            ];
        }

        return $this->json(['result' => 1, 'items' => $result]);
    }
}
