<?php
namespace App\Repository;
use App\Entity\DeliveryRecord;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
class DeliveryRecordRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, DeliveryRecord::class);
    }
}
