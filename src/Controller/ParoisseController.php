<?php

namespace App\Controller;

use App\Entity\Paroisse;
use App\Entity\User;
use App\Enum\TypeParoisse;
use App\Repository\ParoisseRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/diocese/paroisses')]
#[IsGranted('ROLE_ADMIN_DIOCESE')]
class ParoisseController extends AbstractController
{
    #[Route('', name: 'admin_diocese_paroisses')]
    public function index(ParoisseRepository $paroisseRepository): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $diocese = $user->getDiocese();

        if (!$diocese) {
            throw $this->createAccessDeniedException('Vous n\'etes pas associe a un diocese');
        }

        $paroisses = $paroisseRepository->findBy(['diocese' => $diocese], ['nom' => 'ASC']);

        return $this->render('admin/diocese/paroisses/index.html.twig', [
            'paroisses' => $paroisses,
            'diocese' => $diocese,
        ]);
    }

    #[Route('/new', name: 'admin_diocese_paroisse_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $diocese = $user->getDiocese();

        if (!$diocese) {
            throw $this->createAccessDeniedException('Vous n\'etes pas associe a un diocese');
        }

        if ($request->isMethod('POST')) {
            $paroisse = new Paroisse();
            $paroisse->setDiocese($diocese);
            $paroisse->setType(TypeParoisse::from($request->request->get('type', 'paroisse')));
            $paroisse->setNom($request->request->get('nom', ''));
            $paroisse->setAdresse($request->request->get('adresse', ''));
            $paroisse->setNumeroMobileMoney($request->request->get('numero_mobile_money', ''));
            $paroisse->setDelaiMinimumJours((int) $request->request->get('delai_minimum_jours', 2));

            $entityManager->persist($paroisse);
            $entityManager->flush();

            $this->addFlash('success', $paroisse->getTypeLabel() . ' creee avec succes');
            return $this->redirectToRoute('admin_diocese_paroisses');
        }

        return $this->render('admin/diocese/paroisses/new.html.twig', [
            'diocese' => $diocese,
            'types' => TypeParoisse::cases(),
        ]);
    }

    #[Route('/{id}/edit', name: 'admin_diocese_paroisse_edit', methods: ['GET', 'POST'])]
    public function edit(int $id, Request $request, ParoisseRepository $paroisseRepository, EntityManagerInterface $entityManager): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $diocese = $user->getDiocese();

        $paroisse = $paroisseRepository->find($id);

        if (!$paroisse || $paroisse->getDiocese() !== $diocese) {
            throw $this->createNotFoundException('Paroisse non trouvee');
        }

        if ($request->isMethod('POST')) {
            $paroisse->setType(TypeParoisse::from($request->request->get('type', 'paroisse')));
            $paroisse->setNom($request->request->get('nom', ''));
            $paroisse->setAdresse($request->request->get('adresse', ''));
            $paroisse->setNumeroMobileMoney($request->request->get('numero_mobile_money', ''));
            $paroisse->setDelaiMinimumJours((int) $request->request->get('delai_minimum_jours', 2));

            $entityManager->flush();

            $this->addFlash('success', $paroisse->getTypeLabel() . ' modifiee avec succes');
            return $this->redirectToRoute('admin_diocese_paroisses');
        }

        return $this->render('admin/diocese/paroisses/edit.html.twig', [
            'paroisse' => $paroisse,
            'diocese' => $diocese,
            'types' => TypeParoisse::cases(),
        ]);
    }

    #[Route('/{id}/delete', name: 'admin_diocese_paroisse_delete', methods: ['POST'])]
    public function delete(int $id, Request $request, ParoisseRepository $paroisseRepository, EntityManagerInterface $entityManager): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $diocese = $user->getDiocese();

        $paroisse = $paroisseRepository->find($id);

        if (!$paroisse || $paroisse->getDiocese() !== $diocese) {
            throw $this->createNotFoundException('Paroisse non trouvee');
        }

        if ($this->isCsrfTokenValid('delete' . $paroisse->getId(), $request->request->get('_token'))) {
            $entityManager->remove($paroisse);
            $entityManager->flush();
            $this->addFlash('success', 'Paroisse supprimee avec succes');
        }

        return $this->redirectToRoute('admin_diocese_paroisses');
    }
}
