<?php

/**
 * Вкладка «Лист персонажа» (v2, ещё не реализована).
 * Маршрут оставлен, но отвечает 404 — чтобы не светить заглушку по прямой ссылке.
 */

namespace Enterum\CharacterProfile\Pub\Controller;

use XF\Mvc\ParameterBag;
use XF\Mvc\Reply\AbstractReply;

class CharacterSheet extends AbstractProfileAction
{
    /**
     * Лист персонажа (v2): пока недоступен.
     */
    public function actionIndex(ParameterBag $params): AbstractReply
    {
        // Проверяем профиль, чтобы сохранить единый noPermission для «не своей» группы.
        $this->loadProfileUser($params);

        throw $this->exception($this->notFound(\XF::phrase('enterum_char_profile_coming_soon')));
    }
}
