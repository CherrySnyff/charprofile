<?php

/**
 * Рюкзак / «Созданные»: POST CRUD xf_char_profile_backpack_crafted_item.
 * Право manageBackpack (в CraftedItemManager); редирект на #crafted.
 */

namespace Enterum\CharacterProfile\Pub\Controller;

use Enterum\CharacterProfile\Service\Backpack\CraftedItemManager;
use XF\Mvc\ParameterBag;
use XF\Mvc\Reply\AbstractReply;

class BackpackCrafted extends AbstractProfileAction
{
    /**
     * Рюкзак / crafted: POST — добавить созданный предмет.
     */
    public function actionAdd(ParameterBag $params): AbstractReply
    {
        $this->assertPostOnly();
        $this->assertValidCsrfToken($this->filter('_xfToken', 'str'));
        $this->assertRegisteredMutator();

        $user = $this->loadProfileUser($params);
        $visitor = \XF::visitor();

        return $this->runMutation(function () use ($user, $visitor) {
            /** @var CraftedItemManager $manager */
            $manager = $this->service('Enterum\CharacterProfile:Backpack\CraftedItemManager');
            $manager->addItem($user, $visitor, $this->getCraftedInput());
        }, $user);
    }

    /**
     * Рюкзак / crafted: POST — редактировать предмет.
     */
    public function actionEdit(ParameterBag $params): AbstractReply
    {
        $this->assertPostOnly();
        $this->assertValidCsrfToken($this->filter('_xfToken', 'str'));
        $this->assertRegisteredMutator();

        $user = $this->loadProfileUser($params);
        $visitor = \XF::visitor();
        $item = $this->assertCraftedItem($params, $user);

        return $this->runMutation(function () use ($user, $visitor, $item) {
            /** @var CraftedItemManager $manager */
            $manager = $this->service('Enterum\CharacterProfile:Backpack\CraftedItemManager');
            $manager->editItem($user, $visitor, $item, $this->getCraftedInput());
        }, $user);
    }

    /**
     * Рюкзак / crafted: POST — удалить предмет.
     */
    public function actionDelete(ParameterBag $params): AbstractReply
    {
        $this->assertPostOnly();
        $this->assertValidCsrfToken($this->filter('_xfToken', 'str'));
        $this->assertRegisteredMutator();

        $user = $this->loadProfileUser($params);
        $visitor = \XF::visitor();
        $item = $this->assertCraftedItem($params, $user);

        return $this->runMutation(function () use ($user, $visitor, $item) {
            /** @var CraftedItemManager $manager */
            $manager = $this->service('Enterum\CharacterProfile:Backpack\CraftedItemManager');
            $manager->deleteItem($user, $visitor, $item);
        }, $user);
    }

    /**
     * Рюкзак / crafted: выполнить мутацию и редирект на #crafted.
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
                $this->buildLink('members/backpack', $user, ['sub' => 'crafted'], '#crafted')
            )
        );
    }

    /**
     * Рюкзак / crafted: поля формы (включая author_name).
     *
     * @return array<string, mixed>
     */
    protected function getCraftedInput(): array
    {
        return [
            'item_name' => $this->filter('item_name', 'str'),
            'item_url' => $this->filter('item_url', 'str'),
            'item_type' => $this->filter('item_type', 'str'),
            'item_level' => $this->filter('item_level', 'uint'),
            'request_url' => $this->filter('request_url', 'str'),
            'author_name' => $this->filter('author_name', 'str'),
            'display_order' => $this->filter('display_order', 'uint'),
        ];
    }

    /**
     * Рюкзак / crafted: предмет должен принадлежать user_id из URL (IDOR).
     */
    protected function assertCraftedItem(ParameterBag $params, \XF\Entity\User $user)
    {
        $itemId = (int)$params->crafted_item_id;
        if ($itemId <= 0) {
            throw $this->exception($this->notFound());
        }

        /** @var \Enterum\CharacterProfile\Entity\CharProfileBackpackCraftedItem|null $item */
        $item = $this->em()->find('Enterum\CharacterProfile:CharProfileBackpackCraftedItem', $itemId);
        if (!$item || (int)$item->user_id !== (int)$user->user_id) {
            throw $this->exception($this->notFound());
        }

        return $item;
    }
}
