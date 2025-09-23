<?php

namespace App\Repository;

use App\Entity\Address;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Address>
 */
class AddressRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Address::class);
    }

    /**
     * Find addresses by user
     */
    public function findByUser(User $user, array $orderBy = ['createdAt' => 'DESC']): array
    {
        return $this->findBy(['user' => $user], $orderBy);
    }

    /**
     * Find addresses by user and type
     */
    public function findByUserAndType(User $user, string $type): array
    {
        return $this->findBy(['user' => $user, 'type' => $type], ['isDefault' => 'DESC', 'createdAt' => 'DESC']);
    }

    /**
     * Find default address for user and type
     */
    public function findDefaultByUserAndType(User $user, string $type): ?Address
    {
        return $this->findOneBy(['user' => $user, 'type' => $type, 'isDefault' => true]);
    }

    /**
     * Set address as default (and unset others)
     */
    public function setAsDefault(Address $address): void
    {
        // First, unset all other default addresses of the same type for this user
        $this->createQueryBuilder('a')
            ->update()
            ->set('a.isDefault', ':false')
            ->where('a.user = :user')
            ->andWhere('a.type = :type')
            ->andWhere('a.id != :addressId')
            ->setParameter('false', false)
            ->setParameter('user', $address->getUser())
            ->setParameter('type', $address->getType())
            ->setParameter('addressId', $address->getId())
            ->getQuery()
            ->execute();

        // Then set this address as default
        $address->setIsDefault(true);
        $this->getEntityManager()->persist($address);
        $this->getEntityManager()->flush();
    }

    //    /**
    //     * @return Address[] Returns an array of Address objects
    //     */
    //    public function findByExampleField($value): array
    //    {
    //        return $this->createQueryBuilder('a')
    //            ->andWhere('a.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->orderBy('a.id', 'ASC')
    //            ->setMaxResults(10)
    //            ->getQuery()
    //            ->getResult()
    //        ;
    //    }

    //    public function findOneBySomeField($value): ?Address
    //    {
    //        return $this->createQueryBuilder('a')
    //            ->andWhere('a.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }
}