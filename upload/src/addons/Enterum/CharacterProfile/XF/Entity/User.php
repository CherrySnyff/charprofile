<?php

/**
 * Расширение XF\Entity\User: видимость вкладок профиля игрока в шаблонах.
 * Делегирует в PermissionGuard::profileTabsVisibleFor (группа-триггер + view).
 */

namespace Enterum\CharacterProfile\XF\Entity;

class User extends XFCP_User
{
    /**
     * Права / UI: можно ли показывать вкладки аддона на этом профиле.
     */
    public function canViewCharacterProfileTabs(): bool
    {
        /** @var \Enterum\CharacterProfile\Service\PermissionGuard $guard */
        $guard = $this->app()->service('Enterum\CharacterProfile:PermissionGuard');

        return $guard->profileTabsVisibleFor($this);
    }
}
