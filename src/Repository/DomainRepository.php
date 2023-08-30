<?php

namespace App\Repository;

use App\Entity\Domain;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Domain>
 *
 * @method Domain|null find($id, $lockMode = null, $lockVersion = null)
 * @method Domain|null findOneBy(array $criteria, array $orderBy = null)
 * @method Domain[]    findAll()
 * @method Domain[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class DomainRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Domain::class);
    }

    public function findNewDomainsOlderThanDays(int $days, int $maxResults)
    {
        $queryBuilder = $this->createQueryBuilder('q')
            ->where('q.status = :status')
            ->andWhere('q.dateAdded <= :cutoffDate')
            ->setParameter('status', 'new')
            ->setParameter('cutoffDate', new \DateTime('-'.$days.' days'))
            ->setMaxResults($maxResults);

        return $queryBuilder->getQuery()->getResult();
    }

    public function removeDomainsFromDatabase(array $domainsToRemove): void
    {
        $entityManager = $this->getEntityManager();

        foreach ($domainsToRemove as $domain) {
            if ($domain instanceof Domain) {
                $entityManager->remove($domain);
            }
        }

        $entityManager->flush();
    }

    public function markDomainsForRemoval(array $domainsToMark): void
    {
        $entityManager = $this->getEntityManager();

        foreach ($domainsToMark as $domain) {
            if ($domain instanceof Domain) {
                $domain->setStatus('remove');
                $entityManager->persist($domain);
            }
        }

        $entityManager->flush();
    }

}
