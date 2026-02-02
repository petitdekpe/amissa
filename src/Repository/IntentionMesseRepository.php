<?php

namespace App\Repository;

use App\Entity\Diocese;
use App\Entity\IntentionMesse;
use App\Entity\Paroisse;
use App\Entity\User;
use App\Enum\StatutPaiement;
use App\Enum\StatutPayout;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<IntentionMesse>
 */
class IntentionMesseRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, IntentionMesse::class);
    }

    /**
     * @return IntentionMesse[]
     */
    public function findByFidele(User $fidele): array
    {
        return $this->createQueryBuilder('i')
            ->where('i.fidele = :fidele')
            ->setParameter('fidele', $fidele)
            ->orderBy('i.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return IntentionMesse[]
     */
    public function findPendingPayoutByParoisse(Paroisse $paroisse): array
    {
        return $this->createQueryBuilder('i')
            ->join('i.occurrence', 'o')
            ->join('o.messe', 'm')
            ->where('m.paroisse = :paroisse')
            ->andWhere('i.statutPaiement = :statutPaiement')
            ->andWhere('i.statutPayout = :statutPayout')
            ->setParameter('paroisse', $paroisse)
            ->setParameter('statutPaiement', StatutPaiement::PAYE)
            ->setParameter('statutPayout', StatutPayout::EN_ATTENTE)
            ->getQuery()
            ->getResult();
    }

    /**
     * @return array{totalIntentions: int, totalMontant: string, intentionsMois: int, montantMois: string}
     */
    public function getGlobalStats(): array
    {
        $em = $this->getEntityManager();

        $total = $em->createQuery('
            SELECT COUNT(i.id) as totalIntentions, COALESCE(SUM(i.montantPaye), 0) as totalMontant
            FROM App\Entity\IntentionMesse i
            WHERE i.statutPaiement = :statut
        ')
            ->setParameter('statut', StatutPaiement::PAYE)
            ->getSingleResult();

        $startOfMonth = new \DateTime('first day of this month midnight');
        $mois = $em->createQuery('
            SELECT COUNT(i.id) as intentionsMois, COALESCE(SUM(i.montantPaye), 0) as montantMois
            FROM App\Entity\IntentionMesse i
            WHERE i.statutPaiement = :statut
            AND i.createdAt >= :startOfMonth
        ')
            ->setParameter('statut', StatutPaiement::PAYE)
            ->setParameter('startOfMonth', $startOfMonth)
            ->getSingleResult();

        return [
            'totalIntentions' => (int) $total['totalIntentions'],
            'totalMontant' => $total['totalMontant'] ?? '0',
            'intentionsMois' => (int) $mois['intentionsMois'],
            'montantMois' => $mois['montantMois'] ?? '0',
        ];
    }

    /**
     * @return array{totalIntentions: int, totalMontant: string, intentionsMois: int, montantMois: string}
     */
    public function getStatsByDiocese(Diocese $diocese): array
    {
        $em = $this->getEntityManager();

        $total = $em->createQuery('
            SELECT COUNT(i.id) as totalIntentions, COALESCE(SUM(i.montantPaye), 0) as totalMontant
            FROM App\Entity\IntentionMesse i
            JOIN i.occurrence o
            JOIN o.messe m
            JOIN m.paroisse p
            WHERE p.diocese = :diocese
            AND i.statutPaiement = :statut
        ')
            ->setParameter('diocese', $diocese)
            ->setParameter('statut', StatutPaiement::PAYE)
            ->getSingleResult();

        $startOfMonth = new \DateTime('first day of this month midnight');
        $mois = $em->createQuery('
            SELECT COUNT(i.id) as intentionsMois, COALESCE(SUM(i.montantPaye), 0) as montantMois
            FROM App\Entity\IntentionMesse i
            JOIN i.occurrence o
            JOIN o.messe m
            JOIN m.paroisse p
            WHERE p.diocese = :diocese
            AND i.statutPaiement = :statut
            AND i.createdAt >= :startOfMonth
        ')
            ->setParameter('diocese', $diocese)
            ->setParameter('statut', StatutPaiement::PAYE)
            ->setParameter('startOfMonth', $startOfMonth)
            ->getSingleResult();

        return [
            'totalIntentions' => (int) $total['totalIntentions'],
            'totalMontant' => $total['totalMontant'] ?? '0',
            'intentionsMois' => (int) $mois['intentionsMois'],
            'montantMois' => $mois['montantMois'] ?? '0',
        ];
    }

    /**
     * @return array{totalIntentions: int, totalMontant: string, intentionsMois: int, montantMois: string, pendingPayout: string}
     */
    public function getStatsByParoisse(Paroisse $paroisse): array
    {
        $em = $this->getEntityManager();

        $total = $em->createQuery('
            SELECT COUNT(i.id) as totalIntentions, COALESCE(SUM(i.montantPaye), 0) as totalMontant
            FROM App\Entity\IntentionMesse i
            JOIN i.occurrence o
            JOIN o.messe m
            WHERE m.paroisse = :paroisse
            AND i.statutPaiement = :statut
        ')
            ->setParameter('paroisse', $paroisse)
            ->setParameter('statut', StatutPaiement::PAYE)
            ->getSingleResult();

        $startOfMonth = new \DateTime('first day of this month midnight');
        $mois = $em->createQuery('
            SELECT COUNT(i.id) as intentionsMois, COALESCE(SUM(i.montantPaye), 0) as montantMois
            FROM App\Entity\IntentionMesse i
            JOIN i.occurrence o
            JOIN o.messe m
            WHERE m.paroisse = :paroisse
            AND i.statutPaiement = :statut
            AND i.createdAt >= :startOfMonth
        ')
            ->setParameter('paroisse', $paroisse)
            ->setParameter('statut', StatutPaiement::PAYE)
            ->setParameter('startOfMonth', $startOfMonth)
            ->getSingleResult();

        $pending = $em->createQuery('
            SELECT COALESCE(SUM(i.montantPaye), 0) as pendingPayout
            FROM App\Entity\IntentionMesse i
            JOIN i.occurrence o
            JOIN o.messe m
            WHERE m.paroisse = :paroisse
            AND i.statutPaiement = :statutPaiement
            AND i.statutPayout = :statutPayout
        ')
            ->setParameter('paroisse', $paroisse)
            ->setParameter('statutPaiement', StatutPaiement::PAYE)
            ->setParameter('statutPayout', StatutPayout::EN_ATTENTE)
            ->getSingleResult();

        return [
            'totalIntentions' => (int) $total['totalIntentions'],
            'totalMontant' => $total['totalMontant'] ?? '0',
            'intentionsMois' => (int) $mois['intentionsMois'],
            'montantMois' => $mois['montantMois'] ?? '0',
            'pendingPayout' => $pending['pendingPayout'] ?? '0',
        ];
    }
}
