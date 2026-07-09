<?php

/**
 * Рюкзак / «Прочее»: POST сохранение BB-кода в xf_char_profile.
 * Право manageBackpack или manageBackpackOwn (только свой профиль); редирект на #other.
 */

namespace Enterum\CharacterProfile\Pub\Controller;

use Enterum\CharacterProfile\Service\Backpack\OtherContentManager;
use XF\Mvc\ParameterBag;
use XF\Mvc\Reply\AbstractReply;

class BackpackOther extends AbstractProfileAction
{
    /**
     * Рюкзак / «Прочее»: POST — сохранить BB-код из редактора.
     */
    public function actionSave(ParameterBag $params): AbstractReply
    {
        $this->assertPostOnly();
        $this->assertValidCsrfToken($this->filter('_xfToken', 'str'));

        $user = $this->loadProfileUser($params);
        $visitor = \XF::visitor();
        $profile = $this->getPermissionGuard()->getOrCreateProfile($user);
        $message = $this->plugin('XF:Editor')->fromInput('message');

        try {
            /** @var OtherContentManager $manager */
            $manager = $this->service('Enterum\CharacterProfile:Backpack\OtherContentManager');
            $manager->saveContent($user, $visitor, $profile, $message);
        } catch (\XF\PrintableException $e) {
            return $this->error($e->getMessage());
        }

        throw $this->exception(
            $this->redirect(
                $this->buildLink('members/backpack', $user, ['sub' => 'other'], '#other')
            )
        );
    }
}
