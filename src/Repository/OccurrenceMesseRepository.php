<?php

namespace App\Repository;

use App\Entity\OccurrenceMesse;
use App\Entity\Paroisse;
use App\Entity\Messe;
use App\Enum\StatutOccurrence;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<OccurrenceMesse>
 */
class OccurrenceMesseRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, OccurrenceMesse::class);
    }

    /**
     * @return OccurrenceMesse[]
     */
    public function findUpcomingByParoisse(Paroisse $paroisse, int $limit = 50): array
    {
        $delaiMinimum = $paroisse->getDelaiMinimumJours();
        $dateLimite = (new \DateTime())->modify("+{$delaiMinimum} days");

        return $this->createQueryBuilder('o')
            ->join('o.messe', 'm')
            ->where('m.paroisse = :paroisse')
            ->andWhere('o.dateHeure > :dateLimite')
            ->andWhere('o.statut = :statut')
            ->setParameter('paroisse', $paroisse)
            ->setParameter('dateLimite', $dateLimite)
            ->setParameter('statut', StatutOccurrence::CONFIRMEE)
            ->orderBy('o.dateHeure', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * @return OccurrenceMesse[]
     */
    public function findByMesse(Messe $messe): array
    {
        return $this->createQueryBuilder('o')
            ->where('o.messe = :messe')
            ->setParameter('messe', $messe)
            ->orderBy('o.dateHeure', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return OccurrenceMesse[]
     */
    public function findByDateRange(\DateTimeInterface $start, \DateTimeInterface $end, ?Paroisse $paroisse = null): array
    {
        $qb = $this->createQueryBuilder('o')
            ->join('o.messe', 'm')
            ->where('o.dateHeure >= :start')
            ->andWhere('o.dateHeure <= :end')
            ->setParameter('start', $start)
            ->setParameter('end', $end)
            ->orderBy('o.dateHeure', 'ASC');

        if ($paroisse) {
            $qb->andWhere('m.paroisse = :paroisse')
                ->setParameter('paroisse', $paroisse);
        }

        return $qb->getQuery()->getResult();
    }

    public function findMaxDateForMesse(Messe $messe): ?\DateTimeInterface
    {
        $result = $this->createQueryBuilder('o')
            ->select('MAX(o.dateHeure) as maxDate')
            ->where('o.messe = :messe')
            ->setParameter('messe', $messe)
            ->getQuery()
            ->getSingleScalarResult();

        return $result ? new \DateTime($result) : null;
    }
}
