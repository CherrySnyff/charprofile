<?php

/**
 * Вкладка «Рюкзак» / блок «Созданные»: CRUD xf_char_profile_backpack_crafted_item.
 * Автор предмета резолвится по @нику (XF:User); право manageBackpack.
 */

namespace Enterum\CharacterProfile\Service\Backpack;

use Enterum\CharacterProfile\Entity\CharProfileBackpackCraftedItem;
use Enterum\CharacterProfile\Service\ActionLogger;
use Enterum\CharacterProfile\Service\PermissionGuard;
use XF\Entity\User;
use XF\Service\AbstractService;

class CraftedItemManager extends AbstractService
{
    /**
     * Рюкзак / crafted / UI: постраничный список созданных предметов.
     *
     * @return array{rows: array<int, array<string, mixed>>, total: int, page: int, perPage: int}
     */
    public function buildViewData(int $userId, int $page = 1): array
    {
        $perPage = max(1, (int)$this->app->options()->charProfileItemsPerPage);
        $finder = $this->repository('Enterum\CharacterProfile:CharProfileBackpackCraftedItem')
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
     * Рюкзак / crafted / UI: одна строка + резолв автора по нику.
     *
     * @return array<string, mixed>
     */
    public function buildDisplayRow(CharProfileBackpackCraftedItem $item): array
    {
        $authorName = trim((string)$item->author_name);
        $authorDisplay = $authorName;
        if ($authorName !== '' && $authorName[0] !== '@') {
            $authorDisplay = '@' . $authorName;
        }

        $authorUser = $this->findAuthorUser($authorName);

        return [
            'crafted_item_id' => (int)$item->crafted_item_id,
            'item_name' => (string)$item->item_name,
            'item_url' => (string)$item->item_url,
            'item_type' => (string)$item->item_type,
            'item_level' => (int)$item->item_level,
            'request_url' => (string)$item->request_url,
            'author_name' => $authorName,
            'author_display' => $authorDisplay,
            'author_user' => $authorUser,
            'display_order' => (int)$item->display_order,
            'entity' => $item,
        ];
    }

    /**
     * Рюкзак / crafted: найти XF:User по author_name (@ник, без учёта регистра).
     */
    protected function findAuthorUser(string $authorName): ?\XF\Entity\User
    {
        $lookup = ltrim(trim($authorName), '@');
        if ($lookup === '') {
            return null;
        }

        /** @var \XF\Entity\User|null $user */
        $user = $this->em()->findOne('XF:User', ['username' => $lookup]);
        if ($user) {
            return $user;
        }

        return $this->finder('XF:User')
            ->whereSql('LOWER(username) = LOWER(?)', [$lookup])
            ->fetchOne();
    }

    /**
     * Рюкзак / crafted: добавить предмет (manageBackpack).
     */
    public function addItem(User $profileUser, User $actor, array $input): CharProfileBackpackCraftedItem
    {
        $this->assertCanManage($actor);

        /** @var CharProfileBackpackCraftedItem $item */
        $item = $this->em()->create('Enterum\CharacterProfile:CharProfileBackpackCraftedItem');
        $this->applyInput($item, $profileUser, $actor, $input, true);
        $item->save();

        $this->logAction('backpack_crafted', 'add', $profileUser->user_id, $actor->user_id, $item->crafted_item_id, null, $item->toArray());

        return $item;
    }

    /**
     * Рюкзак / crafted: редактировать предмет.
     */
    public function editItem(
        User $profileUser,
        User $actor,
        CharProfileBackpackCraftedItem $item,
        array $input
    ): CharProfileBackpackCraftedItem {
        $this->assertCanManage($actor);
        $this->assertItemOwner($profileUser, $item);

        $old = $item->toArray();
        $this->applyInput($item, $profileUser, $actor, $input, false);
        $item->save();

        $this->logAction('backpack_crafted', 'edit', $profileUser->user_id, $actor->user_id, $item->crafted_item_id, $old, $item->toArray());

        return $item;
    }

    /**
     * Рюкзак / crafted: удалить предмет.
     */
    public function deleteItem(User $profileUser, User $actor, CharProfileBackpackCraftedItem $item): void
    {
        $this->assertCanManage($actor);
        $this->assertItemOwner($profileUser, $item);

        $old = $item->toArray();
        $itemId = $item->crafted_item_id;
        $item->delete();

        $this->em()->clearEntityCache('Enterum\CharacterProfile:CharProfileBackpackCraftedItem', $itemId);
        $this->logAction('backpack_crafted', 'delete', $profileUser->user_id, $actor->user_id, $itemId, $old, null);
    }

    /**
     * Рюкзак / crafted: применить поля формы (автор без ведущего @).
     */
    protected function applyInput(
        CharProfileBackpackCraftedItem $item,
        User $profileUser,
        User $actor,
        array $input,
        bool $isNew
    ): void {
        $item->user_id = $profileUser->user_id;
        $item->item_name = trim((string)($input['item_name'] ?? ''));
        $item->item_url = trim((string)($input['item_url'] ?? ''));
        $item->item_type = trim((string)($input['item_type'] ?? ''));
        $item->item_level = max(0, (int)($input['item_level'] ?? 0));
        $item->request_url = trim((string)($input['request_url'] ?? ''));
        $authorName = trim((string)($input['author_name'] ?? ''));
        $item->author_name = ltrim($authorName, '@');
        $item->display_order = max(0, (int)($input['display_order'] ?? 0));

        if ($isNew) {
            $item->created_by_user_id = $actor->user_id;
            $item->created_date = \XF::$time;
        }
        $item->last_edit_user_id = $actor->user_id;
        $item->last_edit_date = \XF::$time;
    }

    /**
     * Рюкзак / crafted: предмет должен принадлежать владельцу профиля из URL.
     */
    protected function assertItemOwner(User $profileUser, CharProfileBackpackCraftedItem $item): void
    {
        if ((int)$item->user_id !== (int)$profileUser->user_id) {
            throw new \XF\PrintableException(\XF::phrase('requested_page_not_found'));
        }
    }

    /**
     * Рюкзак / права: жёсткая проверка manageBackpack.
     */
    protected function assertCanManage(User $actor): void
    {
        /** @var PermissionGuard $guard */
        $guard = $this->app->service('Enterum\CharacterProfile:PermissionGuard');
        if (!$guard->canManageBackpack($actor)) {
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
