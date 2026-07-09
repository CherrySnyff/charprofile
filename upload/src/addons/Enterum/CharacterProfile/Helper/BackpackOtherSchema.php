<?php

/**
 * Рюкзак / «Прочее» / схема: самовосстановление колонок backpack_other_* в xf_char_profile.
 * Вызывается при просмотре/сохранении «Прочее» и из Setup upgrade 2.2–2.4.
 * Предпочтительно — upgrade в Setup; этот хелпер — runtime fallback.
 */

namespace Enterum\CharacterProfile\Helper;

class BackpackOtherSchema
{
    /**
     * Рюкзак / «Прочее»: добавить недостающие колонки backpack_other_* в xf_char_profile.
     */
    public static function ensureColumns(\XF\App $app): void
    {
        $sm = $app->db()->getSchemaManager();
        $tableName = 'xf_char_profile';

        if (!$sm->tableExists($tableName)) {
            return;
        }

        if (!$sm->columnExists($tableName, 'backpack_other_bbcode')) {
            $sm->alterTable($tableName, function (\XF\Db\Schema\Alter $table) {
                $table->addColumn('backpack_other_bbcode', 'mediumtext')->nullable();
            });
        }
        if (!$sm->columnExists($tableName, 'backpack_other_rendered')) {
            $sm->alterTable($tableName, function (\XF\Db\Schema\Alter $table) {
                $table->addColumn('backpack_other_rendered', 'mediumtext')->nullable();
            });
        }
        if (!$sm->columnExists($tableName, 'backpack_other_update_date')) {
            $sm->alterTable($tableName, function (\XF\Db\Schema\Alter $table) {
                $table->addColumn('backpack_other_update_date', 'int')->unsigned()->setDefault(0);
                $table->addKey('backpack_other_update_date');
            });
        }
        if (!$sm->columnExists($tableName, 'backpack_other_update_user_id')) {
            $sm->alterTable($tableName, function (\XF\Db\Schema\Alter $table) {
                $table->addColumn('backpack_other_update_user_id', 'int')->unsigned()->setDefault(0);
                $table->addKey('backpack_other_update_user_id');
            });
        }
    }
}
