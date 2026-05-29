<?php

namespace App\Controller;

use App\Entity\SalesCenterTag;
use App\Service\Access;
use App\Service\Log;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

class SalesCenterTagController extends AbstractController
{
    #[Route('/api/acc/salescentertag/list', name: 'api_salescentertag_list')]
    public function list(Access $access, EntityManagerInterface $em): JsonResponse
    {
        $acc = $access->hasRole('join');
        if (!$acc) throw $this->createAccessDeniedException();
        $tags = $em->getRepository(SalesCenterTag::class)->findBy(['bid' => $acc['bid']], ['name' => 'ASC']);
        return $this->json(array_map(fn($t) => ['id' => $t->getId(), 'name' => $t->getName(), 'color' => $t->getColor()], $tags));
    }

    #[Route('/api/acc/salescentertag/mod', name: 'api_salescentertag_mod', methods: ['POST'])]
    public function mod(Request $request, Access $access, Log $log, EntityManagerInterface $em): JsonResponse
    {
        $acc = $access->hasRole('join');
        if (!$acc) throw $this->createAccessDeniedException();
        $p = json_decode($request->getContent(), true) ?? [];
        if (empty($p['name'])) return $this->json(['result' => -1]);

        $tag = !empty($p['id'])
            ? $em->getRepository(SalesCenterTag::class)->findOneBy(['id' => $p['id'], 'bid' => $acc['bid']])
            : new SalesCenterTag();

        if (!$tag) throw $this->createNotFoundException();
        $tag->setBid($acc['bid']);
        $tag->setName(trim($p['name']));
        $tag->setColor($p['color'] ?? '#1976D2');
        $em->persist($tag);
        $em->flush();
        $log->insert('تگ مرکز فروش', 'تگ ' . $tag->getName() . ' ذخیره شد.', $this->getUser(), $acc['bid']);
        return $this->json(['result' => 1, 'id' => $tag->getId()]);
    }

    #[Route('/api/acc/salescentertag/del/{id}', name: 'api_salescentertag_del', methods: ['POST'])]
    public function del(int $id, Access $access, Log $log, EntityManagerInterface $em): JsonResponse
    {
        $acc = $access->hasRole('join');
        if (!$acc) throw $this->createAccessDeniedException();
        $tag = $em->getRepository(SalesCenterTag::class)->findOneBy(['id' => $id, 'bid' => $acc['bid']]);
        if (!$tag) throw $this->createNotFoundException();
        $em->remove($tag);
        $em->flush();
        return $this->json(['result' => 1]);
    }

    #[Route('/api/acc/salescentertag/seed', name: 'api_salescentertag_seed', methods: ['POST'])]
    public function seed(Access $access, Log $log, EntityManagerInterface $em): JsonResponse
    {
        $acc = $access->hasRole('join');
        if (!$acc) throw $this->createAccessDeniedException();
        $existing = $em->getRepository(SalesCenterTag::class)->count(['bid' => $acc['bid']]);
        if ($existing > 0) return $this->json(['result' => 0, 'msg' => 'تگ‌ها قبلاً ساخته شده‌اند']);

        $defaults = [
            ['VIP', '#F44336'],
            ['مذاکره فعال', '#FF9800'],
            ['کلیدی', '#9C27B0'],
            ['رقیب جدی', '#F44336'],
            ['نیاز پیگیری', '#2196F3'],
            ['فرصت سفارش', '#4CAF50'],
            ['بودجه محدود', '#795548'],
            ['در صف معطل', '#607D8B'],
        ];
        foreach ($defaults as [$name, $color]) {
            $t = new SalesCenterTag();
            $t->setBid($acc['bid'])->setName($name)->setColor($color);
            $em->persist($t);
        }
        $em->flush();
        return $this->json(['result' => 1]);
    }
}
