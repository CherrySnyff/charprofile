<?php

/**
 * Repository: созданные предметы (xf_char_profile_backpack_crafted_item).
 * Сортировка: display_order, затем новые сверху.
 */

namespace Enterum\CharacterProfile\Repository;

use XF\Mvc\Entity\Repository;

class CharProfileBackpackCraftedItem extends Repository
{
    /**
     * Рюкзак / crafted: finder предметов пользователя для списка/пагинации.
     */
    public function findItemsForUser(int $userId)
    {
        return $this->finder('Enterum\CharacterProfile:CharProfileBackpackCraftedItem')
            ->where('user_id', $userId)
            ->order('display_order')
            ->order('created_date', 'DESC')
            ->order('crafted_item_id', 'DESC');
    }
}
