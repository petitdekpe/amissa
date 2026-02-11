<?php

namespace App\Controller;

use App\Entity\IntentionMesse;
use App\Entity\User;
use App\Enum\TypeParoisse;
use App\Repository\DioceseRepository;
use App\Repository\OccurrenceMesseRepository;
use App\Repository\ParoisseRepository;
use App\Service\ActivityLoggerService;
use App\Service\FedaPayService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class PublicController extends AbstractController
{
    public function __construct(
        private ActivityLoggerService $activityLogger
    ) {
    }

    #[Route('/', name: 'public_home')]
    public function home(
        Request $request,
        ParoisseRepository $paroisseRepository,
        DioceseRepository $dioceseRepository
    ): Response {
        $dioceseId = $request->query->get('diocese');
        $type = $request->query->get('type');

        $dioceseId = $dioceseId ? (int) $dioceseId : null;
        $type = $type ?: null;

        $paroisses = $paroisseRepository->findActiveWithFilters($dioceseId, $type);
        $dioceses = $dioceseRepository->findAllActive();

        $this->activityLogger->logIntention(
            'view_home',
            'Page d\'accueil consultée',
            null,
            'debug',
            [
                'paroisseCount' => count($paroisses),
                'filterDiocese' => $dioceseId,
                'filterType' => $type,
            ]
        );

        return $this->render('public/home.html.twig', [
            'paroisses' => $paroisses,
            'dioceses' => $dioceses,
            'types' => TypeParoisse::cases(),
            'selectedDiocese' => $dioceseId,
            'selectedType' => $type,
        ]);
    }

    #[Route('/paroisse/{id}/calendar', name: 'public_paroisse_calendar')]
    public function calendar(
        int $id,
        Request $request,
        ParoisseRepository $paroisseRepository,
        OccurrenceMesseRepository $occurrenceRepository
    ): Response {
        $paroisse = $paroisseRepository->find($id);

        if (!$paroisse) {
            $this->activityLogger->logIntention(
                'view_calendar_error',
                'Paroisse non trouvée',
                null,
                'warning',
                ['paroisseId' => $id]
            );
            throw $this->createNotFoundException('Paroisse non trouvee');
        }

        if (!$paroisse->getDiocese()?->isActif()) {
            $this->activityLogger->logIntention(
                'view_calendar_error',
                'Diocèse inactif',
                null,
                'warning',
                ['paroisseId' => $id, 'dioceseId' => $paroisse->getDiocese()?->getId()]
            );
            throw $this->createNotFoundException('Diocese inactif');
        }

        // Get selected date (default: today)
        $selectedDateStr = $request->query->get('date', (new \DateTime())->format('Y-m-d'));
        $selectedDate = new \DateTime($selectedDateStr);

        // Get all upcoming occurrences for calendar markers
        $allOccurrences = $occurrenceRepository->findUpcomingByParoisse($paroisse, 100);

        // Group occurrences by date for calendar markers
        $datesWithMasses = [];
        foreach ($allOccurrences as $occurrence) {
            $dateKey = $occurrence->getDateHeure()->format('Y-m-d');
            $datesWithMasses[$dateKey] = ($datesWithMasses[$dateKey] ?? 0) + 1;
        }

        // Get occurrences for selected date
        $startOfDay = (clone $selectedDate)->setTime(0, 0, 0);
        $endOfDay = (clone $selectedDate)->setTime(23, 59, 59);
        $dayOccurrences = $occurrenceRepository->findByDateRange($startOfDay, $endOfDay, $paroisse);

        // Filter only those respecting minimum delay
        $delaiMinimum = $paroisse->getDelaiMinimumJours();
        $dateLimite = (new \DateTime())->modify("+{$delaiMinimum} days");
        $filteredOccurrences = array_filter($dayOccurrences, function($o) use ($dateLimite) {
            return $o->getDateHeure() > $dateLimite && $o->canReceiveIntentions();
        });

        $this->activityLogger->logIntention(
            'view_calendar',
            sprintf('Calendrier consulté pour %s', $paroisse->getNom()),
            null,
            'debug',
            [
                'paroisseId' => $id,
                'paroisseName' => $paroisse->getNom(),
                'selectedDate' => $selectedDateStr,
                'occurrenceCount' => count($filteredOccurrences),
            ]
        );

        return $this->render('public/calendar.html.twig', [
            'paroisse' => $paroisse,
            'occurrences' => array_values($filteredOccurrences),
            'selectedDate' => $selectedDate,
            'datesWithMasses' => $datesWithMasses,
        ]);
    }

    #[Route('/intention/{occurrenceId}/new', name: 'public_intention_new', methods: ['GET', 'POST'])]
    public function newIntention(
        int $occurrenceId,
        Request $request,
        OccurrenceMesseRepository $occurrenceRepository,
        FedaPayService $fedaPayService,
        EntityManagerInterface $entityManager
    ): Response {
        $occurrence = $occurrenceRepository->find($occurrenceId);

        if (!$occurrence) {
            $this->activityLogger->logIntention(
                'new_intention_error',
                'Occurrence non trouvée',
                null,
                'warning',
                ['occurrenceId' => $occurrenceId]
            );
            throw $this->createNotFoundException('Occurrence non trouvee');
        }

        if (!$occurrence->canReceiveIntentions()) {
            $this->activityLogger->logIntention(
                'new_intention_error',
                'Messe ne peut plus recevoir d\'intentions',
                null,
                'warning',
                [
                    'occurrenceId' => $occurrenceId,
                    'dateHeure' => $occurrence->getDateHeure()->format('Y-m-d H:i'),
                ]
            );
            $this->addFlash('error', 'Cette messe ne peut plus recevoir d\'intentions');
            return $this->redirectToRoute('public_paroisse_calendar', ['id' => $occurrence->getMesse()?->getParoisse()?->getId()]);
        }

        if ($request->isMethod('POST')) {
            $nomDemandeur = trim($request->request->get('nom_demandeur', ''));
            $telephone = trim($request->request->get('telephone', ''));
            $email = trim($request->request->get('email', ''));

            $this->activityLogger->logIntention(
                'new_intention_submit',
                'Soumission de formulaire d\'intention',
                null,
                'info',
                [
                    'occurrenceId' => $occurrenceId,
                    'nomDemandeur' => $nomDemandeur,
                    'hasPhone' => !empty($telephone),
                    'hasEmail' => !empty($email),
                ]
            );

            // Validation
            if (empty($nomDemandeur)) {
                $this->activityLogger->logIntention(
                    'new_intention_validation_error',
                    'Validation échouée: nom manquant',
                    null,
                    'warning',
                    ['occurrenceId' => $occurrenceId]
                );
                $this->addFlash('error', 'Veuillez indiquer votre nom');
                return $this->redirectToRoute('public_intention_new', ['occurrenceId' => $occurrenceId]);
            }

            if (empty($telephone) && empty($email)) {
                $this->activityLogger->logIntention(
                    'new_intention_validation_error',
                    'Validation échouée: contact manquant',
                    null,
                    'warning',
                    ['occurrenceId' => $occurrenceId, 'nomDemandeur' => $nomDemandeur]
                );
                $this->addFlash('error', 'Veuillez indiquer un numéro de téléphone ou une adresse email');
                return $this->redirectToRoute('public_intention_new', ['occurrenceId' => $occurrenceId]);
            }

            $intention = new IntentionMesse();
            $intention->setOccurrence($occurrence);
            $intention->setNomDemandeur($nomDemandeur);
            $intention->setTelephone($telephone ?: null);
            $intention->setEmail($email ?: null);

            $beneficiaire = $request->request->get('beneficiaire');
            $intention->setBeneficiaire($beneficiaire ?: null);

            $typeIntention = $request->request->get('type_intention');
            $intention->setTypeIntention($typeIntention ?: null);

            $texteIntention = $request->request->get('texte_intention');
            $intention->setTexteIntention($texteIntention ?: null);

            $intention->setMontantPaye($request->request->get('montant', $occurrence->getMesse()?->getMontantSuggere()));

            $entityManager->persist($intention);
            $entityManager->flush();

            $this->activityLogger->logIntention(
                'intention_created',
                sprintf('Nouvelle intention créée: %s', $intention->getNumeroReference()),
                $intention->getId(),
                'info',
                [
                    'numeroReference' => $intention->getNumeroReference(),
                    'nomDemandeur' => $nomDemandeur,
                    'beneficiaire' => $intention->getBeneficiaireDisplay(),
                    'montant' => $intention->getMontantPaye(),
                    'occurrenceId' => $occurrenceId,
                    'paroisseId' => $occurrence->getMesse()?->getParoisse()?->getId(),
                ]
            );

            try {
                $paymentResult = $fedaPayService->createPayment($intention);
                $intention->setTransactionFedapay($paymentResult['transactionId']);
                $entityManager->flush();

                $this->activityLogger->logPayment(
                    'payment_initiated',
                    sprintf('Paiement initié pour %s', $intention->getNumeroReference()),
                    $intention->getId(),
                    'info',
                    [
                        'transactionId' => $paymentResult['transactionId'],
                        'numeroReference' => $intention->getNumeroReference(),
                        'montant' => $intention->getMontantPaye(),
                    ]
                );

                return $this->redirect($paymentResult['paymentUrl']);
            } catch (\Exception $e) {
                $this->activityLogger->logError(
                    'payment',
                    'payment_error',
                    sprintf('Erreur paiement pour %s: %s', $intention->getNumeroReference(), $e->getMessage()),
                    $e,
                    [
                        'intentionId' => $intention->getId(),
                        'numeroReference' => $intention->getNumeroReference(),
                    ]
                );

                $this->addFlash('error', 'Erreur lors de l\'initialisation du paiement: ' . $e->getMessage());
                return $this->redirectToRoute('public_intention_new', ['occurrenceId' => $occurrenceId]);
            }
        }

        $this->activityLogger->logIntention(
            'view_intention_form',
            'Formulaire d\'intention affiché',
            null,
            'debug',
            [
                'occurrenceId' => $occurrenceId,
                'paroisseId' => $occurrence->getMesse()?->getParoisse()?->getId(),
            ]
        );

        return $this->render('public/intention_form.html.twig', [
            'occurrence' => $occurrence,
            'messe' => $occurrence->getMesse(),
            'paroisse' => $occurrence->getMesse()?->getParoisse(),
        ]);
    }

    #[Route('/intention/{id}/confirmation', name: 'public_intention_confirmation')]
    public function confirmation(int $id, EntityManagerInterface $entityManager): Response
    {
        $intention = $entityManager->getRepository(IntentionMesse::class)->find($id);

        if (!$intention) {
            $this->activityLogger->logIntention(
                'view_confirmation_error',
                'Intention non trouvée pour confirmation',
                null,
                'warning',
                ['intentionId' => $id]
            );
            throw $this->createNotFoundException('Intention non trouvée');
        }

        $this->activityLogger->logIntention(
            'view_confirmation',
            sprintf('Page de confirmation affichée pour %s', $intention->getNumeroReference()),
            $intention->getId(),
            'info',
            [
                'numeroReference' => $intention->getNumeroReference(),
                'statutPaiement' => $intention->getStatutPaiement()->value,
            ]
        );

        return $this->render('public/confirmation.html.twig', [
            'intention' => $intention,
        ]);
    }

    #[Route('/verifier', name: 'public_verifier_intention', methods: ['GET', 'POST'])]
    public function verifierIntention(Request $request, EntityManagerInterface $entityManager): Response
    {
        $intention = null;
        $searched = false;

        if ($request->isMethod('POST') || $request->query->get('ref')) {
            $searched = true;
            $numeroReference = $request->isMethod('POST')
                ? trim($request->request->get('numero_reference', ''))
                : trim($request->query->get('ref', ''));

            $this->activityLogger->logIntention(
                'verify_intention_search',
                sprintf('Recherche d\'intention: %s', $numeroReference),
                null,
                'info',
                ['numeroReference' => $numeroReference]
            );

            if (!empty($numeroReference)) {
                $intention = $entityManager->getRepository(IntentionMesse::class)
                    ->findOneBy(['numeroReference' => strtoupper($numeroReference)]);

                if ($intention) {
                    $this->activityLogger->logIntention(
                        'verify_intention_found',
                        sprintf('Intention trouvée: %s', $intention->getNumeroReference()),
                        $intention->getId(),
                        'info',
                        [
                            'numeroReference' => $intention->getNumeroReference(),
                            'statutPaiement' => $intention->getStatutPaiement()->value,
                        ]
                    );
                } else {
                    $this->activityLogger->logIntention(
                        'verify_intention_not_found',
                        sprintf('Intention non trouvée: %s', $numeroReference),
                        null,
                        'warning',
                        ['numeroReference' => $numeroReference]
                    );
                }
            }
        }

        return $this->render('public/verifier.html.twig', [
            'intention' => $intention,
            'searched' => $searched,
        ]);
    }
}
