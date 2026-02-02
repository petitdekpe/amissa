<?php

namespace App\Controller;

use App\Entity\Diocese;
use App\Enum\StatutDiocese;
use App\Repository\DioceseRepository;
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
    #[Route('', name: 'admin_dioceses_index')]
    public function index(DioceseRepository $dioceseRepository): Response
    {
        $dioceses = $dioceseRepository->findAll();

        return $this->render('admin/super_user/dioceses/index.html.twig', [
            'dioceses' => $dioceses,
        ]);
    }

    #[Route('/new', name: 'admin_dioceses_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        if ($request->isMethod('POST')) {
            $diocese = new Diocese();
            $diocese->setNom($request->request->get('nom', ''));
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

            $this->addFlash('success', 'Diocèse créé avec succès');
            return $this->redirectToRoute('admin_dioceses_index');
        }

        return $this->render('admin/super_user/dioceses/new.html.twig');
    }

    #[Route('/{id}/edit', name: 'admin_dioceses_edit', methods: ['GET', 'POST'])]
    public function edit(Diocese $diocese, Request $request, EntityManagerInterface $entityManager): Response
    {
        if ($request->isMethod('POST')) {
            $diocese->setNom($request->request->get('nom', $diocese->getNom()));

            $secretKey = $request->request->get('fedapay_secret_key');
            $publicKey = $request->request->get('fedapay_public_key');

            $diocese->setFedapaySecretKey($secretKey ?: null);
            $diocese->setFedapayPublicKey($publicKey ?: null);

            $entityManager->flush();

            $this->addFlash('success', 'Diocèse modifié avec succès');
            return $this->redirectToRoute('admin_dioceses_index');
        }

        return $this->render('admin/super_user/dioceses/edit.html.twig', [
            'diocese' => $diocese,
        ]);
    }

    #[Route('/{id}/toggle-status', name: 'admin_dioceses_toggle_status', methods: ['POST'])]
    public function toggleStatus(Diocese $diocese, EntityManagerInterface $entityManager): JsonResponse
    {
        $this->denyAccessUnlessGranted('DIOCESE_TOGGLE_STATUS', $diocese);

        $newStatut = $diocese->getStatut() === StatutDiocese::ACTIF
            ? StatutDiocese::INACTIF
            : StatutDiocese::ACTIF;

        $diocese->setStatut($newStatut);
        $entityManager->flush();

        return new JsonResponse([
            'success' => true,
            'statut' => $newStatut->value,
        ]);
    }
}
