<?php

/**
 * ACP logs / UI: подписи вкладок фильтров и краткий текст «Детали» по content_type.
 * Форматирует снимки old/new_data для hero, reputation, backpack_*, character_sheet.
 */

namespace Enterum\CharacterProfile\Helper;

class ActionLogDisplay
{
    /**
     * ACP logs: карта вкладок фильтра по content_type → phrase.
     */
    public static function getContentTypeTabs(): array
    {
        return [
            '' => 'enterum_char_profile_log_tab_all',
            'hero' => 'enterum_char_profile_log_tab_hero',
            'reputation' => 'enterum_char_profile_log_tab_reputation',
            'backpack_activity' => 'enterum_char_profile_log_tab_backpack_activity',
            'backpack_crafted' => 'enterum_char_profile_log_tab_backpack_crafted',
            'backpack_other' => 'enterum_char_profile_log_tab_backpack_other',
            'character_sheet' => 'enterum_char_profile_log_tab_character_sheet',
        ];
    }

    /**
     * ACP logs: опции фильтра действия (add/edit/delete) → phrase.
     */
    public static function getActionOptions(): array
    {
        return [
            '' => 'enterum_char_profile_log_action_all',
            'add' => 'enterum_char_profile_log_action_add',
            'edit' => 'enterum_char_profile_log_action_edit',
            'delete' => 'enterum_char_profile_log_action_delete',
        ];
    }

    /**
     * ACP logs / UI: вкладки типов с уже разрешёнными phrase-лейблами.
     */
    public static function getContentTypeTabsForView(): array
    {
        $tabs = [];
        foreach (self::getContentTypeTabs() as $key => $phraseName) {
            $tabs[] = [
                'key' => $key,
                'label' => (string)\XF::phrase($phraseName),
            ];
        }

        return $tabs;
    }

    /**
     * ACP logs / UI: опции действий с уже разрешёнными phrase-лейблами.
     */
    public static function getActionOptionsForView(): array
    {
        $options = [];
        foreach (self::getActionOptions() as $key => $phraseName) {
            $options[] = [
                'key' => $key,
                'label' => (string)\XF::phrase($phraseName),
            ];
        }

        return $options;
    }

    /**
     * ACP logs: краткая строка «Детали» по типу контента и снимкам данных.
     */
    public static function formatDetails(string $contentType, string $action, ?array $oldData, ?array $newData): string
    {
        $data = $newData ?: $oldData ?: [];

        switch ($contentType) {
            case 'hero':
                return self::formatHeroDetails($action, $oldData, $newData, $data);
            case 'reputation':
                return self::formatReputationDetails($action, $oldData, $newData, $data);
            case 'backpack_activity':
                return self::formatActivityDetails($action, $oldData, $newData, $data);
            case 'backpack_crafted':
                return self::formatCraftedDetails($action, $oldData, $newData, $data);
            case 'backpack_other':
                return 'Блок «Прочее»';
            case 'character_sheet':
                return 'Лист персонажа';
            default:
                return self::formatJsonPreview($oldData, $newData);
        }
    }

    /**
     * ACP logs / ОГ: детали записи журнала геройства.
     */
    protected static function formatHeroDetails(string $action, ?array $oldData, ?array $newData, array $data): string
    {
        $parts = [];
        $source = $newData ?: $oldData ?: $data;

        if (!empty($source['operation_type'])) {
            $parts[] = 'тип: ' . $source['operation_type'];
        }
        if (isset($source['amount'])) {
            $parts[] = 'кол-во: ' . (int)$source['amount'];
        }
        if (!empty($source['event_date'])) {
            $parts[] = 'дата события: ' . $source['event_date'];
        }
        if (!empty($source['source_title'])) {
            $parts[] = $source['source_title'];
        }
        if (!empty($source['source_url'])) {
            $parts[] = self::truncate((string)$source['source_url'], 80);
        }

        if ($action === 'edit' && $oldData && $newData) {
            $diff = self::formatEditDiff($oldData, $newData);
            if ($diff !== '') {
                $parts[] = $diff;
            }
        }

        return $parts ? implode('; ', $parts) : self::formatJsonPreview($oldData, $newData);
    }

