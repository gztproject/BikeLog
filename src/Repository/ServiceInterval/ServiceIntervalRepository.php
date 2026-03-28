<?php

namespace App\Repository\ServiceInterval;

use App\Entity\ServiceInterval\ServiceInterval;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method ServiceInterval|null find($id, $lockMode = null, $lockVersion = null)
 * @method ServiceInterval|null findOneBy(array $criteria, array $orderBy = null)
 * @method ServiceInterval[]    findAll()
 * @method ServiceInterval[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ServiceIntervalRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
    	parent::__construct($registry, ServiceInterval::class);
    }

    // /**
    //  * @return ServiceInterval[] Returns an array of ServiceInterval objects
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
    public function findOneBySomeField($value): ?ServiceInterval
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
