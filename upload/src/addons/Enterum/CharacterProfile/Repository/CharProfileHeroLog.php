<?php

/**
 * Repository: выборки журнала ОГ (xf_char_profile_hero_log).
 * ASC по event_date/created_date — для UI и хронологического пересчёта.
 */

namespace Enterum\CharacterProfile\Repository;

use XF\Mvc\Entity\Repository;

class CharProfileHeroLog extends Repository
{
    /**
     * ОГ: записи пользователя по возрастанию даты (UI / пагинация).
     */
    public function findLogsForUser(int $userId)
    {
        return $this->finder('Enterum\CharacterProfile:CharProfileHeroLog')
            ->where('user_id', $userId)
            ->order('event_date')
            ->order('created_date')
            ->order('hero_log_id');
    }

    /**
     * ОГ: то же ASC-упорядочивание для recalculateForUser.
     */
    public function findLogsForUserAsc(int $userId)
    {
        return $this->finder('Enterum\CharacterProfile:CharProfileHeroLog')
            ->where('user_id', $userId)
            ->order('event_date')
            ->order('created_date')
            ->order('hero_log_id');
    }
}
