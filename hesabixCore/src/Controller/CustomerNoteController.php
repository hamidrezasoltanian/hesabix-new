<?php

namespace App\Controller;

use App\Entity\CustomerNote;
use App\Entity\Person;
use App\Service\Access;
use App\Service\Jdate;
use App\Service\Notification;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

class CustomerNoteController extends AbstractController
{
    private function toArr(CustomerNote $n): array
    {
        return [
            'id' => $n->getId(),
            'type' => $n->getType(),
            'content' => $n->getContent(),
            'date' => $n->getDate(),
            'reminderDate' => $n->getReminderDate(),
            'mentionedMobile' => $n->getMentionedMobile(),
            'user' => $n->getUser() ? ['id' => $n->getUser()->getId(), 'mobile' => $n->getUser()->getMobile(), 'name' => $n->getUser()->getFullName()] : null,
        ];
    }

    #[Route('/api/acc/customer-note/list/{personId}', name: 'api_customer_note_list', methods: ['GET'])]
    public function list(int $personId, Access $access, EntityManagerInterface $em): JsonResponse
    {
        $acc = $access->hasRole('join');
        if (!$acc) throw $this->createAccessDeniedException();

        $person = $em->getRepository(Person::class)->findOneBy(['id' => $personId, 'bid' => $acc['bid']]);
        if (!$person) throw $this->createNotFoundException();

        $notes = $em->getRepository(CustomerNote::class)->findBy(
            ['bid' => $acc['bid'], 'person' => $person],
            ['id' => 'DESC']
        );

        return $this->json(array_map(fn($n) => $this->toArr($n), $notes));
    }

    #[Route('/api/acc/customer-note/add', name: 'api_customer_note_add', methods: ['POST'])]
    public function add(Request $request, Access $access, Jdate $jdate, Notification $notification, EntityManagerInterface $em): JsonResponse
    {
        $acc = $access->hasRole('join');
        if (!$acc) throw $this->createAccessDeniedException();

        $p = json_decode($request->getContent(), true) ?? [];
        if (empty($p['personId']) || empty($p['content'])) return $this->json(['result' => -1]);

        $person = $em->getRepository(Person::class)->findOneBy(['id' => $p['personId'], 'bid' => $acc['bid']]);
        if (!$person) throw $this->createNotFoundException();

        $note = new CustomerNote();
        $note->setBid($acc['bid'])
            ->setPerson($person)
            ->setUser($this->getUser())
            ->setType($p['type'] ?? 'note')
            ->setContent(trim($p['content']))
            ->setDate($jdate->GetTodayDate())
            ->setReminderDate(!empty($p['reminderDate']) ? $p['reminderDate'] : null)
            ->setMentionedMobile(!empty($p['mentionedMobile']) ? $p['mentionedMobile'] : null);

        $em->persist($note);
        $em->flush();

        if (!empty($p['mentionedMobile'])) {
            $mentionedUser = $em->getRepository(\App\Entity\User::class)->findOneBy(['mobile' => $p['mentionedMobile']]);
            if ($mentionedUser) {
                $notification->insert(
                    'منشن در پرونده مشتری ' . $person->getNikename() . ': ' . mb_substr($p['content'], 0, 80),
                    '/acc/persons/file/' . $person->getId(),
                    $acc['bid'],
                    $mentionedUser
                );
            }
        }

        return $this->json(['result' => 1, 'id' => $note->getId()]);
    }

    #[Route('/api/acc/customer-note/del/{id}', name: 'api_customer_note_del', methods: ['POST'])]
    public function del(int $id, Access $access, EntityManagerInterface $em): JsonResponse
    {
        $acc = $access->hasRole('join');
        if (!$acc) throw $this->createAccessDeniedException();

        $note = $em->getRepository(CustomerNote::class)->findOneBy(['id' => $id, 'bid' => $acc['bid']]);
        if (!$note) throw $this->createNotFoundException();

        $em->remove($note);
        $em->flush();
        return $this->json(['result' => 1]);
    }

    #[Route('/api/acc/customer-note/reminder-check', name: 'api_customer_note_reminder_check', methods: ['GET'])]
    public function reminderCheck(Access $access, Jdate $jdate, Notification $notification, EntityManagerInterface $em): JsonResponse
    {
        $acc = $access->hasRole('join');
        if (!$acc) throw $this->createAccessDeniedException();

        $today = $jdate->GetTodayDate();
        $notes = $em->createQueryBuilder()
            ->select('n')
            ->from(CustomerNote::class, 'n')
            ->where('n.bid = :bid')
            ->andWhere('n.reminderDate IS NOT NULL')
            ->andWhere('n.reminderDate <= :today')
            ->andWhere('n.reminderSent = false OR n.reminderSent IS NULL')
            ->setParameter('bid', $acc['bid'])
            ->setParameter('today', $today)
            ->getQuery()->getResult();

        $due = [];
        foreach ($notes as $note) {
            $due[] = $this->toArr($note);
            if ($note->getUser()) {
                $notification->insert(
                    'یادآور: ' . mb_substr($note->getContent(), 0, 100),
                    '/acc/persons/file/' . $note->getPerson()->getId(),
                    $acc['bid'],
                    $note->getUser()
                );
            }
            $note->setReminderSent(true);
            $em->persist($note);
        }
        $em->flush();

        return $this->json($due);
    }
}
