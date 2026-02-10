<?php

namespace App\Controller;

use App\Service\ActivityLoggerService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

class SecurityController extends AbstractController
{
    public function __construct(
        private ActivityLoggerService $activityLogger
    ) {
    }

    #[Route('/login', name: 'app_login')]
    public function login(AuthenticationUtils $authenticationUtils): Response
    {
        if ($this->getUser()) {
            $this->activityLogger->logAuth(
                'login_redirect',
                'Utilisateur déjà connecté, redirection vers dashboard',
                'debug'
            );
            return $this->redirectToRoute('app_dashboard');
        }

        $error = $authenticationUtils->getLastAuthenticationError();
        $lastUsername = $authenticationUtils->getLastUsername();

        if ($error) {
            $this->activityLogger->logAuth(
                'login_failed',
                sprintf('Échec de connexion pour: %s', $lastUsername),
                'warning',
                [
                    'username' => $lastUsername,
                    'errorMessage' => $error->getMessageKey(),
                ]
            );
        } else {
            $this->activityLogger->logAuth(
                'login_page_view',
                'Page de connexion affichée',
                'debug',
                ['lastUsername' => $lastUsername]
            );
        }

        return $this->render('security/login.html.twig', [
            'last_username' => $lastUsername,
            'error' => $error,
        ]);
    }

    #[Route('/logout', name: 'app_logout')]
    public function logout(): void
    {
        throw new \LogicException('This method can be blank - it will be intercepted by the logout key on your firewall.');
    }
}
