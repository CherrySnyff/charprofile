<?php

/**
 * Единая точка проверки прав аддона «Профиль игрока».
 *
 * Используется контроллерами (флаги для UI) и сервисами (жёсткая проверка перед save).
 * Права «любой профиль»: manageHero, manageReputation, manageBackpack.
 * Права «только свой»: manageHeroOwn, manageReputationOwn, manageBackpackOwn.
 * Не дублируйте hasPermission в других местах — вызывайте методы этого класса.
 */

namespace Enterum\CharacterProfile\Service;

use Enterum\CharacterProfile\Entity\CharProfile;
use XF\Entity\User;
use XF\Service\AbstractService;

class PermissionGuard extends AbstractService
{
    /** @var ProfileInitializer */
    protected $initializer;

    /**
     * Права: инициализация сервиса ProfileInitializer.
     */
    public function __construct(\XF\App $app)
    {
        parent::__construct($app);
        $this->initializer = $app->service('Enterum\CharacterProfile:ProfileInitializer');
    }

    /**
     * Права / UI: видны ли вкладки профиля (группа-триггер + character_profile.view).
     */
    public function profileTabsVisibleFor(User $profileUser, ?User $visitor = null): bool
    {
        $visitor = $visitor ?: \XF::visitor();

        if (!$this->initializer->userHasTriggerGroup($profileUser)) {
            return false;
        }

        return $visitor->hasPermission('character_profile', 'view');
    }

    /**
     * Права / ОГ: manageHero (любой) или manageHeroOwn (только свой профиль).
     */
    public function canManageHero(User $visitor, User $profileUser): bool
    {
        return $this->canManageForProfile($visitor, $profileUser, 'manageHero', 'manageHeroOwn');
    }

    /**
     * Права / ОГ: можно ли ставить записи «за поддержку» (manageHeroSupport).
     */
    public function canManageHeroSupport(User $visitor): bool
    {
        return $visitor->user_id && $visitor->hasPermission('character_profile', 'manageHeroSupport');
    }

    /**
     * Права / репутация: manageReputation (любой) или manageReputationOwn (только свой).
     */
    public function canManageReputation(User $visitor, User $profileUser): bool
    {
        return $this->canManageForProfile($visitor, $profileUser, 'manageReputation', 'manageReputationOwn');
    }

    /**
     * Права / рюкзак: manageBackpack (любой) или manageBackpackOwn (только свой).
     */
    public function canManageBackpack(User $visitor, User $profileUser): bool
    {
        return $this->canManageForProfile($visitor, $profileUser, 'manageBackpack', 'manageBackpackOwn');
    }

    /**
     * Права / рюкзак «Прочее»: то же, что canManageBackpack (все подвкладки рюкзака).
     */
    public function canManageBackpackOther(User $visitor, User $profileUser): bool
    {
        return $this->canManageBackpack($visitor, $profileUser);
    }

    /**
     * Права / лист персонажа (v2): manageCharacterSheet или свой + manageCharacterSheetOwn.
     */
    public function canManageCharacterSheet(User $visitor, User $profileUser): bool
    {
        if (!$visitor->user_id) {
            return false;
        }

        if ($visitor->hasPermission('character_profile', 'manageCharacterSheet')) {
            return true;
        }

        return $visitor->user_id === $profileUser->user_id
            && $visitor->hasPermission('character_profile', 'manageCharacterSheetOwn');
    }

    /**
     * Права / ACP: просмотр логов действий (viewLogs).
     */
    public function canViewLogs(User $visitor): bool
    {
        return $visitor->user_id && $visitor->hasPermission('character_profile', 'viewLogs');
    }

    /**
     * Профиль: найти или создать строку xf_char_profile для пользователя.
     */
    public function getOrCreateProfile(User $user): CharProfile
    {
        /** @var CharProfile|null $profile */
        $profile = $this->em()->find('Enterum\CharacterProfile:CharProfile', $user->user_id);
        if ($profile) {
            return $profile;
        }

        $this->initializer->ensureProfileRow($user->user_id);

        /** @var CharProfile|null $profile */
        $profile = $this->em()->find('Enterum\CharacterProfile:CharProfile', $user->user_id);
        if (!$profile) {
            throw new \LogicException('Unable to initialize character profile for user ' . $user->user_id);
        }

        return $profile;
    }

    /**
     * Общая логика: право на любой профиль или на свой + *Own.
     */
    protected function canManageForProfile(
        User $visitor,
        User $profileUser,
        string $anyPermission,
        string $ownPermission
    ): bool {
        if (!$visitor->user_id) {
            return false;
        }

        if ($visitor->hasPermission('character_profile', $anyPermission)) {
            return true;
        }

        return (int)$visitor->user_id === (int)$profileUser->user_id
            && $visitor->hasPermission('character_profile', $ownPermission);
    }
}
