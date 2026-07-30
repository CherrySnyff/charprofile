<?php

/**
 * Базовый публичный контроллер вкладок профиля игрока.
 *
 * Отвечает за:
 * - загрузку пользователя профиля и проверку canViewFullProfile (XenForo);
 * - видимость вкладок (группа-триггер + character_profile.view);
 * - создание строки xf_char_profile при необходимости;
 * - передачу флагов canManage* в шаблон.
 *
 * Все POST-контроллеры (Hero, ReputationLog, Backpack*) наследуют этот класс.
 */

namespace Enterum\CharacterProfile\Pub\Controller;

use Enterum\CharacterProfile\Service\PermissionGuard;
use Enterum\CharacterProfile\Service\ProfileInitializer;
use XF\Entity\User;
use XF\Mvc\ParameterBag;
use XF\Mvc\Reply\AbstractReply;
use XF\Pub\Controller\AbstractController;

abstract class AbstractProfileAction extends AbstractController
{
    /**
     * Права: сервис PermissionGuard для проверок и getOrCreateProfile.
     */
    protected function getPermissionGuard(): PermissionGuard
    {
        return $this->service('Enterum\CharacterProfile:PermissionGuard');
    }

    /**
     * Профиль: сервис ProfileInitializer (группа-триггер, ensureProfileRow).
     */
    protected function getProfileInitializer(): ProfileInitializer
    {
        return $this->service('Enterum\CharacterProfile:ProfileInitializer');
    }

    /**
     * Общее: загрузить владельца профиля; вкладки аддона должны быть доступны.
     */
    protected function loadProfileUser(ParameterBag $params): User
    {
        $user = $this->assertViewableProfileUser($params->user_id);
        $guard = $this->getPermissionGuard();

        if (!$guard->profileTabsVisibleFor($user)) {
            throw $this->exception($this->noPermission());
        }

        if ($this->getProfileInitializer()->userHasTriggerGroup($user)) {
            $guard->getOrCreateProfile($user);
        }

        return $user;
    }

    /**
     * Общее: пользователь существует и visitor может смотреть полный профиль XF.
     */
    protected function assertViewableProfileUser($userId): User
    {
        $userId = (int)$userId;
        if ($userId <= 0) {
            throw $this->exception($this->notFound(\XF::phrase('requested_member_not_found')));
        }

        /** @var User|null $user */
        $user = $this->em()->find('XF:User', $userId);
        if (!$user || !$user->canViewFullProfile()) {
            throw $this->exception($this->notFound(\XF::phrase('requested_member_not_found')));
        }

        return $user;
    }

    /**
     * UI: базовые параметры шаблона вкладки (user, profile, canManage*).
     */
    protected function buildProfileParams(User $user, string $tab): array
    {
        $visitor = \XF::visitor();
        $guard = $this->getPermissionGuard();
        $profile = $guard->getOrCreateProfile($user);

        return [
            'user' => $user,
            'profile' => $profile,
            'tab' => $tab,
            'cpCharProfileTab' => $tab,
            'canManageHero' => $guard->canManageHero($visitor, $user),
            'canManageHeroSupport' => $guard->canManageHeroSupport($visitor),
            'canManageReputation' => $guard->canManageReputation($visitor, $user),
            'canManageBackpack' => $guard->canManageBackpack($visitor, $user),
            'canManageBackpackOther' => $guard->canManageBackpackOther($visitor, $user),
            'canManageCharacterSheet' => $guard->canManageCharacterSheet($visitor, $user),
        ];
    }

    /**
     * UI: ответ view с параметрами вкладки + extra.
     */
    protected function profileView(User $user, string $tab, string $template, array $extra = []): AbstractReply
    {
        $viewParams = array_merge($this->buildProfileParams($user, $tab), $extra);

        return $this->view('Enterum\CharacterProfile:' . $template, $template, $viewParams);
    }
}
