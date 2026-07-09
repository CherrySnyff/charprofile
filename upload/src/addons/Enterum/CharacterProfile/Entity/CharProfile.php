<?php

/**
 * Entity: основная строка профиля игрока (xf_char_profile), PK = user_id.
 * Кэш ОГ (hero_points_*), блок рюкзака «Прочее» (backpack_other_*), даты.
 * Связь User → XF:User.
 */

namespace Enterum\CharacterProfile\Entity;

use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Structure;

class CharProfile extends Entity
{
    /**
     * Профиль: структура таблицы xf_char_profile и связь с XF:User.
     */
    public static function getStructure(Structure $structure): Structure
    {
        $structure->table = 'xf_char_profile';
        $structure->shortName = 'Enterum\CharacterProfile:CharProfile';
        $structure->primaryKey = 'user_id';
        $structure->columns = [
            'user_id' => ['type' => self::UINT, 'required' => true],
            'is_initialized' => ['type' => self::UINT, 'default' => 0],
            'hero_points_cache' => ['type' => self::UINT, 'default' => 0],
            'hero_points_raw_sum' => ['type' => self::INT, 'default' => 0],
            'created_date' => ['type' => self::UINT, 'default' => 0],
            'last_update' => ['type' => self::UINT, 'default' => 0],
            'backpack_other_bbcode' => ['type' => self::STR, 'default' => ''],
            'backpack_other_rendered' => ['type' => self::STR, 'default' => ''],
            'backpack_other_update_date' => ['type' => self::UINT, 'default' => 0],
            'backpack_other_update_user_id' => ['type' => self::UINT, 'default' => 0],
        ];
        $structure->relations = [
            'User' => [
                'entity' => 'XF:User',
                'type' => self::TO_ONE,
                'conditions' => 'user_id',
                'primary' => true,
            ],
        ];

        return $structure;
    }
}
