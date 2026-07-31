<?php

/**
 * Инициализация профиля игрока (строка xf_char_profile).
 * Создание при выдаче группы-триггера; Listener UserEntityListener вызывает ensureProfileRow.
 * Опция charProfileAcceptedGroupId — ID группы, для которой показываются вкладки.
 */

namespace Enterum\CharacterProfile\Service;

use Enterum\CharacterProfile\Entity\CharProfile;
use XF\Entity\User;
use XF\Service\AbstractService;

class ProfileInitializer extends AbstractService
{
    /**
     * Setup / профиль: массово создать xf_char_profile для всех в группе-триггере.
     *
     * @return int Число вновь созданных строк
     */
    public function batchInitializeForGroup(int $groupId): int
    {
        if ($groupId <= 0) {
            return 0;
        }

        $userIds = $this->db()->fetchAllColumn(
            'SELECT user_id FROM xf_user WHERE user_group_id = ? OR FIND_IN_SET(?, secondary_group_ids)',
            [$groupId, $groupId]
        );

        $count = 0;
        foreach ($userIds as $userId) {
            if ($this->ensureProfileRow((int)$userId)) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * Профиль: гарантировать строку xf_char_profile; true — создана новая.
     */
    public function ensureProfileRow(int $userId): bool
    {
        if ($userId <= 0) {
            return false;
        }

        /** @var CharProfile|null $existing */
        $existing = $this->em()->find('Enterum\CharacterProfile:CharProfile', $userId);
        if ($existing) {
            if (!$existing->is_initialized) {
                $existing->is_initialized = 1;
                $existing->last_update = \XF::$time;
                $existing->save();
            }
            $this->syncProfilePageLinks($userId);
            return false;
        }

        /** @var CharProfile $profile */
        $profile = $this->em()->create('Enterum\CharacterProfile:CharProfile');
        $profile->user_id = $userId;
        $profile->is_initialized = 1;
        $profile->created_date = \XF::$time;
        $profile->last_update = \XF::$time;
        $profile->save();

        $this->syncProfilePageLinks($userId);

        return true;
    }

    /**
     * Профиль / UI: ссылки Репутация/Рюкзак в блоке charfieldslinks поля профиля.
     */
    public function syncProfilePageLinks(int $userId): void
    {
        /** @var User|null $user */
        $user = $this->em()->find('XF:User', $userId);
        if (!$user) {
            return;
        }

        try {
            /** @var ProfilePageLinks $links */
            $links = $this->app->service('Enterum\CharacterProfile:ProfilePageLinks');
            $links->ensureLinks($user);
        } catch (\Throwable $e) {
            // Не блокируем создание профиля из‑за поля ссылок.
        }
    }

    /**
     * Профиль / права: состоит ли пользователь в группе-триггере (вкладки видны).
     */
    public function userHasTriggerGroup(User $user): bool
    {
        $groupId = $this->getAcceptedGroupId();
        if ($groupId <= 0) {
            return false;
        }

        if (method_exists($user, 'isMemberOf')) {
            return $user->isMemberOf($groupId);
        }

        if ((int)$user->user_group_id === $groupId) {
            return true;
        }

        $secondary = $user->secondary_group_ids;
        if (is_array($secondary)) {
            return in_array($groupId, array_map('intval', $secondary), true);
        }

        if (is_string($secondary) && $secondary !== '') {
            foreach (explode(',', $secondary) as $part) {
                if ((int)trim($part) === $groupId) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Профиль / опции: ID группы-триггера из xf_option (по умолчанию 16).
     */
    public function getAcceptedGroupId(): int
    {
        $db = $this->app->db();
        $value = $db->fetchOne(
            "SELECT option_value FROM xf_option WHERE option_id = 'charProfileAcceptedGroupId'"
        );
        if ($value !== false && $value !== null && $value !== '') {
            $groupId = (int)$value;
            if ($groupId > 0) {
                return $groupId;
            }
        }

        return 16;
    }
}
