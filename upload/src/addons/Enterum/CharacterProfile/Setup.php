<?php

/**
 * Установка / обновление / удаление аддона «Профиль игрока».
 * Создаёт и удаляет таблицы xf_char_profile_* (профиль, ОГ, репутация, рюкзак, action log).
 * При установке — массовое создание профилей для пользователей группы-триггера.
 * Upgrade-шаги чинят схему (amount signed, колонки «Прочее» в рюкзаке).
 */

namespace Enterum\CharacterProfile;

use Enterum\CharacterProfile\Helper\BackpackOtherSchema;
use Enterum\CharacterProfile\Service\ProfileInitializer;
use XF\AddOn\AbstractSetup;
use XF\AddOn\StepRunnerInstallTrait;
use XF\AddOn\StepRunnerUninstallTrait;
use XF\AddOn\StepRunnerUpgradeTrait;
use XF\Db\Schema\Create;

class Setup extends AbstractSetup
{
    use StepRunnerInstallTrait;
    use StepRunnerUpgradeTrait;
    use StepRunnerUninstallTrait;

    /**
     * Установка, шаг 1: создание всех таблиц xf_char_profile_* (см. getTables).
     */
    public function installStep1(): void
    {
        $this->doCreateTables($this->getTables());
    }

    /**
     * Установка, шаг 2: массовая инициализация xf_char_profile для группы-триггера.
     */
    public function installStep2(): void
    {
        $groupId = $this->resolveAcceptedGroupId();
        if ($groupId <= 0) {
            return;
        }

        /** @var ProfileInitializer $initializer */
        $initializer = $this->app()->service('Enterum\CharacterProfile:ProfileInitializer');
        $initializer->batchInitializeForGroup($groupId);
    }

    /**
     * Установка, шаг 3: кастомные поля Репутация/Рюкзак + URL у принятых игроков.
     */
    public function installStep3(): void
    {
        /** @var \Enterum\CharacterProfile\Service\ProfilePageLinks $links */
        $links = $this->app()->service('Enterum\CharacterProfile:ProfilePageLinks');
        $links->ensureFieldDefinitions();
        $this->syncAcceptedUserProfileLinks();
    }

    /**
     * Setup / опции: ID группы-триггера из xf_option (или 16 по умолчанию).
     * Во время installStep2 кэш опций аддона ещё может быть пуст — читаем БД напрямую.
     */
    protected function resolveAcceptedGroupId(): int
    {
        $db = $this->app()->db();
        $tableExists = $db->fetchOne(
            "SELECT table_name FROM information_schema.tables
             WHERE table_schema = DATABASE() AND table_name = 'xf_option'"
        );

        if ($tableExists) {
            $value = $db->fetchOne(
                "SELECT option_value FROM xf_option WHERE option_id = 'charProfileAcceptedGroupId'"
            );
            if ($value !== false && $value !== null && $value !== '') {
                $groupId = (int)$value;
                if ($groupId > 0) {
                    return $groupId;
                }
            }
        }

        return 16;
    }

    /**
     * Удаление, шаг 1: удаление всех таблиц xf_char_profile_*.
     */
    public function uninstallStep1(): void
    {
        $this->doDropTables(array_keys($this->getTables()));
    }

    /**
     * Upgrade 1.6: колонка amount в журнале репутации — signed INT (отрицательные значения).
     */
    public function upgrade16Step1(): void
    {
        $tableName = 'xf_char_profile_reputation_log';
        $sm = $this->schemaManager();

        if (!$sm->tableExists($tableName) || !$sm->columnExists($tableName, 'amount')) {
            return;
        }

        // Нужен signed INT для отрицательной репутации.
        $this->query(
            'ALTER TABLE `' . $tableName . '` MODIFY `amount` INT NOT NULL DEFAULT 0'
        );
    }

    /**
     * Upgrade 2.2: колонки блока «Прочее» в xf_char_profile (рюкзак).
     */
    public function upgrade22Step1(): void
    {
        BackpackOtherSchema::ensureColumns($this->app());
    }

    /**
     * Upgrade 2.3: повторная проверка колонок «Прочее» (рюкзак).
     */
    public function upgrade23Step1(): void
    {
        BackpackOtherSchema::ensureColumns($this->app());
    }

    /**
     * Upgrade 2.4: повторная проверка колонок «Прочее» (рюкзак).
     */
    public function upgrade24Step1(): void
    {
        BackpackOtherSchema::ensureColumns($this->app());
    }

