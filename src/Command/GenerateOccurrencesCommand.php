<?php

namespace App\Command;

use App\Service\OccurrenceGeneratorService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:generate-occurrences',
    description: 'Génère les occurrences de messes récurrentes pour les prochains jours',
)]
class GenerateOccurrencesCommand extends Command
{
    public function __construct(
        private OccurrenceGeneratorService $occurrenceGenerator
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('days', 'd', InputOption::VALUE_REQUIRED, 'Nombre de jours à générer', 30)
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $days = (int) $input->getOption('days');

        $io->title(sprintf('Génération des occurrences pour les %d prochains jours', $days));

        $results = $this->occurrenceGenerator->generateForAllMesses($days);

        if (empty($results)) {
            $io->info('Aucune nouvelle occurrence à générer');
            return Command::SUCCESS;
        }

        $rows = [];
        $total = 0;
        foreach ($results as $result) {
            $rows[] = [
                $result['paroisse'],
                $result['messe'],
                $result['occurrencesCreees'],
            ];
            $total += $result['occurrencesCreees'];
        }

        $io->table(
            ['Paroisse', 'Messe', 'Occurrences créées'],
            $rows
        );

        $io->success(sprintf('Total: %d occurrences créées', $total));

        return Command::SUCCESS;
    }
}
