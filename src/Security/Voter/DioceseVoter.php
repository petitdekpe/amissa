<?php

namespace App\Security\Voter;

use App\Entity\Diocese;
use App\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

class DioceseVoter extends Voter
{
    public const VIEW = 'DIOCESE_VIEW';
    public const EDIT = 'DIOCESE_EDIT';
    public const MANAGE = 'DIOCESE_MANAGE';
    public const TOGGLE_STATUS = 'DIOCESE_TOGGLE_STATUS';

    protected function supports(string $attribute, mixed $subject): bool
    {
        return in_array($attribute, [self::VIEW, self::EDIT, self::MANAGE, self::TOGGLE_STATUS])
            && $subject instanceof Diocese;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $user = $token->getUser();
        if (!$user instanceof User) {
            return false;
        }

        /** @var Diocese $diocese */
        $diocese = $subject;

        return match($attribute) {
            self::VIEW => $this->canView($diocese, $user),
            self::EDIT => $this->canEdit($diocese, $user),
            self::MANAGE => $this->canManage($diocese, $user),
            self::TOGGLE_STATUS => $this->canToggleStatus($user),
            default => false,
        };
    }

    private function canView(Diocese $diocese, User $user): bool
    {
        if (in_array('ROLE_SUPER_USER', $user->getRoles())) {
            return true;
        }

        if (in_array('ROLE_ADMIN_DIOCESE', $user->getRoles())) {
            return $user->getDiocese() === $diocese;
        }

        return $diocese->isActif();
    }

    private function canEdit(Diocese $diocese, User $user): bool
    {
        if (in_array('ROLE_SUPER_USER', $user->getRoles())) {
            return true;
        }

        if (in_array('ROLE_ADMIN_DIOCESE', $user->getRoles())) {
            return $user->getDiocese() === $diocese;
        }

        return false;
    }

    private function canManage(Diocese $diocese, User $user): bool
    {
        if (in_array('ROLE_SUPER_USER', $user->getRoles())) {
            return true;
        }

        if (in_array('ROLE_ADMIN_DIOCESE', $user->getRoles())) {
            return $user->getDiocese() === $diocese;
        }

        return false;
    }

    private function canToggleStatus(User $user): bool
    {
        return in_array('ROLE_SUPER_USER', $user->getRoles());
    }
}
