<?php

namespace App\Service\Sync;

use App\Entity\SyncAuditLog;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;

class SyncAuditLogger
{
    private EntityManagerInterface $em;

    public function __construct(EntityManagerInterface $em)
    {
        $this->em = $em;
    }

    public function log(string $eventType, Request $request, int $statusCode, string $responseMessage): void
    {
        $log = new SyncAuditLog();
        $log->setEventType($eventType);
        $log->setIdempotencyKey($request->headers->get('X-Idempotency-Key'));
        $log->setClientIp($request->getClientIp());
        $log->setRequestPayload($request->getContent());
        $log->setStatusCode($statusCode);
        $log->setResponseMessage($responseMessage);
        $log->setCreatedAt(new \DateTime());

        $this->em->persist($log);
        $this->em->flush();
    }
}