    /**
     * Upgrade 1.0.12: проставить ссылки Репутация/Рюкзак в charfieldslinks у принятых игроков.
     */
    public function upgrade1000082Step1(): void
    {
        $this->syncAcceptedUserProfileLinks();
    }

    /**
     * Upgrade 1.0.13: создать кастомные поля Репутация/Рюкзак и заполнить URL.
     */
    public function upgrade1000083Step1(): void
    {
        /** @var \Enterum\CharacterProfile\Service\ProfilePageLinks $links */
        $links = $this->app()->service('Enterum\CharacterProfile:ProfilePageLinks');
        $links->ensureFieldDefinitions();
        $this->syncAcceptedUserProfileLinks();
    }

    /**
     * Upgrade 1.0.14: поля через XF Entity (без колонок match_regex — XF 2.3).
     */
    public function upgrade1000084Step1(): void
    {
        /** @var \Enterum\CharacterProfile\Service\ProfilePageLinks $links */
        $links = $this->app()->service('Enterum\CharacterProfile:ProfilePageLinks');
        $links->ensureFieldDefinitions();
        $this->syncAcceptedUserProfileLinks();
    }

    /**
     * Upgrade 1.0.15: скрыть сырые URL в сайдбаре сообщения; ссылки — в «Анкета».
     */
    public function upgrade1000085Step1(): void
    {
        /** @var \Enterum\CharacterProfile\Service\ProfilePageLinks $links */
        $links = $this->app()->service('Enterum\CharacterProfile:ProfilePageLinks');
        $links->ensureFieldDefinitions();
        $this->syncAcceptedUserProfileLinks();
    }

    /**
     * Upgrade 1.0.16: поля только у группы-триггера; кнопки в tooltip/меню.
     */
    public function upgrade1000086Step1(): void
    {
        /** @var \Enterum\CharacterProfile\Service\ProfilePageLinks $links */
        $links = $this->app()->service('Enterum\CharacterProfile:ProfilePageLinks');
        $links->ensureFieldDefinitions();
        $links->clearLinksForNonTriggerUsers();
        $this->syncAcceptedUserProfileLinks();
    }

    /**
     * Upgrade 1.0.17: исправление синтаксиса шаблона репутации (убрана подсказка ОГ).
     */
    public function upgrade1000087Step1(): void
    {
        /** @var \Enterum\CharacterProfile\Service\ProfilePageLinks $links */
        $links = $this->app()->service('Enterum\CharacterProfile:ProfilePageLinks');
        $links->ensureFieldDefinitions();
        $links->clearLinksForNonTriggerUsers();
        $this->syncAcceptedUserProfileLinks();
    }

    /**
     * Upgrade 1.0.18: ссылки Репутация/Рюкзак в меню аккаунта (visitor menu).
     */
    public function upgrade1000088Step1(): void
    {
        // Только синхронизация данных; шаблоны подтянутся при обновлении аддона.
        $this->syncAcceptedUserProfileLinks();
    }

    /**
     * Upgrade 1.0.19: опция «отнимать отрицательную репутацию от общей».
     */
    public function upgrade1000089Step1(): void
    {
        // Опция импортируется из options.xml; пересчёт live при открытии профилей.
    }

    /**
     * Upgrade 1.0.20: исправление суммы при снятой галочке (знак отрицательной репутации).
     */
    public function upgrade1000090Step1(): void
    {
        // Формула в ReputationDisplay::computeTotal; пересчёт live.
    }

    /**
     * Upgrade 1.0.21: влияние по биомам = |отриц.| + положит. (без вычитания).
     */
    public function upgrade1000091Step1(): void
    {
        // Формула в CharProfileReputationLog::getInfluenceTable; пересчёт live.
    }

    protected function syncAcceptedUserProfileLinks(): void
    {
        $groupId = $this->resolveAcceptedGroupId();
        if ($groupId <= 0) {
            return;
        }

        /** @var ProfileInitializer $initializer */
        $initializer = $this->app()->service('Enterum\CharacterProfile:ProfileInitializer');
        $userIds = $this->db()->fetchAllColumn(
            'SELECT user_id FROM xf_user WHERE user_group_id = ? OR FIND_IN_SET(?, secondary_group_ids)',
            [$groupId, $groupId]
        );
        foreach ($userIds as $userId) {
            $initializer->syncProfilePageLinks((int)$userId);
        }
    }

