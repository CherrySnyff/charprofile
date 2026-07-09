<?php

/**
 * ACP / аудит: запись в xf_char_profile_action_log (add/edit/delete).
 * Вызывается менеджерами ОГ, репутации и рюкзака после успешных мутаций.
 * Уважает опцию charProfileEnableActionLog.
 */

namespace Enterum\CharacterProfile\Service;

use XF\Service\AbstractService;

class ActionLogger extends AbstractService
{
    /**
     * ACP logs: вставить строку аудита, если логирование включено.
     *
     * @param string $contentType hero|reputation|backpack_activity|backpack_crafted|backpack_other
     * @param string $action add|edit|delete
     */
    public function log(
        string $contentType,
        string $action,
        int $targetUserId,
        int $actorUserId,
        int $contentId,
        ?array $oldData,
        ?array $newData
    ): void {
        if (!(bool)$this->app->options()->charProfileEnableActionLog) {
            return;
        }

        $this->db()->insert('xf_char_profile_action_log', [
            'target_user_id' => $targetUserId,
            'actor_user_id' => $actorUserId,
            'content_type' => $contentType,
            'content_id' => $contentId,
            'action' => $action,
            'old_data' => $oldData ? json_encode($oldData, JSON_UNESCAPED_UNICODE) : null,
            'new_data' => $newData ? json_encode($newData, JSON_UNESCAPED_UNICODE) : null,
            'log_date' => \XF::$time,
        ]);
    }
}
