<?php
namespace App\Repository\Task;

use App\Entity\Task\Task;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;
use Ramsey\Uuid\Uuid;

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
        $qb->leftJoin('App\Entity\ServiceInterval\ServiceInterval', 'si', 'WITH', 't.id = si.task')
        ->where('si.bike = :bikeId')
        ->orWhere('si.model = :modelId')
        ->orWhere($qb->expr()->notIn('t.id', $dql))
        ->setParameter('bikeId', $bikeId)
        ->setParameter('modelId', $modelId);
        
        return $qb;
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
