<?php

namespace App\Security\Voter;

use App\Entity\Messe;
use App\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

class MesseVoter extends Voter
{
    public const VIEW = 'MESSE_VIEW';
    public const EDIT = 'MESSE_EDIT';
    public const CANCEL = 'MESSE_CANCEL';

    protected function supports(string $attribute, mixed $subject): bool
    {
        return in_array($attribute, [self::VIEW, self::EDIT, self::CANCEL])
            && $subject instanceof Messe;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $user = $token->getUser();
        if (!$user instanceof User) {
            return false;
        }

        /** @var Messe $messe */
        $messe = $subject;
        $paroisse = $messe->getParoisse();

        return match($attribute) {
            self::VIEW => $this->canView($messe, $user),
            self::EDIT => $this->canEdit($paroisse, $user),
            self::CANCEL => $this->canCancel($paroisse, $user),
            default => false,
        };
    }

    private function canView(Messe $messe, User $user): bool
    {
        $paroisse = $messe->getParoisse();

        if (in_array('ROLE_SUPER_USER', $user->getRoles())) {
            return true;
        }

        if (in_array('ROLE_ADMIN_DIOCESE', $user->getRoles())) {
            return $user->getDiocese() === $paroisse?->getDiocese();
        }

        if ($user->getParoisse() === $paroisse) {
            return true;
        }

        return $messe->isActive() && ($paroisse?->getDiocese()?->isActif() ?? false);
    }

    private function canEdit($paroisse, User $user): bool
    {
        if (in_array('ROLE_SUPER_USER', $user->getRoles())) {
            return true;
        }

        if (in_array('ROLE_ADMIN_DIOCESE', $user->getRoles())) {
            return $user->getDiocese() === $paroisse?->getDiocese();
        }

        if (in_array('ROLE_ADMIN_PAROISSE', $user->getRoles()) || in_array('ROLE_SECRETAIRE', $user->getRoles())) {
            return $user->getParoisse() === $paroisse;
        }

        return false;
    }

    private function canCancel($paroisse, User $user): bool
    {
        return $this->canEdit($paroisse, $user);
    }
}
