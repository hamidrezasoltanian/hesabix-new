<?php

namespace App\Controller\Api\V1;

use App\Entity\Business;
use App\Entity\Commodity;
use App\Entity\CommodityUnit;
use App\Entity\InvoiceType;
use App\Entity\Money;
use App\Entity\Person;
use App\Entity\PreInvoiceDoc;
use App\Entity\PreInvoiceItem;
use App\Entity\Year;
use App\Service\Provider;
use App\Service\Security\HmacAuthenticator;
use App\Service\Sync\ClickSellException;
use App\Service\Sync\ClickSellLifecycle;
use App\Service\Sync\FinancialSanityChecker;
use App\Service\Sync\IdempotencyManager;
use App\Service\Sync\SyncAuditLogger;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/v1/click/sync')]
class ClickSyncController extends AbstractController
{
    private HmacAuthenticator $authenticator;
    private IdempotencyManager $idempotency;
    private FinancialSanityChecker $sanityChecker;
    private SyncAuditLogger $auditLogger;
    private EntityManagerInterface $em;
    private Provider $provider;
    private ClickSellLifecycle $lifecycle;

    public function __construct(
        HmacAuthenticator $authenticator,
        IdempotencyManager $idempotency,
        FinancialSanityChecker $sanityChecker,
        SyncAuditLogger $auditLogger,
        EntityManagerInterface $em,
        Provider $provider,
        ClickSellLifecycle $lifecycle
    ) {
        $this->authenticator = $authenticator;
        $this->idempotency = $idempotency;
        $this->sanityChecker = $sanityChecker;
        $this->auditLogger = $auditLogger;
        $this->em = $em;
        $this->provider = $provider;
        $this->lifecycle = $lifecycle;
    }

    private function findOrCreatePersonWithRetry(Business $business, array $payload): Person
    {
        $delays = [50000, 100000, 200000]; // 50ms, 100ms, 200ms in microseconds
        $attempts = 0;

        while ($attempts <= count($delays)) {
            $person = $this->em->getRepository(Person::class)->findOneBy([
                'nikename' => $payload['name'],
                'bid' => $business
            ]);

            if ($person) {
                return $person;
            }

            try {
                $person = new Person();
                $person->setCode($this->provider->getAccountingCode($business->getId(), 'person'));
                $person->setName($payload['name']);
                $person->setNikename($payload['name']);
                $person->setBid($business);
                if (!empty($payload['mobile'])) $person->setMobile($payload['mobile']);
                if (!empty($payload['codeeMeli'])) $person->setCodeMeli($payload['codeeMeli']);

                $this->em->persist($person);
                $this->em->flush();
                return $person;
            } catch (UniqueConstraintViolationException $e) {
                if ($attempts < count($delays)) {
                    usleep($delays[$attempts]);
                    $attempts++;
                } else {
                    // Final attempt fallback select
                    return $this->em->getRepository(Person::class)->findOneBy([
                        'nikename' => $payload['name'],
                        'bid' => $business
                    ]);
                }
            }
        }
        throw new \RuntimeException('Failed to resolve person after concurrent retry attempts');
    }

    #[Route('/person', name: 'api_v1_click_sync_person', methods: ['POST'])]
    public function syncPerson(Request $request): JsonResponse
    {
        $token = $this->authenticator->authenticate($request);
        $idempotencyKey = $request->headers->get('X-Idempotency-Key');

        if ($idempotencyKey && ($cached = $this->idempotency->getCachedResponse($idempotencyKey))) {
            return $this->json($cached['data'], $cached['statusCode']);
        }

        $payload = json_decode($request->getContent(), true);
        if (empty($payload['name'])) {
            return $this->json(['success' => false, 'error' => 'Person name is required'], Response::HTTP_BAD_REQUEST);
        }

        $business = $token->getBid();
        $person = $this->findOrCreatePersonWithRetry($business, $payload);

        $response = ['success' => true, 'personId' => $person->getId(), 'code' => $person->getCode()];
        if ($idempotencyKey) {
            $this->idempotency->saveResponse($idempotencyKey, Response::HTTP_OK, $response);
        }
        $this->auditLogger->log('sync_person', $request, Response::HTTP_OK, 'Person synced successfully');

        return $this->json($response, Response::HTTP_OK);
    }

