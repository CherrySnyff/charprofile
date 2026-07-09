<?php

/**
 * GET: вкладка «Репутация» — блок ОГ + регионы/фракции.
 * Маршрут members/{user_id}/reputation; при открытии пересчитывает hero_points_cache.
 * Данные: HeroPointManager + ReputationLogManager.
 */

namespace Enterum\CharacterProfile\Pub\Controller;

use Enterum\CharacterProfile\Service\Hero\HeroPointManager;
use Enterum\CharacterProfile\Service\Reputation\ReputationLogManager;
use XF\Mvc\ParameterBag;
use XF\Mvc\Reply\AbstractReply;

class Reputation extends AbstractProfileAction
{
    /**
     * Репутация / ОГ: GET — страница вкладки с журналами и таблицей влияния.
     */
    public function actionIndex(ParameterBag $params): AbstractReply
    {
        $user = $this->loadProfileUser($params);
        $page = max(1, (int)$this->filter('page', 'uint'));
        $repRegion = ReputationLogManager::resolveRegion($this->filter('rep', 'str'));

        /** @var HeroPointManager $heroManager */
        $heroManager = $this->service('Enterum\CharacterProfile:Hero\HeroPointManager');
        $profile = $heroManager->recalculateForUser($user->user_id);

        $heroData = $heroManager->getDisplayRows($user->user_id, $page);
        $today = date('d.m.Y');

        /** @var ReputationLogManager $repManager */
        $repManager = $this->service('Enterum\CharacterProfile:Reputation\ReputationLogManager');
        $repData = $repManager->buildViewData($user->user_id, $repRegion);

        return $this->profileView($user, 'reputation', 'enterum_char_profile_reputation', array_merge([
            'profile' => $profile,
            'heroPairs' => $heroData['pairs'],
            'heroTotal' => $heroData['total'],
            'heroPage' => $heroData['page'],
            'heroPerPage' => $heroData['perPage'],
            'heroToday' => $today,
        ], $repData));
    }
}
