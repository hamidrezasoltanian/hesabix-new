<?php

namespace App\Controller;

use App\Entity\Permission;
use App\Entity\PermissionTemplate;
use App\Entity\User;
use App\Service\Access;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

class PermissionTemplateController extends AbstractController
{
    private const FIELDS = [
        'settings', 'person', 'commodity', 'getpay', 'banks', 'bankTransfer',
        'buy', 'sell', 'cost', 'income', 'accounting', 'report', 'log',
        'permission', 'salary', 'cashdesk', 'shareholder', 'cheque',
        'archiveView', 'archiveUpload', 'archiveMod', 'archiveDelete',
        'plugAccproRfbuy', 'plugAccproRfsell', 'plugAccproAccounting',
        'plugAccproCloseYear', 'plugRepservice', 'plugAccproPresell',
        'plugHrmDocs', 'plugGhestaManager',
    ];

    #[Route('/api/acc/perm-template/list', name: 'api_perm_template_list', methods: ['GET'])]
    public function list(Access $access, EntityManagerInterface $em): JsonResponse
    {
        $acc = $access->hasRole('settings');
        if (!$acc) throw $this->createAccessDeniedException();

        $templates = $em->getRepository(PermissionTemplate::class)->findBy(['bid' => $acc['bid']]);
        return $this->json(array_map(fn($t) => [
            'id' => $t->getId(),
            'name' => $t->getName(),
            'permissions' => $t->getPermissions(),
        ], $templates));
    }

    #[Route('/api/acc/perm-template/save', name: 'api_perm_template_save', methods: ['POST'])]
    public function save(Request $request, Access $access, EntityManagerInterface $em): JsonResponse
    {
        $acc = $access->hasRole('settings');
        if (!$acc) throw $this->createAccessDeniedException();

        $p = json_decode($request->getContent(), true) ?? [];
        if (empty($p['name'])) return $this->json(['result' => -1]);

        $tpl = !empty($p['id'])
            ? $em->getRepository(PermissionTemplate::class)->findOneBy(['id' => $p['id'], 'bid' => $acc['bid']])
            : new PermissionTemplate();
        if (!$tpl) throw $this->createNotFoundException();

        $perms = [];
        foreach (self::FIELDS as $field) {
            $perms[$field] = !empty($p['permissions'][$field]);
        }

        $tpl->setBid($acc['bid'])->setName(trim($p['name']))->setPermissions($perms);
        $em->persist($tpl);
        $em->flush();

        return $this->json(['result' => 1, 'id' => $tpl->getId()]);
    }

    #[Route('/api/acc/perm-template/delete/{id}', name: 'api_perm_template_delete', methods: ['POST'])]
    public function delete(int $id, Access $access, EntityManagerInterface $em): JsonResponse
    {
        $acc = $access->hasRole('settings');
        if (!$acc) throw $this->createAccessDeniedException();

        $tpl = $em->getRepository(PermissionTemplate::class)->findOneBy(['id' => $id, 'bid' => $acc['bid']]);
        if (!$tpl) throw $this->createNotFoundException();

        $em->remove($tpl);
        $em->flush();
        return $this->json(['result' => 1]);
    }

    #[Route('/api/acc/perm-template/apply', name: 'api_perm_template_apply', methods: ['POST'])]
    public function apply(Request $request, Access $access, EntityManagerInterface $em): JsonResponse
    {
        $acc = $access->hasRole('settings');
        if (!$acc) throw $this->createAccessDeniedException();

        $p = json_decode($request->getContent(), true) ?? [];
        if (empty($p['templateId']) || empty($p['userId'])) return $this->json(['result' => -1]);

        $tpl = $em->getRepository(PermissionTemplate::class)->findOneBy(['id' => $p['templateId'], 'bid' => $acc['bid']]);
        if (!$tpl) throw $this->createNotFoundException();

        $user = $em->getRepository(User::class)->find($p['userId']);
        if (!$user) throw $this->createNotFoundException();

        $perm = $em->getRepository(Permission::class)->findOneBy(['bid' => $acc['bid'], 'user' => $user]);
        if (!$perm) return $this->json(['result' => -2, 'message' => 'user not in business']);

        $perms = $tpl->getPermissions();
        foreach (self::FIELDS as $field) {
            $setter = 'set' . ucfirst($field);
            if (method_exists($perm, $setter)) {
                $perm->$setter(!empty($perms[$field]));
            }
        }

        $em->persist($perm);
        $em->flush();
        return $this->json(['result' => 1]);
    }

    #[Route('/api/acc/perm-template/fields', name: 'api_perm_template_fields', methods: ['GET'])]
    public function fields(): JsonResponse
    {
        $labels = [
            'settings' => 'تنظیمات', 'person' => 'اشخاص', 'commodity' => 'کالا',
            'getpay' => 'دریافت/پرداخت', 'banks' => 'بانک', 'bankTransfer' => 'انتقال بانکی',
            'buy' => 'خرید', 'sell' => 'فروش', 'cost' => 'هزینه',
            'income' => 'درآمد', 'accounting' => 'حسابداری', 'report' => 'گزارش',
            'log' => 'لاگ', 'permission' => 'دسترسی', 'salary' => 'حقوق',
            'cashdesk' => 'صندوق', 'shareholder' => 'سهامداران', 'cheque' => 'چک',
            'archiveView' => 'مشاهده آرشیو', 'archiveUpload' => 'آپلود آرشیو',
            'archiveMod' => 'ویرایش آرشیو', 'archiveDelete' => 'حذف آرشیو',
            'plugAccproRfbuy' => 'برگشت خرید', 'plugAccproRfsell' => 'برگشت فروش',
            'plugAccproAccounting' => 'حسابداری پیشرفته', 'plugAccproCloseYear' => 'بستن سال',
            'plugRepservice' => 'خدمات پس از فروش', 'plugAccproPresell' => 'پیش‌فاکتور',
            'plugHrmDocs' => 'اسناد HRM', 'plugGhestaManager' => 'مدیریت اقساط',
        ];

        $result = [];
        foreach (self::FIELDS as $field) {
            $result[] = ['key' => $field, 'label' => $labels[$field] ?? $field];
        }
        return $this->json($result);
    }
}
