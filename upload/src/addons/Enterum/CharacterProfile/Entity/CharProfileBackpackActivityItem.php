<?php

/**
 * Entity: предмет за активность в рюкзаке (xf_char_profile_backpack_activity_item).
 * Редкость common|uncommon|rare|unique; URL — https или пусто.
 */

namespace Enterum\CharacterProfile\Entity;

use XF;
use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Structure;

class CharProfileBackpackActivityItem extends Entity
{
    /**
     * Рюкзак / activity: общая проверка URL-поля (https или пусто).
     */
    protected function verifyUrlField(string $field, &$url, bool $required = false): bool
    {
        $url = trim((string)$url);
        if ($url === '') {
            if ($required) {
                $this->error(XF::phrase('enterum_char_profile_url_https_required'), $field);
                return false;
            }

            return true;
        }

        if (strpos($url, 'https://') !== 0 || !filter_var($url, FILTER_VALIDATE_URL)) {
            $this->error(XF::phrase('enterum_char_profile_url_https_required'), $field);
            return false;
        }

        return true;
    }

    /**
     * Рюкзак / activity: название предмета обязательно.
     */
    protected function verifyItemName(&$name): bool
    {
        $name = trim((string)$name);
        if ($name === '') {
            $this->error(XF::phrase('enterum_char_profile_bp_item_name_required'), 'item_name');
            return false;
        }

        return true;
    }

    /**
     * Рюкзак / activity: причина получения обязательна.
     */
    protected function verifyReason(&$reason): bool
    {
        $reason = trim((string)$reason);
        if ($reason === '') {
            $this->error(XF::phrase('enterum_char_profile_bp_reason_required'), 'reason');
            return false;
        }

        return true;
    }

    /**
     * Рюкзак / activity: ссылка на предмет (опционально, https).
     */
    protected function verifyItemUrl(&$url): bool
    {
        return $this->verifyUrlField('item_url', $url);
    }

    /**
     * Рюкзак / activity: ссылка на источник (опционально, https).
     */
    protected function verifySourceUrl(&$url): bool
    {
        return $this->verifyUrlField('source_url', $url);
    }

    /**
     * Рюкзак / activity: структура таблицы xf_char_profile_backpack_activity_item.
     */
    public static function getStructure(Structure $structure): Structure
    {
        $structure->table = 'xf_char_profile_backpack_activity_item';
        $structure->shortName = 'Enterum\CharacterProfile:CharProfileBackpackActivityItem';
        $structure->primaryKey = 'activity_item_id';
        $structure->columns = [
            'activity_item_id' => ['type' => self::UINT, 'autoIncrement' => true],
            'user_id' => ['type' => self::UINT, 'required' => true],
            'item_name' => ['type' => self::STR, 'maxLength' => 255, 'default' => '', 'verify' => 'verifyItemName'],
            'item_url' => ['type' => self::STR, 'maxLength' => 500, 'default' => '', 'verify' => 'verifyItemUrl'],
            'item_description' => ['type' => self::STR, 'default' => ''],
            'item_type' => ['type' => self::STR, 'maxLength' => 100, 'default' => ''],
            'item_level' => ['type' => self::UINT, 'max' => 65535, 'default' => 0],
            'rarity_key' => ['type' => self::STR, 'allowedValues' => ['common', 'uncommon', 'rare', 'unique'], 'default' => 'common'],
            'source_url' => ['type' => self::STR, 'maxLength' => 500, 'default' => '', 'verify' => 'verifySourceUrl'],
            'source_title' => ['type' => self::STR, 'maxLength' => 255, 'default' => ''],
            'reason' => ['type' => self::STR, 'maxLength' => 255, 'default' => '', 'verify' => 'verifyReason'],
            'quantity' => ['type' => self::UINT, 'default' => 1],
            'note' => ['type' => self::STR, 'default' => ''],
            'display_order' => ['type' => self::UINT, 'default' => 0],
            'created_by_user_id' => ['type' => self::UINT, 'default' => 0],
            'created_date' => ['type' => self::UINT, 'default' => 0],
            'last_edit_user_id' => ['type' => self::UINT, 'default' => 0],
            'last_edit_date' => ['type' => self::UINT, 'default' => 0],
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
