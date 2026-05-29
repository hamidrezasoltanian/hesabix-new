<?php
namespace App\Repository;
use App\Entity\CrmCalendarEvent;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
class CrmCalendarEventRepository extends ServiceEntityRepository {
    public function __construct(ManagerRegistry $registry) { parent::__construct($registry, CrmCalendarEvent::class); }
}
