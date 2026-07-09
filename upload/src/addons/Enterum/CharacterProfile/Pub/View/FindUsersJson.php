<?php

/**
 * Рюкзак / crafted: JSON-представление для BackpackFindUsers.
 * Отдаёт params (results: user_id + username) как JSON-ответ.
 */

namespace Enterum\CharacterProfile\Pub\View;

use XF\Mvc\View;

class FindUsersJson extends View
{
    /**
     * Рюкзак / crafted: сериализация результатов автодополнения в JSON.
     */
    public function renderJson(): array
    {
        return $this->params;
    }
}
