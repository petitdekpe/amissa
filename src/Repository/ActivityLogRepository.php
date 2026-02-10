<?php

namespace App\Repository;

use App\Entity\ActivityLog;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ActivityLog>
 */
class ActivityLogRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ActivityLog::class);
    }

    /**
     * @return ActivityLog[]
     */
    public function findRecent(int $limit = 50): array
    {
        return $this->createQueryBuilder('a')
            ->orderBy('a.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * @return ActivityLog[]
     */
    public function findByCategory(string $category, int $limit = 50): array
    {
        return $this->createQueryBuilder('a')
            ->where('a.category = :category')
            ->setParameter('category', $category)
            ->orderBy('a.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * @return ActivityLog[]
     */
    public function findByUser(User $user, int $limit = 50): array
    {
        return $this->createQueryBuilder('a')
            ->where('a.user = :user')
            ->setParameter('user', $user)
            ->orderBy('a.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * @return ActivityLog[]
     */
    public function findByEntity(string $entityType, int $entityId): array
    {
        return $this->createQueryBuilder('a')
            ->where('a.entityType = :entityType')
            ->andWhere('a.entityId = :entityId')
            ->setParameter('entityType', $entityType)
            ->setParameter('entityId', $entityId)
            ->orderBy('a.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return ActivityLog[]
     */
    public function findByDateRange(\DateTimeInterface $start, \DateTimeInterface $end, ?string $category = null): array
    {
        $qb = $this->createQueryBuilder('a')
            ->where('a.createdAt BETWEEN :start AND :end')
            ->setParameter('start', $start)
            ->setParameter('end', $end)
            ->orderBy('a.createdAt', 'DESC');

        if ($category) {
            $qb->andWhere('a.category = :category')
                ->setParameter('category', $category);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * @return ActivityLog[]
     */
    public function findErrors(int $limit = 100): array
    {
        return $this->createQueryBuilder('a')
            ->where('a.level IN (:levels)')
            ->setParameter('levels', [ActivityLog::LEVEL_ERROR, ActivityLog::LEVEL_CRITICAL])
            ->orderBy('a.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function countByCategory(): array
    {
        return $this->createQueryBuilder('a')
            ->select('a.category, COUNT(a.id) as count')
            ->groupBy('a.category')
            ->getQuery()
            ->getResult();
    }

    public function countByLevel(): array
    {
        return $this->createQueryBuilder('a')
            ->select('a.level, COUNT(a.id) as count')
            ->groupBy('a.level')
            ->getQuery()
            ->getResult();
    }

    public function deleteOlderThan(\DateTimeInterface $date): int
    {
        return $this->createQueryBuilder('a')
            ->delete()
            ->where('a.createdAt < :date')
            ->setParameter('date', $date)
            ->getQuery()
            ->execute();
    }
}
