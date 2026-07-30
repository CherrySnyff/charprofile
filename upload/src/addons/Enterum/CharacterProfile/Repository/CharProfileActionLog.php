<?php

/**
 * Repository: SQL-выборка журнала для ACP (xf_char_profile_action_log).
 * JOIN xf_user (target/actor), фильтры, whitelist сортировки — без entity JOIN.
 */

namespace Enterum\CharacterProfile\Repository;

use XF\Mvc\Entity\Repository;

class CharProfileActionLog extends Repository
{
    /**
     * ACP logs: постраничный список с фильтрами и никами target/actor.
     *
     * @return array{rows: array<int, array<string, mixed>>, total: int}
     */
    public function fetchLogsForAdmin(array $filters, int $page, int $perPage): array
    {
        $db = $this->db();
        [$whereSql, $whereParams] = $this->buildAdminFiltersSql($filters);
        $orderBy = $this->buildAdminOrderBy($filters);
        $offset = max(0, ($page - 1) * $perPage);

        $total = (int)$db->fetchOne(
            'SELECT COUNT(*)
             FROM xf_char_profile_action_log AS l
             LEFT JOIN xf_user AS target ON (target.user_id = l.target_user_id)
             LEFT JOIN xf_user AS actor ON (actor.user_id = l.actor_user_id)'
            . $whereSql,
            $whereParams
        );

        $selectParams = $whereParams;
        $selectParams[] = $offset;
        $selectParams[] = $perPage;

        $rows = $db->fetchAll(
            'SELECT l.*, target.username AS target_username, actor.username AS actor_username
             FROM xf_char_profile_action_log AS l
             LEFT JOIN xf_user AS target ON (target.user_id = l.target_user_id)
             LEFT JOIN xf_user AS actor ON (actor.user_id = l.actor_user_id)'
            . $whereSql
            . ' ORDER BY ' . $orderBy
            . ' LIMIT ?, ?',
            $selectParams
        );

        return [
            'rows' => $rows ?: [],
            'total' => $total,
        ];
    }

    public function deleteLogsByIds(array $ids): int
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
        if (!$ids) {
            return 0;
        }

        return $this->db()->delete(
            'xf_char_profile_action_log',
            'action_log_id IN (' . $this->db()->quote($ids) . ')'
        );
    }

    public function deleteAllLogs(): int
    {
        return (int)$this->db()->delete('xf_char_profile_action_log', '1=1');
    }

    /**
     * ACP logs: все записи по фильтрам (без пагинации) для экспорта.
     *
     * @return list<array<string, mixed>>
     */
    public function fetchLogsForExport(array $filters): array
    {
        $db = $this->db();
        [$whereSql, $whereParams] = $this->buildAdminFiltersSql($filters);
        $orderBy = $this->buildAdminOrderBy($filters);

        $rows = $db->fetchAll(
            'SELECT l.*, target.username AS target_username, actor.username AS actor_username
             FROM xf_char_profile_action_log AS l
             LEFT JOIN xf_user AS target ON (target.user_id = l.target_user_id)
             LEFT JOIN xf_user AS actor ON (actor.user_id = l.actor_user_id)'
            . $whereSql
            . ' ORDER BY ' . $orderBy,
            $whereParams
        );

        return $rows ?: [];
    }

    /**
     * ACP logs: WHERE по типу, действию, никам и диапазону дат.
     *
     * @return array{0: string, 1: array<int, mixed>}
     */
    protected function buildAdminFiltersSql(array $filters): array
    {
        $db = $this->db();
        $where = [];
        $params = [];

        $contentType = (string)($filters['content_type'] ?? '');
        if ($contentType !== '') {
            $where[] = 'l.content_type = ?';
            $params[] = $contentType;
        }

        $action = (string)($filters['action'] ?? '');
        if ($action !== '' && in_array($action, ['add', 'edit', 'delete'], true)) {
            $where[] = 'l.action = ?';
            $params[] = $action;
        }

        $targetUser = trim((string)($filters['target_user'] ?? ''));
        if ($targetUser !== '') {
            $where[] = 'target.username LIKE ?';
            $params[] = $db->escapeLike($targetUser, '?%');
        }

        $actorUser = trim((string)($filters['actor_user'] ?? ''));
        if ($actorUser !== '') {
            $where[] = 'actor.username LIKE ?';
            $params[] = $db->escapeLike($actorUser, '?%');
        }

        $dateFrom = (int)($filters['date_from'] ?? 0);
        if ($dateFrom > 0) {
            $where[] = 'l.log_date >= ?';
            $params[] = $dateFrom;
        }

        $dateTo = (int)($filters['date_to'] ?? 0);
        if ($dateTo > 0) {
            $where[] = 'l.log_date <= ?';
            $params[] = $dateTo;
        }

        if (!$where) {
            return ['', []];
        }

        return [' WHERE ' . implode(' AND ', $where), $params];
    }

    /**
     * ACP logs: ORDER BY из whitelist (date|target|actor|type).
     */
    protected function buildAdminOrderBy(array $filters): string
    {
        $sort = (string)($filters['sort'] ?? 'date');
        $direction = strtolower((string)($filters['direction'] ?? 'desc')) === 'asc' ? 'ASC' : 'DESC';

        switch ($sort) {
            case 'target':
                return 'target.username ' . $direction . ', l.log_date DESC, l.action_log_id DESC';
            case 'actor':
                return 'actor.username ' . $direction . ', l.log_date DESC, l.action_log_id DESC';
            case 'type':
                return 'l.content_type ' . $direction . ', l.log_date DESC, l.action_log_id DESC';
            case 'date':
            default:
                return 'l.log_date ' . $direction . ', l.action_log_id DESC';
        }
    }
}
