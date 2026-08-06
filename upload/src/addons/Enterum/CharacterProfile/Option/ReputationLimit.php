<?php

/**
 * ACP-опция charProfileRepLimit («Ограничение репутации»).
 * При сохранении «трогает» все строки xf_char_profile, чтобы UI сразу отразил новый режим.
 * Сами суммы фракций в БД не меняются — ограничение только на отображении.
 */

namespace Enterum\CharacterProfile\Option;

class ReputationLimit
{
    /**
     * @param mixed $value
     */
    public static function verifyOption(&$value, \XF\Entity\Option $option): bool
    {
        $value = !empty($value) ? 1 : 0;
        // Во время validation на entity ещё старое option_value.
        if ((int)$option->option_value === $value) {
            return true;
        }

        try {
            \XF::db()->update(
                'xf_char_profile',
                ['last_update' => \XF::$time],
                '1=1'
            );
            \XF::em()->clearEntityCache('Enterum\CharacterProfile:CharProfile');
        } catch (\Throwable $e) {
            // Таблица может ещё не существовать на чистой установке до Setup — не блокируем сохранение опции.
        }

        return true;
    }
}
