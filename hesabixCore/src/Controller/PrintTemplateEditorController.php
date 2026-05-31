<?php

namespace App\Controller;

use App\Entity\HesabdariDoc;
use App\Entity\PreInvoiceDoc;
use App\Entity\PrintTemplate;
use App\Entity\StoreroomTicket;
use App\Service\Access;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

class PrintTemplateEditorController extends AbstractController
{
    private function getOrCreate(EntityManagerInterface $em, mixed $bid): PrintTemplate
    {
        $tpl = $em->getRepository(PrintTemplate::class)->findOneBy(['bid' => $bid]);
        if (!$tpl) {
            $tpl = new PrintTemplate();
            $tpl->setBid($bid);
        }
        return $tpl;
    }

    #[Route('/api/acc/print-template/load', name: 'api_print_template_load', methods: ['GET'])]
    public function load(Access $access, EntityManagerInterface $em): JsonResponse
    {
        $acc = $access->hasRole('settings');
        if (!$acc) throw $this->createAccessDeniedException();

        $tpl = $em->getRepository(PrintTemplate::class)->findOneBy(['bid' => $acc['bid']]);

        $defaultInvoice = $this->defaultInvoiceTemplate();
        $defaultPreinvoice = $this->defaultPreinvoiceTemplate();
        $defaultStoreroom = $this->defaultStoreroomTemplate();

        return $this->json([
            'invoiceTemplate'     => $tpl?->getInvoiceTemplate() ?? $defaultInvoice,
            'preinvoiceTemplate'  => $tpl?->getPreinvoiceTemplate() ?? $defaultPreinvoice,
            'storeroomTemplate'   => $tpl?->getStoreroomTemplate() ?? $defaultStoreroom,
            'defaults' => [
                'invoice'     => $defaultInvoice,
                'preinvoice'  => $defaultPreinvoice,
                'storeroom'   => $defaultStoreroom,
            ],
        ]);
    }

    #[Route('/api/acc/print-template/save', name: 'api_print_template_save', methods: ['POST'])]
    public function save(Request $request, Access $access, EntityManagerInterface $em): JsonResponse
    {
        $acc = $access->hasRole('settings');
        if (!$acc) throw $this->createAccessDeniedException();

        $p = json_decode($request->getContent(), true) ?? [];
        $tpl = $this->getOrCreate($em, $acc['bid']);

        if (array_key_exists('invoiceTemplate', $p))    $tpl->setInvoiceTemplate($p['invoiceTemplate']);
        if (array_key_exists('preinvoiceTemplate', $p)) $tpl->setPreinvoiceTemplate($p['preinvoiceTemplate']);
        if (array_key_exists('storeroomTemplate', $p))  $tpl->setStoreroomTemplate($p['storeroomTemplate']);

        $em->persist($tpl);
        $em->flush();
        return $this->json(['result' => 1]);
    }

    #[Route('/api/acc/print-template/variables', name: 'api_print_template_variables', methods: ['GET'])]
    public function variables(): JsonResponse
    {
        return $this->json([
            'invoice' => [
                ['key' => '{{business_name}}', 'label' => 'نام کسب‌وکار'],
                ['key' => '{{invoice_number}}', 'label' => 'شماره فاکتور'],
                ['key' => '{{invoice_date}}', 'label' => 'تاریخ فاکتور'],
                ['key' => '{{person_name}}', 'label' => 'نام مشتری'],
                ['key' => '{{person_mobile}}', 'label' => 'موبایل مشتری'],
                ['key' => '{{person_address}}', 'label' => 'آدرس مشتری'],
                ['key' => '{{total_amount}}', 'label' => 'مبلغ کل'],
                ['key' => '{{discount_amount}}', 'label' => 'تخفیف'],
                ['key' => '{{tax_amount}}', 'label' => 'مالیات'],
                ['key' => '{{payable_amount}}', 'label' => 'مبلغ قابل پرداخت'],
                ['key' => '{{items_table}}', 'label' => 'جدول اقلام'],
                ['key' => '{{description}}', 'label' => 'توضیحات'],
            ],
            'preinvoice' => [
                ['key' => '{{business_name}}', 'label' => 'نام کسب‌وکار'],
                ['key' => '{{preinvoice_number}}', 'label' => 'شماره پیش‌فاکتور'],
                ['key' => '{{preinvoice_date}}', 'label' => 'تاریخ پیش‌فاکتور'],
                ['key' => '{{expire_date}}', 'label' => 'تاریخ انقضا'],
                ['key' => '{{person_name}}', 'label' => 'نام مشتری'],
                ['key' => '{{total_amount}}', 'label' => 'مبلغ کل'],
                ['key' => '{{items_table}}', 'label' => 'جدول اقلام'],
            ],
            'storeroom' => [
                ['key' => '{{business_name}}', 'label' => 'نام کسب‌وکار'],
                ['key' => '{{ticket_number}}', 'label' => 'شماره حواله'],
                ['key' => '{{ticket_date}}', 'label' => 'تاریخ'],
                ['key' => '{{ticket_type}}', 'label' => 'نوع (ورود/خروج)'],
                ['key' => '{{person_name}}', 'label' => 'نام طرف'],
                ['key' => '{{storeroom_name}}', 'label' => 'نام انبار'],
                ['key' => '{{items_table}}', 'label' => 'جدول اقلام'],
                ['key' => '{{description}}', 'label' => 'توضیحات'],
            ],
        ]);
    }

