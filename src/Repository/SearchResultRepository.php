<?php

namespace App\Repository;

use App\Entity\SearchResult;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Symfony\Bridge\Doctrine\RegistryInterface;

/**
 * @method SearchResult|null find($id, $lockMode = null, $lockVersion = null)
 * @method SearchResult|null findOneBy(array $criteria, array $orderBy = null)
 * @method SearchResult[]    findAll()
 * @method SearchResult[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class SearchResultRepository extends ServiceEntityRepository
{
    public function __construct(RegistryInterface $registry)
    {
        parent::__construct($registry, SearchResult::class);
    }

//    /**
//     * @return SearchResult[] Returns an array of SearchResult objects
//     */
    /*
    public function findByExampleField($value)
    {
        return $this->createQueryBuilder('s')
            ->andWhere('s.exampleField = :val')
            ->setParameter('val', $value)
            ->orderBy('s.id', 'ASC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult()
        ;
    }
    */

    /*
    public function findOneBySomeField($value): ?SearchResult
    {
        return $this->createQueryBuilder('s')
            ->andWhere('s.exampleField = :val')
            ->setParameter('val', $value)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }
    */
}
