<?php

namespace App\Controller;

use App\Entity\Person;
use App\Entity\ProvinceManager;
use App\Entity\User;
use App\Service\Access;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

class ProvinceManagerController extends AbstractController
{
    #[Route('/api/acc/province-manager/list', name: 'api_province_manager_list', methods: ['GET'])]
    public function list(Access $access, EntityManagerInterface $em): JsonResponse
    {
        $acc = $access->hasRole('join');
        if (!$acc) throw $this->createAccessDeniedException();

        $items = $em->getRepository(ProvinceManager::class)->findBy(['bid' => $acc['bid']]);
        $result = [];
        foreach ($items as $item) {
            $result[] = [
                'id' => $item->getId(),
                'province' => $item->getProvince(),
                'user' => $item->getUser() ? ['id' => $item->getUser()->getId(), 'mobile' => $item->getUser()->getMobile(), 'name' => $item->getUser()->getFullName()] : null,
            ];
        }
        return $this->json($result);
    }

    #[Route('/api/acc/province-manager/save', name: 'api_province_manager_save', methods: ['POST'])]
    public function save(Request $request, Access $access, EntityManagerInterface $em): JsonResponse
    {
        $acc = $access->hasRole('settings');
        if (!$acc) throw $this->createAccessDeniedException();

        $p = json_decode($request->getContent(), true) ?? [];
        if (empty($p['province'])) return $this->json(['result' => -1, 'message' => 'province required']);

        $item = $em->getRepository(ProvinceManager::class)->findOneBy(['bid' => $acc['bid'], 'province' => $p['province']]);
        if (!$item) {
            $item = new ProvinceManager();
            $item->setBid($acc['bid'])->setProvince($p['province']);
        }

        $user = null;
        if (!empty($p['userId'])) {
            $user = $em->getRepository(User::class)->find($p['userId']);
        }
        $item->setUser($user);
        $em->persist($item);
        $em->flush();

        return $this->json(['result' => 1]);
    }

    #[Route('/api/acc/province-manager/delete/{id}', name: 'api_province_manager_delete', methods: ['POST'])]
    public function delete(int $id, Access $access, EntityManagerInterface $em): JsonResponse
    {
        $acc = $access->hasRole('settings');
        if (!$acc) throw $this->createAccessDeniedException();

        $item = $em->getRepository(ProvinceManager::class)->findOneBy(['id' => $id, 'bid' => $acc['bid']]);
        if (!$item) throw $this->createNotFoundException();

        $em->remove($item);
        $em->flush();
        return $this->json(['result' => 1]);
    }

    #[Route('/api/acc/person/set-responsible', name: 'api_person_set_responsible', methods: ['POST'])]
    public function setPersonResponsible(Request $request, Access $access, EntityManagerInterface $em): JsonResponse
    {
        $acc = $access->hasRole('person');
        if (!$acc) throw $this->createAccessDeniedException();

        $p = json_decode($request->getContent(), true) ?? [];
        if (empty($p['personId'])) return $this->json(['result' => -1]);

        $person = $em->getRepository(Person::class)->findOneBy(['id' => $p['personId'], 'bid' => $acc['bid']]);
        if (!$person) throw $this->createNotFoundException();

        $user = null;
        if (!empty($p['userId'])) {
            $user = $em->getRepository(User::class)->find($p['userId']);
        }
        $person->setResponsible($user);
        $em->persist($person);
        $em->flush();

        return $this->json(['result' => 1]);
    }

    #[Route('/api/acc/person/responsible-list', name: 'api_person_responsible_list', methods: ['POST'])]
    public function personResponsibleList(Request $request, Access $access, EntityManagerInterface $em): JsonResponse
    {
        $acc = $access->hasRole('person');
        if (!$acc) throw $this->createAccessDeniedException();

        $persons = $em->createQueryBuilder()
            ->select('p.id, p.nikename, p.name, p.ostan, u.id as userId, u.mobile as userMobile, u.fullName as userName')
            ->from(Person::class, 'p')
            ->leftJoin('p.responsible', 'u')
            ->where('p.bid = :bid')
            ->setParameter('bid', $acc['bid'])
            ->orderBy('p.nikename', 'ASC')
            ->getQuery()->getScalarResult();

        return $this->json($persons);
    }

    #[Route('/api/acc/province-manager/provinces', name: 'api_province_manager_provinces', methods: ['GET'])]
    public function provinces(Access $access, EntityManagerInterface $em): JsonResponse
    {
        $acc = $access->hasRole('join');
        if (!$acc) throw $this->createAccessDeniedException();

        $provinces = $em->createQueryBuilder()
            ->select('DISTINCT p.ostan')
            ->from(Person::class, 'p')
            ->where('p.bid = :bid')
            ->andWhere('p.ostan IS NOT NULL')
            ->andWhere('p.ostan != \'\'')
            ->setParameter('bid', $acc['bid'])
            ->orderBy('p.ostan', 'ASC')
            ->getQuery()->getScalarResult();

        return $this->json(array_column($provinces, 'ostan'));
    }
}
