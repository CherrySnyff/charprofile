<?php

/**
 * Repository: предметы за активности (xf_char_profile_backpack_activity_item).
 * Сортировка: display_order, затем новые сверху.
 */

namespace Enterum\CharacterProfile\Repository;

use XF\Mvc\Entity\Repository;

class CharProfileBackpackActivityItem extends Repository
{
    /**
     * Рюкзак / activity: finder предметов пользователя для списка/пагинации.
     */
    public function findItemsForUser(int $userId)
    {
        return $this->finder('Enterum\CharacterProfile:CharProfileBackpackActivityItem')
            ->where('user_id', $userId)
            ->order('display_order')
            ->order('created_date', 'DESC')
            ->order('activity_item_id', 'DESC');
    }
}
