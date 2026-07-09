<?php

/**
 * ACP: журнал действий (xf_char_profile_action_log).
 *
 * Доступ: admin permission characterProfile + (супер-админ / админ / viewLogs).
 * Только чтение; фильтры и пагинация. SQL через репозиторий (без entity JOIN).
 * Опция charProfileEnableActionLog влияет на запись, не на просмотр.
 */

namespace Enterum\CharacterProfile\Admin\Controller;

use Enterum\CharacterProfile\Helper\ActionLogDisplay;
use XF\Admin\Controller\AbstractController;
use XF\Mvc\ParameterBag;
use XF\Mvc\Reply\AbstractReply;

class Logs extends AbstractController
{
    /**
     * ACP logs / права: characterProfile + супер-админ / админ / viewLogs.
     */
    protected function preDispatchController($action, ParameterBag $params)
    {
        // Доступ в ACP только с admin permission аддона.
        $this->assertAdminPermission('characterProfile');

        $visitor = \XF::visitor();
        // Дополнительно: супер-админ, админ форума или явное viewLogs.
        if (!$visitor->is_super_admin && !$visitor->is_admin && !$visitor->hasPermission('character_profile', 'viewLogs')) {
            throw $this->exception($this->noPermission());
        }
    }

    /**
     * ACP logs: список записей с фильтрами (тип, действие, пользователи, даты).
     */
    public function actionIndex(): AbstractReply
    {
        $this->ensureActionLogTableExists();
        $hasActionLogTable = $this->hasActionLogTable();

        $page = max(1, (int)$this->filter('page', 'uint'));
        $perPage = max(10, min(200, (int)$this->options()->charProfileItemsPerPage ?: 50));
        $sort = $this->resolveSortKey((string)$this->filter('sort', 'str'));
        $direction = strtolower((string)$this->filter('direction', 'str')) === 'asc' ? 'asc' : 'desc';

        $contentType = (string)$this->filter('type', 'str');
        $allowedTypes = array_keys(ActionLogDisplay::getContentTypeTabs());
        if (!in_array($contentType, $allowedTypes, true)) {
            $contentType = '';
        }
        $actionFilter = (string)$this->filter('action', 'str');
        $targetUserFilter = trim((string)$this->filter('target_user', 'str'));
        $actorUserFilter = trim((string)$this->filter('actor_user', 'str'));
        $dateFromFilter = trim((string)$this->filter('date_from', 'str'));
        $dateToFilter = trim((string)$this->filter('date_to', 'str'));

        $dateFromTs = 0;
        if ($dateFromFilter !== '') {
            $fromTs = strtotime($dateFromFilter . ' 00:00:00');
            if ($fromTs !== false) {
                $dateFromTs = (int)$fromTs;
            }
        }

        $dateToTs = 0;
        if ($dateToFilter !== '') {
            $toTs = strtotime($dateToFilter . ' 23:59:59');
            if ($toTs !== false) {
                $dateToTs = (int)$toTs;
            }
        }

        $filterParams = [
            'type' => $contentType,
            'action' => $actionFilter,
            'target_user' => $targetUserFilter,
            'actor_user' => $actorUserFilter,
            'date_from' => $dateFromFilter,
            'date_to' => $dateToFilter,
            'sort' => $sort,
            'direction' => $direction,
        ];

        $rows = [];
        $total = 0;

        if ($hasActionLogTable) {
            /** @var \Enterum\CharacterProfile\Repository\CharProfileActionLog $repo */
            $repo = $this->repository('Enterum\CharacterProfile:CharProfileActionLog');
            $result = $repo->fetchLogsForAdmin([
                'content_type' => $contentType,
                'action' => $actionFilter,
                'target_user' => $targetUserFilter,
                'actor_user' => $actorUserFilter,
                'date_from' => $dateFromTs,
                'date_to' => $dateToTs,
                'sort' => $sort,
                'direction' => $direction,
            ], $page, $perPage);

            $total = $result['total'];

            foreach ($result['rows'] as $log) {
                $oldData = !empty($log['old_data']) ? json_decode($log['old_data'], true) : null;
                $newData = !empty($log['new_data']) ? json_decode($log['new_data'], true) : null;

                $rows[] = [
                    'action_log_id' => (int)$log['action_log_id'],
                    'log_date' => (int)$log['log_date'],
                    'target_username' => (string)($log['target_username'] ?? ''),
                    'actor_username' => (string)($log['actor_username'] ?? ''),
                    'content_type' => (string)$log['content_type'],
                    'content_id' => (int)$log['content_id'],
                    'action' => (string)$log['action'],
                    'details' => ActionLogDisplay::formatDetails(
                        (string)$log['content_type'],
                        (string)$log['action'],
                        is_array($oldData) ? $oldData : null,
                        is_array($newData) ? $newData : null
                    ),
                ];
            }
        }

        return $this->view('Enterum\CharacterProfile:Logs\Listing', 'enterum_char_profile_acp_logs', [
            'rows' => $rows,
            'page' => $page,
            'perPage' => $perPage,
            'total' => $total,
            'sort' => $sort,
            'direction' => $direction,
            'filterParams' => $filterParams,
            'filterType' => $contentType,
            'filterAction' => $actionFilter,
            'filterTargetUser' => $targetUserFilter,
            'filterActorUser' => $actorUserFilter,
            'filterDateFrom' => $dateFromFilter,
            'filterDateTo' => $dateToFilter,
            'contentTypeTabs' => ActionLogDisplay::getContentTypeTabsForView(),
            'actionOptions' => ActionLogDisplay::getActionOptionsForView(),
            'hasActionLogTable' => $hasActionLogTable,
            'loggingEnabled' => (bool)$this->options()->charProfileEnableActionLog,
        ]);
    }

