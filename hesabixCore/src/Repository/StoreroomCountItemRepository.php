<?php
namespace App\Repository;
use App\Entity\StoreroomCountItem;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
class StoreroomCountItemRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, StoreroomCountItem::class);
    }
}
