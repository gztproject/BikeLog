<?php
namespace App\Repository\Task;

use App\Entity\Bike\Bike;
use App\Entity\Manufacturer\Manufacturer;
use App\Entity\Model\Model;
use App\Entity\Part\Part;
use App\Entity\Task\Task;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 *
 * @method Task|null find($id, $lockMode = null, $lockVersion = null)
 * @method Task|null findOneBy(array $criteria, array $orderBy = null)
 * @method Task[] findAll()
 * @method Task[] findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class TaskRepository extends ServiceEntityRepository
{

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Task::class);
    }

    public function getBikeTasksQuery($bikeId, $modelId)
    {
        $dql = $this->createQueryBuilder('t2')->select('identity(si2.task)')->from('App\Entity\ServiceInterval\ServiceInterval', 'si2')->getDQL();
        // the function returns a QueryBuilder object
        $qb = $this->createQueryBuilder('t');
        $qb->select('DISTINCT t')
        ->leftJoin('App\Entity\ServiceInterval\ServiceInterval', 'si', 'WITH', 't.id = si.task')
        ->where('si.bike = :bikeId')
        ->orWhere('si.model = :modelId')
        ->orWhere($qb->expr()->notIn('t.id', $dql))
        ->setParameter('bikeId', $bikeId)
        ->setParameter('modelId', $modelId)
        ->orderBy('t.name', 'ASC');
        
        return $qb;
    }

    public function getManufacturerTasksQuery(Manufacturer $manufacturer): QueryBuilder
    {
        return $this->createQueryBuilder('t')
            ->select('DISTINCT t')
            ->leftJoin('t.part', 'p')
            ->leftJoin('p.manufacturers', 'pm')
            ->leftJoin('App\Entity\ServiceInterval\ServiceInterval', 'si', 'WITH', 'si.task = t')
            ->leftJoin('si.model', 'm')
            ->leftJoin('si.bike', 'b')
            ->leftJoin('b.model', 'bm')
            ->where('pm = :manufacturer')
            ->orWhere('m.manufacturer = :manufacturer')
            ->orWhere('bm.manufacturer = :manufacturer')
            ->setParameter('manufacturer', $manufacturer)
            ->orderBy('p.name', 'ASC')
            ->addOrderBy('t.name', 'ASC');
    }

    public function getServicePlanTasksQuery(Model $model, ?Bike $bike = null): QueryBuilder
    {
        $queryBuilder = $this->createQueryBuilder('t')
            ->select('DISTINCT t')
            ->innerJoin('App\Entity\ServiceInterval\ServiceInterval', 'si', 'WITH', 'si.task = t')
            ->leftJoin('t.part', 'p')
            ->where('si.model = :model')
            ->setParameter('model', $model)
            ->orderBy('p.name', 'ASC')
            ->addOrderBy('t.name', 'ASC');

        if ($bike != null) {
            $queryBuilder
                ->orWhere('si.bike = :bike')
                ->setParameter('bike', $bike);
        }

        return $queryBuilder;
    }

    public function getModelTasksWithUniversalQuery(Model $model): QueryBuilder
    {
        return $this->createQueryBuilder('t')
            ->select('DISTINCT t')
            ->innerJoin('App\Entity\ServiceInterval\ServiceInterval', 'si', 'WITH', 'si.task = t')
            ->leftJoin('t.part', 'p')
            ->where('si.model = :model')
            ->orWhere('p IS NULL AND si.model IS NOT NULL')
            ->setParameter('model', $model)
            ->orderBy('p.name', 'ASC')
            ->addOrderBy('t.name', 'ASC');
    }

    public function findOneForPartByName(?Part $part, string $name): ?Task
    {
        $queryBuilder = $this->createQueryBuilder('t')
            ->andWhere('LOWER(t.name) = :name')
            ->setParameter('name', mb_strtolower(trim($name)))
            ->setMaxResults(1);

        if ($part == null) {
            $queryBuilder->andWhere('t.part IS NULL');
        } else {
            $queryBuilder
                ->andWhere('t.part = :part')
                ->setParameter('part', $part);
        }

        return $queryBuilder->getQuery()->getOneOrNullResult();
    }
    /**
     *
     * @return Task[] Returns an array of Task objects
     */
    public function getBikeTasks($bikeId, $modelId)
    {
        $qb = $this->getBikeTasksQuery($bikeId, $modelId);        
       
        return $qb->getQuery()->getResult();
    }

    // /**
    // * @return Task[] Returns an array of Task objects
    // */
    /*
     * public function findByExampleField($value)
     * {
     * return $this->createQueryBuilder('u')
     * ->andWhere('u.exampleField = :val')
     * ->setParameter('val', $value)
     * ->orderBy('u.id', 'ASC')
     * ->setMaxResults(10)
     * ->getQuery()
     * ->getResult()
     * ;
     * }
     */

    /*
     * public function findOneBySomeField($value): ?Task
     * {
     * return $this->createQueryBuilder('u')
     * ->andWhere('u.exampleField = :val')
     * ->setParameter('val', $value)
     * ->getQuery()
     * ->getOneOrNullResult()
     * ;
     * }
     */
}
