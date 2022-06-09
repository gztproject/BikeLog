<?php

namespace App\Repository\Maintenance;

use App\Entity\Maintenance\Maintenance;
use App\Entity\User\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method Maintenance|null find($id, $lockMode = null, $lockVersion = null)
 * @method Maintenance|null findOneBy(array $criteria, array $orderBy = null)
 * @method Maintenance[]    findAll()
 * @method Maintenance[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class MaintenanceRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
    	parent::__construct($registry, Maintenance::class);
    }
    
    public function getAllMaintenances(User $user): QueryBuilder
    {
        return $this->getFilteredQuery(null, null, null, $user);
    }
    
    public function getFilteredQuery($from, $to, $bikeId, User $user, $order = "DESC"): QueryBuilder
    {
        $qb = $this
        ->createQueryBuilder('t')
        ->addSelect('t')
        ->leftJoin ( 'App\Entity\Bike\Bike', 'b', 'WITH', 't.bike = b.id' )
        
        ->where('b.owner = :uid')
        ->setParameter('uid', $user->getId());
        
        if($from)
        {
            $qb
            ->andwhere('t.datetime >= :from')
            ->setParameter('from', date('Y-m-d G:i:s', $from));
        }
        
        if($to)
        {
            $qb
            ->andWhere('t.datetime <= :to')
            ->setParameter('to', date('Y-m-d G:i:s', $to));
        }
        
        if($bikeId)
        {
            
            $qb->andWhere('t.bike = :bikeid')
            ->setParameter('bikeid', $bikeId);
        }
        $qb->orderBy('t.datetime', $order);
        $qb->orderBy('t.odometer', $order);
        
        return $qb;
    }

    // /**
    //  * @return Maintenance[] Returns an array of Maintenance objects
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
    public function findOneBySomeField($value): ?Maintenance
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
