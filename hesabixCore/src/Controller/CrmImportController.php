<?php
namespace App\Controller;

use App\Entity\SalesCenter;
use App\Entity\SalesCenterTag;
use App\Entity\Person;
use App\Service\Access;
use App\Service\Jdate;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class CrmImportController extends AbstractController
{
    #[Route('/api/acc/crm/import/template', name: 'api_crm_import_template', methods: ['GET'])]
    public function template(Access $access): Response
    {
        $acc = $access->hasRole('join'); if (!$acc) throw $this->createAccessDeniedException();

        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('مراکز');

        $headers = ['نام مرکز*','استان','شهر','نوع مرکز','پتانسیل(1-5)','دسته‌بندی لید','وضعیت CRM','تلفن','آدرس','تاریخ پیگیری(YYYY/MM/DD)','برچسب‌ها(با کاما)'];
        foreach ($headers as $i => $h) {
            $sheet->setCellValueByColumnAndRow($i + 1, 1, $h);
            $sheet->getColumnDimensionByColumn($i + 1)->setWidth(20);
        }
        $sheet->getStyle('A1:K1')->getFont()->setBold(true);
        $sheet->getStyle('A1:K1')->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setARGB('FFB8CCE4');

        // Example row
        $example = ['مرکز نمونه','تهران','تهران','بیمارستان','4','گرم','initial_contact','02112345678','خیابان ولیعصر','1404/01/15','VIP,مذاکره فعال'];
        foreach ($example as $i => $v) {
            $sheet->setCellValueByColumnAndRow($i + 1, 2, $v);
        }

        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        ob_start();
        $writer->save('php://output');
        $content = ob_get_clean();

        return new Response($content, 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="centers_import_template.xlsx"',
        ]);
    }

    #[Route('/api/acc/crm/import/preview', name: 'api_crm_import_preview', methods: ['POST'])]
    public function preview(Request $request, Access $access): JsonResponse
    {
        $acc = $access->hasRole('join'); if (!$acc) throw $this->createAccessDeniedException();
        $file = $request->files->get('file');
        if (!$file) return $this->json(['result' => -1, 'msg' => 'فایل ارسال نشده']);

        try {
            $ext = strtolower($file->getClientOriginalExtension());
            if ($ext === 'csv') {
                $rows = $this->parseCsv($file->getPathname());
            } else {
                $reader = new \PhpOffice\PhpSpreadsheet\Reader\Xlsx();
                $reader->setReadDataOnly(true);
                $spreadsheet = $reader->load($file->getPathname());
                $rows = $spreadsheet->getActiveSheet()->toArray();
            }
        } catch (\Throwable $e) {
            return $this->json(['result' => -2, 'msg' => 'خطا در خواندن فایل: ' . $e->getMessage()]);
        }

        if (count($rows) < 2) return $this->json(['result' => -3, 'msg' => 'فایل خالی است']);

        $headers = array_shift($rows);
        $preview = [];
        $errors = [];
        foreach (array_slice($rows, 0, 5) as $i => $row) {
            $name = trim($row[0] ?? '');
            if (empty($name)) { $errors[] = "ردیف " . ($i + 2) . ": نام مرکز الزامی است"; continue; }
            $preview[] = [
                'name' => $name, 'province' => $row[1] ?? '', 'city' => $row[2] ?? '',
                'type' => $row[3] ?? '', 'potential' => (int)($row[4] ?? 0),
                'lead' => $row[5] ?? '', 'crmStatus' => $row[6] ?? 'no_contact',
                'tel' => $row[7] ?? '', 'address' => $row[8] ?? '',
                'followupDate' => $row[9] ?? '', 'tags' => $row[10] ?? '',
            ];
        }
        return $this->json(['result' => 1, 'total' => count($rows), 'preview' => $preview, 'errors' => $errors]);
    }

    #[Route('/api/acc/crm/import/run', name: 'api_crm_import_run', methods: ['POST'])]
    public function run(Request $request, Access $access, Jdate $jdate, EntityManagerInterface $em): JsonResponse
    {
        $acc = $access->hasRole('join'); if (!$acc) throw $this->createAccessDeniedException();
        $file = $request->files->get('file');
        $mode = $request->request->get('mode', 'skip'); // skip | update
        if (!$file) return $this->json(['result' => -1, 'msg' => 'فایل ارسال نشده']);

        try {
            $ext = strtolower($file->getClientOriginalExtension());
            if ($ext === 'csv') {
                $rows = $this->parseCsv($file->getPathname());
            } else {
                $reader = new \PhpOffice\PhpSpreadsheet\Reader\Xlsx();
                $reader->setReadDataOnly(true);
                $spreadsheet = $reader->load($file->getPathname());
                $rows = $spreadsheet->getActiveSheet()->toArray();
            }
        } catch (\Throwable $e) {
            return $this->json(['result' => -2, 'msg' => 'خطا در خواندن فایل']);
        }

        if (count($rows) < 2) return $this->json(['result' => -3, 'msg' => 'فایل خالی است']);
        array_shift($rows); // remove header

        $validStatuses = ['no_contact','initial_contact','meeting_done','proposal_sent','contract_closed','inactive'];
        $created = 0; $updated = 0; $skipped = 0; $errors = [];

        foreach ($rows as $i => $row) {
            $name = trim($row[0] ?? '');
            if (empty($name)) { $skipped++; continue; }

            $existing = $em->getRepository(SalesCenter::class)->findOneBy(['bid' => $acc['bid'], 'name' => $name, 'active' => true]);
            if ($existing && $mode === 'skip') { $skipped++; continue; }

            $center = $existing ?? new SalesCenter();
            $center->setBid($acc['bid'])->setName($name)->setActive(true);
            if (!empty($row[1])) $center->setProvince(trim($row[1]));
            if (!empty($row[2])) $center->setCity(trim($row[2]));
            if (!empty($row[3])) $center->setType(trim($row[3]));
            if (!empty($row[4])) $center->setPotential(max(1, min(5, (int)$row[4])));
            if (!empty($row[5])) $center->setLead(trim($row[5]));
            $status = trim($row[6] ?? '');
            $center->setCrmStatus(in_array($status, $validStatuses) ? $status : 'no_contact');
            if (!empty($row[7])) $center->setTel(trim($row[7]));
            if (!empty($row[8])) $center->setAddress(trim($row[8]));
            if (!empty($row[9])) $center->setFollowupDate(trim($row[9]));

            if (!empty($row[10])) {
                $tagNames = array_map('trim', explode(',', $row[10]));
                if ($existing) { foreach ($center->getTags() as $t) $center->getTags()->removeElement($t); }
                foreach ($tagNames as $tn) {
                    if (empty($tn)) continue;
                    $tag = $em->getRepository(SalesCenterTag::class)->findOneBy(['bid' => $acc['bid'], 'name' => $tn]);
                    if (!$tag) { $tag = new SalesCenterTag(); $tag->setBid($acc['bid'])->setName($tn)->setColor('#607D8B'); $em->persist($tag); }
                    $center->getTags()->add($tag);
                }
            }

            $em->persist($center);
            $existing ? $updated++ : $created++;

            if (($i + 1) % 50 === 0) $em->flush();
        }
        $em->flush();

        return $this->json(['result' => 1, 'created' => $created, 'updated' => $updated, 'skipped' => $skipped, 'errors' => $errors]);
    }

    private function parseCsv(string $path): array
    {
        $rows = [];
        if (($handle = fopen($path, 'r')) !== false) {
            while (($data = fgetcsv($handle)) !== false) { $rows[] = $data; }
            fclose($handle);
        }
        return $rows;
    }
}
