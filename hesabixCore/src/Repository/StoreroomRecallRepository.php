<?php
namespace App\Repository;
use App\Entity\StoreroomRecall;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
class StoreroomRecallRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, StoreroomRecall::class);
    }
}
