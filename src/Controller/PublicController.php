<?php

namespace App\Controller;

use App\Entity\IntentionMesse;
use App\Entity\User;
use App\Repository\OccurrenceMesseRepository;
use App\Repository\ParoisseRepository;
use App\Service\FedaPayService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class PublicController extends AbstractController
{
    #[Route('/', name: 'public_home')]
    public function home(ParoisseRepository $paroisseRepository): Response
    {
        $paroisses = $paroisseRepository->findAllActive();

        return $this->render('public/home.html.twig', [
            'paroisses' => $paroisses,
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
            throw $this->createNotFoundException('Paroisse non trouvee');
        }

        if (!$paroisse->getDiocese()?->isActif()) {
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
            throw $this->createNotFoundException('Occurrence non trouvee');
        }

        if (!$occurrence->canReceiveIntentions()) {
            $this->addFlash('error', 'Cette messe ne peut plus recevoir d\'intentions');
            return $this->redirectToRoute('public_paroisse_calendar', ['id' => $occurrence->getMesse()?->getParoisse()?->getId()]);
        }

        if ($request->isMethod('POST')) {
            $nomDemandeur = trim($request->request->get('nom_demandeur', ''));
            $telephone = trim($request->request->get('telephone', ''));
            $email = trim($request->request->get('email', ''));

            // Validation
            if (empty($nomDemandeur)) {
                $this->addFlash('error', 'Veuillez indiquer votre nom');
                return $this->redirectToRoute('public_intention_new', ['occurrenceId' => $occurrenceId]);
            }

            if (empty($telephone) && empty($email)) {
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

            try {
                $paymentResult = $fedaPayService->createPayment($intention);
                $intention->setTransactionFedapay($paymentResult['transactionId']);
                $entityManager->flush();

                return $this->redirect($paymentResult['paymentUrl']);
            } catch (\Exception $e) {
                $this->addFlash('error', 'Erreur lors de l\'initialisation du paiement: ' . $e->getMessage());
                return $this->redirectToRoute('public_intention_new', ['occurrenceId' => $occurrenceId]);
            }
        }

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
            throw $this->createNotFoundException('Intention non trouvée');
        }

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

            if (!empty($numeroReference)) {
                $intention = $entityManager->getRepository(IntentionMesse::class)
                    ->findOneBy(['numeroReference' => strtoupper($numeroReference)]);
            }
        }

        return $this->render('public/verifier.html.twig', [
            'intention' => $intention,
            'searched' => $searched,
        ]);
    }
}
