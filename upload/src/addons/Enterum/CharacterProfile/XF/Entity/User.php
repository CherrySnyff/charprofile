<?php

/**
 * Расширение XF\Entity\User: видимость вкладок профиля игрока в шаблонах.
 * Делегирует в PermissionGuard::profileTabsVisibleFor (группа-триггер + view).
 * Очки героизма для сайдбара сообщений считаются live по журналу ОГ.
 */

namespace Enterum\CharacterProfile\XF\Entity;

use XF\Mvc\Entity\Structure;

class User extends XFCP_User
{
    /** @var array<int, int> Баланс ОГ в рамках одного HTTP-запроса. */
    protected static $heroPointsByUserId = [];

    /**
     * @param Structure $structure
     * @return Structure
     */
    public static function getStructure(Structure $structure)
    {
        $structure = parent::getStructure($structure);

        // Нужно, чтобы в шаблонах работало {$user.character_hero_points}.
        $structure->getters['character_hero_points'] = true;

        return $structure;
    }

    /**
     * Права / UI: можно ли показывать вкладки аддона на этом профиле.
     */
    public function canViewCharacterProfileTabs(): bool
    {
        /** @var \Enterum\CharacterProfile\Service\PermissionGuard $guard */
        $guard = $this->app()->service('Enterum\CharacterProfile:PermissionGuard');

        return $guard->profileTabsVisibleFor($this);
    }

    /**
     * Сбросить memo баланса ОГ (после add/edit/delete журнала).
     */
    public static function clearCharacterHeroPointsMemo(?int $userId = null): void
    {
        if ($userId === null) {
            self::$heroPointsByUserId = [];
            return;
        }

        unset(self::$heroPointsByUserId[$userId]);
    }

    /**
     * ОГ / UI: актуальный баланс по журналу (не hero_points_cache).
     * Прямой SQL ASC по логам — без entity-кэша finder'а.
     */
    public function getCharacterHeroPoints(): int
    {
        $userId = (int)$this->user_id;
        if (!$userId) {
            return 0;
        }

        if (array_key_exists($userId, self::$heroPointsByUserId)) {
            return self::$heroPointsByUserId[$userId];
        }

        /** @var \Enterum\CharacterProfile\Service\Hero\HeroPointManager $heroManager */
        $heroManager = $this->app()->service('Enterum\CharacterProfile:Hero\HeroPointManager');
        $points = $heroManager->getLiveBalance($userId);

        self::$heroPointsByUserId[$userId] = $points;

        return $points;
    }
}
