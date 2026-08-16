<?php

namespace App\Service\Sync;

use App\Entity\APIToken;
use App\Entity\BankAccount;
use App\Entity\Business;
use App\Entity\Commodity;
use App\Entity\HesabdariDoc;
use App\Entity\HesabdariRow;
use App\Entity\HesabdariTable;
use App\Entity\InvoiceType;
use App\Entity\Person;
use App\Entity\PreInvoiceDoc;
use App\Entity\PreInvoiceItem;
use App\Entity\User;
use App\Entity\Year;
use App\Service\Provider;
use Doctrine\ORM\EntityManagerInterface;

class ClickSellLifecycle
{
    public function __construct(
        private EntityManagerInterface $em,
        private Provider $provider
    ) {
    }

    public function issue(APIToken $token, array $payload): array
    {
        $invoiceId = $this->invoiceId($payload);
        $eventId = $this->requireEvent($payload, 'sell.issue:' . $invoiceId);
        $hash = $this->requireHash($payload);
        $business = $this->business($token);
        $pre = $this->findPre($business, $invoiceId);
        if (!$pre) {
            throw new ClickSellException('DRAFT_NOT_FOUND', 'پیش‌نویس فروش کلیک یافت نشد', 404);
        }

        $existing = $this->findSell($business, $invoiceId);
        if ($existing) {
            $this->stampIssue($existing, $pre, $eventId, $hash);
            $this->em->flush();
            return $this->sellResponse($existing, true, 'issued');
        }

        $issuedCode = $this->issuedSellCodeFromStatus($pre);
        if ($issuedCode) {
            $existing = $this->em->getRepository(HesabdariDoc::class)->findOneBy([
                'bid' => $business,
                'year' => $pre->getYear(),
                'type' => 'sell',
                'code' => $issuedCode,
            ]);
            if ($existing) {
                $this->stampIssue($existing, $pre, $eventId, $hash);
                $this->em->flush();
                return $this->sellResponse($existing, true, 'issued');
            }
        }

        $person = $pre->getPerson();
        if (!$person) {
            throw new ClickSellException('NO_PERSON', 'خریدار پیش‌فاکتور مشخص نیست');
        }
        $incomeRef = $this->table('53');
        $personRef = $this->table('3');
        $year = $pre->getYear() ?: $this->openYear($business);
        $money = $pre->getMoney() ?: $business->getMoney();
        if (!$year || !$money) {
            throw new ClickSellException('NO_OPEN_YEAR', 'سال مالی یا ارز کسب‌وکار یافت نشد');
        }

        $this->em->beginTransaction();
        try {
            $doc = new HesabdariDoc();
            $doc->setBid($business);
            $doc->setYear($year);
            $doc->setDateSubmit(time());
            $doc->setType('sell');
            $doc->setSubmitter($token->getSubmitter());
            $doc->setMoney($money);
            $sellCode = $this->sellCodeFromPre($pre, $business, $year);
            $doc->setCode($sellCode);
            $doc->setDate($pre->getDate());
            $doc->setDes((string)$pre->getDes());
            $pluginBits = ['from_preinvoice:' . $pre->getCode(), 'click_h:' . $hash];
            $isAdj = $this->isAdjustment($pre);
            if ($isAdj) {
                $pluginBits[] = 'kind:adjustment';
                $pluginBits[] = 'nomoodian:1';
                $doc->setTaxPercent(0);
            }
            $doc->setPlugin(implode('|', $pluginBits));
            $doc->setRefData($eventId);
            if ($pre->getInvoiceLabel()) {
                $doc->setInvoiceLabel($pre->getInvoiceLabel());
            }

            $sumTax = 0;
            $sumTotal = 0;
            $headerDisc = (int)round((float)$pre->getTotalDiscount());
            $shipping = (int)round((float)$pre->getShippingCost());

            foreach ($pre->getPreInvoiceItems() as $item) {
                $qty = (float)$item->getCommodityCount();
                $price = (float)$item->getBs();
                $discount = (int)round((float)$item->getDiscountAmount());
                $tax = $isAdj ? 0 : (int)round((float)$item->getTax());
                $net = max(0, (int)round($qty * $price) - $discount);
                $sumTotal += $net;
                $sumTax += $tax;

                $row = new HesabdariRow();
                $row->setDes($item->getDes() ?: 'فروش');
                $row->setBid($business);
                $row->setYear($year);
                $row->setDoc($doc);
                $row->setBs((string)($net + $tax));
                $row->setBd('0');
                $row->setDiscount((string)$discount);
                $row->setTax((string)$tax);
                $row->setRef($incomeRef);
                $row->setCommodity($item->getCommodity());
                $row->setCommdityCount((float)$qty);
                $this->em->persist($row);
            }

            $amount = $sumTax + $sumTotal - $headerDisc + $shipping;
            $doc->setAmount((string)$amount);

            $ar = new HesabdariRow();
            $ar->setDes('فاکتور فروش از پیش‌فاکتور ' . $pre->getCode());
            $ar->setBid($business);
            $ar->setYear($year);
            $ar->setDoc($doc);
            $ar->setBs('0');
            $ar->setBd((string)$amount);
            $ar->setRef($personRef);
            $ar->setPerson($person);
            $this->em->persist($ar);

            if ($headerDisc != 0) {
                $discRef = $this->em->getRepository(HesabdariTable::class)->findOneBy(['code' => '104']);
                if ($discRef) {
                    $discRow = new HesabdariRow();
                    $discRow->setDes('تخفیف فاکتور');
                    $discRow->setBid($business);
                    $discRow->setYear($year);
                    $discRow->setDoc($doc);
                    $discRow->setBs('0');
                    $discRow->setBd((string)$headerDisc);
                    $discRow->setRef($discRef);
                    $this->em->persist($discRow);
                }
            }

            $this->em->persist($doc);
            $pre->setStatus('issued:' . $doc->getCode());
            $this->em->flush();
            $this->em->commit();
            return $this->sellResponse($doc, false, 'issued');
        } catch (\Throwable $e) {
            $this->em->rollback();
            throw new ClickSellException('ISSUE_FAILED', $e->getMessage(), 500);
        }
    }