    #[Route('/commodity', name: 'api_v1_click_sync_commodity', methods: ['POST'])]
    public function syncCommodity(Request $request): JsonResponse
    {
        $token = $this->authenticator->authenticate($request);
        $idempotencyKey = $request->headers->get('X-Idempotency-Key');

        if ($idempotencyKey && ($cached = $this->idempotency->getCachedResponse($idempotencyKey))) {
            return $this->json($cached['data'], $cached['statusCode']);
        }

        $payload = json_decode($request->getContent(), true);
        if (empty($payload['name'])) {
            return $this->json(['success' => false, 'error' => 'Commodity name is required'], Response::HTTP_BAD_REQUEST);
        }

        $business = $token->getBid();
        $commodity = $this->em->getRepository(Commodity::class)->findOneBy([
            'name' => $payload['name'],
            'bid' => $business
        ]);

        if (!$commodity) {
            $commodity = new Commodity();
            $commodity->setCode($this->provider->getAccountingCode($business->getId(), 'Commodity'));
            $commodity->setName($payload['name']);
            $commodity->setBid($business);

            $unit = $this->em->getRepository(CommodityUnit::class)->find(1);
            if ($unit) $commodity->setUnit($unit);

            $commodity->setPriceSell($payload['priceSell'] ?? 0);
            $commodity->setPriceBuy($payload['priceBuy'] ?? 0);

            $this->em->persist($commodity);
            $this->em->flush();
        }

        $response = ['success' => true, 'commodityId' => $commodity->getId(), 'code' => $commodity->getCode()];
        if ($idempotencyKey) {
            $this->idempotency->saveResponse($idempotencyKey, Response::HTTP_OK, $response);
        }
        $this->auditLogger->log('sync_commodity', $request, Response::HTTP_OK, 'Commodity synced successfully');

        return $this->json($response, Response::HTTP_OK);
    }

    #[Route('/invoice/create', name: 'api_v1_click_sync_invoice_create', methods: ['POST'])]
    public function createInvoice(Request $request): JsonResponse
    {
        return $this->createSellDraft($request);
    }