    /**
     * ACP logs: допустимый ключ сортировки (date|target|actor|type).
     */
    protected function resolveSortKey(string $sort): string
    {
        return in_array($sort, ['date', 'target', 'actor', 'type'], true) ? $sort : 'date';
    }

    /**
     * ACP logs / схема: создать xf_char_profile_action_log, если таблицы нет.
     */
    protected function ensureActionLogTableExists(): void
    {
        try {
            if ($this->app->schemaManager()->tableExists('xf_char_profile_action_log')) {
                return;
            }

            $this->app->db()->query(
                "CREATE TABLE IF NOT EXISTS `xf_char_profile_action_log` (
                    `action_log_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                    `target_user_id` INT UNSIGNED NOT NULL,
                    `actor_user_id` INT UNSIGNED NOT NULL DEFAULT 0,
                    `content_type` VARCHAR(50) NOT NULL DEFAULT '',
                    `content_id` INT UNSIGNED NOT NULL DEFAULT 0,
                    `action` VARCHAR(20) NOT NULL DEFAULT '',
                    `old_data` MEDIUMTEXT NULL,
                    `new_data` MEDIUMTEXT NULL,
                    `log_date` INT UNSIGNED NOT NULL DEFAULT 0,
                    PRIMARY KEY (`action_log_id`),
                    KEY `target_user_id` (`target_user_id`),
                    KEY `actor_user_id` (`actor_user_id`),
                    KEY `content_type` (`content_type`),
                    KEY `action` (`action`),
                    KEY `log_date` (`log_date`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
            );
        } catch (\Throwable $e) {
            // DDL может не пройти без привилегий; список покажет предупреждение ниже.
        }
    }

    /**
     * ACP logs: есть ли таблица xf_char_profile_action_log.
     */
    protected function hasActionLogTable(): bool
    {
        try {
            return (bool)$this->app->db()->fetchOne("SHOW TABLES LIKE 'xf_char_profile_action_log'");
        } catch (\Throwable $e) {
            return false;
        }
    }
}
