<?php
namespace App\Repository;
use App\Entity\StoreroomCount;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
class StoreroomCountRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, StoreroomCount::class);
    }
}
