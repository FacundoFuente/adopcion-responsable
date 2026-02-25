<?php

namespace App\Repository;

use App\Entity\Person;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Person>
 */
class PersonRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Person::class);
    }

    public function findOwnerByDni(int $dni): ?Person
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.dni = :dni')
            ->setParameter('dni', $dni)
            ->orderBy('p.id', 'ASC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findProntuariosSummaryByOwner(User $owner, ?int $dniFilter = null): array
    {
        $qb = $this->createQueryBuilder('p')
            ->select('p.dni AS dni')
            ->addSelect('COUNT(p.id) AS entriesCount')
            ->addSelect('MAX(p.createdAt) AS lastEntryAt')
            ->andWhere('p.owner = :owner')
            ->setParameter('owner', $owner)
            ->groupBy('p.dni')
            ->orderBy('lastEntryAt', 'DESC');

        if ($dniFilter !== null) {
            $qb->andWhere('p.dni = :dni')->setParameter('dni', $dniFilter);
        }

        return $qb->getQuery()->getArrayResult();
    }

    public function findLatestPhotoEntryByDni(int $dni): ?Person
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.dni = :dni')
            ->andWhere('p.description LIKE :photoPrefix')
            ->setParameter('dni', $dni)
            ->setParameter('photoPrefix', '[FOTO] %')
            ->orderBy('p.createdAt', 'DESC')
            ->addOrderBy('p.id', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    //    /**
    //     * @return Person[] Returns an array of Person objects
    //     */
    //    public function findByExampleField($value): array
    //    {
    //        return $this->createQueryBuilder('p')
    //            ->andWhere('p.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->orderBy('p.id', 'ASC')
    //            ->setMaxResults(10)
    //            ->getQuery()
    //            ->getResult()
    //        ;
    //    }

    //    public function findOneBySomeField($value): ?Person
    //    {
    //        return $this->createQueryBuilder('p')
    //            ->andWhere('p.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }
}