    public function salesReturn(APIToken $token, array $payload): array
    {
        $invoiceId = $this->invoiceId($payload);
        $txnId = trim((string)($payload['wmsTxnId'] ?? $payload['clickTxnId'] ?? ''));
        if ($txnId === '') {
            throw new ClickSellException('TXN_REQUIRED', 'شناسه حواله برگشت لازم است');
        }
        $eventId = $this->requireEvent($payload, 'sell.return:' . $txnId);
        $hash = $this->requireHash($payload);
        $business = $this->business($token);

        $existing = $this->findByRef($business, $eventId, 'rfsell');
        if ($existing) {
            if ($this->hashFromPlugin($existing->getPlugin()) && $this->hashFromPlugin($existing->getPlugin()) !== $hash) {
                throw new ClickSellException('PAYLOAD_CONFLICT', 'برگشت دیگری با همین کلید ثبت شده', 409, [
                    'existingDocId' => $existing->getId(),
                    'existingCode' => $existing->getCode(),
                ]);
            }
            return $this->sellResponse($existing, true, 'rfsell');
        }

        $sell = $this->requireSell($business, $invoiceId);
        $remainders = $this->sellLineRemainders($sell);
        $items = $payload['items'] ?? [];
        if (!is_array($items) || !$items) {
            throw new ClickSellException('NO_LINES', 'اقلام برگشت خالی است');
        }

        $this->em->beginTransaction();
        try {
            $rf = $this->createReverseDoc($token->getSubmitter(), $sell, $eventId, $hash, $payload, $remainders, $items, false);
            $this->em->flush();
            $this->em->commit();
            $left = $this->remainingAmount($this->sellLineRemainders($sell));
            $out = $this->sellResponse($rf, false, 'rfsell');
            $out['fullyReturned'] = $left <= 0.0001;
            $out['remainingAmount'] = $left;
            $out['sellCode'] = $sell->getCode();
            return $out;
        } catch (ClickSellException $e) {
            $this->em->rollback();
            throw $e;
        } catch (\Throwable $e) {
            $this->em->rollback();
            throw new ClickSellException('RETURN_FAILED', $e->getMessage(), 500);
        }
    }

    public function cancel(APIToken $token, array $payload): array
    {
        $invoiceId = $this->invoiceId($payload);
        $eventId = $this->requireEvent($payload, 'sell.cancel:' . $invoiceId);
        $hash = $this->requireHash($payload);
        $business = $this->business($token);

        $draftRef = 'sell.cancel.draft:' . $invoiceId;
        $existingPre = $this->em->getRepository(PreInvoiceDoc::class)->findOneBy([
            'bid' => $business,
            'refData' => $draftRef,
        ]);
        if ($existingPre && !str_starts_with((string)$existingPre->getStatus(), 'issued:')) {
            return $this->returnDraftResponse($existingPre, true, $eventId);
        }

        $pre = $this->findPre($business, $invoiceId);
        $sell = $this->findSell($business, $invoiceId);

        if (!$sell && $pre && !str_starts_with((string)$pre->getStatus(), 'issued:')) {
            $this->em->beginTransaction();
            try {
                foreach ($pre->getPreInvoiceItems() as $item) {
                    $this->em->remove($item);
                }
                $this->em->remove($pre);
                $this->em->flush();
                $this->em->commit();
                return [
                    'success' => true,
                    'status' => 'cancelled',
                    'replay' => false,
                    'kind' => 'preinvoice-deleted',
                    'event_id' => $eventId,
                ];
            } catch (\Throwable $e) {
                $this->em->rollback();
                throw new ClickSellException('CANCEL_FAILED', $e->getMessage(), 500);
            }
        }

        if (!$sell) {
            return [
                'success' => true,
                'status' => 'cancelled',
                'replay' => true,
                'kind' => 'noop',
                'event_id' => $eventId,
            ];
        }

        $remainders = $this->sellLineRemainders($sell);
        $left = $this->remainingAmount($remainders);
        if ($left <= 0.0001) {
            return [
                'success' => true,
                'status' => 'cancelled',
                'replay' => true,
                'kind' => 'already-reversed',
                'docId' => $sell->getId(),
                'docNum' => $sell->getCode(),
                'event_id' => $eventId,
            ];
        }

        $items = [];
        foreach ($remainders as $row) {
            if ($row['qty'] <= 0.0001) {
                continue;
            }
            $items[] = [
                'code' => $this->skuFromCommodity($row['commodity']),
                'name' => $row['commodity']->getName(),
                'quantity' => $row['qty'],
                'lineTotal' => max(0, $row['amount']),
                'tax' => max(0, $row['tax']),
            ];
        }

        $this->em->beginTransaction();
        try {
            $preReturn = $this->createReturnPreinvoice(
                $token->getSubmitter(),
                $sell,
                $invoiceId,
                $draftRef,
                $hash,
                $items,
                true,
                (string)($payload['jalaliDate'] ?? $sell->getDate())
            );
            $this->em->flush();
            $this->em->commit();
            $out = $this->returnDraftResponse($preReturn, false, $eventId);
            $out['sellCode'] = $sell->getCode();
            return $out;
        } catch (ClickSellException $e) {
            $this->em->rollback();
            throw $e;
        } catch (\Throwable $e) {
            $this->em->rollback();
            throw new ClickSellException('CANCEL_FAILED', $e->getMessage(), 500);
        }
    }

