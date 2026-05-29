<?php
namespace App\Repository;
use App\Entity\CrmChecklistLog;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
class CrmChecklistLogRepository extends ServiceEntityRepository {
    public function __construct(ManagerRegistry $registry) { parent::__construct($registry, CrmChecklistLog::class); }
}
