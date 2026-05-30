<?php
namespace App\Repository;
use App\Entity\PurchaseOrderItem;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
class PurchaseOrderItemRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PurchaseOrderItem::class);
    }
}
