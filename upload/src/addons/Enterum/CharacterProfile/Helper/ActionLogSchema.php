<?php

/**
 * ACP / аудит: самовосстановление таблицы xf_char_profile_action_log.
 * Нужна, если аддон ставили до появления журнала или runtime CREATE убрали в security-review.
 */

namespace Enterum\CharacterProfile\Helper;

class ActionLogSchema
{
    public const TABLE = 'xf_char_profile_action_log';

    /**
     * Есть ли таблица журнала (несколько способов — schemaManager иногда врёт на кастомных prefix/кэше).
     */
    public static function tableExists(\XF\App $app): bool
    {
        $table = self::TABLE;

        try {
            if ($app->schemaManager()->tableExists($table)) {
                return true;
            }
        } catch (\Throwable $e) {
        }

        try {
            $db = $app->db();
            if ($db->fetchOne('SHOW TABLES LIKE ' . $db->quote($table))) {
                return true;
            }
        } catch (\Throwable $e) {
        }

        try {
            $app->db()->fetchOne('SELECT 1 FROM `' . self::TABLE . '` LIMIT 1');
            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Создать таблицу, если её нет. Безопасно вызывать повторно (IF NOT EXISTS).
     */
    public static function ensureTable(\XF\App $app): bool
    {
        if (self::tableExists($app)) {
            return true;
        }

        try {
            $app->db()->query(
                'CREATE TABLE IF NOT EXISTS `' . self::TABLE . '` (
                    `action_log_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                    `target_user_id` INT UNSIGNED NOT NULL,
                    `actor_user_id` INT UNSIGNED NOT NULL DEFAULT 0,
                    `content_type` VARCHAR(50) NOT NULL DEFAULT \'\',
                    `content_id` INT UNSIGNED NOT NULL DEFAULT 0,
                    `action` VARCHAR(20) NOT NULL DEFAULT \'\',
                    `old_data` MEDIUMTEXT NULL,
                    `new_data` MEDIUMTEXT NULL,
                    `log_date` INT UNSIGNED NOT NULL DEFAULT 0,
                    PRIMARY KEY (`action_log_id`),
                    KEY `target_user_id` (`target_user_id`),
                    KEY `actor_user_id` (`actor_user_id`),
                    KEY `content_type` (`content_type`),
                    KEY `action` (`action`),
                    KEY `log_date` (`log_date`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
            );
        } catch (\Throwable $e) {
            \XF::logException($e, false, '[Enterum/CharacterProfile] action log CREATE failed: ');
            return self::tableExists($app);
        }

        return self::tableExists($app);
    }
}