    public function receive(APIToken $token, array $payload): array
    {
        $invoiceId = $this->invoiceId($payload);
        $paymentId = trim((string)($payload['paymentId'] ?? ''));
        if ($paymentId === '') {
            throw new ClickSellException('PAYMENT_REQUIRED', 'شناسه وصول لازم است');
        }
        $eventId = $this->requireEvent($payload, 'sell.receive:' . $paymentId);
        $hash = $this->requireHash($payload);
        $amount = (int)round((float)($payload['amount'] ?? 0));
        if ($amount <= 0) {
            throw new ClickSellException('AMOUNT_REQUIRED', 'مبلغ وصول نامعتبر است');
        }
        $business = $this->business($token);

        $existing = $this->findByRef($business, $eventId, 'sell_receive');
        if ($existing) {
            if ($this->hashFromPlugin($existing->getPlugin()) && $this->hashFromPlugin($existing->getPlugin()) !== $hash) {
                throw new ClickSellException('PAYLOAD_CONFLICT', 'وصول دیگری با همین کلید ثبت شده', 409, [
                    'existingDocId' => $existing->getId(),
                ]);
            }
            return $this->sellResponse($existing, true, 'sell_receive');
        }

        $sell = $this->requireSell($business, $invoiceId);
        $person = $this->personFromSell($sell);
        if (!$person) {
            throw new ClickSellException('NO_PERSON', 'شخص فاکتور فروش یافت نشد');
        }
        $bank = $this->pilotBank($business);
        $bankRef = $this->table('5');
        $personRef = $this->table('3');
        $year = $sell->getYear() ?: $this->openYear($business);
        $money = $sell->getMoney() ?: $business->getMoney();

        $this->em->beginTransaction();
        try {
            $doc = new HesabdariDoc();
            $doc->setBid($business);
            $doc->setYear($year);
            $doc->setDateSubmit(time());
            $doc->setType('sell_receive');
            $doc->setSubmitter($token->getSubmitter());
            $doc->setMoney($money);
            $doc->setCode((string)$this->provider->getAccountingCode($business->getId(), 'accounting'));
            $doc->setDate((string)($payload['jalaliDate'] ?? $sell->getDate()));
            $doc->setDes(mb_substr((string)($payload['description'] ?? ('وصول کلیک فاکتور ' . $sell->getCode())), 0, 255));
            $doc->setAmount((string)$amount);
            $doc->setRefData($eventId);
            $doc->setPlugin('click_h:' . $hash . '|kind:bank');
            $this->em->persist($doc);

            $bankRow = new HesabdariRow();
            $bankRow->setDes($doc->getDes());
            $bankRow->setBid($business);
            $bankRow->setYear($year);
            $bankRow->setDoc($doc);
            $bankRow->setBs('0');
            $bankRow->setBd((string)$amount);
            $bankRow->setRef($bankRef);
            $bankRow->setBank($bank);
            $this->em->persist($bankRow);

            $ar = new HesabdariRow();
            $ar->setDes($doc->getDes());
            $ar->setBid($business);
            $ar->setYear($year);
            $ar->setDoc($doc);
            $ar->setBs((string)$amount);
            $ar->setBd('0');
            $ar->setRef($personRef);
            $ar->setPerson($person);
            $this->em->persist($ar);

            $sell->addRelatedDoc($doc);
            $this->em->flush();
            $this->em->commit();
            $out = $this->sellResponse($doc, false, 'sell_receive');
            $out['sellCode'] = $sell->getCode();
            $out['bankId'] = $bank->getId();
            return $out;
        } catch (\Throwable $e) {
            $this->em->rollback();
            throw new ClickSellException('RECEIVE_FAILED', $e->getMessage(), 500);
        }
    }

