<?php

namespace App\Repository;

use App\Entity\Diocese;
use App\Enum\StatutDiocese;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Diocese>
 */
class DioceseRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Diocese::class);
    }

    /**
     * @return Diocese[]
     */
    public function findAllActive(): array
    {
        return $this->createQueryBuilder('d')
            ->where('d.statut = :statut')
            ->setParameter('statut', StatutDiocese::ACTIF)
            ->orderBy('d.nom', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return array<array{diocese: Diocese, nombreParoisses: int, nombreIntentions: int, montantTotal: string}>
     */
    public function findAllWithStats(): array
    {
        $qb = $this->createQueryBuilder('d')
            ->select('d', 'COUNT(DISTINCT p.id) as nombreParoisses')
            ->leftJoin('d.paroisses', 'p')
            ->groupBy('d.id')
            ->orderBy('d.nom', 'ASC');

        $results = $qb->getQuery()->getResult();

        $diocesesWithStats = [];
        foreach ($results as $result) {
            $diocese = $result[0];
            $stats = $this->getStatsForDiocese($diocese);

            $diocesesWithStats[] = [
                'diocese' => $diocese,
                'nombreParoisses' => (int) $result['nombreParoisses'],
                'nombreIntentions' => $stats['nombreIntentions'],
                'montantTotal' => $stats['montantTotal'],
            ];
        }

        return $diocesesWithStats;
    }

    private function getStatsForDiocese(Diocese $diocese): array
    {
        $em = $this->getEntityManager();

        $result = $em->createQuery('
            SELECT COUNT(i.id) as nombreIntentions, COALESCE(SUM(i.montantPaye), 0) as montantTotal
            FROM App\Entity\IntentionMesse i
            JOIN i.occurrence o
            JOIN o.messe m
            JOIN m.paroisse p
            WHERE p.diocese = :diocese
            AND i.statutPaiement = :statut
        ')
            ->setParameter('diocese', $diocese)
            ->setParameter('statut', \App\Enum\StatutPaiement::PAYE)
            ->getSingleResult();

        return [
            'nombreIntentions' => (int) $result['nombreIntentions'],
            'montantTotal' => $result['montantTotal'] ?? '0',
        ];
    }
}
