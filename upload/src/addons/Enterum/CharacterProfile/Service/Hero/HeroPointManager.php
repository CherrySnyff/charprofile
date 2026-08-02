<?php

/**
 * Вкладка «Репутация» / блок ОГ: бизнес-логика очков геройства.
 * CRUD xf_char_profile_hero_log, хронологический пересчёт 0..max, overflow/burned.
 * Право manageHero; записи «за поддержку» — отдельно manageHeroSupport.
 * Кэш итога пишется в xf_char_profile.hero_points_cache.
 */

namespace Enterum\CharacterProfile\Service\Hero;

use Enterum\CharacterProfile\Entity\CharProfile;
use Enterum\CharacterProfile\Entity\CharProfileHeroLog;
use Enterum\CharacterProfile\Service\ActionLogger;
use Enterum\CharacterProfile\Service\PermissionGuard;
use XF\Entity\User;
use XF\Service\AbstractService;

class HeroPointManager extends AbstractService
{
    /**
     * ОГ / опции: максимум очков геройства (charProfileHeroMax, по умолчанию 3).
     */
    public function getHeroMax(): int
    {
        $max = (int)$this->app->options()->charProfileHeroMax;
        return $max > 0 ? $max : 3;
    }

    /**
     * ОГ / UI: постраничный список записей для вкладки репутации (#hero).
     *
     * @return array<int, array<string, mixed>>
     */
    public function getDisplayRows(int $userId, int $page = 1): array
    {
        $perPage = max(1, (int)$this->app->options()->charProfileItemsPerPage);
        $finder = $this->repository('Enterum\CharacterProfile:CharProfileHeroLog')
            ->findLogsForUser($userId);
        $total = $finder->total();
        $logs = $finder->limitByPage($page, $perPage)->fetch();

        $rows = [];
        foreach ($logs as $log) {
            $rows[] = $this->buildDisplayRow($log);
        }

        return [
            'rows' => $rows,
            'total' => $total,
            'page' => $page,
            'perPage' => $perPage,
            'pairs' => $this->pairRows($rows),
        ];
    }

    /**
     * ОГ / UI: сгруппировать строки попарно для сетки шаблона.
     *
     * @param array<int, array<string, mixed>> $rows
     * @return array<int, array<int, array<string, mixed>>>
     */
    public function pairRows(array $rows): array
    {
        $pairs = [];
        $chunk = [];
        foreach ($rows as $row) {
            $chunk[] = $row;
            if (count($chunk) === 2) {
                $pairs[] = $chunk;
                $chunk = [];
            }
        }
        if ($chunk) {
            $pairs[] = $chunk;
        }

        return $pairs;
    }

    /**
     * ОГ: пересчитать overflow/burned по хронологии и обновить hero_points_cache.
     */
    public function recalculateForUser(int $userId): CharProfile
    {
        $max = $this->getHeroMax();
        $this->em()->clearEntityCache('Enterum\CharacterProfile:CharProfileHeroLog');

        $logs = $this->repository('Enterum\CharacterProfile:CharProfileHeroLog')
            ->findLogsForUserAsc($userId)
            ->fetch();

        $running = 0;
        foreach ($logs as $log) {
            $amount = abs((int)$log->amount);
            $signed = $log->operation_type === 'loss' ? -$amount : $amount;

            $before = $running;
            $afterRaw = $before + $signed;
            $afterClamped = max(0, min($max, $afterRaw));

            $burned = 0;
            $isOverflow = false;
            if ($signed > 0 && $afterRaw > $max) {
                $burned = $afterRaw - $max;
                $isOverflow = true;
            }

            $log->is_overflow = $isOverflow ? 1 : 0;
            $log->burned_amount = $burned;
            $log->saveIfChanged();

            $running = $afterClamped;
        }

        /** @var \XF\Entity\User|null $userEntity */
        $userEntity = $this->em()->find('XF:User', $userId);
        if (!$userEntity) {
            throw new \LogicException('Unable to recalculate hero points for missing user ' . $userId);
        }

        /** @var PermissionGuard $guard */
        $guard = $this->app->service('Enterum\CharacterProfile:PermissionGuard');
        $profile = $guard->getOrCreateProfile($userEntity);

        $profile->hero_points_cache = $running;
        $profile->hero_points_raw_sum = $running;
        $profile->last_update = \XF::$time;
        $profile->save();

        \Enterum\CharacterProfile\XF\Entity\User::clearCharacterHeroPointsMemo($userId);

        return $profile;
    }

    /**
     * ОГ: добавить запись в журнал (manageHero; support — manageHeroSupport).
     */
    public function addLog(User $profileUser, User $actor, array $input): CharProfileHeroLog
    {
        $this->assertCanManage($actor, $profileUser);
        $this->assertLossWithinBalance($profileUser, $input);

        /** @var CharProfileHeroLog $log */
        $log = $this->em()->create('Enterum\CharacterProfile:CharProfileHeroLog');
        $this->applyInput($log, $profileUser, $actor, $input, true);
        $log->save();

        $this->recalculateForUser($profileUser->user_id);
        $this->logAction('hero', 'add', $profileUser->user_id, $actor->user_id, $log->hero_log_id, null, $log->toArray());

        return $log;
    }

