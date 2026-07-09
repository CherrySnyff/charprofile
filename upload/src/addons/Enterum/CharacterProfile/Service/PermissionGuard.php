<?php

/**
 * Единая точка проверки прав аддона «Профиль игрока».
 *
 * Используется контроллерами (флаги для UI) и сервисами (жёсткая проверка перед save).
 * Права: view, manageHero, manageHeroSupport, manageReputation, manageBackpack,
 * manageBackpackOwn, manageCharacterSheet*, viewLogs.
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
     * Права / ОГ: можно ли управлять очками геройства (manageHero).
     */
    public function canManageHero(User $visitor): bool
    {
        return $visitor->user_id && $visitor->hasPermission('character_profile', 'manageHero');
    }

    /**
     * Права / ОГ: можно ли ставить записи «за поддержку» (manageHeroSupport).
     */
    public function canManageHeroSupport(User $visitor): bool
    {
        return $visitor->user_id && $visitor->hasPermission('character_profile', 'manageHeroSupport');
    }

    /**
     * Права / репутация: можно ли CRUD журнала репутации (manageReputation).
     */
    public function canManageReputation(User $visitor): bool
    {
        return $visitor->user_id && $visitor->hasPermission('character_profile', 'manageReputation');
    }

    /**
     * Права / рюкзак: полное управление activity/crafted (manageBackpack).
     */
    public function canManageBackpack(User $visitor): bool
    {
        return $visitor->user_id && $visitor->hasPermission('character_profile', 'manageBackpack');
    }

    /**
     * Права / рюкзак «Прочее»: manageBackpack или свой профиль + manageBackpackOwn.
     */
    public function canManageBackpackOther(User $visitor, User $profileUser): bool
    {
        if (!$visitor->user_id) {
            return false;
        }

        if ($visitor->hasPermission('character_profile', 'manageBackpack')) {
            return true;
        }

        return (int)$visitor->user_id === (int)$profileUser->user_id
            && $visitor->hasPermission('character_profile', 'manageBackpackOwn');
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
}
