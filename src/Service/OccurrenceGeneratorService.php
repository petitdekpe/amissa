<?php

namespace App\Service;

use App\Entity\Messe;
use App\Entity\OccurrenceMesse;
use App\Enum\RecurrenceMesse;
use App\Enum\TypeMesse;
use App\Repository\OccurrenceMesseRepository;
use Doctrine\ORM\EntityManagerInterface;

class OccurrenceGeneratorService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private OccurrenceMesseRepository $occurrenceRepository
    ) {}

    public function generateOccurrences(Messe $messe, int $daysAhead = 30): int
    {
        if ($messe->getType() !== TypeMesse::RECURRENTE) {
            return 0;
        }

        if (!$messe->isActive()) {
            return 0;
        }

        $lastOccurrence = $this->occurrenceRepository->findMaxDateForMesse($messe);
        $startDate = $lastOccurrence
            ? (clone $lastOccurrence)->modify('+1 day')
            : ($messe->getDateDebut() ?? new \DateTime('today'));

        $endDate = (new \DateTime())->modify("+{$daysAhead} days");

        if ($messe->getDateFin() && $messe->getDateFin() < $endDate) {
            $endDate = $messe->getDateFin();
        }

        if ($startDate > $endDate) {
            return 0;
        }

        $count = 0;
        $current = clone $startDate;

        while ($current <= $endDate) {
            if ($this->shouldCreateOccurrence($messe, $current)) {
                $occurrence = $this->createOccurrence($messe, $current);
                $this->entityManager->persist($occurrence);
                $count++;
            }

            $current->modify('+1 day');
        }

        if ($count > 0) {
            $this->entityManager->flush();
        }

        return $count;
    }

    private function shouldCreateOccurrence(Messe $messe, \DateTimeInterface $date): bool
    {
        $dayOfWeek = (int) $date->format('w');
        $dayOfMonth = (int) $date->format('j');

        return match($messe->getRecurrence()) {
            RecurrenceMesse::QUOTIDIENNE => true,
            RecurrenceMesse::HEBDOMADAIRE => $dayOfWeek === $messe->getJourSemaine(),
            RecurrenceMesse::MENSUELLE => $dayOfMonth === ($messe->getJourSemaine() ?? 1),
            default => false,
        };
    }

    private function createOccurrence(Messe $messe, \DateTimeInterface $date): OccurrenceMesse
    {
        $occurrence = new OccurrenceMesse();
        $occurrence->setMesse($messe);

        $dateHeure = \DateTime::createFromInterface($date);
        $dateHeure->setTime(
            (int) $messe->getHeure()->format('H'),
            (int) $messe->getHeure()->format('i'),
            0
        );

        $occurrence->setDateHeure($dateHeure);

        return $occurrence;
    }

    public function generateForAllMesses(int $daysAhead = 30): array
    {
        $messes = $this->entityManager->getRepository(Messe::class)->findRecurrentesActives();

        $results = [];
        foreach ($messes as $messe) {
            $count = $this->generateOccurrences($messe, $daysAhead);
            if ($count > 0) {
                $results[$messe->getId()] = [
                    'messe' => $messe->getTitre(),
                    'paroisse' => $messe->getParoisse()?->getNom(),
                    'occurrencesCreees' => $count,
                ];
            }
        }

        return $results;
    }
}
