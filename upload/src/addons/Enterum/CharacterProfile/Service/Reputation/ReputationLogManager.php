<?php

/**
 * Вкладка «Репутация»: CRUD журнала xf_char_profile_reputation_log.
 * Сборка данных для UI (регионы aramidis/korzus/union, фракции, таблица влияния).
 * Право manageReputation; amount может быть отрицательным (signed INT).
 */

namespace Enterum\CharacterProfile\Service\Reputation;

use Enterum\CharacterProfile\Entity\CharProfileReputationLog;
use Enterum\CharacterProfile\Service\ActionLogger;
use Enterum\CharacterProfile\Service\PermissionGuard;
use XF\Entity\User;
use XF\Service\AbstractService;

class ReputationLogManager extends AbstractService
{
    /**
     * Репутация: нормализовать ключ региона (aramidis|korzus|union).
     */
    public static function resolveRegion(string $region): string
    {
        return in_array($region, ['aramidis', 'korzus', 'union'], true) ? $region : 'aramidis';
    }

    /**
     * Репутация / UI: данные вкладки — влияние, фракции и записи выбранного региона.
     *
     * @return array<string, mixed>
     */
    public function buildViewData(int $userId, string $repRegion): array
    {
        $repRegion = self::resolveRegion($repRegion);
        /** @var \Enterum\CharacterProfile\Repository\CharProfileReputationLog $repo */
        $repo = $this->repository('Enterum\CharacterProfile:CharProfileReputationLog');

        $regionRows = $repo->findLogsForUserRegion($userId, $repRegion)->fetch();
        $repRowsByFaction = [];
        foreach ($regionRows as $row) {
            $factionKey = mb_strtolower(trim((string)$row->faction_name));
            if ($factionKey === '') {
                $factionKey = '__empty__';
            }
            if (!isset($repRowsByFaction[$factionKey])) {
                $repRowsByFaction[$factionKey] = [];
            }
            $repRowsByFaction[$factionKey][] = $row;
        }

        $factionRows = $repo->getFactionAggregatesForRegion($userId, $repRegion);
        foreach ($factionRows as &$factionRow) {
            $key = (string)($factionRow['faction_key'] ?? '');
            $factionRow['entries'] = $repRowsByFaction[$key] ?? [];
        }
        unset($factionRow);

        return [
            'influenceRows' => $repo->getInfluenceTable($userId),
            'repRegion' => $repRegion,
            'factionRows' => $factionRows,
        ];
    }

    /**
     * Репутация: добавить запись в журнал (manageReputation).
     */
    public function addLog(User $profileUser, User $actor, array $input): CharProfileReputationLog
    {
        $this->assertCanManage($actor, $profileUser);
        $this->assertAmountColumnSupportsNegative();

        $amount = (int)($input['reputation_amount'] ?? 0);
        if ($amount === 0) {
            throw new \XF\PrintableException(\XF::phrase('enterum_char_profile_rep_amount_invalid'));
        }

        /** @var CharProfileReputationLog $log */
        $log = $this->em()->create('Enterum\CharacterProfile:CharProfileReputationLog');
        $this->applyInput($log, $profileUser, $actor, $input, $amount, true);
        $log->save();

        $this->logAction('reputation', 'add', $profileUser->user_id, $actor->user_id, $log->reputation_log_id, null, $log->toArray());

        return $log;
    }

    /**
     * Репутация: редактировать запись журнала.
     */
    public function editLog(
        User $profileUser,
        User $actor,
        CharProfileReputationLog $log,
        array $input
    ): CharProfileReputationLog {
        $this->assertCanManage($actor, $profileUser);
        if ((int)$log->user_id !== (int)$profileUser->user_id) {
            throw new \XF\PrintableException(\XF::phrase('requested_page_not_found'));
        }

        $this->assertAmountColumnSupportsNegative();

        $amount = (int)($input['reputation_amount'] ?? 0);
        if ($amount === 0) {
            throw new \XF\PrintableException(\XF::phrase('enterum_char_profile_rep_amount_invalid'));
        }

        $old = $log->toArray();
        $this->applyInput($log, $profileUser, $actor, $input, $amount, false);
        $log->save();

        $this->logAction('reputation', 'edit', $profileUser->user_id, $actor->user_id, $log->reputation_log_id, $old, $log->toArray());

        return $log;
    }

