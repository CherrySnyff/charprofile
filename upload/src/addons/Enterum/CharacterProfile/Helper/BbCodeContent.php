<?php

/**
 * Рюкзак / «Прочее»: рендер BB-кода в HTML через стандартный движок XenForo.
 * Используется OtherContentManager при сохранении и отображении блока.
 */

namespace Enterum\CharacterProfile\Helper;

class BbCodeContent
{
    /**
     * Рюкзак / «Прочее»: BB-код → HTML; fallback на nl2br при ошибке рендера.
     */
    public static function renderToHtml(\XF\App $app, string $message, bool $userContentContext = false): string
    {
        if (trim($message) === '') {
            return '';
        }

        $bb = $app->bbCode();
        $visitor = \XF::visitor();

        $generic = static function () use ($bb, $message) {
            return (string)$bb->render($message, 'html', '', null);
        };
        $asUser = static function () use ($bb, $message, $visitor) {
            return (string)$bb->render($message, 'html', 'user', $visitor);
        };
        $candidates = $userContentContext
            ? [$asUser, $generic]
            : [$generic, $asUser];

        $last = null;
        foreach ($candidates as $c) {
            try {
                return $c();
            } catch (\Throwable $e) {
                $last = $e;
            }
        }

        if ($app->config('debug') && $last) {
            throw $last;
        }

        return '<div class="bbWrapper bbWrapper--plain">'
            . nl2br(htmlspecialchars($message, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'))
            . '</div>';
    }
}
