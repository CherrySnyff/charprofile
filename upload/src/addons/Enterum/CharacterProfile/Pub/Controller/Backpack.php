<?php

/**
 * GET: вкладка «Рюкзак» (подвкладки activity / crafted / other).
 * Маршрут members/{user_id}/backpack; собирает данные из трёх менеджеров рюкзака.
 */

namespace Enterum\CharacterProfile\Pub\Controller;

use Enterum\CharacterProfile\Service\Backpack\ActivityItemManager;
use Enterum\CharacterProfile\Service\Backpack\CraftedItemManager;
use Enterum\CharacterProfile\Service\Backpack\OtherContentManager;
use XF\Mvc\ParameterBag;
use XF\Mvc\Reply\AbstractReply;

class Backpack extends AbstractProfileAction
{
    /**
     * Рюкзак: GET — страница вкладки с тремя блоками (пагинация активной подвкладки).
     */
    public function actionIndex(ParameterBag $params): AbstractReply
    {
        $user = $this->loadProfileUser($params);
        $subTab = $this->filter('sub', 'str');
        if (!in_array($subTab, ['activity', 'crafted', 'other'], true)) {
            $subTab = 'activity';
        }

        $page = max(1, (int)$this->filter('page', 'uint'));
        $activityRoot = $this->app->router('public')->buildLink('members/backpack_activity', $user);
        $craftedRoot = $this->app->router('public')->buildLink('members/backpack_crafted', $user);
        $otherRoot = $this->app->router('public')->buildLink('members/backpack_other', $user);
        $findUsersUrl = $this->app->router('public')->buildLink('members/backpack_find_users', $user);

        /** @var ActivityItemManager $activityManager */
        $activityManager = $this->service('Enterum\CharacterProfile:Backpack\ActivityItemManager');
        /** @var CraftedItemManager $craftedManager */
        $craftedManager = $this->service('Enterum\CharacterProfile:Backpack\CraftedItemManager');
        /** @var OtherContentManager $otherManager */
        $otherManager = $this->service('Enterum\CharacterProfile:Backpack\OtherContentManager');

        $profile = $this->getPermissionGuard()->getOrCreateProfile($user);
        $otherData = $otherManager->buildViewData($profile);

        if ($subTab === 'crafted') {
            $craftedData = $craftedManager->buildViewData($user->user_id, $page);
            $activityData = $activityManager->buildViewData($user->user_id, 1);
        } elseif ($subTab === 'other') {
            $craftedData = $craftedManager->buildViewData($user->user_id, 1);
            $activityData = $activityManager->buildViewData($user->user_id, 1);
        } else {
            $activityData = $activityManager->buildViewData($user->user_id, $page);
            $craftedData = $craftedManager->buildViewData($user->user_id, 1);
        }

        return $this->profileView($user, 'backpack', 'enterum_char_profile_backpack', array_merge([
            'subTab' => $subTab,
            'activityRows' => $activityData['rows'],
            'activityTotal' => $activityData['total'],
            'activityPage' => $activityData['page'],
            'activityPerPage' => $activityData['perPage'],
            'craftedRows' => $craftedData['rows'],
            'craftedTotal' => $craftedData['total'],
            'craftedPage' => $craftedData['page'],
            'craftedPerPage' => $craftedData['perPage'],
            'activityRoot' => $activityRoot,
            'craftedRoot' => $craftedRoot,
            'otherRoot' => $otherRoot,
            'findUsersUrl' => $findUsersUrl,
            'profile' => $profile,
        ], $otherData));
    }
}