    /**
     * Setup: создать таблицы из getTables(), пропуская уже существующие.
     */
    protected function doCreateTables(array $tables): void
    {
        $sm = $this->schemaManager();
        foreach ($tables as $tableName => $callback) {
            if ($sm->tableExists($tableName)) {
                continue;
            }
            $sm->createTable($tableName, $callback);
        }
    }

    /**
     * Setup: удалить перечисленные таблицы, если они есть.
     */
    protected function doDropTables(array $tableNames): void
    {
        $sm = $this->schemaManager();
        foreach ($tableNames as $tableName) {
            if (!$sm->tableExists($tableName)) {
                continue;
            }
            $sm->dropTable($tableName);
        }
    }

    /**
     * Setup: схема всех таблиц аддона (профиль, ОГ, репутация, рюкзак, ACP action log).
     *
     * @return array<string, callable>
     */
    protected function getTables(): array
    {
        return [
            'xf_char_profile' => function (Create $table) {
                $table->addColumn('user_id', 'int')->unsigned()->primaryKey();
                $table->addColumn('is_initialized', 'tinyint')->unsigned()->setDefault(0);
                $table->addColumn('hero_points_cache', 'tinyint')->unsigned()->setDefault(0);
                $table->addColumn('hero_points_raw_sum', 'int')->setDefault(0);
                $table->addColumn('created_date', 'int')->unsigned()->setDefault(0);
                $table->addColumn('last_update', 'int')->unsigned()->setDefault(0);
                $table->addColumn('backpack_other_bbcode', 'mediumtext')->nullable();
                $table->addColumn('backpack_other_rendered', 'mediumtext')->nullable();
                $table->addColumn('backpack_other_update_date', 'int')->unsigned()->setDefault(0);
                $table->addColumn('backpack_other_update_user_id', 'int')->unsigned()->setDefault(0);

                $table->addKey('is_initialized');
                $table->addKey('hero_points_cache');
                $table->addKey('created_date');
                $table->addKey('last_update');
                $table->addKey('backpack_other_update_date');
                $table->addKey('backpack_other_update_user_id');
            },
            'xf_char_profile_hero_log' => function (Create $table) {
                $table->addColumn('hero_log_id', 'int')->unsigned()->autoIncrement();
                $table->addColumn('user_id', 'int')->unsigned();
                $table->addColumn('event_date', 'date')->nullable();
                $table->addColumn('operation_type', 'varchar', 20)->setDefault('gain');
                $table->addColumn('amount', 'tinyint')->unsigned();
                $table->addColumn('source_url', 'varchar', 500)->setDefault('');
                $table->addColumn('source_title', 'varchar', 255)->setDefault('');
                $table->addColumn('is_support', 'tinyint')->unsigned()->setDefault(0);
                $table->addColumn('is_overflow', 'tinyint')->unsigned()->setDefault(0);
                $table->addColumn('burned_amount', 'tinyint')->unsigned()->setDefault(0);
                $table->addColumn('created_by_user_id', 'int')->unsigned()->setDefault(0);
                $table->addColumn('created_date', 'int')->unsigned()->setDefault(0);
                $table->addColumn('last_edit_user_id', 'int')->unsigned()->setDefault(0);
                $table->addColumn('last_edit_date', 'int')->unsigned()->setDefault(0);

                $table->addPrimaryKey('hero_log_id');
                $table->addKey('user_id');
                $table->addKey('event_date');
                $table->addKey('operation_type');
                $table->addKey('is_support');
                $table->addKey('is_overflow');
                $table->addKey('created_by_user_id');
                $table->addKey('created_date');
            },
            'xf_char_profile_reputation_log' => function (Create $table) {
                $table->addColumn('reputation_log_id', 'int')->unsigned()->autoIncrement();
                $table->addColumn('user_id', 'int')->unsigned();
                $table->addColumn('region_key', 'varchar', 30);
                $table->addColumn('character_name', 'varchar', 100)->setDefault('');
                $table->addColumn('faction_name', 'varchar', 150)->setDefault('');
                $table->addColumn('amount', 'int')->setDefault(0);
                $table->addColumn('operation_type', 'varchar', 20)->setDefault('gain');
                $table->addColumn('source_url', 'varchar', 500)->setDefault('');
                $table->addColumn('source_title', 'varchar', 255)->setDefault('Ссылка на источник');
                $table->addColumn('created_by_user_id', 'int')->unsigned()->setDefault(0);
                $table->addColumn('created_date', 'int')->unsigned()->setDefault(0);
                $table->addColumn('last_edit_user_id', 'int')->unsigned()->setDefault(0);
                $table->addColumn('last_edit_date', 'int')->unsigned()->setDefault(0);

                $table->addPrimaryKey('reputation_log_id');
                $table->addKey('user_id');
                $table->addKey('region_key');
                $table->addKey('character_name');
                $table->addKey('faction_name');
                $table->addKey('created_by_user_id');
                $table->addKey('created_date');
            },
            'xf_char_profile_backpack_activity_item' => function (Create $table) {
                $table->addColumn('activity_item_id', 'int')->unsigned()->autoIncrement();
                $table->addColumn('user_id', 'int')->unsigned();
                $table->addColumn('item_name', 'varchar', 255)->setDefault('');
                $table->addColumn('item_url', 'varchar', 500)->setDefault('');
                $table->addColumn('item_description', 'text')->nullable();
                $table->addColumn('item_type', 'varchar', 100)->setDefault('');
                $table->addColumn('item_level', 'smallint')->unsigned()->setDefault(0);
                $table->addColumn('rarity_key', 'varchar', 30)->setDefault('common');
                $table->addColumn('source_url', 'varchar', 500)->setDefault('');
                $table->addColumn('source_title', 'varchar', 255)->setDefault('');
                $table->addColumn('reason', 'varchar', 255)->setDefault('');
                $table->addColumn('quantity', 'int')->unsigned()->setDefault(1);
                $table->addColumn('note', 'text')->nullable();
                $table->addColumn('display_order', 'int')->unsigned()->setDefault(0);
                $table->addColumn('created_by_user_id', 'int')->unsigned()->setDefault(0);
                $table->addColumn('created_date', 'int')->unsigned()->setDefault(0);
                $table->addColumn('last_edit_user_id', 'int')->unsigned()->setDefault(0);
                $table->addColumn('last_edit_date', 'int')->unsigned()->setDefault(0);

                $table->addPrimaryKey('activity_item_id');
                $table->addKey('user_id');
                $table->addKey('item_name');
                $table->addKey('item_type');
                $table->addKey('rarity_key');
                $table->addKey('display_order');
                $table->addKey('created_by_user_id');
                $table->addKey('created_date');
            },
            'xf_char_profile_backpack_crafted_item' => function (Create $table) {
                $table->addColumn('crafted_item_id', 'int')->unsigned()->autoIncrement();
                $table->addColumn('user_id', 'int')->unsigned();
                $table->addColumn('item_name', 'varchar', 255)->setDefault('');
                $table->addColumn('item_url', 'varchar', 500)->setDefault('');
                $table->addColumn('item_type', 'varchar', 100)->setDefault('');
                $table->addColumn('item_level', 'smallint')->unsigned()->setDefault(0);
                $table->addColumn('request_url', 'varchar', 500)->setDefault('');
                $table->addColumn('author_name', 'varchar', 100)->setDefault('');
                $table->addColumn('display_order', 'int')->unsigned()->setDefault(0);
                $table->addColumn('created_by_user_id', 'int')->unsigned()->setDefault(0);
                $table->addColumn('created_date', 'int')->unsigned()->setDefault(0);
                $table->addColumn('last_edit_user_id', 'int')->unsigned()->setDefault(0);
                $table->addColumn('last_edit_date', 'int')->unsigned()->setDefault(0);

                $table->addPrimaryKey('crafted_item_id');
                $table->addKey('user_id');
                $table->addKey('item_name');
                $table->addKey('display_order');
                $table->addKey('created_by_user_id');
                $table->addKey('created_date');
            },
            'xf_char_profile_action_log' => function (Create $table) {
                $table->addColumn('action_log_id', 'int')->unsigned()->autoIncrement();
                $table->addColumn('target_user_id', 'int')->unsigned();
                $table->addColumn('actor_user_id', 'int')->unsigned()->setDefault(0);
                $table->addColumn('content_type', 'varchar', 50)->setDefault('');
                $table->addColumn('content_id', 'int')->unsigned()->setDefault(0);
                $table->addColumn('action', 'varchar', 20)->setDefault('');
                $table->addColumn('old_data', 'mediumtext')->nullable();
                $table->addColumn('new_data', 'mediumtext')->nullable();
                $table->addColumn('log_date', 'int')->unsigned()->setDefault(0);

                $table->addPrimaryKey('action_log_id');
                $table->addKey('target_user_id');
                $table->addKey('actor_user_id');
                $table->addKey('content_type');
                $table->addKey('action');
                $table->addKey('log_date');
            },
        ];
    }
}
