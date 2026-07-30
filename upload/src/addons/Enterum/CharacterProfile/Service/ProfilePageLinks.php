<?php

/**
 * Кастомные поля профиля: Репутация и Рюкзак.
 * Значения пишутся только пользователям группы-триггера (по умолчанию 16).
 */

namespace Enterum\CharacterProfile\Service;

use XF\Entity\User;
use XF\Entity\UserField;
use XF\Service\AbstractService;

class ProfilePageLinks extends AbstractService
{
    public const FIELD_REPUTATION = 'enterum_cp_reputation';
    public const FIELD_BACKPACK = 'enterum_cp_backpack';

    /**
     * Записать/обновить ссылки в кастомных полях пользователя (только группа-триггер).
     */
    public function ensureLinks(User $user): bool
    {
        if ((int)$user->user_id <= 0) {
            return false;
        }

        /** @var ProfileInitializer $initializer */
        $initializer = $this->app->service('Enterum\CharacterProfile:ProfileInitializer');
        if (!$initializer->userHasTriggerGroup($user)) {
            return $this->clearLinks($user);
        }

        $this->ensureFieldDefinitions();

        $repUrl = $this->app->router('public')->buildLink('canonical:members/reputation', $user);
        $bpUrl = $this->app->router('public')->buildLink('canonical:members/backpack', $user);

        $changed = false;
        if ($this->saveFieldValue($user, self::FIELD_REPUTATION, $repUrl)) {
            $changed = true;
        }
        if ($this->saveFieldValue($user, self::FIELD_BACKPACK, $bpUrl)) {
            $changed = true;
        }

        return $changed;
    }

    /**
     * Удалить значения полей Репутация/Рюкзак у пользователя (не в группе-триггере).
     */
    public function clearLinks(User $user): bool
    {
        if ((int)$user->user_id <= 0) {
            return false;
        }

        $changed = false;
        foreach ([self::FIELD_REPUTATION, self::FIELD_BACKPACK] as $fieldId) {
            if ($this->deleteFieldValue($user, $fieldId)) {
                $changed = true;
            }
        }

        if ($changed) {
            $this->em()->clearEntityCache('XF:UserProfile', $user->user_id);
            $this->em()->clearEntityCache('XF:User', $user->user_id);
        }

        return $changed;
    }

