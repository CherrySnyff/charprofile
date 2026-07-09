<?php

/**
 * Entity: строка аудита ACP (xf_char_profile_action_log).
 * content_type: hero|reputation|backpack_*; old_data/new_data — JSON-снимки.
 * Хелперы getOldDataArray / getNewDataArray для декодирования.
 */

namespace Enterum\CharacterProfile\Entity;

use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Structure;

class CharProfileActionLog extends Entity
{
    /**
     * ACP logs: декодировать old_data из JSON в массив.
     */
    public function getOldDataArray(): ?array
    {
        $raw = $this->old_data;
        if ($raw === null || $raw === '') {
            return null;
        }

        $decoded = json_decode($raw, true);

        return is_array($decoded) ? $decoded : null;
    }

    /**
     * ACP logs: декодировать new_data из JSON в массив.
     */
    public function getNewDataArray(): ?array
    {
        $raw = $this->new_data;
        if ($raw === null || $raw === '') {
            return null;
        }

        $decoded = json_decode($raw, true);

        return is_array($decoded) ? $decoded : null;
    }

    /**
     * ACP logs: структура таблицы xf_char_profile_action_log.
     */
    public static function getStructure(Structure $structure): Structure
    {
        $structure->table = 'xf_char_profile_action_log';
        $structure->shortName = 'Enterum\CharacterProfile:CharProfileActionLog';
        $structure->primaryKey = 'action_log_id';
        $structure->columns = [
            'action_log_id' => ['type' => self::UINT, 'autoIncrement' => true],
            'target_user_id' => ['type' => self::UINT, 'required' => true],
            'actor_user_id' => ['type' => self::UINT, 'default' => 0],
            'content_type' => ['type' => self::STR, 'maxLength' => 50, 'default' => ''],
            'content_id' => ['type' => self::UINT, 'default' => 0],
            'action' => ['type' => self::STR, 'maxLength' => 20, 'default' => ''],
            'old_data' => ['type' => self::STR, 'default' => null, 'nullable' => true],
            'new_data' => ['type' => self::STR, 'default' => null, 'nullable' => true],
            'log_date' => ['type' => self::UINT, 'default' => 0],
        ];

        return $structure;
    }
}