    public function issueReturnFromPre(?User $user, array $acc, PreInvoiceDoc $pre): array
    {
        $plugin = (string)$pre->getPlugin();
        $ref = (string)$pre->getRefData();
        if (!str_contains($plugin, 'kind:rfsell') && !str_starts_with($ref, 'sell.return.draft:')) {
            throw new ClickSellException('NOT_RETURN_PRE', 'این پیش‌فاکتور برگشت از فروش نیست');
        }
        $status = (string)$pre->getStatus();
        if (str_starts_with($status, 'issued:')) {
            $existing = $this->em->getRepository(HesabdariDoc::class)->findOneBy([
                'bid' => $acc['bid'],
                'year' => $acc['year'],
                'type' => 'rfsell',
                'code' => substr($status, 7),
            ]);
            if ($existing) {
                return $this->sellResponse($existing, true, 'rfsell');
            }
        }
        $invoiceId = '';
        if (preg_match('/click_inv:([^|]+)/', $plugin, $m)) {
            $invoiceId = trim($m[1]);
        }
        $sell = $invoiceId !== '' ? $this->findSell($acc['bid'], $invoiceId) : null;
        if (!$sell) {
            throw new ClickSellException('SELL_NOT_FOUND', 'فاکتور فروش صادرشده برای این برگشت یافت نشد', 404);
        }
        $hash = $this->hashFromPlugin($plugin) ?: hash('sha256', $ref);
        $requestId = str_starts_with($ref, 'sell.return.draft:') ? substr($ref, strlen('sell.return.draft:')) : ('pre' . $pre->getId());
        $eventId = 'sell.return:' . $requestId;
        $items = [];
        foreach ($pre->getPreInvoiceItems() as $item) {
            $c = $item->getCommodity();
            $qty = (float)$item->getCommodityCount();
            $price = (float)$item->getBs();
            $disc = (int)round((float)$item->getDiscountAmount());
            $tax = (int)round((float)$item->getTax());
            $net = max(0, (int)round($qty * $price) - $disc);
            $items[] = [
                'code' => $c ? $this->skuFromCommodity($c) : '',
                'name' => $c ? (string)$c->getName() : (string)$item->getDes(),
                'quantity' => $qty,
                'lineTotal' => $net + $tax,
                'tax' => $tax,
            ];
        }
        $remainders = $this->sellLineRemainders($sell);
        $submitter = $user ?: $pre->getSubmitter();
        $this->em->beginTransaction();
        try {
            $rf = $this->createReverseDoc($submitter, $sell, $eventId, $hash, ['jalaliDate' => $pre->getDate()], $remainders, $items, false);
            $pre->setStatus('issued:' . $rf->getCode());
            $this->em->flush();
            $this->em->commit();
            $left = $this->remainingAmount($this->sellLineRemainders($sell));
            $out = $this->sellResponse($rf, false, 'rfsell');
            $out['fullyReturned'] = $left <= 0.0001;
            $out['remainingAmount'] = $left;
            $out['rfsellCode'] = $rf->getCode();
            $out['sellCode'] = $rf->getCode();
            $out['originalSellCode'] = $sell->getCode();
            $out['clickInvoiceId'] = $invoiceId;
            $out['requestId'] = $requestId;
            $out['kind'] = 'rfsell';
            return $out;
        } catch (ClickSellException $e) {
            $this->em->rollback();
            throw $e;
        } catch (\Throwable $e) {
            $this->em->rollback();
            throw new ClickSellException('RETURN_FAILED', $e->getMessage(), 500);
        }
    }

    public function cancelReturnDraft(APIToken $token, array $payload): array
    {
        $business = $this->business($token);
        $invoiceId = trim((string)($payload['clickInvoiceId'] ?? ''));
        $requestId = trim((string)($payload['requestId'] ?? ''));
        $code = trim((string)($payload['preinvoiceCode'] ?? ''));
        $pre = null;
        if ($requestId !== '') {
            $pre = $this->em->getRepository(PreInvoiceDoc::class)->findOneBy([
                'bid' => $business,
                'refData' => 'sell.return.draft:' . $requestId,
            ]);
        }
        if (!$pre && $code !== '') {
            $pre = $this->em->getRepository(PreInvoiceDoc::class)->findOneBy([
                'bid' => $business,
                'code' => $code,
            ]);
        }
        if (!$pre && $invoiceId !== '') {
            $pre = $this->em->getRepository(PreInvoiceDoc::class)->createQueryBuilder('p')
                ->where('p.bid = :bid')
                ->andWhere('p.plugin LIKE :plug')
                ->andWhere('p.status NOT LIKE :issued')
                ->setParameter('bid', $business)
                ->setParameter('plug', '%click_inv:' . $invoiceId . '%')
                ->setParameter('issued', 'issued:%')
                ->orderBy('p.id', 'DESC')
                ->setMaxResults(1)
                ->getQuery()
                ->getOneOrNullResult();
        }
        if (!$pre || !$this->isReturnPre($pre)) {
            throw new ClickSellException('NOT_FOUND', 'پیش‌فاکتور برگشت یافت نشد', 404);
        }
        if (str_starts_with((string)$pre->getStatus(), 'issued:')) {
            throw new ClickSellException('RETURN_ALREADY_ISSUED', 'فاکتور برگشت در حسابیکس صادر شده و از کلیک قابل لغو نیست', 409);
        }
        $deletedCode = $pre->getCode();
        $this->em->beginTransaction();
        try {
            foreach ($pre->getPreInvoiceItems() as $item) {
                $this->em->remove($item);
            }
            $this->em->remove($pre);
            $this->em->flush();
            $this->em->commit();
            return [
                'success' => true,
                'status' => 'cancelled',
                'kind' => 'return-preinvoice-deleted',
                'deletedCode' => $deletedCode,
                'clickInvoiceId' => $invoiceId,
                'requestId' => $requestId,
            ];
        } catch (\Throwable $e) {
            $this->em->rollback();
            throw new ClickSellException('CANCEL_FAILED', $e->getMessage(), 500);
        }
    }

