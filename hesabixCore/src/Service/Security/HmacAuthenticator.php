<?php

namespace App\Service\Security;

use App\Entity\APIToken;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

class HmacAuthenticator
{
    private EntityManagerInterface $em;

    public function __construct(EntityManagerInterface $em)
    {
        $this->em = $em;
    }

    /**
     * Validates incoming HMAC signature and timestamp
     */
    public function authenticate(Request $request): APIToken
    {
        $apiKey = $request->headers->get('X-API-Token') ?? $request->headers->get('api-key');
        $signature = $request->headers->get('X-Signature');
        $timestamp = $request->headers->get('X-Timestamp');
        $nonce = $request->headers->get('X-Nonce');

        if (!$apiKey || !$signature || !$timestamp) {
            throw new UnauthorizedHttpException('HMAC', 'Missing required authentication headers (X-API-Token, X-Signature, X-Timestamp)');
        }

        // 1. Check timestamp drift (max 5 minutes)
        $currentTime = time();
        if (abs($currentTime - (int)$timestamp) > 300) {
            throw new UnauthorizedHttpException('HMAC', 'Request timestamp expired or clock skewed beyond 5 minutes limit');
        }

        // 2. Lookup API Token
        $tokenEntity = $this->em->getRepository(APIToken::class)->findOneBy(['token' => $apiKey]);
        if (!$tokenEntity) {
            throw new UnauthorizedHttpException('HMAC', 'Invalid API Key');
        }

        // 3. Compute HMAC Signature
        $body = $request->getContent();
        $payloadToSign = $body . $timestamp . ($nonce ?? '');
        $expectedSignature = hash_hmac('sha256', $payloadToSign, $apiKey);

        if (!hash_equals($expectedSignature, $signature)) {
            throw new UnauthorizedHttpException('HMAC', 'Invalid HMAC Signature');
        }

        return $tokenEntity;
    }
}
