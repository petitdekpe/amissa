<?php

namespace App\Controller;

use App\Entity\Messe;
use App\Entity\OccurrenceMesse;
use App\Entity\User;
use App\Enum\RecurrenceMesse;
use App\Enum\StatutMesse;
use App\Enum\StatutOccurrence;
use App\Enum\TypeMesse;
use App\Repository\MesseRepository;
use App\Service\OccurrenceGeneratorService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/paroisse/messes')]
#[IsGranted('ROLE_SECRETAIRE')]
class MesseController extends AbstractController
{
    #[Route('', name: 'admin_messes_index')]
    public function index(MesseRepository $messeRepository): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $paroisse = $user->getParoisse();

        if (!$paroisse) {
            throw $this->createNotFoundException('Paroisse non trouvee');
        }

        $messes = $messeRepository->findByParoisse($paroisse);

        return $this->render('admin/messe/index.html.twig', [
            'messes' => $messes,
            'paroisse' => $paroisse,
        ]);
    }

    #[Route('/new', name: 'admin_messes_new', methods: ['GET', 'POST'])]
    public function new(
        Request $request,
        EntityManagerInterface $entityManager,
        OccurrenceGeneratorService $occurrenceGenerator
    ): Response {
        /** @var User $user */
        $user = $this->getUser();
        $paroisse = $user->getParoisse();

        if (!$paroisse) {
            throw $this->createNotFoundException('Paroisse non trouvee');
        }

        if ($request->isMethod('POST')) {
            $messe = new Messe();
            $messe->setParoisse($paroisse);
            $messe->setTitre($request->request->get('titre', ''));
            $messe->setType(TypeMesse::from($request->request->get('type', 'recurrente')));
            $messe->setHeure(new \DateTime($request->request->get('heure', '09:00')));
            $messe->setMontantSuggere($request->request->get('montant_suggere', '2000'));
            $messe->setStatut(StatutMesse::ACTIVE);

            if ($messe->isRecurrente()) {
                $messe->setRecurrence(RecurrenceMesse::from($request->request->get('recurrence', 'hebdomadaire')));
                $messe->setJourSemaine((int) $request->request->get('jour_semaine', 0));
            } else {
                $messe->setDateDebut(new \DateTime($request->request->get('date_debut')));
                if ($request->request->get('date_fin')) {
                    $messe->setDateFin(new \DateTime($request->request->get('date_fin')));
                }
            }

            $entityManager->persist($messe);
            $entityManager->flush();

            if ($messe->isRecurrente()) {
                $occurrenceGenerator->generateOccurrences($messe, 30);
            } else {
                $occurrence = new OccurrenceMesse();
                $occurrence->setMesse($messe);
                $dateHeure = new \DateTime($request->request->get('date_debut'));
                $dateHeure->setTime(
                    (int) $messe->getHeure()->format('H'),
                    (int) $messe->getHeure()->format('i')
                );
                $occurrence->setDateHeure($dateHeure);
                $entityManager->persist($occurrence);
                $entityManager->flush();
            }

            $this->addFlash('success', 'Messe creee avec succes');
            return $this->redirectToRoute('admin_messes_index');
        }

        return $this->render('admin/messe/new.html.twig', [
            'paroisse' => $paroisse,
        ]);
    }

    #[Route('/{id}/edit', name: 'admin_messes_edit', methods: ['GET', 'POST'])]
    public function edit(
        int $id,
        Request $request,
        MesseRepository $messeRepository,
        EntityManagerInterface $entityManager
    ): Response {
        /** @var User $user */
        $user = $this->getUser();
        $paroisse = $user->getParoisse();

        $messe = $messeRepository->find($id);

        if (!$messe || $messe->getParoisse() !== $paroisse) {
            throw $this->createNotFoundException('Messe non trouvee');
        }

        if ($request->isMethod('POST')) {
            $messe->setTitre($request->request->get('titre', ''));
            $messe->setType(TypeMesse::from($request->request->get('type', 'recurrente')));
            $messe->setHeure(new \DateTime($request->request->get('heure', '09:00')));
            $messe->setMontantSuggere($request->request->get('montant_suggere', '2000'));

            if ($messe->isRecurrente()) {
                $messe->setRecurrence(RecurrenceMesse::from($request->request->get('recurrence', 'hebdomadaire')));
                $messe->setJourSemaine((int) $request->request->get('jour_semaine', 0));
                $messe->setDateDebut(null);
                $messe->setDateFin(null);
            } else {
                $messe->setRecurrence(null);
                $messe->setJourSemaine(null);
                if ($request->request->get('date_debut')) {
                    $messe->setDateDebut(new \DateTime($request->request->get('date_debut')));
                }
                if ($request->request->get('date_fin')) {
                    $messe->setDateFin(new \DateTime($request->request->get('date_fin')));
                }
            }

            $entityManager->flush();

            $this->addFlash('success', 'Messe modifiee avec succes');
            return $this->redirectToRoute('admin_messes_index');
        }

        return $this->render('admin/messe/edit.html.twig', [
            'messe' => $messe,
            'paroisse' => $paroisse,
        ]);
    }

    #[Route('/{id}/delete', name: 'admin_messes_delete', methods: ['POST'])]
    public function delete(
        int $id,
        Request $request,
        MesseRepository $messeRepository,
        EntityManagerInterface $entityManager
    ): Response {
        /** @var User $user */
        $user = $this->getUser();
        $paroisse = $user->getParoisse();

        $messe = $messeRepository->find($id);

        if (!$messe || $messe->getParoisse() !== $paroisse) {
            throw $this->createNotFoundException('Messe non trouvee');
        }

        if ($this->isCsrfTokenValid('delete' . $messe->getId(), $request->request->get('_token'))) {
            $entityManager->remove($messe);
            $entityManager->flush();
            $this->addFlash('success', 'Messe supprimee avec succes');
        }

        return $this->redirectToRoute('admin_messes_index');
    }

    #[Route('/{id}/toggle-status', name: 'admin_messes_toggle_status', methods: ['POST'])]
    public function toggleStatus(Messe $messe, EntityManagerInterface $entityManager): JsonResponse
    {
        $this->denyAccessUnlessGranted('MESSE_EDIT', $messe);

        $newStatut = $messe->getStatut() === StatutMesse::ACTIVE
            ? StatutMesse::SUSPENDUE
            : StatutMesse::ACTIVE;

        $messe->setStatut($newStatut);
        $entityManager->flush();

        return new JsonResponse([
            'success' => true,
            'statut' => $newStatut->value,
        ]);
    }

    #[Route('/occurrence/{id}/cancel', name: 'admin_occurrence_cancel', methods: ['POST'])]
    public function cancelOccurrence(OccurrenceMesse $occurrence, EntityManagerInterface $entityManager): JsonResponse
    {
        $this->denyAccessUnlessGranted('MESSE_CANCEL', $occurrence->getMesse());

        $occurrence->setStatut(StatutOccurrence::ANNULEE);
        $entityManager->flush();

        return new JsonResponse([
            'success' => true,
        ]);
    }
}