    private function defaultInvoiceTemplate(): string
    {
        return '<div style="font-family:Tahoma,sans-serif;direction:rtl;padding:20px">
  <h2 style="text-align:center">{{business_name}}</h2>
  <table style="width:100%;border-collapse:collapse;margin-bottom:10px">
    <tr><td><strong>شماره:</strong> {{invoice_number}}</td><td><strong>تاریخ:</strong> {{invoice_date}}</td></tr>
    <tr><td colspan="2"><strong>مشتری:</strong> {{person_name}} | {{person_mobile}}</td></tr>
    <tr><td colspan="2"><strong>آدرس:</strong> {{person_address}}</td></tr>
  </table>
  {{items_table}}
  <table style="width:100%;margin-top:10px">
    <tr><td>مبلغ کل:</td><td>{{total_amount}}</td></tr>
    <tr><td>تخفیف:</td><td>{{discount_amount}}</td></tr>
    <tr><td>مالیات:</td><td>{{tax_amount}}</td></tr>
    <tr><td><strong>مبلغ قابل پرداخت:</strong></td><td><strong>{{payable_amount}}</strong></td></tr>
  </table>
  <p style="margin-top:15px">{{description}}</p>
</div>';
    }

    private function defaultPreinvoiceTemplate(): string
    {
        return '<div style="font-family:Tahoma,sans-serif;direction:rtl;padding:20px">
  <h2 style="text-align:center">پیش‌فاکتور — {{business_name}}</h2>
  <table style="width:100%;border-collapse:collapse;margin-bottom:10px">
    <tr><td><strong>شماره:</strong> {{preinvoice_number}}</td><td><strong>تاریخ:</strong> {{preinvoice_date}}</td></tr>
    <tr><td><strong>مشتری:</strong> {{person_name}}</td><td><strong>اعتبار تا:</strong> {{expire_date}}</td></tr>
  </table>
  {{items_table}}
  <table style="width:100%;margin-top:10px">
    <tr><td><strong>جمع کل:</strong></td><td><strong>{{total_amount}}</strong></td></tr>
  </table>
</div>';
    }

    private function defaultStoreroomTemplate(): string
    {
        return '<div style="font-family:Tahoma,sans-serif;direction:rtl;padding:20px">
  <h2 style="text-align:center">حواله انبار — {{business_name}}</h2>
  <table style="width:100%;border-collapse:collapse;margin-bottom:10px">
    <tr><td><strong>شماره:</strong> {{ticket_number}}</td><td><strong>تاریخ:</strong> {{ticket_date}}</td></tr>
    <tr><td><strong>نوع:</strong> {{ticket_type}}</td><td><strong>انبار:</strong> {{storeroom_name}}</td></tr>
    <tr><td colspan="2"><strong>طرف:</strong> {{person_name}}</td></tr>
  </table>
  {{items_table}}
  <p style="margin-top:15px">{{description}}</p>
</div>';
    }
}
