<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\DioceseRepository;
use App\Repository\IntentionMesseRepository;
use App\Repository\ParoisseRepository;
use App\Repository\OccurrenceMesseRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class DashboardController extends AbstractController
{
    #[Route('/dashboard', name: 'app_dashboard')]
    #[IsGranted('ROLE_FIDELE')]
    public function index(): Response
    {
        if ($this->isGranted('ROLE_SUPER_USER')) {
            return $this->redirectToRoute('admin_super_dashboard');
        }
        if ($this->isGranted('ROLE_ADMIN_DIOCESE')) {
            return $this->redirectToRoute('admin_diocese_dashboard');
        }
        if ($this->isGranted('ROLE_ADMIN_PAROISSE') || $this->isGranted('ROLE_SECRETAIRE')) {
            return $this->redirectToRoute('admin_paroisse_dashboard');
        }

        return $this->redirectToRoute('public_home');
    }

    #[Route('/admin/super/dashboard', name: 'admin_super_dashboard')]
    #[IsGranted('ROLE_SUPER_USER')]
    public function superUserDashboard(
        DioceseRepository $dioceseRepository,
        IntentionMesseRepository $intentionRepository
    ): Response {
        $diocesesWithStats = $dioceseRepository->findAllWithStats();
        $globalStats = $intentionRepository->getGlobalStats();

        return $this->render('admin/super_user/dashboard.html.twig', [
            'dioceses' => $diocesesWithStats,
            'globalStats' => $globalStats,
        ]);
    }

    #[Route('/admin/diocese/dashboard', name: 'admin_diocese_dashboard')]
    #[IsGranted('ROLE_ADMIN_DIOCESE')]
    public function dioceseDashboard(
        ParoisseRepository $paroisseRepository,
        IntentionMesseRepository $intentionRepository
    ): Response {
        /** @var User $user */
        $user = $this->getUser();
        $diocese = $user->getDiocese();

        if (!$diocese) {
            throw $this->createNotFoundException('Diocese non trouve');
        }

        $paroissesWithStats = $paroisseRepository->findByDioceseWithStats($diocese);
        $stats = $intentionRepository->getStatsByDiocese($diocese);

        return $this->render('admin/diocese/dashboard.html.twig', [
            'diocese' => $diocese,
            'paroisses' => $paroissesWithStats,
            'stats' => $stats,
        ]);
    }

    #[Route('/admin/paroisse/dashboard', name: 'admin_paroisse_dashboard')]
    #[IsGranted('ROLE_SECRETAIRE')]
    public function paroisseDashboard(
        Request $request,
        IntentionMesseRepository $intentionRepository,
        OccurrenceMesseRepository $occurrenceRepository
    ): Response {
        /** @var User $user */
        $user = $this->getUser();
        $paroisse = $user->getParoisse();

        if (!$paroisse) {
            throw $this->createNotFoundException('Paroisse non trouvee');
        }

        $stats = $intentionRepository->getStatsByParoisse($paroisse);

        // Calendar logic
        $selectedDateStr = $request->query->get('date', (new \DateTime())->format('Y-m-d'));
        $selectedDate = new \DateTime($selectedDateStr);

        // Get all upcoming occurrences for calendar markers (60 days ahead)
        $allOccurrences = $occurrenceRepository->findUpcomingByParoisse($paroisse, 200);

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

        return $this->render('admin/paroisse/dashboard.html.twig', [
            'paroisse' => $paroisse,
            'stats' => $stats,
            'selectedDate' => $selectedDate,
            'datesWithMasses' => $datesWithMasses,
            'dayOccurrences' => $dayOccurrences,
        ]);
    }
}
