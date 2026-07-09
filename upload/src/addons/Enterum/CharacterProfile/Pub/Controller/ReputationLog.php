<?php

/**
 * Вкладка «Репутация»: POST CRUD журнала xf_char_profile_reputation_log.
 * Право manageReputation (в ReputationLogManager); редирект на #reputation с ?rep=.
 */

namespace Enterum\CharacterProfile\Pub\Controller;

use Enterum\CharacterProfile\Service\Reputation\ReputationLogManager;
use XF\Mvc\ParameterBag;
use XF\Mvc\Reply\AbstractReply;

class ReputationLog extends AbstractProfileAction
{
    /**
     * Репутация: POST — добавить запись журнала.
     */
    public function actionAdd(ParameterBag $params): AbstractReply
    {
        $this->assertPostOnly();
        $this->assertValidCsrfToken($this->filter('_xfToken', 'str'));

        $user = $this->loadProfileUser($params);
        $visitor = \XF::visitor();
        $input = $this->getRepInput();
        $repRegion = ReputationLogManager::resolveRegion($input['region_key']);

        return $this->runRepMutation(function () use ($user, $visitor, $input) {
            /** @var ReputationLogManager $manager */
            $manager = $this->service('Enterum\CharacterProfile:Reputation\ReputationLogManager');
            $manager->addLog($user, $visitor, $input);
        }, $user, $repRegion);
    }

    /**
     * Репутация: POST — редактировать запись журнала.
     */
    public function actionEdit(ParameterBag $params): AbstractReply
    {
        $this->assertPostOnly();
        $this->assertValidCsrfToken($this->filter('_xfToken', 'str'));

        $user = $this->loadProfileUser($params);
        $visitor = \XF::visitor();
        $log = $this->assertReputationLog($params, $user);
        $input = $this->getRepInput();
        $repRegion = ReputationLogManager::resolveRegion($input['region_key']);

        return $this->runRepMutation(function () use ($user, $visitor, $log, $input) {
            /** @var ReputationLogManager $manager */
            $manager = $this->service('Enterum\CharacterProfile:Reputation\ReputationLogManager');
            $manager->editLog($user, $visitor, $log, $input);
        }, $user, $repRegion);
    }

    /**
     * Репутация: POST — удалить запись журнала.
     */
    public function actionDelete(ParameterBag $params): AbstractReply
    {
        $this->assertPostOnly();
        $this->assertValidCsrfToken($this->filter('_xfToken', 'str'));

        $user = $this->loadProfileUser($params);
        $visitor = \XF::visitor();
        $log = $this->assertReputationLog($params, $user);
        $repRegion = ReputationLogManager::resolveRegion($this->filter('rep', 'str'));

        return $this->runRepMutation(function () use ($user, $visitor, $log) {
            /** @var ReputationLogManager $manager */
            $manager = $this->service('Enterum\CharacterProfile:Reputation\ReputationLogManager');
            $manager->deleteLog($user, $visitor, $log);
        }, $user, $repRegion);
    }

    /**
     * Репутация: выполнить мутацию и редирект на #reputation выбранного региона.
     */
    protected function runRepMutation(callable $fn, \XF\Entity\User $user, string $repRegion): AbstractReply
    {
        try {
            $fn();
        } catch (\XF\PrintableException $e) {
            return $this->error($e->getMessage());
        }

        throw $this->exception(
            $this->redirect(
                $this->buildLink('members/reputation', $user, ['rep' => $repRegion], '#reputation')
            )
        );
    }

    /**
     * Репутация: поля формы (регион, источник, amount, фракция).
     */
    protected function getRepInput(): array
    {
        return [
            'region_key' => $this->filter('region_key', 'str'),
            'source_url' => $this->filter('source_url', 'str'),
            'source_title' => $this->filter('source_title', 'str'),
            'reputation_amount' => $this->filter('reputation_amount', 'int'),
            'faction_name' => $this->filter('faction_name', 'str'),
        ];
    }

    /**
     * Репутация: запись должна принадлежать user_id из URL (защита от IDOR).
     */
    protected function assertReputationLog(ParameterBag $params, \XF\Entity\User $user)
    {
        $logId = (int)$params->reputation_log_id;
        if ($logId <= 0) {
            throw $this->exception($this->notFound());
        }

        /** @var \Enterum\CharacterProfile\Entity\CharProfileReputationLog|null $log */
        $log = $this->em()->find('Enterum\CharacterProfile:CharProfileReputationLog', $logId);
        if (!$log || (int)$log->user_id !== (int)$user->user_id) {
            throw $this->exception($this->notFound());
        }

        return $log;
    }
}
