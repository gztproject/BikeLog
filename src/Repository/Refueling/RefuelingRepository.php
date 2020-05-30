<?php

namespace App\Repository\Refueling;

use App\Entity\Refueling\Refueling;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Common\Persistence\ManagerRegistry;

/**
 * @method Refueling|null find($id, $lockMode = null, $lockVersion = null)
 * @method Refueling|null findOneBy(array $criteria, array $orderBy = null)
 * @method Refueling[]    findAll()
 * @method Refueling[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class RefuelingRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
    	parent::__construct($registry, Refueling::class);
    }

    // /**
    //  * @return Refueling[] Returns an array of Refueling objects
    //  */
    /*
    public function findByExampleField($value)
    {
        return $this->createQueryBuilder('u')
            ->andWhere('u.exampleField = :val')
            ->setParameter('val', $value)
            ->orderBy('u.id', 'ASC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult()
        ;
    }
    */

    /*
    public function findOneBySomeField($value): ?Refueling
    {
        return $this->createQueryBuilder('u')
            ->andWhere('u.exampleField = :val')
            ->setParameter('val', $value)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }
    */
}
