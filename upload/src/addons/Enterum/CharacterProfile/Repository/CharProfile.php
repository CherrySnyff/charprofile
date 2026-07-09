<?php

/**
 * Repository: доступ к xf_char_profile (основная строка профиля).
 * Finder по user_id для ProfileInitializer / PermissionGuard.
 */

namespace Enterum\CharacterProfile\Repository;

use XF\Mvc\Entity\Repository;

class CharProfile extends Repository
{
    /**
     * Профиль: finder строки xf_char_profile по user_id.
     */
    public function findProfileByUserId(int $userId)
    {
        return $this->finder('Enterum\CharacterProfile:CharProfile')
            ->where('user_id', $userId);
    }
}
