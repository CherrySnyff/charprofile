<?php

/**
 * Listener: после сохранения профиля пользователя заново проставить ссылки Репутация/Рюкзак,
 * если пользователь в группе-триггере (поля нельзя «очистить» вручную).
 */

namespace Enterum\CharacterProfile\Listener;

use Enterum\CharacterProfile\Service\ProfileInitializer;
use Enterum\CharacterProfile\Service\ProfilePageLinks;
use XF\Entity\UserProfile;
use XF\Mvc\Entity\Entity;

class UserProfileListener
{
    public static function userProfilePostSave(Entity $entity): void
    {
        if (!($entity instanceof UserProfile)) {
            return;
        }

        $user = $entity->User;
        if (!$user) {
            return;
        }

        /** @var ProfileInitializer $initializer */
        $initializer = \XF::app()->service('Enterum\CharacterProfile:ProfileInitializer');
        if (!$initializer->userHasTriggerGroup($user)) {
            return;
        }

        /** @var ProfilePageLinks $links */
        $links = \XF::app()->service('Enterum\CharacterProfile:ProfilePageLinks');
        $links->ensureLinks($user);
    }
}
