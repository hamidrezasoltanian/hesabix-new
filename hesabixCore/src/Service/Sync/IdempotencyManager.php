<?php

namespace App\Service\Sync;

use App\Entity\IdempotencyLog;
use Doctrine\ORM\EntityManagerInterface;

class IdempotencyManager
{
    private EntityManagerInterface $em;

    public function __construct(EntityManagerInterface $em)
    {
        $this->em = $em;
    }

    /**
     * Checks if an idempotency key was already processed
     */
    public function getCachedResponse(string $idempotencyKey): ?array
    {
        $log = $this->em->getRepository(IdempotencyLog::class)->findOneBy(['idempotencyKey' => $idempotencyKey]);
        if ($log) {
            return [
                'statusCode' => $log->getStatusCode(),
                'data' => json_decode($log->getResponseBody(), true)
            ];
        }
        return null;
    }

    /**
     * Stores the response for an idempotency key
     */
    public function saveResponse(string $idempotencyKey, int $statusCode, array $responseBody): void
    {
        $log = new IdempotencyLog();
        $log->setIdempotencyKey($idempotencyKey);
        $log->setStatusCode($statusCode);
        $log->setResponseBody(json_encode($responseBody));
        $log->setCreatedAt(new \DateTime());

        $this->em->persist($log);
        $this->em->flush();
    }
}
