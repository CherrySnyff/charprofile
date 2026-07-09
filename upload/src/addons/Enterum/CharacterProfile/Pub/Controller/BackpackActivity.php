<?php

/**
 * Рюкзак / «За активности»: POST CRUD xf_char_profile_backpack_activity_item.
 * Право manageBackpack (в ActivityItemManager); редирект на #activity.
 */

namespace Enterum\CharacterProfile\Pub\Controller;

use Enterum\CharacterProfile\Service\Backpack\ActivityItemManager;
use XF\Mvc\ParameterBag;
use XF\Mvc\Reply\AbstractReply;

class BackpackActivity extends AbstractProfileAction
{
    /**
     * Рюкзак / activity: POST — добавить предмет.
     */
    public function actionAdd(ParameterBag $params): AbstractReply
    {
        $this->assertPostOnly();
        $this->assertValidCsrfToken($this->filter('_xfToken', 'str'));

        $user = $this->loadProfileUser($params);
        $visitor = \XF::visitor();

        return $this->runMutation(function () use ($user, $visitor) {
            /** @var ActivityItemManager $manager */
            $manager = $this->service('Enterum\CharacterProfile:Backpack\ActivityItemManager');
            $manager->addItem($user, $visitor, $this->getActivityInput());
        }, $user);
    }

    /**
     * Рюкзак / activity: POST — редактировать предмет.
     */
    public function actionEdit(ParameterBag $params): AbstractReply
    {
        $this->assertPostOnly();
        $this->assertValidCsrfToken($this->filter('_xfToken', 'str'));

        $user = $this->loadProfileUser($params);
        $visitor = \XF::visitor();
        $item = $this->assertActivityItem($params, $user);

        return $this->runMutation(function () use ($user, $visitor, $item) {
            /** @var ActivityItemManager $manager */
            $manager = $this->service('Enterum\CharacterProfile:Backpack\ActivityItemManager');
            $manager->editItem($user, $visitor, $item, $this->getActivityInput());
        }, $user);
    }

    /**
     * Рюкзак / activity: POST — удалить предмет.
     */
    public function actionDelete(ParameterBag $params): AbstractReply
    {
        $this->assertPostOnly();
        $this->assertValidCsrfToken($this->filter('_xfToken', 'str'));

        $user = $this->loadProfileUser($params);
        $visitor = \XF::visitor();
        $item = $this->assertActivityItem($params, $user);

        return $this->runMutation(function () use ($user, $visitor, $item) {
            /** @var ActivityItemManager $manager */
            $manager = $this->service('Enterum\CharacterProfile:Backpack\ActivityItemManager');
            $manager->deleteItem($user, $visitor, $item);
        }, $user);
    }

    /**
     * Рюкзак / activity: выполнить мутацию и редирект на #activity.
     */
    protected function runMutation(callable $fn, \XF\Entity\User $user): AbstractReply
    {
        try {
            $fn();
        } catch (\XF\PrintableException $e) {
            return $this->error($e->getMessage());
        }

        throw $this->exception(
            $this->redirect(
                $this->buildLink('members/backpack', $user, ['sub' => 'activity'], '#activity')
            )
        );
    }

    /**
     * Рюкзак / activity: поля формы предмета.
     *
     * @return array<string, mixed>
     */
    protected function getActivityInput(): array
    {
        return [
            'item_name' => $this->filter('item_name', 'str'),
            'item_url' => $this->filter('item_url', 'str'),
            'item_description' => $this->filter('item_description', 'str'),
            'item_type' => $this->filter('item_type', 'str'),
            'item_level' => $this->filter('item_level', 'uint'),
            'rarity_key' => $this->filter('rarity_key', 'str'),
            'source_url' => $this->filter('source_url', 'str'),
            'source_title' => $this->filter('source_title', 'str'),
            'reason' => $this->filter('reason', 'str'),
            'display_order' => $this->filter('display_order', 'uint'),
        ];
    }

    /**
     * Рюкзак / activity: предмет должен принадлежать user_id из URL (IDOR).
     */
    protected function assertActivityItem(ParameterBag $params, \XF\Entity\User $user)
    {
        $itemId = (int)$params->activity_item_id;
        if ($itemId <= 0) {
            throw $this->exception($this->notFound());
        }

        /** @var \Enterum\CharacterProfile\Entity\CharProfileBackpackActivityItem|null $item */
        $item = $this->em()->find('Enterum\CharacterProfile:CharProfileBackpackActivityItem', $itemId);
        if (!$item || (int)$item->user_id !== (int)$user->user_id) {
            throw $this->exception($this->notFound());
        }

        return $item;
    }
}
