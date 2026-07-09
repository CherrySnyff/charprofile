<?php

/**
 * Entity: запись журнала репутации (xf_char_profile_reputation_log).
 * Регион aramidis|korzus|union; amount signed (gain/loss по знаку в _preSave).
 * Валидация: character_name, faction_name, source_url (https обязателен).
 */

namespace Enterum\CharacterProfile\Entity;

use XF;
use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Structure;

class CharProfileReputationLog extends Entity
{
    /**
     * Репутация: имя персонажа обязательно.
     */
    protected function verifyCharacterName(&$name): bool
    {
        $name = trim((string)$name);
        if ($name === '') {
            $this->error(XF::phrase('enterum_char_profile_rep_character_required'), 'character_name');
            return false;
        }

        return true;
    }

    /**
     * Репутация: название фракции обязательно.
     */
    protected function verifyFactionName(&$name): bool
    {
        $name = trim((string)$name);
        if ($name === '') {
            $this->error(XF::phrase('enterum_char_profile_rep_faction_required'), 'faction_name');
            return false;
        }

        return true;
    }

    /**
     * Репутация: source_url обязателен и должен быть https://.
     */
    protected function verifySourceUrl(&$url): bool
    {
        $url = trim((string)$url);
        if ($url === '') {
            $this->error(XF::phrase('enterum_char_profile_url_https_required'), 'source_url');
            return false;
        }

        if (strpos($url, 'https://') !== 0 || !filter_var($url, FILTER_VALIDATE_URL)) {
            $this->error(XF::phrase('enterum_char_profile_url_https_required'), 'source_url');
            return false;
        }

        return true;
    }

    /**
     * Репутация: amount ≠ 0 (отрицательные допустимы).
     */
    protected function verifyAmount(&$amount): bool
    {
        $amount = (int)$amount;
        if ($amount === 0) {
            $this->error(XF::phrase('enterum_char_profile_rep_amount_invalid'), 'amount');
            return false;
        }

        return true;
    }

    /**
     * Репутация: выставить operation_type по знаку amount; дефолт source_title.
     */
    protected function _preSave(): void
    {
        $amount = (int)$this->amount;
        if ($amount > 0) {
            $this->operation_type = 'gain';
        } elseif ($amount < 0) {
            $this->operation_type = 'loss';
        }

        $this->source_title = trim((string)$this->source_title);
        if ($this->source_title === '') {
            $this->source_title = 'Ссылка на источник';
        }
    }

    /**
     * Репутация: структура таблицы xf_char_profile_reputation_log.
     */
    public static function getStructure(Structure $structure): Structure
    {
        $structure->table = 'xf_char_profile_reputation_log';
        $structure->shortName = 'Enterum\CharacterProfile:CharProfileReputationLog';
        $structure->primaryKey = 'reputation_log_id';
        $structure->columns = [
            'reputation_log_id' => ['type' => self::UINT, 'autoIncrement' => true],
            'user_id' => ['type' => self::UINT, 'required' => true],
            'region_key' => ['type' => self::STR, 'allowedValues' => ['aramidis', 'korzus', 'union'], 'default' => 'aramidis'],
            'character_name' => ['type' => self::STR, 'maxLength' => 100, 'default' => '', 'verify' => 'verifyCharacterName'],
            'faction_name' => ['type' => self::STR, 'maxLength' => 150, 'default' => '', 'verify' => 'verifyFactionName'],
            'amount' => ['type' => self::INT, 'default' => 0, 'verify' => 'verifyAmount'],
            'operation_type' => ['type' => self::STR, 'allowedValues' => ['gain', 'loss'], 'default' => 'gain'],
            'source_url' => ['type' => self::STR, 'maxLength' => 500, 'default' => '', 'verify' => 'verifySourceUrl'],
            'source_title' => ['type' => self::STR, 'maxLength' => 255, 'default' => 'Ссылка на источник'],
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
