<?php

/**
 * Вкладка «Репутация» / блок ОГ: POST добавление / правка / удаление записей.
 * Маршруты: members/{user_id}/reputation/hero/...
 * CSRF + проверка, что hero_log_id принадлежит user_id из URL (защита от IDOR).
 * Логика в HeroPointManager; редирект на #hero.
 */

namespace Enterum\CharacterProfile\Pub\Controller;

use Enterum\CharacterProfile\Service\Hero\HeroPointManager;
use XF\Mvc\ParameterBag;
use XF\Mvc\Reply\AbstractReply;

class Hero extends AbstractProfileAction
{
    /**
     * ОГ: POST — добавить запись очков геройства.
     */
    public function actionAdd(ParameterBag $params): AbstractReply
    {
        $this->assertPostOnly();
        $this->assertValidCsrfToken($this->filter('_xfToken', 'str'));

        $user = $this->loadProfileUser($params);
        $visitor = \XF::visitor();

        return $this->runHeroMutation(function () use ($user, $visitor) {
            /** @var HeroPointManager $manager */
            $manager = $this->service('Enterum\CharacterProfile:Hero\HeroPointManager');
            $manager->addLog($user, $visitor, $this->getHeroInput());
        }, $user);
    }

    /**
     * ОГ: POST — редактировать запись журнала.
     */
    public function actionEdit(ParameterBag $params): AbstractReply
    {
        $this->assertPostOnly();
        $this->assertValidCsrfToken($this->filter('_xfToken', 'str'));

        $user = $this->loadProfileUser($params);
        $visitor = \XF::visitor();
        $log = $this->assertHeroLog($params, $user);

        return $this->runHeroMutation(function () use ($user, $visitor, $log) {
            /** @var HeroPointManager $manager */
            $manager = $this->service('Enterum\CharacterProfile:Hero\HeroPointManager');
            $manager->editLog($user, $visitor, $log, $this->getHeroInput());
        }, $user);
    }

    /**
     * ОГ: POST — удалить запись журнала.
     */
    public function actionDelete(ParameterBag $params): AbstractReply
    {
        $this->assertPostOnly();
        $this->assertValidCsrfToken($this->filter('_xfToken', 'str'));

        $user = $this->loadProfileUser($params);
        $visitor = \XF::visitor();
        $log = $this->assertHeroLog($params, $user);

        return $this->runHeroMutation(function () use ($user, $visitor, $log) {
            /** @var HeroPointManager $manager */
            $manager = $this->service('Enterum\CharacterProfile:Hero\HeroPointManager');
            $manager->deleteLog($user, $visitor, $log);
        }, $user);
    }

    /**
     * ОГ: выполнить мутацию и редирект на members/reputation#hero.
     */
    protected function runHeroMutation(callable $fn, \XF\Entity\User $user): AbstractReply
    {
        try {
            $fn();
        } catch (\XF\PrintableException $e) {
            return $this->error($e->getMessage());
        }

        throw $this->exception(
            $this->redirect($this->buildLink('members/reputation', $user, [], '#hero'))
        );
    }

    /**
     * ОГ: поля формы (дата, gain/loss, amount, источник, is_support).
     */
    protected function getHeroInput(): array
    {
        return [
            'event_date' => $this->filter('event_date', 'str'),
            'operation_type' => $this->filter('operation_type', 'str'),
            'amount' => $this->filter('amount', 'int'),
            'source_url' => $this->filter('source_url', 'str'),
            'source_title' => $this->filter('source_title', 'str'),
            'is_support' => $this->filter('is_support', 'bool'),
        ];
    }

    /**
     * ОГ: запись должна принадлежать user_id из URL (защита от IDOR).
     */
    protected function assertHeroLog(ParameterBag $params, \XF\Entity\User $user)
    {
        $logId = (int)$params->hero_log_id;
        if ($logId <= 0) {
            throw $this->exception($this->notFound());
        }

        /** @var \Enterum\CharacterProfile\Entity\CharProfileHeroLog|null $log */
        $log = $this->em()->find('Enterum\CharacterProfile:CharProfileHeroLog', $logId);
        if (!$log || (int)$log->user_id !== (int)$user->user_id) {
            throw $this->exception($this->notFound());
        }

        return $log;
    }
}