    private function createReverseDoc(
        ?User $submitter,
        HesabdariDoc $sell,
        string $eventId,
        string $hash,
        array $payload,
        array $remainders,
        array $items,
        bool $isCancel
    ): HesabdariDoc {
        $business = $sell->getBid();
        $year = $sell->getYear();
        $money = $sell->getMoney();
        $incomeRef = $this->table('53');
        $personRef = $this->table('3');
        $person = $this->personFromSell($sell);
        if (!$person) {
            throw new ClickSellException('NO_PERSON', 'شخص فاکتور فروش یافت نشد');
        }
        $isAdj = str_contains((string)$sell->getPlugin(), 'nomoodian:1')
            || str_contains((string)$sell->getPlugin(), 'kind:adjustment');

        $doc = new HesabdariDoc();
        $doc->setBid($business);
        $doc->setYear($year);
        $doc->setDateSubmit(time());
        $doc->setType('rfsell');
        $doc->setSubmitter($submitter);
        $doc->setMoney($money);
        $doc->setCode((string)$this->provider->getAccountingCode($business->getId(), 'accounting'));
        $doc->setDate((string)($payload['jalaliDate'] ?? $sell->getDate()));
        $doc->setDes(mb_substr(($isCancel ? 'ابطال کلیک ' : 'برگشت کلیک ') . $sell->getCode(), 0, 255));
        $plugin = 'click_h:' . $hash . ($isAdj ? '|kind:adjustment|nomoodian:1' : '');
        $doc->setPlugin($plugin);
        $doc->setRefData($eventId);
        if ($sell->getInvoiceLabel()) {
            $doc->setInvoiceLabel($sell->getInvoiceLabel());
        }
        $this->em->persist($doc);

        $sum = 0;
        foreach ($items as $item) {
            $commodity = $this->matchRemainderCommodity($item, $remainders);
            if (!$commodity) {
                throw new ClickSellException('OVER_RETURN', 'برای این کالا مانده قابل برگشت نیست', 409);
            }
            $cid = $commodity->getId();
            $want = (float)($item['quantity'] ?? 0);
            if ($want <= 0) {
                continue;
            }
            $avail = (float)$remainders[$cid]['qty'];
            if ($want - $avail > 0.0001) {
                throw new ClickSellException('OVER_RETURN', 'مقدار برگشت از مانده فاکتور بیشتر است', 409, [
                    'commodity' => $commodity->getName(),
                    'requested' => $want,
                    'remaining' => $avail,
                ]);
            }
            $ratio = $avail > 0 ? ($want / $avail) : 0;
            $lineAmt = isset($item['lineTotal'])
                ? (int)round((float)$item['lineTotal'])
                : (int)round(((float)$remainders[$cid]['amount']) * $ratio);
            $tax = $isAdj ? 0 : (isset($item['tax'])
                ? (int)round((float)$item['tax'])
                : (int)round(((float)$remainders[$cid]['tax']) * $ratio));
            $lineAmt = max(0, $lineAmt);
            $remainders[$cid]['qty'] -= $want;
            $remainders[$cid]['amount'] -= $lineAmt;
            $remainders[$cid]['tax'] -= $tax;
            $sum += $lineAmt;

            $row = new HesabdariRow();
            $row->setDes($remainders[$cid]['des'] ?: ($commodity->getName() ?: 'برگشت فروش'));
            $row->setBid($business);
            $row->setYear($year);
            $row->setDoc($doc);
            $row->setBd((string)$lineAmt);
            $row->setBs('0');
            $row->setTax((string)$tax);
            $row->setRef($incomeRef);
            $row->setCommodity($commodity);
            $row->setCommdityCount($want);
            $this->em->persist($row);
        }
        if ($sum <= 0) {
            throw new ClickSellException('NO_LINES', 'مانده‌ای برای برگشت/ابطال نیست');
        }
        $doc->setAmount((string)$sum);

        $ar = new HesabdariRow();
        $ar->setDes($doc->getDes());
        $ar->setBid($business);
        $ar->setYear($year);
        $ar->setDoc($doc);
        $ar->setBd('0');
        $ar->setBs((string)$sum);
        $ar->setRef($personRef);
        $ar->setPerson($person);
        $this->em->persist($ar);

        $sell->addRelatedDoc($doc);
        $sell->addPairDoc($doc);
        $this->em->persist($sell);
        return $doc;
    }

    private function createReturnPreinvoice(
        ?User $submitter,
        HesabdariDoc $sell,
        string $invoiceId,
        string $draftRef,
        string $hash,
        array $items,
        bool $isCancel,
        string $jalaliDate
    ): PreInvoiceDoc {
        $business = $sell->getBid();
        $year = $sell->getYear();
        $money = $sell->getMoney();
        $person = $this->personFromSell($sell);
        if (!$person) {
            throw new ClickSellException('NO_PERSON', 'شخص فاکتور فروش یافت نشد');
        }
        $pre = new PreInvoiceDoc();
        $pre->setBid($business);
        $pre->setYear($year);
        $pre->setMoney($money);
        $pre->setPerson($person);
        $pre->setSubmitter($submitter);
        $pre->setCode((string)$this->provider->getAccountingCode($business->getId(), 'accounting'));
        $pre->setDate($jalaliDate);
        $pre->setDes(mb_substr(
            ($isCancel ? 'ابطال فاکتور حسابیکس ' : 'برگشت از فروش فاکتور ') . $sell->getCode()
            . ' / کلیک ' . $invoiceId,
            0,
            255
        ));
        $pre->setTaxPercent((string)(int)round((float)($sell->getTaxPercent() ?? 0)));
        $pre->setTotalDiscount('0');
        $pre->setTotalDiscountPercent('0');
        $pre->setShippingCost('0');
        $pre->setShowTotalPercentDiscount(false);
        $pre->setShowPercentDiscount(false);
        $pre->setStatus('click_return_draft');
        $pre->setPlugin('click_h:' . $hash . '|kind:rfsell|click_inv:' . $invoiceId);
        $pre->setRefData($draftRef);
        $pre->setInvoiceLabel($this->ensureRfsellInvoiceType());
        $this->em->persist($pre);

        $sum = 0;
        foreach ($items as $item) {
            $qty = (float)($item['quantity'] ?? 0);
            if ($qty <= 0) {
                continue;
            }
            $lineAmt = (int)round((float)($item['lineTotal'] ?? 0));
            $tax = (int)round((float)($item['tax'] ?? 0));
            $net = max(0, $lineAmt - $tax);
            $unit = $qty > 0 ? (int)round($net / $qty) : $net;
            $commodity = null;
            $code = trim((string)($item['code'] ?? ''));
            $name = trim((string)($item['name'] ?? ''));
            foreach ($sell->getHesabdariRows() as $row) {
                $c = $row->getCommodity();
                if (!$c) {
                    continue;
                }
                if ($name !== '' && $c->getName() === $name) {
                    $commodity = $c;
                    break;
                }
                $marker = (string)$c->getDes();
                if ($code !== '' && ($marker === 'click_sku:' . $code || (string)$c->getCode() === $code)) {
                    $commodity = $c;
                    break;
                }
            }
            if (!$commodity) {
                continue;
            }
            $row = new PreInvoiceItem();
            $row->setDoc($pre);
            $row->setCommodity($commodity);
            $row->setCommodityCount((string)$qty);
            $row->setBs((string)$unit);
            $row->setDiscountAmount('0');
            $row->setDiscountPercent('0');
            $row->setTax((string)$tax);
            $row->setDes(mb_substr($name ?: (string)$commodity->getName(), 0, 255));
            $row->setShowPercentDiscount(false);
            $this->em->persist($row);
            $sum += $lineAmt;
        }
        if ($sum <= 0) {
            throw new ClickSellException('NO_LINES', 'مانده‌ای برای پیش‌فاکتور برگشت/ابطال نیست');
        }
        $pre->setAmount((string)$sum);
        return $pre;
    }

