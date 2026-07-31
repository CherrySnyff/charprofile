<?php

/**
 * Listener / профиль: при смене групп пользователя создать xf_char_profile,
 * если выдана группа-триггер (ProfileInitializer::userHasTriggerGroup).
 */

namespace Enterum\CharacterProfile\Listener;

use Enterum\CharacterProfile\Service\ProfileInitializer;
use XF\Entity\User as UserEntity;
use XF\Mvc\Entity\Entity;

class UserEntityListener
{
    /**
     * Профиль / setup: после save User — ensureProfileRow при смене групп.
     */
    public static function userEntityPostSave(Entity $entity): void
    {
        if (!($entity instanceof UserEntity)) {
            return;
        }

        if (!$entity->isUpdate()) {
            return;
        }

        $changes = $entity->getPreviousValues();
        if (!isset($changes['user_group_id']) && !isset($changes['secondary_group_ids'])) {
            return;
        }

        /** @var ProfileInitializer $initializer */
        $initializer = \XF::app()->service('Enterum\CharacterProfile:ProfileInitializer');
        if ($initializer->userHasTriggerGroup($entity)) {
            $initializer->ensureProfileRow($entity->user_id);
            return;
        }

        // Сняли группу-триггер — убрать значения полей Репутация/Рюкзак.
        try {
            /** @var \Enterum\CharacterProfile\Service\ProfilePageLinks $links */
            $links = \XF::app()->service('Enterum\CharacterProfile:ProfilePageLinks');
            $links->clearLinks($entity);
        } catch (\Throwable $e) {
            // не блокируем сохранение пользователя
        }
    }
}
