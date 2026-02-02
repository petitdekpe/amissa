<?php

namespace App\Repository;

use App\Entity\Messe;
use App\Entity\Paroisse;
use App\Enum\StatutMesse;
use App\Enum\TypeMesse;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Messe>
 */
class MesseRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Messe::class);
    }

    /**
     * @return Messe[]
     */
    public function findByParoisse(Paroisse $paroisse): array
    {
        return $this->createQueryBuilder('m')
            ->where('m.paroisse = :paroisse')
            ->setParameter('paroisse', $paroisse)
            ->orderBy('m.heure', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return Messe[]
     */
    public function findActiveByParoisse(Paroisse $paroisse): array
    {
        return $this->createQueryBuilder('m')
            ->where('m.paroisse = :paroisse')
            ->andWhere('m.statut = :statut')
            ->setParameter('paroisse', $paroisse)
            ->setParameter('statut', StatutMesse::ACTIVE)
            ->orderBy('m.heure', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return Messe[]
     */
    public function findRecurrentesActives(): array
    {
        return $this->createQueryBuilder('m')
            ->where('m.type = :type')
            ->andWhere('m.statut = :statut')
            ->setParameter('type', TypeMesse::RECURRENTE)
            ->setParameter('statut', StatutMesse::ACTIVE)
            ->getQuery()
            ->getResult();
    }

    /**
     * @return Messe[]
     */
    public function findNeedingOccurrenceGeneration(\DateTimeInterface $untilDate): array
    {
        return $this->createQueryBuilder('m')
            ->leftJoin('m.occurrences', 'o', 'WITH', 'o.dateHeure <= :untilDate')
            ->where('m.type = :type')
            ->andWhere('m.statut = :statut')
            ->andWhere('(m.dateFin IS NULL OR m.dateFin >= :today)')
            ->setParameter('type', TypeMesse::RECURRENTE)
            ->setParameter('statut', StatutMesse::ACTIVE)
            ->setParameter('untilDate', $untilDate)
            ->setParameter('today', new \DateTime('today'))
            ->getQuery()
            ->getResult();
    }
}
