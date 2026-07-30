<?php

/**
 * Вкладка «Рюкзак» / блок «За активности»: CRUD xf_char_profile_backpack_activity_item.
 * Право manageBackpack; редкость common|uncommon|rare|unique.
 */

namespace Enterum\CharacterProfile\Service\Backpack;

use Enterum\CharacterProfile\Entity\CharProfileBackpackActivityItem;
use Enterum\CharacterProfile\Service\ActionLogger;
use Enterum\CharacterProfile\Service\PermissionGuard;
use XF\Entity\User;
use XF\Service\AbstractService;

class ActivityItemManager extends AbstractService
{
    /**
     * Рюкзак / activity: нормализовать ключ редкости.
     */
    public static function resolveRarity(string $rarity): string
    {
        return in_array($rarity, ['common', 'uncommon', 'rare', 'unique'], true) ? $rarity : 'common';
    }

    /**
     * Рюкзак / activity / UI: постраничный список предметов для вкладки.
     *
     * @return array{rows: array<int, array<string, mixed>>, total: int, page: int, perPage: int}
     */
    public function buildViewData(int $userId, int $page = 1): array
    {
        $perPage = max(1, (int)$this->app->options()->charProfileItemsPerPage);
        $finder = $this->repository('Enterum\CharacterProfile:CharProfileBackpackActivityItem')
            ->findItemsForUser($userId);
        $total = $finder->total();
        $items = $finder->limitByPage($page, $perPage)->fetch();

        $rows = [];
        foreach ($items as $item) {
            $rows[] = $this->buildDisplayRow($item);
        }

        return [
            'rows' => $rows,
            'total' => $total,
            'page' => $page,
            'perPage' => $perPage,
        ];
    }

    /**
     * Рюкзак / activity / UI: одна строка предмета для шаблона.
     *
     * @return array<string, mixed>
     */
    public function buildDisplayRow(CharProfileBackpackActivityItem $item): array
    {
        $reason = trim((string)$item->reason);
        $sourceTitle = trim((string)$item->source_title);
        $reasonText = $reason !== '' ? $reason : $sourceTitle;
        $sourceUrl = trim((string)$item->source_url);

        return [
            'activity_item_id' => (int)$item->activity_item_id,
            'item_name' => (string)$item->item_name,
            'item_url' => (string)$item->item_url,
            'item_type' => (string)$item->item_type,
            'item_level' => (int)$item->item_level,
            'item_description' => (string)$item->item_description,
            'rarity_key' => (string)$item->rarity_key,
            'reason_text' => $reasonText,
            'source_url' => $sourceUrl,
            'reason' => $reason,
            'source_title' => $sourceTitle,
            'display_order' => (int)$item->display_order,
            'entity' => $item,
        ];
    }

    /**
     * Рюкзак / activity: добавить предмет (manageBackpack).
     */
    public function addItem(User $profileUser, User $actor, array $input): CharProfileBackpackActivityItem
    {
        $this->assertCanManage($actor, $profileUser);

        /** @var CharProfileBackpackActivityItem $item */
        $item = $this->em()->create('Enterum\CharacterProfile:CharProfileBackpackActivityItem');
        $this->applyInput($item, $profileUser, $actor, $input, true);
        $item->save();

        $this->logAction('backpack_activity', 'add', $profileUser->user_id, $actor->user_id, $item->activity_item_id, null, $item->toArray());

        return $item;
    }

    /**
     * Рюкзак / activity: редактировать предмет.
     */
    public function editItem(
        User $profileUser,
        User $actor,
        CharProfileBackpackActivityItem $item,
        array $input
    ): CharProfileBackpackActivityItem {
        $this->assertCanManage($actor, $profileUser);
        $this->assertItemOwner($profileUser, $item);

        $old = $item->toArray();
        $this->applyInput($item, $profileUser, $actor, $input, false);
        $item->save();

        $this->logAction('backpack_activity', 'edit', $profileUser->user_id, $actor->user_id, $item->activity_item_id, $old, $item->toArray());

        return $item;
    }

    /**
     * Рюкзак / activity: удалить предмет.
     */
    public function deleteItem(User $profileUser, User $actor, CharProfileBackpackActivityItem $item): void
    {
        $this->assertCanManage($actor, $profileUser);
        $this->assertItemOwner($profileUser, $item);

        $old = $item->toArray();
        $itemId = $item->activity_item_id;
        $item->delete();

        $this->em()->clearEntityCache('Enterum\CharacterProfile:CharProfileBackpackActivityItem', $itemId);
        $this->logAction('backpack_activity', 'delete', $profileUser->user_id, $actor->user_id, $itemId, $old, null);
    }

    /**
     * Рюкзак / activity: применить поля формы к сущности.
     */
    protected function applyInput(
        CharProfileBackpackActivityItem $item,
        User $profileUser,
        User $actor,
        array $input,
        bool $isNew
    ): void {
        $item->user_id = $profileUser->user_id;
        $item->item_name = trim((string)($input['item_name'] ?? ''));
        $item->item_url = trim((string)($input['item_url'] ?? ''));
        $item->item_description = trim((string)($input['item_description'] ?? ''));
        $item->item_type = trim((string)($input['item_type'] ?? ''));
        $item->item_level = max(0, (int)($input['item_level'] ?? 0));
        $item->rarity_key = self::resolveRarity((string)($input['rarity_key'] ?? 'common'));
        $item->source_url = trim((string)($input['source_url'] ?? ''));
        $item->source_title = trim((string)($input['source_title'] ?? ''));
        $item->reason = trim((string)($input['reason'] ?? ''));
        $item->display_order = max(0, (int)($input['display_order'] ?? 0));

        if ($isNew) {
            $item->created_by_user_id = $actor->user_id;
            $item->created_date = \XF::$time;
        }
        $item->last_edit_user_id = $actor->user_id;
        $item->last_edit_date = \XF::$time;
    }

    /**
     * Рюкзак / activity: предмет должен принадлежать владельцу профиля из URL.
     */
    protected function assertItemOwner(User $profileUser, CharProfileBackpackActivityItem $item): void
    {
        if ((int)$item->user_id !== (int)$profileUser->user_id) {
            throw new \XF\PrintableException(\XF::phrase('requested_page_not_found'));
        }
    }

    /**
     * Рюкзак / права: manageBackpack или manageBackpackOwn на своём профиле.
     */
    protected function assertCanManage(User $actor, User $profileUser): void
    {
        /** @var PermissionGuard $guard */
        $guard = $this->app->service('Enterum\CharacterProfile:PermissionGuard');
        if (!$guard->canManageBackpack($actor, $profileUser)) {
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
