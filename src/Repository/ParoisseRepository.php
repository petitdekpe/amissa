<?php

namespace App\Repository;

use App\Entity\Diocese;
use App\Entity\Paroisse;
use App\Enum\StatutDiocese;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Paroisse>
 */
class ParoisseRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Paroisse::class);
    }

    /**
     * @return Paroisse[]
     */
    public function findAllActive(): array
    {
        return $this->createQueryBuilder('p')
            ->join('p.diocese', 'd')
            ->where('d.statut = :statut')
            ->setParameter('statut', StatutDiocese::ACTIF)
            ->orderBy('p.nom', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return Paroisse[]
     */
    public function findByDiocese(Diocese $diocese): array
    {
        return $this->createQueryBuilder('p')
            ->where('p.diocese = :diocese')
            ->setParameter('diocese', $diocese)
            ->orderBy('p.nom', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return array<array{paroisse: Paroisse, nombreMesses: int, nombreIntentions: int, montantTotal: string}>
     */
    public function findByDioceseWithStats(Diocese $diocese): array
    {
        $paroisses = $this->findByDiocese($diocese);

        $paroissesWithStats = [];
        foreach ($paroisses as $paroisse) {
            $stats = $this->getStatsForParoisse($paroisse);

            $paroissesWithStats[] = [
                'paroisse' => $paroisse,
                'nombreMesses' => count($paroisse->getMesses()),
                'nombreIntentions' => $stats['nombreIntentions'],
                'montantTotal' => $stats['montantTotal'],
            ];
        }

        return $paroissesWithStats;
    }

    private function getStatsForParoisse(Paroisse $paroisse): array
    {
        $em = $this->getEntityManager();

        $result = $em->createQuery('
            SELECT COUNT(i.id) as nombreIntentions, COALESCE(SUM(i.montantPaye), 0) as montantTotal
            FROM App\Entity\IntentionMesse i
            JOIN i.occurrence o
            JOIN o.messe m
            WHERE m.paroisse = :paroisse
            AND i.statutPaiement = :statut
        ')
            ->setParameter('paroisse', $paroisse)
            ->setParameter('statut', \App\Enum\StatutPaiement::PAYE)
            ->getSingleResult();

        return [
            'nombreIntentions' => (int) $result['nombreIntentions'],
            'montantTotal' => $result['montantTotal'] ?? '0',
        ];
    }
}
