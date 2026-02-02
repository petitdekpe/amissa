<?php

namespace App\Security\Voter;

use App\Entity\Paroisse;
use App\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

class ParoisseVoter extends Voter
{
    public const VIEW = 'PAROISSE_VIEW';
    public const EDIT = 'PAROISSE_EDIT';
    public const MANAGE_FINANCES = 'PAROISSE_MANAGE_FINANCES';
    public const MANAGE_MESSES = 'PAROISSE_MANAGE_MESSES';

    protected function supports(string $attribute, mixed $subject): bool
    {
        return in_array($attribute, [self::VIEW, self::EDIT, self::MANAGE_FINANCES, self::MANAGE_MESSES])
            && $subject instanceof Paroisse;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $user = $token->getUser();
        if (!$user instanceof User) {
            return false;
        }

        /** @var Paroisse $paroisse */
        $paroisse = $subject;

        return match($attribute) {
            self::VIEW => $this->canView($paroisse, $user),
            self::EDIT => $this->canEdit($paroisse, $user),
            self::MANAGE_FINANCES => $this->canManageFinances($paroisse, $user),
            self::MANAGE_MESSES => $this->canManageMesses($paroisse, $user),
            default => false,
        };
    }

    private function canView(Paroisse $paroisse, User $user): bool
    {
        if (in_array('ROLE_SUPER_USER', $user->getRoles())) {
            return true;
        }

        if (in_array('ROLE_ADMIN_DIOCESE', $user->getRoles())) {
            return $user->getDiocese() === $paroisse->getDiocese();
        }

        if ($user->getParoisse() === $paroisse) {
            return true;
        }

        return $paroisse->getDiocese()?->isActif() ?? false;
    }

    private function canEdit(Paroisse $paroisse, User $user): bool
    {
        if (in_array('ROLE_SUPER_USER', $user->getRoles())) {
            return true;
        }

        if (in_array('ROLE_ADMIN_DIOCESE', $user->getRoles())) {
            return $user->getDiocese() === $paroisse->getDiocese();
        }

        if (in_array('ROLE_ADMIN_PAROISSE', $user->getRoles())) {
            return $user->getParoisse() === $paroisse;
        }

        return false;
    }

    private function canManageFinances(Paroisse $paroisse, User $user): bool
    {
        if (in_array('ROLE_SUPER_USER', $user->getRoles())) {
            return true;
        }

        if (in_array('ROLE_ADMIN_DIOCESE', $user->getRoles())) {
            return $user->getDiocese() === $paroisse->getDiocese();
        }

        if (in_array('ROLE_ADMIN_PAROISSE', $user->getRoles())) {
            return $user->getParoisse() === $paroisse;
        }

        return false;
    }

    private function canManageMesses(Paroisse $paroisse, User $user): bool
    {
        if (in_array('ROLE_SUPER_USER', $user->getRoles())) {
            return true;
        }

        if (in_array('ROLE_ADMIN_DIOCESE', $user->getRoles())) {
            return $user->getDiocese() === $paroisse->getDiocese();
        }

        if (in_array('ROLE_ADMIN_PAROISSE', $user->getRoles()) || in_array('ROLE_SECRETAIRE', $user->getRoles())) {
            return $user->getParoisse() === $paroisse;
        }

        return false;
    }
}