    /**
     * ОГ: редактировать запись журнала и пересчитать кэш.
     */
    public function editLog(
        User $profileUser,
        User $actor,
        CharProfileHeroLog $log,
        array $input
    ): CharProfileHeroLog {
        $this->assertCanManage($actor, $profileUser);
        if ((int)$log->user_id !== (int)$profileUser->user_id) {
            throw new \XF\PrintableException(\XF::phrase('requested_page_not_found'));
        }

        $this->assertLossWithinBalance($profileUser, $input, $log);

        $old = $log->toArray();
        $this->applyInput($log, $profileUser, $actor, $input, false);
        $log->save();

        $this->recalculateForUser($profileUser->user_id);
        $this->logAction('hero', 'edit', $profileUser->user_id, $actor->user_id, $log->hero_log_id, $old, $log->toArray());

        return $log;
    }

    /**
     * ОГ: удалить запись журнала и пересчитать кэш.
     */
    public function deleteLog(User $profileUser, User $actor, CharProfileHeroLog $log): void
    {
        $this->assertCanManage($actor, $profileUser);
        if ((int)$log->user_id !== (int)$profileUser->user_id) {
            throw new \XF\PrintableException(\XF::phrase('requested_page_not_found'));
        }

        $old = $log->toArray();
        $logId = $log->hero_log_id;
        $userId = (int)$profileUser->user_id;
        $log->delete();

        $this->em()->clearEntityCache('Enterum\CharacterProfile:CharProfileHeroLog', $logId);
        $this->recalculateForUser($userId);
        $this->logAction('hero', 'delete', $profileUser->user_id, $actor->user_id, $logId, $old, null);
    }

    /**
     * ОГ: применить поля формы к сущности (дата, gain/loss/support, источник).
     */
    protected function applyInput(
        CharProfileHeroLog $log,
        User $profileUser,
        User $actor,
        array $input,
        bool $isNew
    ): void {
        $isSupport = !empty($input['is_support']);
        /** @var PermissionGuard $guard */
        $guard = $this->app->service('Enterum\CharacterProfile:PermissionGuard');
        if ($isSupport && !$guard->canManageHeroSupport($actor)) {
            throw new \XF\PrintableException(\XF::phrase('enterum_char_profile_no_permission'));
        }

        $operation = (string)($input['operation_type'] ?? 'gain');
        if ($isSupport) {
            $operation = 'support';
        } elseif (!in_array($operation, ['gain', 'loss'], true)) {
            $operation = 'gain';
        }

        $amount = abs((int)($input['amount'] ?? 0));
        if ($amount < 1 || $amount > 3) {
            throw new \XF\PrintableException(\XF::phrase('enterum_char_profile_hero_amount_invalid'));
        }

        $eventDate = $this->parseEventDate((string)($input['event_date'] ?? ''));
        $sourceUrl = trim((string)($input['source_url'] ?? ''));
        $sourceTitle = trim((string)($input['source_title'] ?? ''));

        if ($operation !== 'support') {
            if ($sourceUrl === '' || $sourceTitle === '') {
                throw new \XF\PrintableException(\XF::phrase('enterum_char_profile_hero_source_required'));
            }
        }

        $log->user_id = $profileUser->user_id;
        $log->event_date = $eventDate;
        $log->operation_type = $operation;
        $log->amount = $amount;
        $log->source_url = $sourceUrl;
        $log->source_title = $sourceTitle;
        $log->is_support = $operation === 'support' ? 1 : 0;

        if ($isNew) {
            $log->created_by_user_id = $actor->user_id;
            $log->created_date = \XF::$time;
        }
        $log->last_edit_user_id = $actor->user_id;
        $log->last_edit_date = \XF::$time;
    }

    /**
     * ОГ: разобрать дату события (дд.мм.гггг → Y-m-d для БД).
     */
    public function parseEventDate(string $raw): string
    {
        $raw = trim($raw);
        if ($raw === '') {
            return date('Y-m-d');
        }

        if (preg_match('/^(\d{2})\.(\d{2})\.(\d{4})$/', $raw, $m)) {
            return sprintf('%04d-%02d-%02d', (int)$m[3], (int)$m[2], (int)$m[1]);
        }

        throw new \XF\PrintableException(\XF::phrase('enterum_char_profile_hero_date_invalid'));
    }

    /**
     * ОГ / UI: дата события в формате дд.мм.гггг для отображения.
     */
    public function formatEventDate($value): string
    {
        if (!$value) {
            return date('d.m.Y');
        }

        $ts = is_numeric($value) ? (int)$value : strtotime((string)$value);
        if (!$ts) {
            return date('d.m.Y');
        }

        return date('d.m.Y', $ts);
    }

