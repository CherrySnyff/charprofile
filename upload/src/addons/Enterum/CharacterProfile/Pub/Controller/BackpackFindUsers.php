<?php

/**
 * Рюкзак / crafted: GET JSON автодополнение @ника для поля «Автор».
 * Маршрут members/backpack_find_users; доступ при manageBackpack или manageBackpackOwn на своём профиле.
 */

namespace Enterum\CharacterProfile\Pub\Controller;

use XF\Mvc\ParameterBag;
use XF\Mvc\Reply\AbstractReply;

class BackpackFindUsers extends AbstractProfileAction
{
    /**
     * Рюкзак / crafted: поиск пользователей по префиксу ника (JSON).
     */
    public function actionIndex(ParameterBag $params): AbstractReply
    {
        $user = $this->loadProfileUser($params);
        $visitor = \XF::visitor();

        if (!$this->getPermissionGuard()->canManageBackpack($visitor, $user)) {
            return $this->noPermission();
        }

        $q = trim($this->filter('q', 'str'));
        if (mb_strlen($q) < 2) {
            $this->setResponseType('json');

            return $this->view('Enterum\CharacterProfile:FindUsersJson', '', ['results' => []]);
        }

        if ($q[0] === '@') {
            $q = ltrim($q, '@');
            if (mb_strlen($q) < 2) {
                $this->setResponseType('json');

                return $this->view('Enterum\CharacterProfile:FindUsersJson', '', ['results' => []]);
            }
        }

        $like = $this->app->db()->escapeLike($q, '?%');
        $users = $this->finder('XF:User')
            ->where('username', 'like', $like)
            ->where('user_state', 'valid')
            ->order('username')
            ->limit(15)
            ->fetch();

        $results = [];
        foreach ($users as $user) {
            $results[] = [
                'user_id' => (int)$user->user_id,
                'username' => (string)$user->username,
            ];
        }

        $this->setResponseType('json');

        return $this->view('Enterum\CharacterProfile:FindUsersJson', '', ['results' => $results]);
    }
}