    private function returnDraftResponse(PreInvoiceDoc $pre, bool $replay, string $eventId): array
    {
        return [
            'success' => true,
            'status' => 'cancel_pending',
            'replay' => $replay,
            'kind' => 'return-draft',
            'docId' => $pre->getId(),
            'docNum' => $pre->getCode(),
            'event_id' => $eventId,
            'preRef' => $pre->getRefData(),
            'amount' => $pre->getAmount(),
        ];
    }

    private function matchRemainderCommodity(array $item, array $remainders): ?Commodity
    {
        $sku = trim((string)($item['code'] ?? ''));
        $name = trim((string)($item['name'] ?? ''));
        if ($sku !== '') {
            foreach ($remainders as $row) {
                $c = $row['commodity'];
                $marker = (string)$c->getDes();
                if ($marker === 'click_sku:' . $sku || str_ends_with($marker, ':' . $sku) || (string)$c->getCode() === $sku) {
                    return $row['qty'] > 0.0001 ? $c : null;
                }
            }
        }
        if ($name !== '') {
            foreach ($remainders as $row) {
                if ($row['qty'] > 0.0001 && $row['commodity']->getName() === $name) {
                    return $row['commodity'];
                }
            }
        }
        $hits = 0;
        $only = null;
        foreach ($remainders as $row) {
            if ($row['qty'] > 0.0001) {
                $hits++;
                $only = $row['commodity'];
            }
        }
        return $hits === 1 ? $only : null;
    }

    private function sellLineRemainders(HesabdariDoc $sell): array
    {
        $lines = [];
        foreach ($sell->getHesabdariRows() as $row) {
            $c = $row->getCommodity();
            if (!$c) {
                continue;
            }
            $qty = (float)$row->getCommdityCount();
            if ($qty <= 0) {
                continue;
            }
            $id = $c->getId();
            if (!isset($lines[$id])) {
                $lines[$id] = [
                    'commodity' => $c,
                    'qty' => 0.0,
                    'amount' => 0.0,
                    'tax' => 0.0,
                    'des' => (string)$row->getDes(),
                ];
            }
            $lines[$id]['qty'] += $qty;
            $lines[$id]['amount'] += (float)$row->getBs();
            $lines[$id]['tax'] += (float)$row->getTax();
        }
        foreach ($sell->getRelatedDocs() as $rel) {
            if ($rel->getType() !== 'rfsell') {
                continue;
            }
            foreach ($rel->getHesabdariRows() as $row) {
                $c = $row->getCommodity();
                if (!$c || !isset($lines[$c->getId()])) {
                    continue;
                }
                $id = $c->getId();
                $lines[$id]['qty'] -= (float)$row->getCommdityCount();
                $lines[$id]['amount'] -= (float)$row->getBd();
                $lines[$id]['tax'] -= (float)$row->getTax();
            }
        }
        return $lines;
    }

    private function remainingAmount(array $remainders): float
    {
        $n = 0.0;
        foreach ($remainders as $row) {
            $n += max(0, (float)$row['amount']);
        }
        return $n;
    }

    private function stampIssue(HesabdariDoc $sell, PreInvoiceDoc $pre, string $eventId, string $hash): void
    {
        if (!$sell->getRefData() || str_starts_with((string)$sell->getRefData(), 'sell.draft:')) {
            $sell->setRefData($eventId);
        }
        $plugin = (string)$sell->getPlugin();
        if (!str_contains($plugin, 'click_h:')) {
            $sell->setPlugin(trim($plugin . '|click_h:' . $hash, '|'));
        }
        $pre->setStatus('issued:' . $sell->getCode());
    }

    private function sellResponse(HesabdariDoc $doc, bool $replay, string $status): array
    {
        return [
            'success' => true,
            'status' => $status,
            'replay' => $replay,
            'docId' => $doc->getId(),
            'docNum' => $doc->getCode(),
            'event_id' => $doc->getRefData(),
            'kind' => $doc->getType(),
            'amount' => $doc->getAmount(),
        ] + $this->personSnapshot($this->personFromSell($doc));
    }