    /**
     * Удалить значения полей у всех, кто не в группе-триггере.
     */
    public function clearLinksForNonTriggerUsers(): int
    {
        /** @var ProfileInitializer $initializer */
        $initializer = $this->app->service('Enterum\CharacterProfile:ProfileInitializer');
        $groupId = $initializer->getAcceptedGroupId();
        if ($groupId <= 0) {
            return 0;
        }

        $userIds = $this->db()->fetchAllColumn(
            'SELECT DISTINCT v.user_id
             FROM xf_user_field_value AS v
             INNER JOIN xf_user AS u ON (u.user_id = v.user_id)
             WHERE v.field_id IN (?, ?)
               AND u.user_group_id <> ?
               AND FIND_IN_SET(?, u.secondary_group_ids) = 0',
            [self::FIELD_REPUTATION, self::FIELD_BACKPACK, $groupId, $groupId]
        );

        $count = 0;
        foreach ($userIds as $userId) {
            /** @var User|null $user */
            $user = $this->em()->find('XF:User', (int)$userId);
            if ($user && $this->clearLinks($user)) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * Создать определения полей через XF Entity (совместимо со схемой текущей версии XF).
     */
    public function ensureFieldDefinitions(): void
    {
        $this->ensureFieldDefinition(
            self::FIELD_REPUTATION,
            50,
            'Репутация',
            'Ссылка на вкладку «Репутация». Заполняется автоматически и не редактируется.'
        );
        $this->ensureFieldDefinition(
            self::FIELD_BACKPACK,
            55,
            'Рюкзак',
            'Ссылка на вкладку «Рюкзак». Заполняется автоматически и не редактируется.'
        );

        try {
            $this->repository('XF:UserField')->rebuildFieldCache();
        } catch (\Throwable $e) {
            // кэш полей пересоберётся при общей пересборке
        }
    }

    protected function ensureFieldDefinition(string $fieldId, int $displayOrder, string $title, string $description): void
    {
        /** @var UserField|null $existing */
        $existing = $this->em()->find('XF:UserField', $fieldId);
        if ($existing) {
            $existing->bulkSet([
                'user_editable' => 'never',
                'viewable_profile' => false,
                'viewable_message' => false,
                'display_group' => 'contact',
                'match_type' => $this->resolveMatchTypeUrl(),
                'display_order' => $displayOrder,
            ]);
            $existing->saveIfChanged();
            $this->ensureFieldPhrases($existing, $title, $description);
            return;
        }

        /** @var UserField $field */
        $field = $this->em()->create('XF:UserField');
        $field->field_id = $fieldId;
        $field->display_group = 'contact';
        $field->display_order = $displayOrder;
        $field->field_type = 'textbox';
        $field->match_type = $this->resolveMatchTypeUrl();
        $field->max_length = 255;
        $field->required = false;
        $field->show_registration = false;
        $field->user_editable = 'never';
        $field->viewable_profile = false;
        $field->viewable_message = false;
        if ($field->isValidColumn('moderator_editable')) {
            $field->moderator_editable = false;
        }

        $this->ensureFieldPhrases($field, $title, $description);
        $field->save();
    }

    /**
     * @param UserField $field
     */
    protected function ensureFieldPhrases(UserField $field, string $title, string $description): void
    {
        try {
            $titlePhrase = $field->getMasterPhrase(true);
            $titlePhrase->phrase_text = $title;
            $titlePhrase->addon_id = 'Enterum/CharacterProfile';
            $field->addCascadedSave($titlePhrase);

            $descPhrase = $field->getMasterPhrase(false);
            $descPhrase->phrase_text = $description;
            $descPhrase->addon_id = 'Enterum/CharacterProfile';
            $field->addCascadedSave($descPhrase);
        } catch (\Throwable $e) {
            $this->ensurePhraseFallback('user_field_title.' . $field->field_id, $title);
            $this->ensurePhraseFallback('user_field_desc.' . $field->field_id, $description);
        }
    }

    protected function resolveMatchTypeUrl(): string
    {
        try {
            /** @var UserField $probe */
            $probe = $this->em()->create('XF:UserField');
            $allowed = $probe->structure()->columns['match_type']['allowedValues'] ?? null;
            if (is_array($allowed) && in_array('url', $allowed, true)) {
                return 'url';
            }
        } catch (\Throwable $e) {
            // fallback below
        }

        return 'none';
    }

    protected function ensurePhraseFallback(string $title, string $text): void
    {
        $exists = $this->db()->fetchOne(
            'SELECT phrase_id FROM xf_phrase WHERE language_id = 0 AND title = ?',
            [$title]
        );
        if ($exists) {
            return;
        }

        $this->db()->insert('xf_phrase', [
            'language_id' => 0,
            'title' => $title,
            'phrase_text' => $text,
            'global_cache' => 0,
            'addon_id' => 'Enterum/CharacterProfile',
            'version_id' => 1000086,
            'version_string' => '1.0.16',
        ]);
    }

    protected function saveFieldValue(User $user, string $fieldId, string $value): bool
    {
        try {
            $current = $this->db()->fetchOne(
                'SELECT field_value FROM xf_user_field_value WHERE user_id = ? AND field_id = ?',
                [$user->user_id, $fieldId]
            );

            if ($current === false || $current === null) {
                $this->db()->insert('xf_user_field_value', [
                    'user_id' => $user->user_id,
                    'field_id' => $fieldId,
                    'field_value' => $value,
                ]);
            } elseif ((string)$current !== $value) {
                $this->db()->update(
                    'xf_user_field_value',
                    ['field_value' => $value],
                    'user_id = ? AND field_id = ?',
                    [$user->user_id, $fieldId]
                );
            } else {
                return false;
            }

            $this->em()->clearEntityCache('XF:UserProfile', $user->user_id);
            $this->em()->clearEntityCache('XF:User', $user->user_id);

            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }

    protected function deleteFieldValue(User $user, string $fieldId): bool
    {
        try {
            $deleted = $this->db()->delete(
                'xf_user_field_value',
                'user_id = ? AND field_id = ?',
                [$user->user_id, $fieldId]
            );

            return $deleted > 0;
        } catch (\Throwable $e) {
            return false;
        }
    }
}
