<?php

namespace App\Controller;

use App\Entity\User;
use App\Service\ActivityLoggerService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
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

    #[Route('/register', name: 'app_register', methods: ['GET', 'POST'])]
    public function register(
        Request $request,
        UserPasswordHasherInterface $passwordHasher,
        EntityManagerInterface $entityManager
    ): Response {
        if ($this->getUser()) {
            return $this->redirectToRoute('app_dashboard');
        }

        $error = null;
        $success = false;

        if ($request->isMethod('POST')) {
            $email = trim($request->request->get('email', ''));
            $password = $request->request->get('password', '');
            $passwordConfirm = $request->request->get('password_confirm', '');
            $nom = trim($request->request->get('nom', ''));
            $prenom = trim($request->request->get('prenom', ''));

            // Validation
            if (empty($email) || empty($password) || empty($nom) || empty($prenom)) {
                $error = 'Tous les champs sont obligatoires.';
            } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $error = 'L\'adresse email n\'est pas valide.';
            } elseif (strlen($password) < 8) {
                $error = 'Le mot de passe doit contenir au moins 8 caractères.';
            } elseif ($password !== $passwordConfirm) {
                $error = 'Les mots de passe ne correspondent pas.';
            } else {
                // Check if email already exists
                $existingUser = $entityManager->getRepository(User::class)->findOneBy(['email' => $email]);
                if ($existingUser) {
                    $error = 'Cette adresse email est déjà utilisée.';
                } else {
                    // Create user
                    $user = new User();
                    $user->setEmail($email);
                    $user->setNom($nom);
                    $user->setPrenom($prenom);
                    $user->setRoles([]);
                    $user->setIsValidated(false);

                    $hashedPassword = $passwordHasher->hashPassword($user, $password);
                    $user->setPassword($hashedPassword);

                    $entityManager->persist($user);
                    $entityManager->flush();

                    $this->activityLogger->logAuth(
                        'user_registered',
                        sprintf('Nouvel utilisateur inscrit: %s', $email),
                        'info',
                        [
                            'userId' => $user->getId(),
                            'email' => $email,
                            'nom' => $nom,
                            'prenom' => $prenom,
                        ]
                    );

                    $success = true;
                }
            }

            if ($error) {
                $this->activityLogger->logAuth(
                    'register_failed',
                    sprintf('Échec d\'inscription: %s', $error),
                    'warning',
                    ['email' => $email, 'error' => $error]
                );
            }
        }

        return $this->render('security/register.html.twig', [
            'error' => $error,
            'success' => $success,
        ]);
    }

    #[Route('/forgot-password', name: 'app_forgot_password', methods: ['GET', 'POST'])]
    public function forgotPassword(
        Request $request,
        EntityManagerInterface $entityManager,
        MailerInterface $mailer
    ): Response {
        if ($this->getUser()) {
            return $this->redirectToRoute('app_dashboard');
        }

        $error = null;
        $success = false;

        if ($request->isMethod('POST')) {
            $email = trim($request->request->get('email', ''));

            if (empty($email)) {
                $error = 'Veuillez entrer votre adresse email.';
            } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $error = 'L\'adresse email n\'est pas valide.';
            } else {
                $user = $entityManager->getRepository(User::class)->findOneBy(['email' => $email]);

                if ($user) {
                    // Generate reset token
                    $token = $user->generateResetToken();
                    $entityManager->flush();

                    // Generate reset URL
                    $resetUrl = $this->generateUrl('app_reset_password', [
                        'token' => $token
                    ], UrlGeneratorInterface::ABSOLUTE_URL);

                    // Send email
                    try {
                        $emailMessage = (new Email())
                            ->from('noreply@amissa.bj')
                            ->to($user->getEmail())
                            ->subject('Reinitialisation de votre mot de passe - Amissa')
                            ->html(sprintf(
                                '<h2>Reinitialisation de mot de passe</h2>
                                <p>Bonjour %s,</p>
                                <p>Vous avez demande la reinitialisation de votre mot de passe.</p>
                                <p>Cliquez sur le lien ci-dessous pour definir un nouveau mot de passe :</p>
                                <p><a href="%s">%s</a></p>
                                <p>Ce lien expire dans 1 heure.</p>
                                <p>Si vous n\'avez pas demande cette reinitialisation, ignorez cet email.</p>
                                <p>Cordialement,<br>L\'equipe Amissa</p>',
                                $user->getPrenom(),
                                $resetUrl,
                                $resetUrl
                            ));

                        $mailer->send($emailMessage);

                        $this->activityLogger->logAuth(
                            'password_reset_requested',
                            sprintf('Demande de reinitialisation pour: %s', $email),
                            'info',
                            ['email' => $email, 'userId' => $user->getId()]
                        );
                    } catch (\Exception $e) {
                        $this->activityLogger->logError(
                            'auth',
                            'password_reset_email_failed',
                            sprintf('Echec envoi email reset pour: %s', $email),
                            $e
                        );
                    }
                } else {
                    $this->activityLogger->logAuth(
                        'password_reset_unknown_email',
                        sprintf('Demande reset pour email inconnu: %s', $email),
                        'warning',
                        ['email' => $email]
                    );
                }

                // Always show success to prevent email enumeration
                $success = true;
            }
        }

        return $this->render('security/forgot_password.html.twig', [
            'error' => $error,
            'success' => $success,
        ]);
    }

    #[Route('/reset-password/{token}', name: 'app_reset_password', methods: ['GET', 'POST'])]
    public function resetPassword(
        string $token,
        Request $request,
        EntityManagerInterface $entityManager,
        UserPasswordHasherInterface $passwordHasher
    ): Response {
        if ($this->getUser()) {
            return $this->redirectToRoute('app_dashboard');
        }

        $user = $entityManager->getRepository(User::class)->findOneBy(['resetToken' => $token]);

        if (!$user || !$user->isResetTokenValid()) {
            $this->activityLogger->logAuth(
                'password_reset_invalid_token',
                'Token de reinitialisation invalide ou expire',
                'warning',
                ['token' => substr($token, 0, 10) . '...']
            );

            $this->addFlash('error', 'Le lien de reinitialisation est invalide ou a expire.');
            return $this->redirectToRoute('app_forgot_password');
        }

        $error = null;
        $success = false;

        if ($request->isMethod('POST')) {
            $password = $request->request->get('password', '');
            $passwordConfirm = $request->request->get('password_confirm', '');

            if (strlen($password) < 8) {
                $error = 'Le mot de passe doit contenir au moins 8 caracteres.';
            } elseif ($password !== $passwordConfirm) {
                $error = 'Les mots de passe ne correspondent pas.';
            } else {
                $hashedPassword = $passwordHasher->hashPassword($user, $password);
                $user->setPassword($hashedPassword);
                $user->clearResetToken();
                $entityManager->flush();

                $this->activityLogger->logAuth(
                    'password_reset_success',
                    sprintf('Mot de passe reinitialise pour: %s', $user->getEmail()),
                    'info',
                    ['userId' => $user->getId(), 'email' => $user->getEmail()]
                );

                $success = true;
            }
        }

        return $this->render('security/reset_password.html.twig', [
            'error' => $error,
            'success' => $success,
            'token' => $token,
        ]);
    }

    #[Route('/logout', name: 'app_logout')]
    public function logout(): void
    {
        throw new \LogicException('This method can be blank - it will be intercepted by the logout key on your firewall.');
    }
}