    private function findPre(Business $business, string $invoiceId): ?PreInvoiceDoc
    {
        return $this->em->getRepository(PreInvoiceDoc::class)->findOneBy([
            'bid' => $business,
            'refData' => 'sell.draft:' . $invoiceId,
        ]);
    }

    public function docsStatusForClickInvoices(Business $business, array $invoiceIds): array
    {
        $out = [];
        foreach ($invoiceIds as $id) {
            $id = trim((string)$id);
            if ($id === '') {
                continue;
            }
            $out[$id] = [
                'sellExists' => false,
                'sellCode' => null,
                'preinvoiceCode' => null,
                'preinvoiceStatus' => null,
                'preinvoiceIssued' => false,
                'returns' => [],
                'personId' => null,
                'personCode' => null,
                'personName' => null,
            ];
        }
        if (!$out) {
            return $out;
        }
        $ids = array_keys($out);
        $draftRefs = [];
        foreach ($ids as $id) {
            $draftRefs[] = 'sell.draft:' . $id;
        }
        $pres = $this->em->getRepository(PreInvoiceDoc::class)->createQueryBuilder('p')
            ->where('p.bid = :bid')
            ->andWhere('p.refData IN (:refs) OR p.plugin LIKE :rfsell')
            ->setParameter('bid', $business)
            ->setParameter('refs', $draftRefs)
            ->setParameter('rfsell', '%kind:rfsell%')
            ->getQuery()
            ->getResult();
        foreach ($pres as $pre) {
            $ref = (string)$pre->getRefData();
            $plugin = (string)$pre->getPlugin();
            $status = (string)$pre->getStatus();
            $issued = str_starts_with($status, 'issued:');
            $issuedCode = $issued ? substr($status, 7) : null;
            if (str_starts_with($ref, 'sell.draft:')) {
                $cid = substr($ref, strlen('sell.draft:'));
                if (isset($out[$cid])) {
                    $out[$cid]['preinvoiceCode'] = $pre->getCode();
                    $out[$cid]['preinvoiceStatus'] = $status;
                    $out[$cid]['preinvoiceIssued'] = $issued;
                    if ($issuedCode) {
                        $out[$cid]['sellCode'] = $issuedCode;
                        $out[$cid]['sellExists'] = true;
                    }
                    $out[$cid] = $this->withPerson($out[$cid], $pre->getPerson());
                }
            }
            if (str_contains($plugin, 'kind:rfsell') && preg_match('/click_inv:([^|]+)/', $plugin, $m)) {
                $cid = trim($m[1]);
                if (isset($out[$cid])) {
                    $out[$cid]['returns'][] = [
                        'preCode' => $pre->getCode(),
                        'status' => $status,
                        'issued' => $issued,
                        'rfsellCode' => $issuedCode,
                    ];
                }
            }
        }
        foreach ($ids as $id) {
            if (!$out[$id]['sellExists']) {
                $sell = $this->findSell($business, $id);
                if ($sell) {
                    $out[$id]['sellExists'] = true;
                    $out[$id]['sellCode'] = $sell->getCode();
                }
            }
            if (empty($out[$id]['personId'])) {
                $sell = $this->findSell($business, $id);
                if ($sell) {
                    $out[$id] = $this->withPerson($out[$id], $this->personFromSell($sell));
                }
            }
        }
        return $out;
    }

    private function findSell(Business $business, string $invoiceId): ?HesabdariDoc
    {
        $repo = $this->em->getRepository(HesabdariDoc::class);
        foreach (['sell.issue:' . $invoiceId, 'sell.draft:' . $invoiceId] as $ref) {
            $sell = $repo->findOneBy(['bid' => $business, 'type' => 'sell', 'refData' => $ref]);
            if ($sell) {
                return $sell;
            }
        }
        $pre = $this->findPre($business, $invoiceId);
        if ($pre) {
            $code = $this->issuedSellCodeFromStatus($pre);
            if ($code) {
                return $repo->findOneBy(['bid' => $business, 'type' => 'sell', 'code' => $code]);
            }
        }
        return null;
    }

    private function requireSell(Business $business, string $invoiceId): HesabdariDoc
    {
        $sell = $this->findSell($business, $invoiceId);
        if (!$sell) {
            throw new ClickSellException('SELL_NOT_FOUND', 'فاکتور فروش صادرشده در حسابیکس یافت نشد', 404);
        }
        return $sell;
    }

    private function findByRef(Business $business, string $eventId, string $type): ?HesabdariDoc
    {
        return $this->em->getRepository(HesabdariDoc::class)->findOneBy([
            'bid' => $business,
            'type' => $type,
            'refData' => $eventId,
        ]);
    }

    private function personFromSell(HesabdariDoc $sell): ?Person
    {
        foreach ($sell->getHesabdariRows() as $row) {
            if ($row->getPerson()) {
                return $row->getPerson();
            }
        }
        return null;
    }

    private function personSnapshot(?Person $person): array
    {
        if (!$person) {
            return ['personId' => null, 'personCode' => null, 'personName' => null];
        }
        return [
            'personId' => $person->getId(),
            'personCode' => $person->getCode(),
            'personName' => $person->getNikename() ?: $person->getName(),
        ];
    }

    private function withPerson(array $row, ?Person $person): array
    {
        if (!$person || !empty($row['personId'])) {
            return $row;
        }
        return array_merge($row, $this->personSnapshot($person));
    }