    /**
     * ОГ / UI: одна строка журнала для шаблона (CSS, подпись, поля формы).
     */
    protected function buildDisplayRow(CharProfileHeroLog $log): array
    {
        $cssClass = 'cp-heroLogEntry';
        if ($log->operation_type === 'loss') {
            $cssClass .= ' cp-heroLogEntry--loss';
        } elseif ($log->is_overflow) {
            $cssClass .= ' cp-heroLogEntry--overflow';
        }

        $label = $this->buildLabel($log);

        return [
            'log' => $log,
            'hero_log_id' => $log->hero_log_id,
            'cssClass' => $cssClass,
            'labelHtml' => $label,
            'event_date_display' => $this->formatEventDate($log->event_date),
            'event_date_value' => $this->formatEventDate($log->event_date),
            'operation_type' => $log->operation_type === 'loss' ? 'loss' : 'gain',
            'amount' => (int)$log->amount,
            'source_url' => $log->source_url,
            'source_title' => $log->source_title,
            'is_support' => (bool)$log->is_support,
            'is_overflow' => (bool)$log->is_overflow,
        ];
    }

    /**
     * ОГ / UI: HTML-подпись записи («Получено/Потрачено N ОГ» + ссылка).
     */
    protected function buildLabel(CharProfileHeroLog $log): string
    {
        $date = htmlspecialchars($this->formatEventDate($log->event_date), ENT_QUOTES, 'UTF-8');
        $amount = (int)$log->amount;
        $dateHtml = '<strong class="cp-heroLogDate">' . $date . '</strong>';
        $amountHtml = '<strong class="cp-heroLogAmount">' . $amount . ' ОГ</strong>';

        if ($log->operation_type === 'support' || $log->is_support) {
            return $dateHtml . ' - Получено ' . $amountHtml . ' за поддержку';
        }

        if ($log->operation_type === 'loss') {
            $text = $dateHtml . ' - Потрачено ' . $amountHtml;
        } else {
            $text = $dateHtml . ' - Получено ' . $amountHtml;
        }

        if ($log->source_title !== '') {
            if ($log->source_url !== '') {
                $text .= ' <a class="cp-heroLogLink" href="' . htmlspecialchars($log->source_url, ENT_QUOTES, 'UTF-8') . '" target="_blank" rel="noopener">'
                    . htmlspecialchars($log->source_title, ENT_QUOTES, 'UTF-8') . '</a>';
            } else {
                $text .= ' ' . htmlspecialchars($log->source_title, ENT_QUOTES, 'UTF-8');
            }
        }

        return $text;
    }

    /**
     * ОГ: текущий баланс после хронологического пересчёта (опционально без одной записи).
     */
    public function getCurrentBalance(int $userId, ?int $excludeLogId = null): int
    {
        $max = $this->getHeroMax();
        $logs = $this->repository('Enterum\CharacterProfile:CharProfileHeroLog')
            ->findLogsForUserAsc($userId)
            ->fetch();

        $running = 0;
        foreach ($logs as $log) {
            if ($excludeLogId !== null && (int)$log->hero_log_id === $excludeLogId) {
                continue;
            }

            $amount = abs((int)$log->amount);
            $signed = $log->operation_type === 'loss' ? -$amount : $amount;
            $running = max(0, min($max, $running + $signed));
        }

        return $running;
    }

    /**
     * ОГ / UI: актуальный баланс напрямую из журнала (обход entity-кэша).
     * Только чтение — кэш xf_char_profile здесь не пишем (запись только в recalculateForUser).
     */
    public function getLiveBalance(int $userId): int
    {
        $max = $this->getHeroMax();
        $rows = $this->db()->fetchAll(
            'SELECT amount, operation_type
             FROM xf_char_profile_hero_log
             WHERE user_id = ?
             ORDER BY event_date ASC, created_date ASC, hero_log_id ASC',
            [$userId]
        );

        $running = 0;
        foreach ($rows as $row) {
            $amount = abs((int)$row['amount']);
            $signed = ((string)$row['operation_type'] === 'loss') ? -$amount : $amount;
            $running = max(0, min($max, $running + $signed));
        }

        return $running;
    }

    /**
     * ОГ: запретить трату больше текущего баланса (ОГ не уходят в минус).
     */
    protected function assertLossWithinBalance(User $profileUser, array $input, ?CharProfileHeroLog $editingLog = null): void
    {
        $isSupport = !empty($input['is_support']);
        $operation = (string)($input['operation_type'] ?? 'gain');
        if ($isSupport) {
            return;
        }
        if ($operation !== 'loss') {
            return;
        }

        $amount = abs((int)($input['amount'] ?? 0));
        if ($amount < 1) {
            return;
        }

        $excludeId = $editingLog ? (int)$editingLog->hero_log_id : null;
        $balance = $this->getCurrentBalance((int)$profileUser->user_id, $excludeId);
        if ($amount > $balance) {
            throw new \XF\PrintableException(\XF::phrase('enterum_char_profile_hero_loss_exceeds_balance'));
        }
    }

    /**
     * ОГ / права: manageHero или manageHeroOwn на своём профиле.
     */
    protected function assertCanManage(User $actor, User $profileUser): void
    {
        /** @var PermissionGuard $guard */
        $guard = $this->app->service('Enterum\CharacterProfile:PermissionGuard');
        if (!$guard->canManageHero($actor, $profileUser)) {
            throw new \XF\PrintableException(\XF::phrase('enterum_char_profile_no_permission'));
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
