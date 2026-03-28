<?php

namespace App\Repository\Part;

use App\Entity\Manufacturer\Manufacturer;
use App\Entity\Part\Part;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method Part|null find($id, $lockMode = null, $lockVersion = null)
 * @method Part|null findOneBy(array $criteria, array $orderBy = null)
 * @method Part[]    findAll()
 * @method Part[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class PartRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
    	parent::__construct($registry, Part::class);
    }

    public function getManufacturerPartsQuery(Manufacturer $manufacturer): QueryBuilder
    {
        return $this->createQueryBuilder('p')
            ->leftJoin('p.manufacturers', 'm')
            ->where('m = :manufacturer')
            ->setParameter('manufacturer', $manufacturer)
            ->orderBy('p.name', 'ASC');
    }

    public function findOneForManufacturerByName(Manufacturer $manufacturer, string $name): ?Part
    {
        return $this->getManufacturerPartsQuery($manufacturer)
            ->andWhere('LOWER(p.name) = :name')
            ->setParameter('name', mb_strtolower(trim($name)))
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    // /**
    //  * @return Part[] Returns an array of Part objects
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
    public function findOneBySomeField($value): ?Part
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