    /**
     * Репутация: удалить запись журнала.
     */
    public function deleteLog(User $profileUser, User $actor, CharProfileReputationLog $log): void
    {
        $this->assertCanManage($actor, $profileUser);
        if ((int)$log->user_id !== (int)$profileUser->user_id) {
            throw new \XF\PrintableException(\XF::phrase('requested_page_not_found'));
        }

        $old = $log->toArray();
        $logId = $log->reputation_log_id;
        $log->delete();

        $this->em()->clearEntityCache('Enterum\CharacterProfile:CharProfileReputationLog', $logId);
        $this->logAction('reputation', 'delete', $profileUser->user_id, $actor->user_id, $logId, $old, null);
    }

    /**
     * Репутация: применить поля формы к сущности (регион, фракция, amount, источник).
     */
    protected function applyInput(
        CharProfileReputationLog $log,
        User $profileUser,
        User $actor,
        array $input,
        int $amount,
        bool $isNew
    ): void {
        $region = self::resolveRegion((string)($input['region_key'] ?? 'aramidis'));

        $log->user_id = $profileUser->user_id;
        $log->region_key = $region;
        $log->character_name = $profileUser->username;
        $log->source_url = trim((string)($input['source_url'] ?? ''));
        $log->source_title = trim((string)($input['source_title'] ?? ''));
        $log->faction_name = trim((string)($input['faction_name'] ?? ''));
        $log->amount = $amount;

        if ($isNew) {
            $log->created_by_user_id = $actor->user_id;
            $log->created_date = \XF::$time;
        }
        $log->last_edit_user_id = $actor->user_id;
        $log->last_edit_date = \XF::$time;
    }

    /**
     * Репутация / права: manageReputation или manageReputationOwn на своём профиле.
     */
    protected function assertCanManage(User $actor, User $profileUser): void
    {
        /** @var PermissionGuard $guard */
        $guard = $this->app->service('Enterum\CharacterProfile:PermissionGuard');
        if (!$guard->canManageReputation($actor, $profileUser)) {
            throw new \XF\PrintableException(\XF::phrase('enterum_char_profile_no_permission'));
        }
    }

    /**
     * Репутация / схема: убедиться, что amount — signed INT (самолечение UNSIGNED).
     */
    protected function assertAmountColumnSupportsNegative(): void
    {
        $tableName = 'xf_char_profile_reputation_log';
        $columnType = (string)$this->db()->fetchOne(
            '
                SELECT COLUMN_TYPE
                FROM information_schema.COLUMNS
                WHERE TABLE_SCHEMA = DATABASE()
                    AND TABLE_NAME = ?
                    AND COLUMN_NAME = ?
                LIMIT 1
            ',
            [$tableName, 'amount']
        );

        if ($columnType !== '' && stripos($columnType, 'unsigned') !== false) {
            try {
                $this->db()->query(
                    'ALTER TABLE `' . $tableName . '` MODIFY `amount` INT NOT NULL DEFAULT 0'
                );
            } catch (\Throwable $e) {
                // Ниже проверим тип колонки после попытки самолечения.
            }

            $columnType = (string)$this->db()->fetchOne(
                '
                    SELECT COLUMN_TYPE
                    FROM information_schema.COLUMNS
                    WHERE TABLE_SCHEMA = DATABASE()
                        AND TABLE_NAME = ?
                        AND COLUMN_NAME = ?
                    LIMIT 1
                ',
                [$tableName, 'amount']
            );

            if ($columnType !== '' && stripos($columnType, 'unsigned') !== false) {
                throw new \XF\PrintableException(
                    'Колонка amount в таблице ' . $tableName
                    . ' имеет тип UNSIGNED. Выполните SQL: ALTER TABLE `' . $tableName
                    . '` MODIFY `amount` INT NOT NULL DEFAULT 0;'
                );
            }
        }
    }

    /**
     * ACP logs: делегировать запись аудита в ActionLogger.
     */
    protected function logAction(
        string $contentType,
        string $action,
        int $targetUserId,
        int $actorUserId,
        int $contentId,
        ?array $oldData,
        ?array $newData
    ): void {
        /** @var ActionLogger $logger */
        $logger = $this->app->service('Enterum\CharacterProfile:ActionLogger');
        $logger->log($contentType, $action, $targetUserId, $actorUserId, $contentId, $oldData, $newData);
    }
}
