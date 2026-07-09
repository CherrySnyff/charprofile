<?php

/**
 * Вкладка «Лист персонажа» (заглушка под v2).
 *
 * UI-вкладка скрыта до v2; маршрут и контроллер оставлены для будущей реализации.
 * GET members/{user_id}/character-sheet — только шаблон-заглушка.
 */

namespace Enterum\CharacterProfile\Pub\Controller;

use XF\Mvc\ParameterBag;
use XF\Mvc\Reply\AbstractReply;

class CharacterSheet extends AbstractProfileAction
{
    /**
     * Лист персонажа (v2): GET — заглушка; вкладка в UI пока скрыта.
     */
    public function actionIndex(ParameterBag $params): AbstractReply
    {
        $user = $this->loadProfileUser($params);

        return $this->profileView($user, 'character_sheet', 'enterum_char_profile_character_sheet');
    }
}
