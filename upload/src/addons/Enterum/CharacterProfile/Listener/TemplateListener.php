<?php

/**
 * Listener / UI: подготовка cpCharProfileTab для шаблонов страниц профиля.
 * member_view и смежные member_* — чтобы вкладки аддона знали активный таб.
 */

namespace Enterum\CharacterProfile\Listener;

use XF\Entity\User;
use XF\Template\Templater as XfTemplater;

class TemplateListener
{
    /** @var string[] Шаблоны профиля участника, куда прокидываем cpCharProfileTab. */
    protected static $memberTemplates = [
        'member_view',
        'member_about',
        'member_recent_content',
        'member_trophies',
        'member_following',
        'member_ignore',
    ];

    /**
     * UI / вкладки: перед рендером member_* задать cpCharProfileTab (пустая строка по умолчанию).
     */
    public static function templaterTemplatePreRender(XfTemplater $templater, $type, &$template, array &$params): void
    {
        if ($type !== 'public' || !in_array($template, self::$memberTemplates, true)) {
            return;
        }

        if (empty($params['user']) || !($params['user'] instanceof User)) {
            return;
        }

        $params['cpCharProfileTab'] = $params['cpCharProfileTab'] ?? '';
    }
}