    private function pilotBank(Business $business): BankAccount
    {
        $wantId = (int)($_ENV['HESABIX_BANK_ID'] ?? getenv('HESABIX_BANK_ID') ?: 2);
        $bank = $this->em->getRepository(BankAccount::class)->find($wantId);
        if ($bank && $bank->getBid() && $bank->getBid()->getId() === $business->getId()) {
            return $bank;
        }
        $bank = $this->em->getRepository(BankAccount::class)->findOneBy(['bid' => $business]);
        if (!$bank) {
            throw new ClickSellException('NO_BANK', 'حساب بانک پایلوت در حسابیکس تعریف نشده است');
        }
        return $bank;
    }

    private function table(string $code): HesabdariTable
    {
        $row = $this->em->getRepository(HesabdariTable::class)->findOneBy(['code' => $code]);
        if (!$row) {
            throw new ClickSellException('NO_ACCOUNT', 'سرفصل ' . $code . ' یافت نشد', 500);
        }
        return $row;
    }

    private function openYear(Business $business): ?Year
    {
        return $this->em->getRepository(Year::class)->findOneBy(['bid' => $business, 'head' => true]);
    }

    private function business(APIToken $token): Business
    {
        $business = $token->getBid();
        if (!$business) {
            throw new ClickSellException('NO_BUSINESS', 'توکن به کسب‌وکار وصل نیست', 401);
        }
        return $business;
    }

    private function invoiceId(array $payload): string
    {
        $id = trim((string)($payload['clickInvoiceId'] ?? $payload['invoiceId'] ?? ''));
        if ($id === '') {
            $event = trim((string)($payload['event_id'] ?? ''));
            if (preg_match('/^sell\\.(?:issue|cancel):(.+)$/', $event, $m)) {
                $id = $m[1];
            }
        }
        if ($id === '') {
            throw new ClickSellException('INVOICE_REQUIRED', 'شناسه فاکتور کلیک لازم است');
        }
        return $id;
    }

    private function requireEvent(array $payload, string $expected): string
    {
        $eventId = trim((string)($payload['event_id'] ?? ''));
        if ($eventId !== $expected) {
            throw new ClickSellException('EVENT_ID_REQUIRED', 'event_id نامعتبر است');
        }
        return $eventId;
    }

    private function requireHash(array $payload): string
    {
        $hash = trim((string)($payload['payload_hash'] ?? ''));
        if (!preg_match('/^[a-f0-9]{64}$/', $hash)) {
            throw new ClickSellException('PAYLOAD_HASH_REQUIRED', 'payload_hash نامعتبر است');
        }
        return $hash;
    }

    private function hashFromPlugin(?string $plugin): string
    {
        if (preg_match('/click_h:([a-f0-9]{64})/', (string)$plugin, $m)) {
            return $m[1];
        }
        return '';
    }

    private function issuedSellCodeFromStatus(PreInvoiceDoc $pre): ?string
    {
        $status = (string)$pre->getStatus();
        if (str_starts_with($status, 'issued:')) {
            return substr($status, 7);
        }
        return null;
    }

    public function isReturnPre(PreInvoiceDoc $pre): bool
    {
        $label = $pre->getInvoiceLabel();
        if ($label && $label->getCode() === 'click_rfsell') {
            return true;
        }
        $plugin = (string)$pre->getPlugin();
        $ref = (string)$pre->getRefData();
        $status = (string)$pre->getStatus();
        $des = (string)$pre->getDes();
        return str_contains($plugin, 'kind:rfsell')
            || str_starts_with($ref, 'sell.return.draft:')
            || str_starts_with($status, 'click_return')
            || str_contains($des, 'برگشت از فروش')
            || str_contains($des, 'ابطال فاکتور');
    }

    public function kindFa(PreInvoiceDoc $pre): string
    {
        if ($this->isReturnPre($pre)) {
            return 'برگشت از فروش';
        }
        if ($this->isAdjustment($pre)) {
            return 'تنظیمی';
        }
        return 'رسمی';
    }

    private function ensureRfsellInvoiceType(): InvoiceType
    {
        $row = $this->em->getRepository(InvoiceType::class)->findOneBy([
            'code' => 'click_rfsell',
            'type' => 'sell',
        ]);
        if ($row) {
            return $row;
        }
        $row = new InvoiceType();
        $row->setCode('click_rfsell');
        $row->setType('sell');
        $row->setLabel('برگشت از فروش کلیک');
        $this->em->persist($row);
        $this->em->flush();
        return $row;
    }

    private function isAdjustment(PreInvoiceDoc $pre): bool
    {
        $label = $pre->getInvoiceLabel();
        if ($label && $label->getCode() === 'click_adjustment') {
            return true;
        }
        $plugin = (string)$pre->getPlugin();
        return str_contains($plugin, 'kind:adjustment') || str_contains($plugin, 'nomoodian:1');
    }

    private function sellCodeFromPre(PreInvoiceDoc $pre, Business $business, Year $year): string
    {
        $digits = preg_replace('/\D+/', '', (string)$pre->getDes() . (string)$pre->getCode()) ?: '';
        $short = $digits !== '' ? substr($digits, -4) : '';
        if ($short !== '') {
            $taken = $this->em->getRepository(HesabdariDoc::class)->findOneBy([
                'bid' => $business,
                'year' => $year,
                'type' => 'sell',
                'code' => $short,
            ]);
            if (!$taken) {
                return $short;
            }
        }
        return (string)$this->provider->getAccountingCode($business->getId(), 'accounting');
    }

    private function skuFromCommodity(Commodity $commodity): string
    {
        $des = (string)$commodity->getDes();
        if (str_starts_with($des, 'click_sku:')) {
            return substr($des, 10);
        }
        return (string)$commodity->getCode();
    }
}
