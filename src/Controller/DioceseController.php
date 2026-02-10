<?php

namespace App\Controller;

use App\Entity\Diocese;
use App\Entity\User;
use App\Enum\StatutDiocese;
use App\Repository\DioceseRepository;
use App\Service\ActivityLoggerService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/super/dioceses')]
#[IsGranted('ROLE_SUPER_USER')]
class DioceseController extends AbstractController
{
    public function __construct(
        private ActivityLoggerService $activityLogger
    ) {
    }

    #[Route('', name: 'admin_dioceses_index')]
    public function index(DioceseRepository $dioceseRepository): Response
    {
        $dioceses = $dioceseRepository->findAll();

        $this->activityLogger->logAdmin(
            'view_dioceses_list',
            'Liste des diocèses consultée',
            'Diocese',
            null,
            'debug',
            ['count' => count($dioceses)]
        );

        return $this->render('admin/super_user/dioceses/index.html.twig', [
            'dioceses' => $dioceses,
        ]);
    }

    #[Route('/new', name: 'admin_dioceses_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        if ($request->isMethod('POST')) {
            $diocese = new Diocese();
            $nom = $request->request->get('nom', '');
            $diocese->setNom($nom);
            $diocese->setStatut(StatutDiocese::INACTIF);
            $diocese->setCreatedBy($this->getUser());

            $secretKey = $request->request->get('fedapay_secret_key');
            $publicKey = $request->request->get('fedapay_public_key');

            if ($secretKey) {
                $diocese->setFedapaySecretKey($secretKey);
            }
            if ($publicKey) {
                $diocese->setFedapayPublicKey($publicKey);
            }

            $entityManager->persist($diocese);
            $entityManager->flush();

            $this->activityLogger->logAdmin(
                'diocese_created',
                sprintf('Diocèse créé: %s', $nom),
                'Diocese',
                $diocese->getId(),
                'info',
                [
                    'dioceseName' => $nom,
                    'hasFedapayKeys' => !empty($secretKey) || !empty($publicKey),
                ],
                null,
                [
                    'nom' => $nom,
                    'statut' => StatutDiocese::INACTIF->value,
                ]
            );

            $this->addFlash('success', 'Diocèse créé avec succès');
            return $this->redirectToRoute('admin_dioceses_index');
        }

        $this->activityLogger->logAdmin(
            'view_diocese_new_form',
            'Formulaire de création de diocèse affiché',
            'Diocese',
            null,
            'debug'
        );

        return $this->render('admin/super_user/dioceses/new.html.twig');
    }

    #[Route('/{id}/edit', name: 'admin_dioceses_edit', methods: ['GET', 'POST'])]
    public function edit(Diocese $diocese, Request $request, EntityManagerInterface $entityManager): Response
    {
        $oldValues = [
            'nom' => $diocese->getNom(),
            'hasFedapaySecretKey' => !empty($diocese->getFedapaySecretKey()),
            'hasFedapayPublicKey' => !empty($diocese->getFedapayPublicKey()),
        ];

        if ($request->isMethod('POST')) {
            $newNom = $request->request->get('nom', $diocese->getNom());
            $diocese->setNom($newNom);

            $secretKey = $request->request->get('fedapay_secret_key');
            $publicKey = $request->request->get('fedapay_public_key');

            $diocese->setFedapaySecretKey($secretKey ?: null);
            $diocese->setFedapayPublicKey($publicKey ?: null);

            $entityManager->flush();

            $newValues = [
                'nom' => $newNom,
                'hasFedapaySecretKey' => !empty($secretKey),
                'hasFedapayPublicKey' => !empty($publicKey),
            ];

            $this->activityLogger->logAdmin(
                'diocese_updated',
                sprintf('Diocèse modifié: %s', $newNom),
                'Diocese',
                $diocese->getId(),
                'info',
                null,
                $oldValues,
                $newValues
            );

            $this->addFlash('success', 'Diocèse modifié avec succès');
            return $this->redirectToRoute('admin_dioceses_index');
        }

        $this->activityLogger->logAdmin(
            'view_diocese_edit_form',
            sprintf('Formulaire d\'édition du diocèse: %s', $diocese->getNom()),
            'Diocese',
            $diocese->getId(),
            'debug'
        );

        return $this->render('admin/super_user/dioceses/edit.html.twig', [
            'diocese' => $diocese,
        ]);
    }

    #[Route('/{id}/toggle-status', name: 'admin_dioceses_toggle_status', methods: ['POST'])]
    public function toggleStatus(Diocese $diocese, EntityManagerInterface $entityManager): JsonResponse
    {
        $this->denyAccessUnlessGranted('DIOCESE_TOGGLE_STATUS', $diocese);

        $oldStatut = $diocese->getStatut();
        $newStatut = $oldStatut === StatutDiocese::ACTIF
            ? StatutDiocese::INACTIF
            : StatutDiocese::ACTIF;

        $diocese->setStatut($newStatut);
        $entityManager->flush();

        $this->activityLogger->logAdmin(
            'diocese_status_changed',
            sprintf('Statut du diocèse %s changé: %s → %s', $diocese->getNom(), $oldStatut->value, $newStatut->value),
            'Diocese',
            $diocese->getId(),
            'info',
            null,
            ['statut' => $oldStatut->value],
            ['statut' => $newStatut->value]
        );

        return new JsonResponse([
            'success' => true,
            'statut' => $newStatut->value,
        ]);
    }
}