    /**
     * ACP logs / репутация: детали записи журнала репутации.
     */
    protected static function formatReputationDetails(string $action, ?array $oldData, ?array $newData, array $data): string
    {
        $source = $newData ?: $oldData ?: $data;
        $parts = [];

        if (!empty($source['faction_id'])) {
            $parts[] = 'фракция #' . (int)$source['faction_id'];
        }
        if (!empty($source['faction_name'])) {
            $parts[] = (string)$source['faction_name'];
        }
        if (!empty($source['region_id'])) {
            $parts[] = 'регион #' . (int)$source['region_id'];
        }
        if (!empty($source['region_key'])) {
            $parts[] = (string)$source['region_key'];
        }
        if (!empty($source['character_name'])) {
            $parts[] = (string)$source['character_name'];
        }
        if (isset($source['amount'])) {
            $parts[] = 'сумма: ' . (int)$source['amount'];
        }
        if (!empty($source['event_date'])) {
            $parts[] = 'дата: ' . $source['event_date'];
        }

        if ($action === 'edit' && $oldData && $newData) {
            $diff = self::formatEditDiff($oldData, $newData);
            if ($diff !== '') {
                $parts[] = $diff;
            }
        }

        return $parts ? implode('; ', $parts) : self::formatJsonPreview($oldData, $newData);
    }

    /**
     * ACP logs / рюкзак activity: детали предмета за активность.
     */
    protected static function formatActivityDetails(string $action, ?array $oldData, ?array $newData, array $data): string
    {
        $source = $newData ?: $oldData ?: $data;
        $parts = [];

        if (!empty($source['item_name'])) {
            $parts[] = $source['item_name'];
        }
        if (!empty($source['item_rarity'])) {
            $parts[] = $source['item_rarity'];
        }
        if (isset($source['item_level'])) {
            $parts[] = 'ур. ' . (int)$source['item_level'];
        }

        return $parts ? implode('; ', $parts) : self::formatJsonPreview($oldData, $newData);
    }

    /**
     * ACP logs / рюкзак crafted: детали созданного предмета.
     */
    protected static function formatCraftedDetails(string $action, ?array $oldData, ?array $newData, array $data): string
    {
        $source = $newData ?: $oldData ?: $data;
        $parts = [];

        if (!empty($source['item_name'])) {
            $parts[] = $source['item_name'];
        }
        if (!empty($source['item_type'])) {
            $parts[] = $source['item_type'];
        }
        if (isset($source['item_level'])) {
            $parts[] = 'ур. ' . (int)$source['item_level'];
        }
        if (!empty($source['author_username'])) {
            $parts[] = '@' . $source['author_username'];
        }

        return $parts ? implode('; ', $parts) : self::formatJsonPreview($oldData, $newData);
    }

    /**
     * ACP logs: запасной превью JSON old/new, если нет типизированных полей.
     */
    protected static function formatJsonPreview(?array $oldData, ?array $newData): string
    {
        $chunks = [];

        if ($oldData) {
            $chunks[] = 'было: ' . self::truncate(json_encode($oldData, JSON_UNESCAPED_UNICODE), 120);
        }
        if ($newData) {
            $chunks[] = 'стало: ' . self::truncate(json_encode($newData, JSON_UNESCAPED_UNICODE), 120);
        }

        return $chunks ? implode(' | ', $chunks) : '—';
    }

    /**
     * ACP logs: краткий diff изменённых полей при edit (без служебных ключей).
     */
    protected static function formatEditDiff(?array $oldData, ?array $newData): string
    {
        if (!$oldData || !$newData) {
            return '';
        }

        $skipKeys = [
            'last_edit_date', 'last_edit_user_id', 'created_date', 'created_by_user_id',
            'hero_log_id', 'reputation_log_id', 'activity_item_id', 'crafted_item_id',
        ];
        $changes = [];

        foreach ($newData as $key => $newVal) {
            if (in_array($key, $skipKeys, true)) {
                continue;
            }

            $oldVal = $oldData[$key] ?? null;
            if ((string)$oldVal === (string)$newVal) {
                continue;
            }

            $changes[] = $key . ': '
                . self::truncate((string)$oldVal, 30)
                . ' → '
                . self::truncate((string)$newVal, 30);
        }

        if (!$changes) {
            return '';
        }

        return 'изм.: ' . implode(', ', array_slice($changes, 0, 5));
    }

    /**
     * ACP logs: обрезать длинную строку для колонки «Детали».
     */
    protected static function truncate(string $value, int $max = 180): string
    {
        if (strlen($value) <= $max) {
            return $value;
        }

        return substr($value, 0, $max - 3) . '...';
    }
}
