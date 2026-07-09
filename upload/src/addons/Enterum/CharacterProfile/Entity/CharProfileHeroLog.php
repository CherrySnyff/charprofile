<?php

/**
 * Entity: одна запись журнала ОГ (xf_char_profile_hero_log).
 * gain/loss/support, amount 1–3, overflow/burned после пересчёта HeroPointManager.
 * Валидация: amount, source_url (https); support очищает источник в _preSave.
 */

namespace Enterum\CharacterProfile\Entity;

use XF;
use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Structure;

class CharProfileHeroLog extends Entity
{
    /**
     * ОГ: amount должен быть 1..3 (в БД всегда положительный).
     */
    protected function verifyAmount(&$amount): bool
    {
        $amount = abs((int)$amount);
        if ($amount < 1 || $amount > 3) {
            $this->error(XF::phrase('enterum_char_profile_hero_amount_invalid'), 'amount');
            return false;
        }

        return true;
    }

    /**
     * ОГ: source_url пустой или валидный https://.
     */
    protected function verifySourceUrl(&$url): bool
    {
        $url = trim((string)$url);
        if ($url === '') {
            return true;
        }

        if (strpos($url, 'https://') !== 0 || !filter_var($url, FILTER_VALIDATE_URL)) {
            $this->error(XF::phrase('enterum_char_profile_url_https_required'), 'source_url');
            return false;
        }

        return true;
    }

    /**
     * ОГ: перед save нормализовать support / gain / loss.
     */
    protected function _preSave(): void
    {
        if ($this->operation_type === 'support' || $this->is_support) {
            $this->operation_type = 'support';
            $this->is_support = 1;
            $this->source_url = '';
            $this->source_title = '';
        } else {
            $this->is_support = 0;
            if ($this->operation_type === 'loss') {
                // amount в БД остаётся положительным; знак задаёт operation_type.
            } elseif ($this->operation_type !== 'gain') {
                $this->operation_type = 'gain';
            }
        }
    }

    /**
     * ОГ: структура таблицы xf_char_profile_hero_log.
     */
    public static function getStructure(Structure $structure): Structure
    {
        $structure->table = 'xf_char_profile_hero_log';
        $structure->shortName = 'Enterum\CharacterProfile:CharProfileHeroLog';
        $structure->primaryKey = 'hero_log_id';
        $structure->columns = [
            'hero_log_id' => ['type' => self::UINT, 'autoIncrement' => true],
            'user_id' => ['type' => self::UINT, 'required' => true],
            'event_date' => ['type' => self::STR, 'maxLength' => 10, 'default' => null, 'nullable' => true],
            'operation_type' => ['type' => self::STR, 'default' => 'gain', 'allowedValues' => ['gain', 'loss', 'support']],
            'amount' => ['type' => self::UINT, 'required' => true, 'min' => 1, 'max' => 3, 'verify' => 'verifyAmount'],
            'source_url' => ['type' => self::STR, 'maxLength' => 500, 'default' => '', 'verify' => 'verifySourceUrl'],
            'source_title' => ['type' => self::STR, 'maxLength' => 255, 'default' => ''],
            'is_support' => ['type' => self::UINT, 'default' => 0],
            'is_overflow' => ['type' => self::UINT, 'default' => 0],
            'burned_amount' => ['type' => self::UINT, 'default' => 0],
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
