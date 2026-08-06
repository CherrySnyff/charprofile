<?php

/**
 * Repository: выборки журнала репутации (xf_char_profile_reputation_log).
 * Агрегаты по региону (влияние floor(sum/10)) и по фракциям (классы отношений).
 */

namespace Enterum\CharacterProfile\Repository;

use Enterum\CharacterProfile\Service\Reputation\ReputationDisplay;
use XF\Mvc\Entity\Repository;

class CharProfileReputationLog extends Repository
{
    /**
     * Репутация: все записи пользователя (новые сверху).
     */
    public function findLogsForUser(int $userId)
    {
        return $this->finder('Enterum\CharacterProfile:CharProfileReputationLog')
            ->where('user_id', $userId)
            ->order('created_date', 'DESC')
            ->order('reputation_log_id', 'DESC');
    }

    /**
     * Репутация: записи пользователя в выбранном регионе.
     */
    public function findLogsForUserRegion(int $userId, string $regionKey)
    {
        return $this->finder('Enterum\CharacterProfile:CharProfileReputationLog')
            ->where('user_id', $userId)
            ->where('region_key', $regionKey)
            ->order('created_date', 'DESC')
            ->order('reputation_log_id', 'DESC');
    }

    /**
     * Репутация / влияние: floor(|neg|/10) и floor(pos/10) по региону.
     *
     * @return array{neg:int, pos:int}
     */
    public function fetchRegionReputationFlooredSums(int $userId, string $regionKey): array
    {
        $raw = $this->db()->fetchRow(
            '
                SELECT
                    COALESCE(SUM(CASE WHEN amount < 0 THEN amount ELSE 0 END), 0) AS neg_sum,
                    COALESCE(SUM(CASE WHEN amount > 0 THEN amount ELSE 0 END), 0) AS pos_sum
                FROM xf_char_profile_reputation_log
                WHERE user_id = ? AND region_key = ?
            ',
            [$userId, $regionKey]
        );

        $negSum = (float)$raw['neg_sum'];
        $posSum = (float)$raw['pos_sum'];

        // Отрицательная сумма хранится как −25; величина влияния — floor(abs/10), как у положительной.
        $neg = $negSum < 0 ? -(int)floor(abs($negSum) / 10) : 0;
        $pos = (int)floor($posSum / 10);

        return ['neg' => $neg, 'pos' => $pos];
    }

    /**
     * Репутация / UI: таблица влияния по трём регионам (Арамидис, Корзус, Юнион).
     *
     * @return array<string, array{label: string, negative: int, positive: int, total: int}>
     */
    public function getInfluenceTable(int $userId): array
    {
        $labels = [
            'aramidis' => 'Арамидис',
            'korzus' => 'Корзус',
            'union' => 'Юнион',
        ];

        $rows = [];
        foreach ($labels as $key => $label) {
            $sums = $this->fetchRegionReputationFlooredSums($userId, $key);
            $neg = $sums['neg'];
            $pos = $sums['pos'];
            // Влияние (ТЗ): Общая = |отрицательная| + положительная — всегда сумма модулей, без вычитания.
            // Опция ACP «отнимать отрицательную» влияет только на суммы фракций, не на эту таблицу.
            $rows[$key] = [
                'label' => $label,
                'negative' => $neg,
                'positive' => $pos,
                'total' => abs($neg) + $pos,
            ];
        }

        return $rows;
    }

    /**
     * Репутация / UI: агрегаты по фракциям региона + класс/подпись/тултип отношения.
     * total — для отображения (с учётом лимита ±100); total_raw — фактическая сумма журнала.
     *
     * @return list<array{
     *   display_name: string,
     *   faction_key: string,
     *   total: int,
     *   total_raw: int,
     *   relation: string,
     *   relation_class: string,
     *   relation_tooltip: string,
     *   show_quest_footnote: bool,
     *   show_cap_footnote: bool
     * }>
     */
    public function getFactionAggregatesForRegion(int $userId, string $regionKey): array
    {
        $rows = $this->db()->fetchAll(
            '
                SELECT
                    LOWER(TRIM(faction_name)) AS fn,
                    MIN(faction_name) AS display_name,
                    COALESCE(SUM(CASE WHEN amount > 0 THEN amount ELSE 0 END), 0) AS pos_sum,
                    COALESCE(SUM(CASE WHEN amount < 0 THEN amount ELSE 0 END), 0) AS neg_sum
                FROM xf_char_profile_reputation_log
                WHERE user_id = ? AND region_key = ?
                GROUP BY LOWER(TRIM(faction_name))
                ORDER BY MIN(faction_name) ASC
            ',
            [$userId, $regionKey]
        );

        $out = [];
        foreach ($rows as $row) {
            $rawTotal = ReputationDisplay::computeTotal((int)$row['pos_sum'], (int)$row['neg_sum']);
            $displayTotal = ReputationDisplay::displayFactionTotal($rawTotal);
            $relationClass = ReputationDisplay::relationClass($displayTotal);
            $out[] = [
                'display_name' => (string)$row['display_name'],
                'faction_key' => (string)$row['fn'],
                'total' => $displayTotal,
                'total_raw' => $rawTotal,
                'relation' => mb_strtoupper(ReputationDisplay::relationLabel($displayTotal)),
                'relation_class' => $relationClass,
                'relation_tooltip' => ReputationDisplay::relationTooltipByClass($relationClass),
                'show_quest_footnote' => $displayTotal >= 70,
                'show_cap_footnote' => ReputationDisplay::showFactionCapFootnote($rawTotal),
            ];
        }

        return $out;
    }
}