    #[Route('/sell/draft', name: 'api_v1_click_sync_sell_draft', methods: ['POST'])]
    public function createSellDraft(Request $request): JsonResponse
    {
        $token = $this->authenticator->authenticate($request);
        $idempotencyKey = $request->headers->get('X-Idempotency-Key');

        if ($idempotencyKey && ($cached = $this->idempotency->getCachedResponse($idempotencyKey))) {
            return $this->json($cached['data'], $cached['statusCode']);
        }

        $payload = json_decode($request->getContent(), true);
        if (!is_array($payload)) {
            return $this->json(['success' => false, 'code' => 'INVALID_JSON', 'error' => 'بدنه نامعتبر است'], Response::HTTP_BAD_REQUEST);
        }

        $eventId = trim((string)($payload['event_id'] ?? ''));
        $payloadHash = trim((string)($payload['payload_hash'] ?? ''));
        if ($eventId === '' || !preg_match('/^sell\\.(draft|return\\.draft):[A-Za-z0-9._-]+$/', $eventId)) {
            return $this->json(['success' => false, 'code' => 'EVENT_ID_REQUIRED', 'error' => 'event_id نامعتبر است'], Response::HTTP_BAD_REQUEST);
        }
        if (!preg_match('/^[a-f0-9]{64}$/', $payloadHash)) {
            return $this->json(['success' => false, 'code' => 'PAYLOAD_HASH_REQUIRED', 'error' => 'payload_hash نامعتبر است'], Response::HTTP_BAD_REQUEST);
        }

        try {
            $this->sanityChecker->validateInvoice($payload);
        } catch (\Exception $e) {
            $this->auditLogger->log('sync_sell_draft', $request, Response::HTTP_BAD_REQUEST, $e->getMessage());
            return $this->json(['success' => false, 'code' => 'SANITY', 'error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }

        $docKind = (string)($payload['docKind'] ?? 'official');
        if ($docKind === 'adjustment') {
            foreach ($payload['items'] as $idx => $item) {
                if ((float)($item['tax'] ?? 0) > 0.0001) {
                    return $this->json(['success' => false, 'code' => 'ADJUSTMENT_TAX', 'error' => 'فاکتور تنظیمی نباید مالیات داشته باشد'], Response::HTTP_BAD_REQUEST);
                }
            }
        }
        $isReturnDraft = str_starts_with($eventId, 'sell.return.draft:') || $docKind === 'sales_return';

        $business = $token->getBid();
        $year = $this->em->getRepository(Year::class)->findOneBy(['bid' => $business, 'head' => true]);
        if (!$year) {
            return $this->json(['success' => false, 'code' => 'NO_OPEN_YEAR', 'error' => 'سال مالی باز در حسابیکس نیست'], Response::HTTP_BAD_REQUEST);
        }
        $money = $business->getMoney() ?: $this->em->getRepository(Money::class)->findOneBy([]);
        if (!$money) {
            return $this->json(['success' => false, 'code' => 'NO_MONEY', 'error' => 'ارز کسب‌وکار یافت نشد'], Response::HTTP_BAD_REQUEST);
        }

        $existing = $this->em->getRepository(PreInvoiceDoc::class)->findOneBy([
            'bid' => $business,
            'refData' => $eventId,
        ]);
        if ($existing) {
            $storedHash = $this->hashFromPlugin($existing->getPlugin());
            if ($storedHash === $payloadHash) {
                $this->upsertClickPerson($business, $payload['person'] ?? [], (string)($payload['centerKey'] ?? ''));
                $this->applyClickDocKind($existing, $payload);
                $this->em->flush();
                $response = $this->draftResponse($existing, true);
                if ($idempotencyKey) {
                    $this->idempotency->saveResponse($idempotencyKey, Response::HTTP_OK, $response);
                }
                return $this->json($response, Response::HTTP_OK);
            }
            return $this->json([
                'success' => false,
                'code' => 'PAYLOAD_CONFLICT',
                'error' => 'برای این فاکتور پیش‌نویس دیگری با محتوای متفاوت ثبت شده است',
                'event_id' => $eventId,
                'existingDocId' => $existing->getId(),
                'existingCode' => $existing->getCode(),
            ], Response::HTTP_CONFLICT);
        }

        $this->em->beginTransaction();
        try {
            $person = $this->upsertClickPerson($business, $payload['person'] ?? [], (string)($payload['centerKey'] ?? ''));
            $pre = new PreInvoiceDoc();
            $pre->setBid($business);
            $pre->setYear($year);
            $pre->setMoney($money);
            $pre->setPerson($person);
            $pre->setSubmitter($token->getSubmitter());
            $pre->setCode($isReturnDraft
                ? (string)$this->provider->getAccountingCode($business->getId(), 'accounting')
                : $this->clickDocumentCode($business, $year, (string)($payload['invoiceNo'] ?? '')));
            $pre->setDate((string)($payload['jalaliDate'] ?? ''));
            $pre->setDes(mb_substr(($payload['invoiceNo'] ?? '') . ' ' . ($payload['description'] ?? ($isReturnDraft ? 'پیش‌فاکتور برگشت از فروش کلیک' : 'پیش‌نویس فروش کلیک')), 0, 255));
            $pre->setAmount((string)((int)round((float)($payload['totalAmount'] ?? 0)) + (int)round((float)($payload['totalDiscount'] ?? 0))));
            $pre->setTaxPercent((string)(float)($payload['taxPercent'] ?? 0));
            $pre->setTotalDiscount((string)(int)round((float)($payload['totalDiscount'] ?? 0)));
            $pre->setTotalDiscountPercent('0');
            $pre->setShippingCost('0');
            $pre->setShowTotalPercentDiscount(false);
            $pre->setShowPercentDiscount(false);
            $pre->setStatus($isReturnDraft ? 'click_return_draft' : 'click_draft');
            $pre->setPlugin('click_h:' . $payloadHash);
            $pre->setRefData($eventId);
            $this->applyClickDocKind($pre, $payload);
            $this->em->persist($pre);

            foreach ($payload['items'] as $item) {
                $row = new PreInvoiceItem();
                $row->setDoc($pre);
                $row->setCommodity($this->findOrCreateClickCommodity($business, $item));
                $row->setCommodityCount((string)($item['quantity'] ?? 0));
                $row->setBs((string)(int)round((float)($item['unitPrice'] ?? 0)));
                $row->setDiscountAmount((string)(int)round((float)($item['discount'] ?? 0)));
                $row->setDiscountPercent((string)(float)($item['discountPercent'] ?? 0));
                $row->setTax((string)(int)round((float)($item['tax'] ?? 0)));
                $row->setDes(mb_substr((string)($item['name'] ?? ''), 0, 255));
                $row->setShowPercentDiscount(((float)($item['discountPercent'] ?? 0)) > 0);
                $this->em->persist($row);
            }

            $this->em->flush();
            $this->em->commit();

            $response = $this->draftResponse($pre, false);
            if ($idempotencyKey) {
                $this->idempotency->saveResponse($idempotencyKey, Response::HTTP_OK, $response);
            }
            $this->auditLogger->log('sync_sell_draft', $request, Response::HTTP_OK, 'Click sell draft stored');
            return $this->json($response, Response::HTTP_OK);
        } catch (\Exception $e) {
            $this->em->rollback();
        $this->auditLogger->log('sync_sell_draft', $request, Response::HTTP_INTERNAL_SERVER_ERROR, $e->getMessage());
            return $this->json(['success' => false, 'code' => 'DRAFT_FAILED', 'error' => $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/docs-status', name: 'api_v1_click_sync_docs_status', methods: ['POST'])]
    public function docsStatus(Request $request): JsonResponse
    {
        $token = $this->authenticator->authenticate($request);
        $payload = json_decode($request->getContent(), true);
        if (!is_array($payload)) {
            return $this->json(['success' => false, 'code' => 'INVALID_JSON', 'error' => 'بدنه نامعتبر است'], Response::HTTP_BAD_REQUEST);
        }
        $ids = [];
        foreach (($payload['invoiceIds'] ?? []) as $id) {
            $id = trim((string)$id);
            if ($id !== '' && !in_array($id, $ids, true)) {
                $ids[] = $id;
            }
            if (count($ids) >= 80) {
                break;
            }
        }
        return $this->json([
            'success' => true,
            'items' => $this->lifecycle->docsStatusForClickInvoices($token->getBid(), $ids),
        ]);
    }

    #[Route('/sell/issue', name: 'api_v1_click_sync_sell_issue', methods: ['POST'])]
    public function issueSell(Request $request): JsonResponse
    {
        return $this->runLifecycle($request, 'issue', 'sync_sell_issue');
    }

    #[Route('/sell/return', name: 'api_v1_click_sync_sell_return', methods: ['POST'])]
    public function returnSell(Request $request): JsonResponse
    {
        return $this->runLifecycle($request, 'return', 'sync_sell_return');
    }

    #[Route('/sell/cancel', name: 'api_v1_click_sync_sell_cancel', methods: ['POST'])]
    public function cancelSell(Request $request): JsonResponse
    {
        return $this->runLifecycle($request, 'cancel', 'sync_sell_cancel');
    }

    #[Route('/sell/return-draft/cancel', name: 'api_v1_click_sync_sell_return_draft_cancel', methods: ['POST'])]
    public function cancelReturnDraft(Request $request): JsonResponse
    {
        return $this->runLifecycle($request, 'cancel-return-draft', 'sync_sell_return_draft_cancel');
    }

    #[Route('/sell/receive', name: 'api_v1_click_sync_sell_receive', methods: ['POST'])]
    public function receiveSell(Request $request): JsonResponse
    {
        return $this->runLifecycle($request, 'receive', 'sync_sell_receive');
    }

    private function runLifecycle(Request $request, string $op, string $audit): JsonResponse
    {
        $token = $this->authenticator->authenticate($request);
        $idempotencyKey = $request->headers->get('X-Idempotency-Key');
        if ($idempotencyKey && ($cached = $this->idempotency->getCachedResponse($idempotencyKey))) {
            return $this->json($cached['data'], $cached['statusCode']);
        }
        $payload = json_decode($request->getContent(), true);
        if (!is_array($payload)) {
            return $this->json(['success' => false, 'code' => 'INVALID_JSON', 'error' => 'بدنه نامعتبر است'], Response::HTTP_BAD_REQUEST);
        }
        try {
            $response = match ($op) {
                'issue' => $this->lifecycle->issue($token, $payload),
                'return' => $this->lifecycle->salesReturn($token, $payload),
                'cancel' => $this->lifecycle->cancel($token, $payload),
                'receive' => $this->lifecycle->receive($token, $payload),
                'cancel-return-draft' => $this->lifecycle->cancelReturnDraft($token, $payload),
                default => throw new ClickSellException('UNKNOWN_OP', 'عملیات ناشناخته', 400),
            };
            if ($idempotencyKey) {
                $this->idempotency->saveResponse($idempotencyKey, Response::HTTP_OK, $response);
            }
            $this->auditLogger->log($audit, $request, Response::HTTP_OK, $op . ' ok');
            return $this->json($response, Response::HTTP_OK);
        } catch (ClickSellException $e) {
            $this->auditLogger->log($audit, $request, $e->httpStatus, $e->getMessage());
            return $this->json($e->toArray(), $e->httpStatus);
        }
    }

    private function draftResponse(PreInvoiceDoc $pre, bool $replay): array
    {
        return [
            'success' => true,
            'status' => 'draft',
            'replay' => $replay,
            'docId' => $pre->getId(),
            'docNum' => $pre->getCode(),
            'event_id' => $pre->getRefData(),
            'kind' => 'preinvoice',
            'personId' => $pre->getPerson() ? $pre->getPerson()->getId() : null,
            'personCode' => $pre->getPerson() ? $pre->getPerson()->getCode() : null,
            'personName' => $pre->getPerson() ? ($pre->getPerson()->getNikename() ?: $pre->getPerson()->getName()) : null,
        ];
    }

    private function hashFromPlugin(?string $plugin): string
    {
        $p = (string)$plugin;
        if (preg_match('/^click_h:([a-f0-9]{64})/', $p, $m)) {
            return $m[1];
        }
        return '';
    }

    private function applyClickDocKind(PreInvoiceDoc $pre, array $payload): void
    {
        $docKind = (string)($payload['docKind'] ?? 'official');
        $isAdj = $docKind === 'adjustment';
        $isReturn = $docKind === 'sales_return' || str_starts_with((string)$pre->getRefData(), 'sell.return.draft:');
        $code = $isReturn ? 'click_rfsell' : ($isAdj ? 'click_adjustment' : 'click_official');
        $label = $this->ensureClickInvoiceType($code, $isAdj, $isReturn ? 'برگشت از فروش کلیک' : null);
        $pre->setInvoiceLabel($label);
        $hash = $this->hashFromPlugin($pre->getPlugin()) ?: trim((string)($payload['payload_hash'] ?? ''));
        $inv = trim((string)($payload['clickInvoiceId'] ?? ''));
        if ($isReturn) {
            $pre->setPlugin('click_h:' . $hash . '|kind:rfsell' . ($inv !== '' ? ('|click_inv:' . $inv) : ''));
            return;
        }
        if ($isAdj) {
            $pre->setTaxPercent('0');
            $pre->setPlugin('click_h:' . $hash . '|kind:adjustment|nomoodian:1');
        }
    }

    private function ensureClickInvoiceType(string $code, bool $adjustment, ?string $forcedLabel = null): InvoiceType
    {
        $row = $this->em->getRepository(InvoiceType::class)->findOneBy([
            'code' => $code,
            'type' => 'sell',
        ]);
        if ($row) {
            return $row;
        }
        $row = new InvoiceType();
        $row->setCode($code);
        $row->setType('sell');
        $row->setLabel($forcedLabel ?: ($adjustment ? 'تنظیمی — بدون سامانه مودیان' : 'رسمی کلیک'));
        $this->em->persist($row);
        $this->em->flush();
        return $row;
    }

    private function upsertClickPerson(Business $business, array $personPayload, string $centerKey): Person
    {
        $centerKey = trim($centerKey);
        $marker = $centerKey !== '' ? ('click:' . $centerKey) : '';
        $person = null;
        if (!empty($personPayload['personId'])) {
            $person = $this->em->getRepository(Person::class)->findOneBy([
                'id' => (int)$personPayload['personId'],
                'bid' => $business,
            ]);
        }
        if (!$person && $marker !== '') {
            $person = $this->em->getRepository(Person::class)->findOneBy([
                'des' => $marker,
                'bid' => $business,
            ]);
        }
        if (!$person && $centerKey !== '') {
            $person = $this->em->getRepository(Person::class)->findOneBy([
                'des' => 'click-center:' . $centerKey,
                'bid' => $business,
            ]);
        }
        $name = $this->utf8Field(trim((string)($personPayload['name'] ?? $centerKey ?: 'مشتری کلیک')));
        if (!$person) {
            $person = new Person();
            $person->setBid($business);
            $person->setCode($this->nextUniquePersonCode($business));
            $person->setNikename($name);
            $person->setName($name);
            if ($marker !== '') {
                $person->setDes($marker);
            }
            $this->em->persist($person);
        } else {
            if ($name !== '') {
                $person->setNikename($name);
                $person->setName($name);
            }
            if ($marker !== '' && !$person->getDes()) {
                $person->setDes($marker);
            }
            $this->ensureUniquePersonCode($business, $person);
        }
        $mobile = preg_replace('/\D+/', '', (string)($personPayload['mobile'] ?? ''));
        if ($mobile !== '') {
            $person->setMobile(substr($mobile, 0, 12));
        }
        if (!empty($personPayload['nationalId'])) {
            $person->setShenasemeli(mb_substr((string)$personPayload['nationalId'], 0, 20));
        }
        if (!empty($personPayload['economicCode'])) {
            $person->setCodeeghtesadi(mb_substr((string)$personPayload['economicCode'], 0, 20));
        }
        $regId = trim((string)($personPayload['registrationId'] ?? $personPayload['sabt'] ?? ''));
        if ($regId !== '') {
            $person->setSabt(mb_substr($regId, 0, 20));
        }
        $postal = preg_replace('/\D+/', '', (string)($personPayload['postalCode'] ?? $personPayload['postalcode'] ?? ''));
        if ($postal !== '') {
            $person->setPostalcode(substr($postal, 0, 10));
        }
        if (!empty($personPayload['address'])) {
            $person->setAddress(mb_substr($this->utf8Field((string)$personPayload['address']), 0, 255, 'UTF-8'));
        }
        $ostan = $this->utf8Field((string)($personPayload['province'] ?? $personPayload['ostan'] ?? ''));
        if ($ostan !== '') {
            $person->setOstan(mb_substr($ostan, 0, 80, 'UTF-8'));
        }
        $shahr = $this->utf8Field((string)($personPayload['city'] ?? $personPayload['shahr'] ?? ''));
        if ($shahr !== '') {
            $person->setShahr(mb_substr($shahr, 0, 80, 'UTF-8'));
        }
        $this->em->flush();
        return $person;
    }

    private function findOrCreateClickCommodity(Business $business, array $item): Commodity
    {
        $sku = trim((string)($item['code'] ?? ''));
        $name = trim((string)($item['name'] ?? 'کالای کلیک')) ?: 'کالای کلیک';
        $marker = $sku !== '' ? ('click_sku:' . $sku) : ('click_name:' . mb_substr($name, 0, 80));
        $commodity = $this->em->getRepository(Commodity::class)->findOneBy([
            'des' => $marker,
            'bid' => $business,
        ]);
        if ($commodity) {
            return $commodity;
        }
        $commodity = new Commodity();
        $commodity->setBid($business);
        $commodity->setName(mb_substr($name, 0, 255));
        $commodity->setDes($marker);
        $commodity->setCode($this->provider->getAccountingCode($business->getId(), 'Commodity'));
        $commodity->setKhadamat(true);
        $commodity->setPriceSell((int)round((float)($item['unitPrice'] ?? 0)));
        $unit = $this->em->getRepository(CommodityUnit::class)->findOneBy([]);
        if ($unit) {
            $commodity->setUnit($unit);
        }
        $this->em->persist($commodity);
        $this->em->flush();
        return $commodity;
    }

    private function clickDocumentCode(Business $business, Year $year, string $invoiceNo): string
    {
        $short = $this->lastFourInvoiceDigits($invoiceNo);
        if ($short !== '') {
            $taken = $this->em->getRepository(PreInvoiceDoc::class)->findOneBy([
                'bid' => $business,
                'year' => $year,
                'code' => $short,
            ]);
            if (!$taken) {
                return $short;
            }
        }
        return (string)$this->provider->getAccountingCode($business->getId(), 'accounting');
    }

    private function lastFourInvoiceDigits(string $raw): string
    {
        $digits = preg_replace('/\D+/', '', $raw);
        if ($digits === null || $digits === '') {
            return '';
        }
        return substr($digits, -4);
    }

    private function nextUniquePersonCode(Business $business): string
    {
        for ($i = 0; $i < 50; $i++) {
            $code = (string)$this->provider->getAccountingCode($business->getId(), 'person');
            $exist = $this->em->getRepository(Person::class)->findOneBy([
                'bid' => $business,
                'code' => $code,
            ]);
            if (!$exist) {
                return $code;
            }
        }
        return (string)(time() % 1000000000);
    }

    private function ensureUniquePersonCode(Business $business, Person $person): void
    {
        $code = (string)$person->getCode();
        $dup = $this->em->createQueryBuilder()
            ->select('COUNT(p.id)')
            ->from(Person::class, 'p')
            ->where('p.bid = :bid')
            ->andWhere('p.code = :code')
            ->andWhere('p.id != :id')
            ->setParameter('bid', $business)
            ->setParameter('code', $code)
            ->setParameter('id', $person->getId() ?: 0)
            ->getQuery()
            ->getSingleScalarResult();
        if ((int)$dup > 0) {
            $person->setCode($this->nextUniquePersonCode($business));
        }
    }

    /** Normalize Persian/UTF-8 and repair Latin-1 mojibake (Ø³Ù†Ø§Ù† → سمنان). */
    private function utf8Field(string $s): string
    {
        $s = trim($s);
        if ($s === '') {
            return '';
        }
        if (!mb_check_encoding($s, 'UTF-8')) {
            $s = mb_convert_encoding($s, 'UTF-8', ['UTF-8', 'Windows-1252', 'ISO-8859-1']);
        }
        $latin = @mb_convert_encoding($s, 'ISO-8859-1', 'UTF-8');
        if (is_string($latin) && $latin !== $s && mb_check_encoding($latin, 'UTF-8') && preg_match('/\p{Arabic}/u', $latin)) {
            return $latin;
        }
        return $s;
    }
}
