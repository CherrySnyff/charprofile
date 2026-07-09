<?php

/**
 * Entity: созданный предмет в рюкзаке (xf_char_profile_backpack_crafted_item).
 * Обязательны: название, тип, автор, item_url и request_url (https).
 */

namespace Enterum\CharacterProfile\Entity;

use XF;
use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Structure;

class CharProfileBackpackCraftedItem extends Entity
{
    /**
     * Рюкзак / crafted: URL обязателен и должен быть https://.
     */
    protected function verifyUrlField(string $field, &$url): bool
    {
        $url = trim((string)$url);
        if ($url === '') {
            $this->error(XF::phrase('enterum_char_profile_url_https_required'), $field);
            return false;
        }

        if (strpos($url, 'https://') !== 0 || !filter_var($url, FILTER_VALIDATE_URL)) {
            $this->error(XF::phrase('enterum_char_profile_url_https_required'), $field);
            return false;
        }

        return true;
    }

    /**
     * Рюкзак / crafted: название предмета обязательно.
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
     * Рюкзак / crafted: тип предмета обязателен.
     */
    protected function verifyItemType(&$type): bool
    {
        $type = trim((string)$type);
        if ($type === '') {
            $this->error(XF::phrase('enterum_char_profile_bp_type_required'), 'item_type');
            return false;
        }

        return true;
    }

    /**
     * Рюкзак / crafted: автор (@ник) обязателен.
     */
    protected function verifyAuthorName(&$name): bool
    {
        $name = trim((string)$name);
        if ($name === '') {
            $this->error(XF::phrase('enterum_char_profile_bp_author_required'), 'author_name');
            return false;
        }

        return true;
    }

    /**
     * Рюкзак / crafted: ссылка на предмет (https).
     */
    protected function verifyItemUrl(&$url): bool
    {
        return $this->verifyUrlField('item_url', $url);
    }

    /**
     * Рюкзак / crafted: ссылка на заявку (https).
     */
    protected function verifyRequestUrl(&$url): bool
    {
        return $this->verifyUrlField('request_url', $url);
    }

    /**
     * Рюкзак / crafted: структура таблицы xf_char_profile_backpack_crafted_item.
     */
    public static function getStructure(Structure $structure): Structure
    {
        $structure->table = 'xf_char_profile_backpack_crafted_item';
        $structure->shortName = 'Enterum\CharacterProfile:CharProfileBackpackCraftedItem';
        $structure->primaryKey = 'crafted_item_id';
        $structure->columns = [
            'crafted_item_id' => ['type' => self::UINT, 'autoIncrement' => true],
            'user_id' => ['type' => self::UINT, 'required' => true],
            'item_name' => ['type' => self::STR, 'maxLength' => 255, 'default' => '', 'verify' => 'verifyItemName'],
            'item_url' => ['type' => self::STR, 'maxLength' => 500, 'default' => '', 'verify' => 'verifyItemUrl'],
            'item_type' => ['type' => self::STR, 'maxLength' => 100, 'default' => '', 'verify' => 'verifyItemType'],
            'item_level' => ['type' => self::UINT, 'max' => 65535, 'default' => 0],
            'request_url' => ['type' => self::STR, 'maxLength' => 500, 'default' => '', 'verify' => 'verifyRequestUrl'],
            'author_name' => ['type' => self::STR, 'maxLength' => 100, 'default' => '', 'verify' => 'verifyAuthorName'],
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
