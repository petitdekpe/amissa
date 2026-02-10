<?php

namespace App\EventSubscriber;

use App\Entity\User;
use App\Service\ActivityLoggerService;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;
use Symfony\Component\Security\Http\Event\LogoutEvent;

class AuthenticationSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private ActivityLoggerService $activityLogger
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            LoginSuccessEvent::class => 'onLoginSuccess',
            LogoutEvent::class => 'onLogout',
        ];
    }

    public function onLoginSuccess(LoginSuccessEvent $event): void
    {
        $user = $event->getUser();

        if ($user instanceof User) {
            $this->activityLogger->logAuth(
                'login_success',
                sprintf('Connexion réussie: %s', $user->getEmail()),
                'info',
                [
                    'userId' => $user->getId(),
                    'email' => $user->getEmail(),
                    'roles' => $user->getRoles(),
                    'dioceseId' => $user->getDiocese()?->getId(),
                    'paroisseId' => $user->getParoisse()?->getId(),
                ]
            );
        }
    }

    public function onLogout(LogoutEvent $event): void
    {
        $token = $event->getToken();
        $user = $token?->getUser();

        if ($user instanceof User) {
            $this->activityLogger->logAuth(
                'logout',
                sprintf('Déconnexion: %s', $user->getEmail()),
                'info',
                [
                    'userId' => $user->getId(),
                    'email' => $user->getEmail(),
                ]
            );
        }
    }
}
