<?php

namespace App\Repository\Refueling;

use App\Entity\Refueling\Refueling;
use App\Entity\User\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Common\Persistence\ManagerRegistry;
use Doctrine\ORM\QueryBuilder;
use App\Entity\Bike\Bike;

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
    
    public function getQuery(): QueryBuilder
    {
    	return $this->createQueryBuilder('t')
    	->addSelect('t')
    	->orderBy('t.datetime', 'DESC');
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
    	
    	return $qb->orderBy('t.datetime', $order);
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
