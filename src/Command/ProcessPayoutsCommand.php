<?php

namespace App\Command;

use App\Enum\StatutPayout;
use App\Repository\IntentionMesseRepository;
use App\Repository\ParoisseRepository;
use App\Service\FedaPayService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:process-payouts',
    description: 'Traite les payouts en attente vers les numéros Mobile Money des paroisses',
)]
class ProcessPayoutsCommand extends Command
{
    public function __construct(
        private ParoisseRepository $paroisseRepository,
        private IntentionMesseRepository $intentionRepository,
        private FedaPayService $fedaPayService,
        private EntityManagerInterface $entityManager
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Simuler sans effectuer les transferts')
            ->addOption('paroisse', 'p', InputOption::VALUE_REQUIRED, 'ID de la paroisse spécifique')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $dryRun = $input->getOption('dry-run');
        $paroisseId = $input->getOption('paroisse');

        if ($dryRun) {
            $io->note('Mode simulation activé - aucun transfert réel ne sera effectué');
        }

        $paroisses = $paroisseId
            ? [$this->paroisseRepository->find($paroisseId)]
            : $this->paroisseRepository->findAll();

        $totalTransfered = 0;
        $totalAmount = 0;

        foreach ($paroisses as $paroisse) {
            if (!$paroisse) {
                continue;
            }

            if (!$paroisse->getNumeroMobileMoney()) {
                $io->warning(sprintf(
                    'Paroisse "%s" n\'a pas de numéro Mobile Money configuré',
                    $paroisse->getNom()
                ));
                continue;
            }

            $pendingIntentions = $this->intentionRepository->findPendingPayoutByParoisse($paroisse);

            if (empty($pendingIntentions)) {
                continue;
            }

            $amount = array_reduce($pendingIntentions, function ($carry, $intention) {
                return $carry + (float) $intention->getMontantPaye();
            }, 0.0);

            $intentionIds = array_map(fn($i) => $i->getId(), $pendingIntentions);

            $io->text(sprintf(
                'Paroisse "%s": %d intentions, %s FCFA',
                $paroisse->getNom(),
                count($pendingIntentions),
                number_format($amount, 0, ',', ' ')
            ));

            if ($dryRun) {
                continue;
            }

            try {
                $result = $this->fedaPayService->createPayout($paroisse, $amount, $intentionIds);

                foreach ($pendingIntentions as $intention) {
                    $intention->setStatutPayout(StatutPayout::TRANSFERE);
                    $intention->setPayoutReference($result['reference'] ?? $result['payoutId']);
                }

                $this->entityManager->flush();

                $io->success(sprintf(
                    'Payout réussi: %s FCFA vers %s (ref: %s)',
                    number_format($amount, 0, ',', ' '),
                    $paroisse->getNom(),
                    $result['reference'] ?? $result['payoutId']
                ));

                $totalTransfered++;
                $totalAmount += $amount;

            } catch (\Exception $e) {
                $io->error(sprintf(
                    'Échec du payout pour "%s": %s',
                    $paroisse->getNom(),
                    $e->getMessage()
                ));

                foreach ($pendingIntentions as $intention) {
                    $intention->setStatutPayout(StatutPayout::ECHOUE);
                }

                $this->entityManager->flush();
            }
        }

        if (!$dryRun) {
            $io->success(sprintf(
                'Terminé: %d payouts effectués pour un total de %s FCFA',
                $totalTransfered,
                number_format($totalAmount, 0, ',', ' ')
            ));
        }

        return Command::SUCCESS;
    }
}
