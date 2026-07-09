<?php

/**
 * Вкладка «Рюкзак» / блок «Прочее»: BB-код в xf_char_profile.
 * Хранение backpack_other_bbcode + предрендер backpack_other_rendered.
 * Права: manageBackpack или свой профиль + manageBackpackOwn.
 */

namespace Enterum\CharacterProfile\Service\Backpack;

use Enterum\CharacterProfile\Entity\CharProfile;
use Enterum\CharacterProfile\Helper\BackpackOtherSchema;
use Enterum\CharacterProfile\Helper\BbCodeContent;
use Enterum\CharacterProfile\Service\ActionLogger;
use Enterum\CharacterProfile\Service\PermissionGuard;
use XF\Entity\User;
use XF\Service\AbstractService;

class OtherContentManager extends AbstractService
{
    /**
     * Рюкзак / «Прочее» / UI: BB-код и HTML для отображения блока.
     *
     * @return array<string, mixed>
     */
    public function buildViewData(CharProfile $profile): array
    {
        BackpackOtherSchema::ensureColumns($this->app);
        $profile = $this->reloadProfile($profile);

        $bbcode = (string)$profile->backpack_other_bbcode;
        $rendered = (string)$profile->backpack_other_rendered;
        if ($rendered === '' && $bbcode !== '') {
            $rendered = BbCodeContent::renderToHtml($this->app, $bbcode, true);
        }

        return [
            'backpackOtherBbcode' => $bbcode,
            'backpackOtherHtml' => $rendered,
            'hasBackpackOtherToShow' => trim($bbcode) !== '' || trim($rendered) !== '',
        ];
    }

    /**
     * Рюкзак / «Прочее»: сохранить BB-код, перерендерить HTML, записать audit log.
     */
    public function saveContent(User $profileUser, User $actor, CharProfile $profile, string $bbcode): CharProfile
    {
        $this->assertCanManageOther($actor, $profileUser);
        BackpackOtherSchema::ensureColumns($this->app);
        $profile = $this->reloadProfile($profile);

        $old = [
            'backpack_other_bbcode' => (string)$profile->backpack_other_bbcode,
            'backpack_other_rendered' => (string)$profile->backpack_other_rendered,
        ];

        $bbcode = trim($bbcode);
        $rendered = $bbcode !== '' ? BbCodeContent::renderToHtml($this->app, $bbcode, true) : '';

        $profile->backpack_other_bbcode = $bbcode;
        $profile->backpack_other_rendered = $rendered;
        $profile->backpack_other_update_date = \XF::$time;
        $profile->backpack_other_update_user_id = $actor->user_id;
        $profile->last_update = \XF::$time;
        $profile->save();

        $this->em()->clearEntityCache('Enterum\CharacterProfile:CharProfile', $profile->user_id);

        $this->logAction($profileUser->user_id, $actor->user_id, $old, [
            'backpack_other_bbcode' => $bbcode,
            'backpack_other_rendered' => $rendered,
        ]);

        return $profile;
    }

    /**
     * Рюкзак / «Прочее»: перечитать xf_char_profile из БД (после ensureColumns).
     */
    protected function reloadProfile(CharProfile $profile): CharProfile
    {
        $userId = (int)$profile->user_id;
        $this->em()->clearEntityCache('Enterum\CharacterProfile:CharProfile', $userId);

        /** @var CharProfile|null $fresh */
        $fresh = $this->em()->find('Enterum\CharacterProfile:CharProfile', $userId);

        return $fresh ?: $profile;
    }

    /**
     * Рюкзак / «Прочее» / права: manageBackpack или manageBackpackOwn на своём профиле.
     */
    protected function assertCanManageOther(User $actor, User $profileUser): void
    {
        /** @var PermissionGuard $guard */
        $guard = $this->app->service('Enterum\CharacterProfile:PermissionGuard');
        if (!$guard->canManageBackpackOther($actor, $profileUser)) {
            throw new \XF\PrintableException(\XF::phrase('enterum_char_profile_no_permission'));
        }
    }

    /**
     * ACP logs: аудит правки блока «Прочее» (content_type backpack_other).
     */
    protected function logAction(int $targetUserId, int $actorUserId, ?array $oldData, ?array $newData): void
    {
        /** @var ActionLogger $logger */
        $logger = $this->app->service('Enterum\CharacterProfile:ActionLogger');
        $logger->log('backpack_other', 'edit', $targetUserId, $actorUserId, $targetUserId, $oldData, $newData);
    }
}
