# TZ_CHARACTER_PROFILE_RU

# Техническое задание: аддон «Профиль игрока» для XenForo 2.2

## 1. Общие сведения

**Название аддона:** Профиль игрока  
**Namespace:** `Enterum/CharacterProfile`  
**version_id:** `1`  
**version_string:** `0.0.1 Alpha`  
**CSS-префикс:** `cp-` / `charProfile-`, без прямого использования `gm-`-классов  
**Font Awesome:** уже подключён на форуме  
**Целевая версия XenForo:** 2.2.x  
**PHP:** 7.2+  
**Зависимость от `guildM`:** только как референс. Данные из `guildM` не мигрируются, но часть логики и визуальных блоков копируется.

Аддон добавляет в профиль пользователя три большие вкладки:

1. **Репутация**
2. **Рюкзак**
3. **Лист персонажа**

На текущем этапе вкладка **«Лист персонажа»** является заглушкой с текстом «Скоро».

---

## 2. Главный принцип хранения данных

Не создавать отдельные таблицы для каждого пользователя.

Создаются общие таблицы аддона, а связь с конкретным профилем пользователя выполняется через поле:

```sql
user_id int unsigned
```

Когда пользователю присваивается группа **«Принятые игроки с анкетой»**, система создаёт для него базовую строку в таблице `xf_char_profile`, если такой строки ещё нет.

При снятии группы вкладки скрываются, но данные остаются в БД. При повторной выдаче группы вкладки снова становятся доступны.

---

## 3. Условие появления вкладок

### Механика: Группа-триггер

- Поле: `accepted_player_group_id`
- Тип: `int unsigned`
- Где: настройки аддона XenForo / ACP option
- Default: `16`
- Меняет: администратор форума
- Когда меняется: при настройке аддона в ACP
- История: нет
- Индексация: нет
- Примечание: в ТЗ указано `prinjatye-igroki-s-anketoj.16`, для настроек нужен числовой ID группы `16`

### Механика: Появление вкладок профиля

- Поле: `has_character_profile`
- Тип: вычисляемое значение
- Где: не хранить / проверять по группе пользователя
- Default: нет
- Меняет: системный процесс
- Когда меняется: при выдаче или снятии группы
- История: нет
- Индексация: нет
- Логика:
  - если пользователь состоит в группе `accepted_player_group_id` - вкладки показываются;
  - если группу сняли - вкладки скрываются;
  - данные в БД остаются;
  - если группу вернули - вкладки снова открываются.

---

## 4. Видимость вкладок

### Механика: Видимость вкладок

- Поле: `can_view_character_profile`
- Тип: permission
- Где: XenForo permissions, группа прав `character_profile`
- Default: по настройкам прав
- Меняет: администратор через ACP
- Когда меняется: при настройке прав групп пользователей
- История: нет
- Индексация: нет
- Примечание: вкладки профиля видят гости и зарегистрированные пользователи, если у владельца профиля есть группа-триггер. Для гостей право `view` должно быть включено в ACP для группы Unregistered / Unconfirmed.

---

## 5. Права аддона

Права лучше хранить через стандартную систему permissions XenForo, а не в собственной таблице.

### Permission group

```text
character_profile
```

### Рекомендуемые permission keys

```text
view
manageHero
manageHeroSupport
manageReputation
manageBackpack
manageCharacterSheet
manageCharacterSheetOwn
viewLogs
```

---

## 5.1. Матрица прав

| Действие | Админ | Группы через ACP | Владелец профиля |
|---|---:|---:|---:|
| Просмотр вкладок | да | настраивается | да |
| Добавить ОГ | да | настраивается | нет |
| Редактировать ОГ | да | настраивается | нет |
| Удалить ОГ | да | настраивается | нет |
| Галочка «Получено за поддержку» | да | настраивается | нет |
| Репутация: добавить/редактировать/удалить | да | настраивается | нет |
| Рюкзак: добавить/редактировать/удалить | да | настраивается | нет |
| Лист персонажа: редактировать любой | да | настраивается | нет |
| Лист персонажа: редактировать свой | да | настраивается | да |
| Просмотр логов в ACP | да | настраивается (`viewLogs`) | нет |

---

## 5.2. Право просмотра вкладок

- Поле: `character_profile.view`
- Тип: XenForo permission
- Где: `permissions.xml`
- Default: зависит от группы
- Меняет: администратор через ACP
- Когда меняется: при настройке прав
- История: нет
- Индексация: нет

---

## 5.3. Право управления очками геройства

- Поле: `character_profile.manageHero`
- Тип: XenForo permission
- Где: `permissions.xml`
- Default: нет
- Меняет: администратор через ACP
- Когда меняется: при настройке прав
- История: нет
- Индексация: нет
- Доступ:
  - администратор - да;
  - группы из ACP - настраивается;
  - владелец профиля - нет.

---

## 5.4. Право ставить «Получено за поддержку»

- Поле: `character_profile.manageHeroSupport`
- Тип: XenForo permission
- Где: `permissions.xml`
- Default: нет
- Меняет: администратор через ACP
- Когда меняется: при настройке прав
- История: нет
- Индексация: нет
- Примечание: галочка «Получено за поддержку» видна только пользователям с этим правом

---

## 5.5. Право управления репутацией

- Поле: `character_profile.manageReputation`
- Тип: XenForo permission
- Где: `permissions.xml`
- Default: нет
- Меняет: администратор через ACP
- Когда меняется: при настройке прав
- История: нет
- Индексация: нет
- Действия: add/edit/delete

---

## 5.6. Право управления рюкзаком

- Поле: `character_profile.manageBackpack`
- Тип: XenForo permission
- Где: `permissions.xml`
- Default: нет
- Меняет: администратор через ACP
- Когда меняется: при настройке прав
- История: нет
- Индексация: нет
- Действия: add/edit/delete

---

## 5.7. Право управления листом персонажа

- Поле: `character_profile.manageCharacterSheet`
- Тип: XenForo permission
- Где: `permissions.xml`
- Default: нет
- Меняет: администратор через ACP
- Когда меняется: при настройке прав
- История: нет
- Индексация: нет
- Доступ:
  - администратор - да;
  - группы из ACP - настраивается.

---

## 5.8. Право редактировать свой лист персонажа

- Поле: `character_profile.manageCharacterSheetOwn`
- Тип: XenForo permission
- Где: `permissions.xml`
- Default: нет
- Меняет: администратор через ACP
- Когда меняется: при настройке прав
- История: нет
- Индексация: нет
- Доступ:
  - владелец профиля - да, только свой лист.

---

## 5.9. Право просмотра логов в ACP

- Поле: `character_profile.viewLogs`
- Тип: XenForo permission
- Где: `permissions.xml`
- Default: нет
- Меняет: администратор через ACP
- Когда меняется: при настройке прав
- История: нет
- Индексация: нет
- Доступ:
  - только ACP, страница «Профиль игрока → Логи действий»;
  - не даёт доступа к редактированию данных на фронтенде.

---

# 6. Основная таблица профиля

## Таблица: `xf_char_profile`

Одна строка на пользователя.

### Механика: Профиль игрока

- Поле: `user_id`
- Тип: `int unsigned`
- Где: `xf_char_profile`
- Default: нет
- Меняет: системный процесс
- Когда меняется: при создании строки профиля
- История: нет
- Индексация: да, primary key
- Примечание: связь с пользователем XenForo

- Поле: `is_initialized`
- Тип: `tinyint unsigned`
- Где: `xf_char_profile`
- Default: `0`
- Меняет: системный процесс
- Когда меняется: при выдаче группы «Принятые игроки с анкетой»
- История: нет
- Индексация: да
- Примечание: показывает, что профиль уже был создан

- Поле: `hero_points_cache`
- Тип: `tinyint unsigned`
- Где: `xf_char_profile`
- Default: `0`
- Меняет: системный процесс
- Когда меняется: при добавлении, редактировании или удалении записи ОГ
- История: да, через `xf_char_profile_hero_log`
- Индексация: да, если нужно искать/сортировать пользователей по ОГ
- Примечание: хранит итоговое отображаемое значение от `0` до `3`

- Поле: `hero_points_raw_sum`
- Тип: `int`
- Где: `xf_char_profile`
- Default: `0`
- Меняет: системный процесс
- Когда меняется: при изменении журнала ОГ
- История: да, через `xf_char_profile_hero_log`
- Индексация: нет
- Примечание: хранит финальный `running` после хронологического пересчёта (0..3), совпадает с `hero_points_cache`

- Поле: `created_date`
- Тип: `int unsigned`
- Где: `xf_char_profile`
- Default: `0`
- Меняет: системный процесс
- Когда меняется: при первом создании профиля
- История: нет
- Индексация: да

- Поле: `last_update`
- Тип: `int unsigned`
- Где: `xf_char_profile`
- Default: `0`
- Меняет: системный процесс
- Когда меняется: при изменении любой сущности профиля
- История: нет
- Индексация: да

### Минимальная схема

```sql
CREATE TABLE xf_char_profile (
  user_id int unsigned NOT NULL,
  is_initialized tinyint unsigned NOT NULL DEFAULT 0,
  hero_points_cache tinyint unsigned NOT NULL DEFAULT 0,
  hero_points_raw_sum int NOT NULL DEFAULT 0,
  created_date int unsigned NOT NULL DEFAULT 0,
  last_update int unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (user_id),
  KEY is_initialized (is_initialized),
  KEY hero_points_cache (hero_points_cache),
  KEY created_date (created_date),
  KEY last_update (last_update)
);
```

---

# 7. Вкладка «Репутация»

Вкладка состоит из двух больших блоков:

1. **Очки геройства**
2. **Репутация персонажа**

---

# 7.1. Очки геройства

В самом верху вкладки должна быть строка:

```text
Актуальное количество Очков Геройства: ● ● ○
```

Пустое очко:

```html
<i class="fa-regular fa-circle"></i>
```

Заполненное очко:

```html
<i class="fa-solid fa-circle"></i>
```

Под строкой находится всегда свёрнутый спойлер:

```text
Получение Очков Героизма
```

Внутри спойлера находится форма добавления ОГ.

---

## Механика: Текущее количество очков геройства

- Поле: `hero_points_cache`
- Тип: `tinyint unsigned`
- Где: `xf_char_profile`
- Default: `0`
- Меняет: системный процесс
- Когда меняется: при добавлении, редактировании или удалении записи ОГ
- История: да, через `xf_char_profile_hero_log`
- Индексация: да, если нужно искать/сортировать пользователей по ОГ
- Примечание: хранит итоговое отображаемое значение от `0` до `3`

Логика:

```text
hero_points_cache считается по хронологическому алгоритму с лимитом 0-3
amount хранится положительным числом, знак берётся из operation_type
```

Вывод:

```text
0 ОГ: ○ ○ ○
1 ОГ: ● ○ ○
2 ОГ: ● ● ○
3 ОГ: ● ● ●
```

---

## Механика: Сырая сумма очков геройства

- Поле: `hero_points_raw_sum`
- Тип: `int`
- Где: `xf_char_profile`
- Default: `0`
- Меняет: системный процесс
- Когда меняется: при изменении журнала ОГ
- История: да, через `xf_char_profile_hero_log`
- Индексация: нет
- Примечание: необязательное поле, но удобно для отладки

---

## Механика: Сгорание очков геройства

По ТЗ запись не может иметь значение больше `3` или меньше `-3`, но итоговая сумма отображаемых ОГ не может превышать `3`.

Пример:

```text
сейчас ОГ = 1
+1 ОГ → стало 2
+1 ОГ → стало 3
-1 ОГ → стало 2, строка траты отображается красным
+2 ОГ → сырая сумма 4, но отображается 3; 1 ОГ сгорает, строка отображается цветом #DD5000
```

Цвет строки, где есть сгорание:

```css
#DD5000
```

---

## Таблица: `xf_char_profile_hero_log`

Журнал записей очков геройства.

### Механика: Запись ОГ

- Поле: `hero_log_id`
- Тип: `int unsigned auto_increment`
- Где: `xf_char_profile_hero_log`
- Default: нет
- Меняет: система
- История: это и есть история
- Индексация: primary key

- Поле: `user_id`
- Тип: `int unsigned`
- Где: `xf_char_profile_hero_log`
- Default: нет
- Меняет: система
- История: да
- Индексация: да

- Поле: `event_date`
- Тип: `date`
- Где: `xf_char_profile_hero_log`
- Default: текущая дата
- Меняет: администратор/пользователь с правом `manageHero`
- Когда меняется: при добавлении или редактировании записи
- История: да
- Индексация: да
- Примечание: в интерфейсе показывать как `дд.мм.гггг`

- Поле: `operation_type`
- Тип: `enum('gain','loss','support')` или `varchar(20)`
- Где: `xf_char_profile_hero_log`
- Default: `gain`
- Меняет: администратор/пользователь с правом `manageHero`
- История: да
- Индексация: да
- Значения:
  - `gain` - Получено ОГ
  - `loss` - Потрачено ОГ
  - `support` - Получено ОГ за поддержку

- Поле: `amount`
- Тип: `tinyint unsigned`
- Где: `xf_char_profile_hero_log`
- Default: нет
- Меняет: администратор/пользователь с правом `manageHero`
- История: да
- Индексация: да
- Валидация:
  - в БД хранится только положительное значение `1`, `2` или `3`;
  - знак определяется через `operation_type`;
  - если в интерфейсе ввели отрицательное число, система берёт `abs(value)` и применяет знак по выбранному типу операции;
  - `0` запрещён;
  - значение больше `3` запрещено.

- Поле: `source_url`
- Тип: `varchar(500)`
- Где: `xf_char_profile_hero_log`
- Default: пустая строка
- Меняет: администратор/пользователь с правом `manageHero`
- История: да
- Индексация: нет
- Валидация: если поле заполнено, должно начинаться с `https://`
- Примечание: для `support` может быть пустым

- Поле: `source_title`
- Тип: `varchar(255)`
- Где: `xf_char_profile_hero_log`
- Default: пустая строка
- Меняет: администратор/пользователь с правом `manageHero`
- История: да
- Индексация: нет
- Примечание: отображаемое название ссылки

- Поле: `is_support`
- Тип: `tinyint unsigned`
- Где: `xf_char_profile_hero_log`
- Default: `0`
- Меняет: администратор/пользователь с правом `manageHeroSupport`
- История: да
- Индексация: да
- Примечание: если `1`, запись отображается как «Получено ОГ за поддержку»

- Поле: `is_overflow`
- Тип: `tinyint unsigned`
- Где: `xf_char_profile_hero_log`
- Default: `0`
- Меняет: системный процесс
- Когда меняется: при пересчёте ОГ
- История: да
- Индексация: да
- Примечание: строка получает цвет `#DD5000`, если из-за этой записи сумма превысила максимум `3`

- Поле: `burned_amount`
- Тип: `tinyint unsigned`
- Где: `xf_char_profile_hero_log`
- Default: `0`
- Меняет: системный процесс
- Когда меняется: при пересчёте ОГ
- История: да
- Индексация: нет
- Примечание: сколько ОГ сгорело из-за лимита `3`

- Поле: `created_by_user_id`
- Тип: `int unsigned`
- Где: `xf_char_profile_hero_log`
- Default: `0`
- Меняет: система
- История: да
- Индексация: да

- Поле: `created_date`
- Тип: `int unsigned`
- Где: `xf_char_profile_hero_log`
- Default: `0`
- Меняет: система
- История: да
- Индексация: да

- Поле: `last_edit_user_id`
- Тип: `int unsigned`
- Где: `xf_char_profile_hero_log`
- Default: `0`
- Меняет: система
- История: да
- Индексация: да

- Поле: `last_edit_date`
- Тип: `int unsigned`
- Где: `xf_char_profile_hero_log`
- Default: `0`
- Меняет: система
- История: да
- Индексация: да

### Минимальная схема

```sql
CREATE TABLE xf_char_profile_hero_log (
  hero_log_id int unsigned NOT NULL AUTO_INCREMENT,
  user_id int unsigned NOT NULL,
  event_date date DEFAULT NULL,
  operation_type varchar(20) NOT NULL DEFAULT 'gain',
  amount tinyint unsigned NOT NULL,
  source_url varchar(500) NOT NULL DEFAULT '',
  source_title varchar(255) NOT NULL DEFAULT '',
  is_support tinyint unsigned NOT NULL DEFAULT 0,
  is_overflow tinyint unsigned NOT NULL DEFAULT 0,
  burned_amount tinyint unsigned NOT NULL DEFAULT 0,
  created_by_user_id int unsigned NOT NULL DEFAULT 0,
  created_date int unsigned NOT NULL DEFAULT 0,
  last_edit_user_id int unsigned NOT NULL DEFAULT 0,
  last_edit_date int unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (hero_log_id),
  KEY user_id (user_id),
  KEY event_date (event_date),
  KEY operation_type (operation_type),
  KEY amount (amount),
  KEY is_support (is_support),
  KEY is_overflow (is_overflow),
  KEY created_by_user_id (created_by_user_id),
  KEY created_date (created_date)
);
```

---

## Механика: «Получено за поддержку»

- Поле: `is_support`
- Тип: `tinyint unsigned`
- Где: `xf_char_profile_hero_log`
- Default: `0`
- Меняет: администратор/пользователь с правом `manageHeroSupport`
- Когда меняется: при установке галочки «Получено за поддержку»
- История: да
- Индексация: да

При включении галочки:

```text
operation_type = support
is_support = 1
source_url = ''
source_title = ''
```

Поля `Получено/Потрачено`, `source_url`, `source_title` блокируются.

При выводе запись должна выглядеть примерно так:

```text
06.07.2026 | Получено ОГ за поддержку | 1
```

---

## Механика: Двухколоночный вывод записей ОГ

- Поле: не требуется
- Тип: UI-логика
- Где: шаблон XenForo
- Default: нет
- Меняет: система
- Когда меняется: при выводе страницы
- История: нет
- Индексация: нет
- Логика: фиксированно две записи в ряд

Порядок вывода:

```text
1, 2
3, 4
5, 6
```

---

# 7.2. Репутация персонажа

Репутацию нужно перенести из `guildM` почти 1:1, но заменить `guild_id` на `user_id`.

Регионы:

```text
aramidis
korzus
union
```

В интерфейсе:

```text
АРАМИДИС / КОРЗУС / ЮНИОН
```

---

## Таблица: `xf_char_profile_reputation_log`

### Механика: Запись репутации

- Поле: `reputation_log_id`
- Тип: `int unsigned auto_increment`
- Где: `xf_char_profile_reputation_log`
- Default: нет
- Меняет: система
- История: это и есть история
- Индексация: primary key

- Поле: `user_id`
- Тип: `int unsigned`
- Где: `xf_char_profile_reputation_log`
- Default: нет
- Меняет: система
- История: да
- Индексация: да
- Примечание: вместо `guild_id` из `guildM`

- Поле: `region_key`
- Тип: `enum('aramidis','korzus','union')` или `varchar(30)`
- Где: `xf_char_profile_reputation_log`
- Default: нет
- Меняет: администратор/пользователь с правом `manageReputation`
- История: да
- Индексация: да
- Значения:
  - `aramidis`
  - `korzus`
  - `union`

- Поле: `character_name`
- Тип: `varchar(100)`
- Где: `xf_char_profile_reputation_log`
- Default: пустая строка
- Меняет: администратор/пользователь с правом `manageReputation`
- История: да
- Индексация: да, если нужен поиск по персонажу

- Поле: `faction_name`
- Тип: `varchar(150)`
- Где: `xf_char_profile_reputation_log`
- Default: пустая строка
- Меняет: администратор/пользователь с правом `manageReputation`
- История: да
- Индексация: да
- Примечание: нужно для общего поля фракции

- Поле: `amount`
- Тип: `int`
- Где: `xf_char_profile_reputation_log`
- Default: `0`
- Меняет: администратор/пользователь с правом `manageReputation`
- История: да
- Индексация: да
- Примечание: положительная репутация хранится плюсом, отрицательная минусом

- Поле: `operation_type`
- Тип: `enum('gain','loss')` или `varchar(20)`
- Где: `xf_char_profile_reputation_log`
- Default: `gain`
- Меняет: администратор/пользователь с правом `manageReputation`
- История: да
- Индексация: да

- Поле: `source_url`
- Тип: `varchar(500)`
- Где: `xf_char_profile_reputation_log`
- Default: пустая строка
- Меняет: администратор/пользователь с правом `manageReputation`
- История: да
- Индексация: нет
- Валидация: должно начинаться с `https://`

- Поле: `source_title`
- Тип: `varchar(255)`
- Где: `xf_char_profile_reputation_log`
- Default: `Ссылка на источник`
- Меняет: администратор/пользователь с правом `manageReputation`
- История: да
- Индексация: нет

- Поле: `created_by_user_id`
- Тип: `int unsigned`
- Где: `xf_char_profile_reputation_log`
- Default: `0`
- Меняет: система
- История: да
- Индексация: да

- Поле: `created_date`
- Тип: `int unsigned`
- Где: `xf_char_profile_reputation_log`
- Default: `0`
- Меняет: система
- История: да
- Индексация: да

- Поле: `last_edit_user_id`
- Тип: `int unsigned`
- Где: `xf_char_profile_reputation_log`
- Default: `0`
- Меняет: система
- История: да
- Индексация: да

- Поле: `last_edit_date`
- Тип: `int unsigned`
- Где: `xf_char_profile_reputation_log`
- Default: `0`
- Меняет: система
- История: да
- Индексация: да

### Минимальная схема

```sql
CREATE TABLE xf_char_profile_reputation_log (
  reputation_log_id int unsigned NOT NULL AUTO_INCREMENT,
  user_id int unsigned NOT NULL,
  region_key varchar(30) NOT NULL,
  character_name varchar(100) NOT NULL DEFAULT '',
  faction_name varchar(150) NOT NULL DEFAULT '',
  amount int NOT NULL DEFAULT 0,
  operation_type varchar(20) NOT NULL DEFAULT 'gain',
  source_url varchar(500) NOT NULL DEFAULT '',
  source_title varchar(255) NOT NULL DEFAULT 'Ссылка на источник',
  created_by_user_id int unsigned NOT NULL DEFAULT 0,
  created_date int unsigned NOT NULL DEFAULT 0,
  last_edit_user_id int unsigned NOT NULL DEFAULT 0,
  last_edit_date int unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (reputation_log_id),
  KEY user_id (user_id),
  KEY region_key (region_key),
  KEY character_name (character_name),
  KEY faction_name (faction_name),
  KEY amount (amount),
  KEY operation_type (operation_type),
  KEY created_by_user_id (created_by_user_id),
  KEY created_date (created_date)
);
```

---

## Механика: Влияние персонажа

Таблица влияния не редактируется вручную. Она считается из `xf_char_profile_reputation_log`.

- Поле: `influence_negative`
- Тип: вычисляемое значение
- Где: не хранить / считать из `xf_char_profile_reputation_log`
- Default: `0`
- Меняет: системный процесс
- Когда меняется: при изменении репутации
- История: да, через журнал репутации
- Индексация: нет

- Поле: `influence_positive`
- Тип: вычисляемое значение
- Где: не хранить / считать из `xf_char_profile_reputation_log`
- Default: `0`
- Меняет: системный процесс
- Когда меняется: при изменении репутации
- История: да
- Индексация: нет

- Поле: `influence_total`
- Тип: вычисляемое значение
- Где: не хранить / считать из `xf_char_profile_reputation_log`
- Default: `0`
- Меняет: системный процесс
- Когда меняется: при изменении репутации
- История: да
- Индексация: нет

Логика (как в `guildM`, с `floor()`):

```text
Отрицательное влияние региона = floor(SUM(отрицательной репутации региона) / 10)
Положительное влияние региона = floor(SUM(положительной репутации региона) / 10)
Общее влияние региона = abs(отрицательное) + положительное
```

Поле `amount` хранится **со знаком** (как в `guildM`). Поле `operation_type` (`gain`/`loss`) вычисляется при сохранении для удобства логов, но для UI и подсчётов достаточно signed `amount`.

По уточнению Cursor, блок должен быть без мировой известности, если переносится именно версия для профиля.

---

## Механика: Общее поле фракции

- Поле: `faction_name`
- Тип: вычисляемое / `varchar(150)`
- Где: из `xf_char_profile_reputation_log`
- Default: нет
- Меняет: системный процесс
- История: да, через журнал репутации
- Индексация: да, по исходному полю `faction_name`

- Поле: `faction_reputation_total`
- Тип: вычисляемое значение `int`
- Где: не хранить / считать из `xf_char_profile_reputation_log`
- Default: `0`
- Меняет: системный процесс
- История: да
- Индексация: нет

- Поле: `faction_relation_label`
- Тип: вычисляемое значение `varchar(50)`
- Где: не хранить / считать по сумме
- Default: `Нейтральный`
- Меняет: системный процесс
- История: нет
- Индексация: нет

Градация:

```text
<= -100: Презренный
-99 - -70: Ненавистный
-69 - -40: Разыскиваемый
-39 - -11: Подозрительный
-10 - 10: Нейтральный
11 - 39: Дружественный
40 - 69: Подающий надежды
70 - 99: Герой
>= 100: Легенда
```

---

# 8. Вкладка «Рюкзак»

Вкладка делится на две подвкладки:

1. **Предметы за активности**
2. **Созданные предметы**

---

# 8.1. Предметы за активности

Это не буквальный перенос склада из `guildM`. Визуальный принцип и часть логики берутся из `guildM`, но схема сознательно расширяется под профиль персонажа. `guild_id` заменяется на `user_id`.

Иконка перед названием предмета — **всегда** только:

```html
<i class="fa-solid fa-box"></i>
```

Иконку подарка и другие варианты не использовать, даже для «Подарок от Тайного Санты».

Иконку хранить в БД не нужно. Это часть шаблона.

Колонка «Причина» в UI = поле `reason` (текст). Если заполнен `source_url`, текст `reason` (или `source_title`, если `reason` пуст) отображается как кликабельная ссылка.

---

## Таблица: `xf_char_profile_backpack_activity_item`

### Механика: Запись предмета за активность

- Поле: `activity_item_id`
- Тип: `int unsigned auto_increment`
- Где: `xf_char_profile_backpack_activity_item`
- Default: нет
- Меняет: система
- История: это основная таблица записей
- Индексация: primary key

- Поле: `user_id`
- Тип: `int unsigned`
- Где: `xf_char_profile_backpack_activity_item`
- Default: нет
- Меняет: система
- История: да
- Индексация: да

- Поле: `item_name`
- Тип: `varchar(255)`
- Где: `xf_char_profile_backpack_activity_item`
- Default: пустая строка
- Меняет: администратор/пользователь с правом `manageBackpack`
- История: да, через `xf_char_profile_action_log`
- Индексация: да

- Поле: `item_url`
- Тип: `varchar(500)`
- Где: `xf_char_profile_backpack_activity_item`
- Default: пустая строка
- Меняет: администратор/пользователь с правом `manageBackpack`
- История: да
- Индексация: нет
- Валидация: если заполнено, должно начинаться с `https://`

- Поле: `item_description`
- Тип: `text`
- Где: `xf_char_profile_backpack_activity_item`
- Default: пустая строка
- Меняет: администратор/пользователь с правом `manageBackpack`
- История: да
- Индексация: нет
- Примечание: описание предмета; при наличии выводится через `<details>`

- Поле: `item_type`
- Тип: `varchar(100)`
- Где: `xf_char_profile_backpack_activity_item`
- Default: пустая строка
- Меняет: администратор/пользователь с правом `manageBackpack`
- История: да
- Индексация: да

- Поле: `item_level`
- Тип: `smallint unsigned`
- Где: `xf_char_profile_backpack_activity_item`
- Default: `0`
- Меняет: администратор/пользователь с правом `manageBackpack`
- История: да
- Индексация: да

- Поле: `rarity_key`
- Тип: `varchar(30)`
- Где: `xf_char_profile_backpack_activity_item`
- Default: `common`
- Меняет: администратор/пользователь с правом `manageBackpack`
- История: да
- Индексация: да
- Значения: `common`, `uncommon`, `rare`, `unique`

- Поле: `source_url`
- Тип: `varchar(500)`
- Где: `xf_char_profile_backpack_activity_item`
- Default: пустая строка
- Меняет: администратор/пользователь с правом `manageBackpack`
- История: да
- Индексация: нет
- Валидация: если заполнено, должно начинаться с `https://`

- Поле: `source_title`
- Тип: `varchar(255)`
- Где: `xf_char_profile_backpack_activity_item`
- Default: пустая строка
- Меняет: администратор/пользователь с правом `manageBackpack`
- История: да
- Индексация: нет
- Примечание: альтернативный текст ссылки для колонки «Причина», если `reason` пуст

- Поле: `reason`
- Тип: `varchar(255)`
- Где: `xf_char_profile_backpack_activity_item`
- Default: пустая строка
- Меняет: администратор/пользователь с правом `manageBackpack`
- История: да
- Индексация: нет
- Примечание: основной текст колонки «Причина», например «Получено за квест»

- Поле: `quantity`
- Тип: `int unsigned`
- Где: `xf_char_profile_backpack_activity_item`
- Default: `1`
- Меняет: администратор/пользователь с правом `manageBackpack`
- История: да
- Индексация: нет

- Поле: `note`
- Тип: `text`
- Где: `xf_char_profile_backpack_activity_item`
- Default: пустая строка
- Меняет: администратор/пользователь с правом `manageBackpack`
- История: да
- Индексация: нет

- Поле: `display_order`
- Тип: `int unsigned`
- Где: `xf_char_profile_backpack_activity_item`
- Default: `0`
- Меняет: администратор/пользователь с правом `manageBackpack`
- История: нет
- Индексация: да

- Поле: `created_by_user_id`
- Тип: `int unsigned`
- Где: `xf_char_profile_backpack_activity_item`
- Default: `0`
- Меняет: система
- История: да
- Индексация: да

- Поле: `created_date`
- Тип: `int unsigned`
- Где: `xf_char_profile_backpack_activity_item`
- Default: `0`
- Меняет: система
- История: да
- Индексация: да

- Поле: `last_edit_user_id`
- Тип: `int unsigned`
- Где: `xf_char_profile_backpack_activity_item`
- Default: `0`
- Меняет: система
- История: да
- Индексация: да

- Поле: `last_edit_date`
- Тип: `int unsigned`
- Где: `xf_char_profile_backpack_activity_item`
- Default: `0`
- Меняет: система
- История: да
- Индексация: да

### Минимальная схема

```sql
CREATE TABLE xf_char_profile_backpack_activity_item (
  activity_item_id int unsigned NOT NULL AUTO_INCREMENT,
  user_id int unsigned NOT NULL,
  item_name varchar(255) NOT NULL DEFAULT '',
  item_url varchar(500) NOT NULL DEFAULT '',
  item_description text,
  item_type varchar(100) NOT NULL DEFAULT '',
  item_level smallint unsigned NOT NULL DEFAULT 0,
  rarity_key varchar(30) NOT NULL DEFAULT 'common',
  source_url varchar(500) NOT NULL DEFAULT '',
  source_title varchar(255) NOT NULL DEFAULT '',
  reason varchar(255) NOT NULL DEFAULT '',
  quantity int unsigned NOT NULL DEFAULT 1,
  note text,
  display_order int unsigned NOT NULL DEFAULT 0,
  created_by_user_id int unsigned NOT NULL DEFAULT 0,
  created_date int unsigned NOT NULL DEFAULT 0,
  last_edit_user_id int unsigned NOT NULL DEFAULT 0,
  last_edit_date int unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (activity_item_id),
  KEY user_id (user_id),
  KEY item_name (item_name),
  KEY item_type (item_type),
  KEY item_level (item_level),
  KEY rarity_key (rarity_key),
  KEY display_order (display_order),
  KEY created_by_user_id (created_by_user_id),
  KEY created_date (created_date)
);
```

---

# 8.2. Созданные предметы

Иконка перед названием предмета:

```html
<i class="fa-solid fa-screwdriver-wrench"></i>
```

Иконку хранить в БД не нужно. Это часть шаблона.

---

## Таблица: `xf_char_profile_backpack_crafted_item`

### Механика: Запись созданного предмета

- Поле: `crafted_item_id`
- Тип: `int unsigned auto_increment`
- Где: `xf_char_profile_backpack_crafted_item`
- Default: нет
- Меняет: система
- История: это основная таблица записей
- Индексация: primary key

- Поле: `user_id`
- Тип: `int unsigned`
- Где: `xf_char_profile_backpack_crafted_item`
- Default: нет
- Меняет: система
- История: да
- Индексация: да

- Поле: `item_name`
- Тип: `varchar(255)`
- Где: `xf_char_profile_backpack_crafted_item`
- Default: пустая строка
- Меняет: администратор/пользователь с правом `manageBackpack`
- Когда меняется: при добавлении или редактировании созданного предмета
- История: желательно да, через общий лог действий
- Индексация: да

- Поле: `item_url`
- Тип: `varchar(500)`
- Где: `xf_char_profile_backpack_crafted_item`
- Default: пустая строка
- Меняет: администратор/пользователь с правом `manageBackpack`
- История: желательно да
- Индексация: нет
- Валидация: должно начинаться с `https://`
- Примечание: при выводе ссылка отображается названием предмета из `item_name`

- Поле: `item_type`
- Тип: `varchar(100)`
- Где: `xf_char_profile_backpack_crafted_item`
- Default: пустая строка
- Меняет: администратор/пользователь с правом `manageBackpack`
- История: желательно да
- Индексация: да, если нужна фильтрация

- Поле: `item_level`
- Тип: `smallint unsigned`
- Где: `xf_char_profile_backpack_crafted_item`
- Default: `0`
- Меняет: администратор/пользователь с правом `manageBackpack`
- История: желательно да
- Индексация: да

- Поле: `request_url`
- Тип: `varchar(500)`
- Где: `xf_char_profile_backpack_crafted_item`
- Default: пустая строка
- Меняет: администратор/пользователь с правом `manageBackpack`
- История: желательно да
- Индексация: нет
- Валидация: должно начинаться с `https://`
- Примечание: в сохранённой таблице отображается словом `Заявка`

- Поле: `author_name`
- Тип: `varchar(100)`
- Где: `xf_char_profile_backpack_crafted_item`
- Default: пустая строка
- Меняет: администратор/пользователь с правом `manageBackpack`
- История: желательно да
- Индексация: да, если нужен поиск по автору
- Примечание: сохраняется текстом, потому что по уточнению Cursor `user_id` автора хранить не нужно

- Поле: `created_by_user_id`
- Тип: `int unsigned`
- Где: `xf_char_profile_backpack_crafted_item`
- Default: `0`
- Меняет: система
- История: да
- Индексация: да

- Поле: `created_date`
- Тип: `int unsigned`
- Где: `xf_char_profile_backpack_crafted_item`
- Default: `0`
- Меняет: система
- История: да
- Индексация: да

- Поле: `last_edit_user_id`
- Тип: `int unsigned`
- Где: `xf_char_profile_backpack_crafted_item`
- Default: `0`
- Меняет: система
- История: да
- Индексация: да

- Поле: `last_edit_date`
- Тип: `int unsigned`
- Где: `xf_char_profile_backpack_crafted_item`
- Default: `0`
- Меняет: система
- История: да
- Индексация: да

### Минимальная схема

```sql
CREATE TABLE xf_char_profile_backpack_crafted_item (
  crafted_item_id int unsigned NOT NULL AUTO_INCREMENT,
  user_id int unsigned NOT NULL,
  item_name varchar(255) NOT NULL DEFAULT '',
  item_url varchar(500) NOT NULL DEFAULT '',
  item_type varchar(100) NOT NULL DEFAULT '',
  item_level smallint unsigned NOT NULL DEFAULT 0,
  request_url varchar(500) NOT NULL DEFAULT '',
  author_name varchar(100) NOT NULL DEFAULT '',
  created_by_user_id int unsigned NOT NULL DEFAULT 0,
  created_date int unsigned NOT NULL DEFAULT 0,
  last_edit_user_id int unsigned NOT NULL DEFAULT 0,
  last_edit_date int unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (crafted_item_id),
  KEY user_id (user_id),
  KEY item_name (item_name),
  KEY item_type (item_type),
  KEY item_level (item_level),
  KEY author_name (author_name),
  KEY created_by_user_id (created_by_user_id),
  KEY created_date (created_date)
);
```

---

## Механика: Подсказка автора через @ник

- Поле: `author_name`
- Тип: `varchar(100)`
- Где: `xf_char_profile_backpack_crafted_item`
- Default: пустая строка
- Меняет: администратор/пользователь с правом `manageBackpack`
- Когда меняется: при выборе ника из подсказки
- История: желательно да
- Индексация: да, если нужен поиск
- Примечание: подсказка нужна только для удобного ввода. В БД сохраняется текстовое значение, не `user_id`

---

# 9. Вкладка «Лист персонажа»

На текущем этапе это заглушка.

## Механика: Заглушка листа персонажа

- Поле: не требуется
- Тип: UI-логика
- Где: шаблон вкладки профиля
- Default: нет
- Меняет: разработчик
- Когда меняется: при будущей реализации листа персонажа
- История: нет
- Индексация: нет
- Примечание: вкладка существует, но внутри выводится текст вроде `Скоро`

Иконки будущих подвкладок:

```html
Персонаж: <i class="fa-solid fa-person"></i>
Колдовство: <i class="fa-solid fa-wand-magic-sparkles"></i>
Мастерство: <i class="fa-solid fa-hand"></i>
Способности: <i class="fa-solid fa-medal"></i>
Дополнительно: <i class="fa-solid fa-clipboard-list"></i>
```

---

## Механика: Будущие данные листа персонажа

На текущем этапе можно не создавать таблицу. Но если нужно заложить основу под будущее, можно сделать отдельную таблицу.

## Таблица: `xf_char_profile_sheet`

- Поле: `user_id`
- Тип: `int unsigned`
- Где: `xf_char_profile_sheet`
- Default: нет
- Меняет: система
- История: нет
- Индексация: primary key

- Поле: `sheet_data`
- Тип: `mediumtext` или `json`
- Где: `xf_char_profile_sheet`
- Default: пустой JSON
- Меняет: администратор/владелец профиля/пользователь с правом `manageCharacterSheet`
- Когда меняется: при сохранении листа персонажа
- История: желательно да, когда лист будет реализован
- Индексация: нет
- Примечание: пока можно не добавлять, потому что лист персонажа вне текущего scope

- Поле: `last_edit_user_id`
- Тип: `int unsigned`
- Где: `xf_char_profile_sheet`
- Default: `0`
- Меняет: система
- История: желательно да
- Индексация: да

- Поле: `last_edit_date`
- Тип: `int unsigned`
- Где: `xf_char_profile_sheet`
- Default: `0`
- Меняет: система
- История: желательно да
- Индексация: да

### Опциональная схема

```sql
CREATE TABLE xf_char_profile_sheet (
  user_id int unsigned NOT NULL,
  sheet_data mediumtext,
  last_edit_user_id int unsigned NOT NULL DEFAULT 0,
  last_edit_date int unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (user_id),
  KEY last_edit_user_id (last_edit_user_id),
  KEY last_edit_date (last_edit_date)
);
```

---

# 10. Лог действий

Cursor отдельно уточнил, что нужно логировать add/edit/delete: кто, когда, `user_id`, по какому типу данных.

Лучше сделать общий лог действий.

## Таблица: `xf_char_profile_action_log`

### Механика: Запись действия

- Поле: `action_log_id`
- Тип: `int unsigned auto_increment`
- Где: `xf_char_profile_action_log`
- Default: нет
- Меняет: система
- История: это и есть история
- Индексация: primary key

- Поле: `target_user_id`
- Тип: `int unsigned`
- Где: `xf_char_profile_action_log`
- Default: нет
- Меняет: система
- История: да
- Индексация: да
- Примечание: чей профиль изменили

- Поле: `actor_user_id`
- Тип: `int unsigned`
- Где: `xf_char_profile_action_log`
- Default: `0`
- Меняет: система
- История: да
- Индексация: да
- Примечание: кто выполнил действие

- Поле: `content_type`
- Тип: `varchar(50)`
- Где: `xf_char_profile_action_log`
- Default: пустая строка
- Меняет: система
- История: да
- Индексация: да
- Примеры:
  - `hero`
  - `reputation`
  - `backpack_activity`
  - `backpack_crafted`
  - `character_sheet`

- Поле: `content_id`
- Тип: `int unsigned`
- Где: `xf_char_profile_action_log`
- Default: `0`
- Меняет: система
- История: да
- Индексация: да
- Примечание: ID изменённой записи

- Поле: `action`
- Тип: `enum('add','edit','delete')` или `varchar(20)`
- Где: `xf_char_profile_action_log`
- Default: нет
- Меняет: система
- История: да
- Индексация: да

- Поле: `old_data`
- Тип: `mediumtext` или `json`
- Где: `xf_char_profile_action_log`
- Default: пустой JSON
- Меняет: система
- История: да
- Индексация: нет
- Примечание: состояние до изменения

- Поле: `new_data`
- Тип: `mediumtext` или `json`
- Где: `xf_char_profile_action_log`
- Default: пустой JSON
- Меняет: система
- История: да
- Индексация: нет
- Примечание: состояние после изменения

- Поле: `log_date`
- Тип: `int unsigned`
- Где: `xf_char_profile_action_log`
- Default: `0`
- Меняет: система
- История: да
- Индексация: да

### Минимальная схема

```sql
CREATE TABLE xf_char_profile_action_log (
  action_log_id int unsigned NOT NULL AUTO_INCREMENT,
  target_user_id int unsigned NOT NULL,
  actor_user_id int unsigned NOT NULL DEFAULT 0,
  content_type varchar(50) NOT NULL DEFAULT '',
  content_id int unsigned NOT NULL DEFAULT 0,
  action varchar(20) NOT NULL DEFAULT '',
  old_data mediumtext,
  new_data mediumtext,
  log_date int unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (action_log_id),
  KEY target_user_id (target_user_id),
  KEY actor_user_id (actor_user_id),
  KEY content_type (content_type),
  KEY content_id (content_id),
  KEY action (action),
  KEY log_date (log_date)
);
```

---

# 11. Настройки ACP

Настройки лучше хранить через стандартные options XenForo, а не в своей таблице.

---

## Механика: ID группы-триггера

- Поле: `charProfileAcceptedGroupId`
- Тип: XenForo option / `int unsigned`
- Где: ACP options
- Default: `16`
- Меняет: администратор форума
- Когда меняется: при настройке аддона
- История: нет
- Индексация: нет

---

## Механика: Максимум очков геройства

- Поле: `charProfileHeroMax`
- Тип: XenForo option / `tinyint unsigned`
- Где: ACP options
- Default: `3`
- Меняет: администратор форума
- Когда меняется: при настройке лимитов
- История: нет
- Индексация: нет
- Примечание: лучше вынести в настройку, хотя сейчас по ТЗ максимум всегда `3`

---

## Механика: Включить логирование

- Поле: `charProfileEnableActionLog`
- Тип: XenForo option / `bool`
- Где: ACP options
- Default: `1`
- Меняет: администратор форума
- Когда меняется: при настройке аддона
- История: нет
- Индексация: нет

---

# 12. Что лучше вычислять, а не хранить

```text
has_character_profile
can_view_tabs
hero_points_icons
hero_points_overflow_display
influence_negative
influence_positive
influence_total
faction_reputation_total
faction_relation_label
```

---

# 13. Что обязательно хранить

```text
xf_char_profile
xf_char_profile_hero_log
xf_char_profile_reputation_log
xf_char_profile_backpack_activity_item
xf_char_profile_backpack_crafted_item
xf_char_profile_action_log
```

Опционально:

```text
xf_char_profile_sheet
```

---

# 14. Краткая логика пересчётов

## При выдаче группы 16

```text
- проверить, есть ли строка в xf_char_profile;
- если строки нет, создать её;
- вкладки становятся видимыми.
```

## При снятии группы 16

```text
- ничего не удалять;
- данные остаются в БД;
- вкладки скрываются.
```

## При добавлении/редактировании/удалении ОГ

```text
- отсортировать все записи пользователя: event_date ASC, created_date ASC, hero_log_id ASC;
- пройти хронологическим алгоритмом (см. §21.3): running, effective_amount, burned_amount, is_overflow;
- hero_points_cache = финальный running (0..3);
- hero_points_raw_sum = финальный running после пересчёта (не наивная SUM(amount));
- effective_amount вычисляется при рендере и в БД не хранится;
- записать действие в xf_char_profile_action_log.
```

## При добавлении/редактировании/удалении репутации

```text
- сохранить запись в xf_char_profile_reputation_log;
- влияние и отношения фракций считать при выводе;
- записать действие в xf_char_profile_action_log.
```

## При добавлении/редактировании/удалении предмета

```text
- изменить соответствующую таблицу рюкзака;
- записать действие в xf_char_profile_action_log.
```

---

# 15. Что означает «миграция данных из guildM»

Миграция данных - это перенос уже существующих записей из старого плагина `guildM` в новый аддон.

В этом проекте миграция, скорее всего, не нужна, потому что `guildM` используется как референс, а не как источник старых данных.

То есть нужно:

```text
- копировать логику;
- копировать структуру блоков;
- копировать визуальный принцип репутации и склада;
- не переносить существующие записи из таблиц гильдий.
```

Рекомендуемая формулировка для ТЗ:

```text
Миграция данных из guildM не требуется.
Из guildM копируется только логика, структура и визуальный принцип блоков репутации/склада.
```

---

# 16. Итоговый список таблиц

## Обязательные таблицы

```text
xf_char_profile
xf_char_profile_hero_log
xf_char_profile_reputation_log
xf_char_profile_backpack_activity_item
xf_char_profile_backpack_crafted_item
xf_char_profile_action_log
```

## Опциональная таблица

```text
xf_char_profile_sheet
```

---

# 17. Минимальный список для разработчика

```text
Аддон: Enterum/CharacterProfile

Основная таблица:
- xf_char_profile

Журналы и сущности:
- xf_char_profile_hero_log
- xf_char_profile_reputation_log
- xf_char_profile_backpack_activity_item
- xf_char_profile_backpack_crafted_item
- xf_char_profile_action_log

Опционально:
- xf_char_profile_sheet

Права:
- character_profile.view
- character_profile.manageHero
- character_profile.manageHeroSupport
- character_profile.manageReputation
- character_profile.manageBackpack
- character_profile.manageCharacterSheet
- character_profile.manageCharacterSheetOwn
- character_profile.viewLogs

ACP options:
- charProfileAcceptedGroupId = 16
- charProfileHeroMax = 3
- charProfileEnableActionLog = 1
- charProfileItemsPerPage = 50
```

---

# 18. Рекомендуемый порядок разработки

```text
Фаза 1 - Каркас аддона
- addon.json
- Setup.php
- routes
- permissions.xml
- options.xml
- подключение вкладок в профиль пользователя

Фаза 2 - Группа-триггер
- настройка ID группы в ACP
- создание xf_char_profile при выдаче группы
- скрытие вкладок при снятии группы

Фаза 3 - Очки геройства
- форма добавления
- журнал ОГ
- пересчёт суммы
- отображение точек
- правило сгорания
- двухколоночный вывод

Фаза 4 - Репутация
- перенос логики из guildM
- замена guild_id на user_id
- таблицы регионов
- влияние
- отношения фракций

Фаза 5 - Рюкзак
- предметы за активности
- созданные предметы
- подсказка автора через @ник

Фаза 6 - Лист персонажа
- заглушка «Скоро»
- права на будущую вкладку

Фаза 7 - ACP и логи
- настройки
- общий лог add/edit/delete

Фаза 8 - Тестирование
- проверка прав
- проверка скрытия вкладок
- проверка сохранения данных
- проверка удаления/редактирования записей
```

---

# 19. Вне scope текущего этапа

```text
- полноценная реализация листа персонажа;
- миграция данных из guildM;
- перенос существующих записей гильдий в профиль пользователя;
- сложная фильтрация предметов;
- отдельная админ-панель для ручной правки всех данных.
```

---

# 20. Краткое резюме архитектуры

```text
xf_char_profile
- хранит одну базовую строку на пользователя;
- хранит кэш ОГ;
- создаётся при выдаче группы 16.

xf_char_profile_hero_log
- хранит все операции с очками геройства;
- на основе этой таблицы считается текущее количество ОГ.

xf_char_profile_reputation_log
- хранит всю репутацию по регионам и фракциям;
- влияние и отношения считаются при выводе.

xf_char_profile_backpack_activity_item
- хранит предметы, полученные за игровые активности.

xf_char_profile_backpack_crafted_item
- хранит предметы, созданные для персонажа.

xf_char_profile_action_log
- хранит историю add/edit/delete.

xf_char_profile_sheet
- можно добавить позже для полноценного листа персонажа.
```


---

# 21. Финальные правки и ответы на вопросы Cursor

Этот раздел фиксирует решения, которые имеют приоритет над более ранними черновыми формулировками ТЗ, если где-то осталось расхождение.

## 21.1. Предметы за активности

Решение: **схема расширяется сознательно**.

Блок «Предметы за активности» не является буквальным переносом склада `guildM` один-в-один. Из `guildM` копируется визуальная логика, общий принцип таблицы и подход к ссылкам/иконкам, но для профиля персонажа используется расширенная схема.

Итоговая логика:

```text
Предметы за активности = расширенный склад персонажа
Связь с профилем = user_id
Иконка перед названием = fa-solid fa-box
```

Рекомендуемые колонки сохранённой таблицы:

```text
Название | Тип | Ур | Редкость | Причина / Источник
```

Дополнительные поля в БД для этого блока:

- Поле: `item_description`
- Тип: `text`
- Где: `xf_char_profile_backpack_activity_item`
- Default: пустая строка
- Меняет: администратор/пользователь с правом `manageBackpack`
- История: да, через `xf_char_profile_action_log`
- Индексация: нет
- Примечание: описание предмета, если нужно раскрывать строку через `<details>`

- Поле: `rarity_key`
- Тип: `varchar(30)`
- Где: `xf_char_profile_backpack_activity_item`
- Default: `common`
- Меняет: администратор/пользователь с правом `manageBackpack`
- История: да, через `xf_char_profile_action_log`
- Индексация: да
- Примечание: редкость предмета, например `common`, `uncommon`, `rare`, `unique`

- Поле: `reason`
- Тип: `varchar(255)`
- Где: `xf_char_profile_backpack_activity_item`
- Default: пустая строка
- Меняет: администратор/пользователь с правом `manageBackpack`
- История: да, через `xf_char_profile_action_log`
- Индексация: нет
- Примечание: причина получения, например `Получено за квест`, `Подарок от Тайного Санты`, `Битва с монстром`

Обновлённая минимальная схема для `xf_char_profile_backpack_activity_item`:

```sql
CREATE TABLE xf_char_profile_backpack_activity_item (
  activity_item_id int unsigned NOT NULL AUTO_INCREMENT,
  user_id int unsigned NOT NULL,
  item_name varchar(255) NOT NULL DEFAULT '',
  item_url varchar(500) NOT NULL DEFAULT '',
  item_description text,
  item_type varchar(100) NOT NULL DEFAULT '',
  item_level smallint unsigned NOT NULL DEFAULT 0,
  rarity_key varchar(30) NOT NULL DEFAULT 'common',
  source_url varchar(500) NOT NULL DEFAULT '',
  source_title varchar(255) NOT NULL DEFAULT '',
  reason varchar(255) NOT NULL DEFAULT '',
  quantity int unsigned NOT NULL DEFAULT 1,
  note text,
  display_order int unsigned NOT NULL DEFAULT 0,
  created_by_user_id int unsigned NOT NULL DEFAULT 0,
  created_date int unsigned NOT NULL DEFAULT 0,
  last_edit_user_id int unsigned NOT NULL DEFAULT 0,
  last_edit_date int unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (activity_item_id),
  KEY user_id (user_id),
  KEY item_name (item_name),
  KEY item_type (item_type),
  KEY item_level (item_level),
  KEY rarity_key (rarity_key),
  KEY display_order (display_order),
  KEY created_by_user_id (created_by_user_id),
  KEY created_date (created_date)
);
```

---

## 21.2. Шкала отношений фракций

Решение: **использовать шкалу из `guildM`, а не шкалу из раннего черновика ТЗ**.

Итоговая шкала:

```text
<= -100: Презренный
-99 - -70: Ненавистный
-69 - -40: Разыскиваемый
-39 - -11: Подозрительный
-10 - 10: Нейтральный
11 - 39: Дружественный
40 - 69: Подающий надежды
70 - 99: Герой
>= 100: Легенда
```

Если в `ReputationDisplay.php` у `guildM` значения отличаются, приоритет имеет код `guildM`: нужно повторить его поведение один-в-один.

---

## 21.3. Алгоритм сгорания очков геройства

Решение:

- итоговое количество ОГ не может быть меньше `0` и больше `3`;
- траты ОГ отображаются красным всегда;
- строки, где часть полученных ОГ сгорела из-за лимита `3`, отображаются оранжевым цветом `rgb(221, 80, 0)` / `#DD5000`;
- при редактировании старой записи пересчитываются все строки ОГ этого пользователя.

### Порядок пересчёта

Для расчёта используется хронологический порядок:

```text
event_date ASC, created_date ASC, hero_log_id ASC
```

Для отображения в таблице используется обратный порядок:

```text
event_date DESC, created_date DESC, hero_log_id DESC
```

### Нормализация amount

В БД `amount` хранится **всегда положительным числом**: `1`, `2` или `3`.

Знак операции определяется через `operation_type`:

```text
gain    -> +amount
support -> +amount
loss    -> -amount
```

Если в интерфейсе пользователь ввёл отрицательное число, система делает автокоррекцию:

```text
abs(value)
```

Примеры:

```text
operation_type = loss, пользователь ввёл -1 -> в БД amount = 1, эффективное значение = -1
operation_type = loss, пользователь ввёл 1  -> в БД amount = 1, эффективное значение = -1
operation_type = gain, пользователь ввёл -2 -> в БД amount = 2, эффективное значение = +2
```

`0` запрещён.

Значения больше `3` запрещены.

### Псевдокод пересчёта

```php
$running = 0;

foreach ($logsSortedAsc as $log) {
    $amount = abs((int) $log->amount);

    if ($log->operation_type === 'loss') {
        $signed = -$amount;
    } else {
        $signed = $amount;
    }

    $before = $running;
    $afterRaw = $before + $signed;
    $afterClamped = max(0, min(3, $afterRaw));

    $effective = $afterClamped - $before;

    $burned = 0;
    $isOverflow = false;

    if ($signed > 0 && $afterRaw > 3) {
        $burned = $afterRaw - 3;
        $isOverflow = true;
    }

    $log->effective_amount = $effective;
    $log->burned_amount = $burned;
    $log->is_overflow = $isOverflow ? 1 : 0;

    $running = $afterClamped;
}

$profile->hero_points_cache = $running;
$profile->hero_points_raw_sum = $running;
```

`effective_amount` вычисляется при пересчёте и рендере, **в БД не хранится**. В таблице сохраняются только `burned_amount` и `is_overflow`.

### Цвета строк ОГ

```text
operation_type = loss       -> красная строка
is_overflow = 1             -> оранжевая строка #DD5000
обычная полученная запись   -> стандартный цвет текста
support                     -> стандартный цвет текста, если нет overflow
```

Оранжевый цвет (`#DD5000`) используется **только** для overflow (`is_overflow = 1`). Оранжевые строки «Получено» на старом макете (экспедиции и т.п.) — устаревший вариант, не реализовывать.

Если строка одновременно подходит под несколько правил, приоритет:

```text
overflow > loss > normal
```

Но на практике `loss` не должен давать overflow.

---

## 21.4. Вкладки, видимость и доступ

Решение: вкладки видят **и гости, и зарегистрированные пользователи**, если у владельца профиля есть группа-триггер.

Формула видимости:

```text
target_user_has_group_16 AND viewer_can_view_character_profile
```

Где:

```text
target_user_has_group_16 = владелец профиля состоит в группе «Принятые игроки с анкетой»
viewer_can_view_character_profile = у группы зрителя есть permission character_profile.view
```

Для гостей нужно выдать `character_profile.view` группе XenForo:

```text
Unregistered / Unconfirmed
```

Если у пользователя нет группы 16, вкладки не показываются никому, даже если у зрителя есть право `view`. Данные при этом остаются в БД.

---

## 21.5. Триггер группы и создание данных

Решение:

```text
Listener на изменение групп пользователя: да
Lazy-init при первом заходе в профиль: нет
Batch для уже существующих пользователей группы 16 при установке аддона: да
```

### При установке аддона

Запустить batch через **Setup step** (`installStep2` / `upgradeStep`):

```text
найти всех пользователей, которые уже состоят в группе charProfileAcceptedGroupId
для каждого пользователя создать строку xf_char_profile, если её ещё нет
```

Отдельная CLI-команда на текущем этапе не требуется.

### При выдаче группы 16

```text
создать строку xf_char_profile, если её ещё нет
```

### При снятии группы 16

```text
ничего не удалять
вкладки скрыть
data сохранить
```

---

## 21.6. Интеграция в профиль XenForo

Вкладки должны быть встроены в профиль пользователя как отдельные страницы профиля, а не как блок внутри одной общей вкладки.

### Позиции вкладок

```text
1. стандартная первая вкладка профиля XenForo
2. Репутация
3. Рюкзак
4. Лист персонажа
```

### Рекомендуемые routes

```text
GET  members/{user_id}/reputation
POST members/{user_id}/reputation/hero/add
POST members/{user_id}/reputation/hero/edit
POST members/{user_id}/reputation/hero/delete
POST members/{user_id}/reputation/log/add
POST members/{user_id}/reputation/log/edit
POST members/{user_id}/reputation/log/delete

GET  members/{user_id}/backpack
POST members/{user_id}/backpack/activity/add
POST members/{user_id}/backpack/activity/edit
POST members/{user_id}/backpack/activity/delete
POST members/{user_id}/backpack/crafted/add
POST members/{user_id}/backpack/crafted/edit
POST members/{user_id}/backpack/crafted/delete

GET  members/{user_id}/character-sheet
POST members/{user_id}/character-sheet/save
```

`POST` после сохранения, редактирования или удаления должен возвращать пользователя на ту же вкладку и в тот же блок, где было выполнено действие.

Пример:

```text
сохранили ОГ -> редирект обратно на members/{user_id}/reputation#hero
сохранили созданный предмет -> редирект обратно на members/{user_id}/backpack#crafted
```

---

## 21.7. UI очков геройства

UI должен повторять приложенные референсы.

### Свёрнутое состояние

Вверху:

```text
Актуальное количество Очков Геройства: ● ● ●
```

Заполненные точки — `<i class="fa-solid fa-circle cp-heroDot cp-heroDot--filled"></i>`, пустые — `<i class="fa-regular fa-circle cp-heroDot cp-heroDot--empty"></i>`.

CSS:

```css
.cp-heroDot--filled { color: #DD5000; }
.cp-heroDot--empty { color: #DD5000; opacity: 0.35; }
```

Под ним горизонтальная линия.

Ниже свёрнутый блок:

```text
▸ Получение Очков Героизма
```

### Развёрнутое состояние

```text
▾ Получение Очков Героизма

[Дата] [Получено/Потрачено] [К-во] [Ссылка] [Название ссылки] [☑ Поддержка] [💾]

левая колонка записей       | правая колонка записей
```

### Форма добавления ОГ

Колонки формы:

```text
Дата | Получено/Потрачено | К-во ОГ | Ссылка | Название ссылки | Поддержка | Сохранить
```

Поле `Дата`:

```text
по умолчанию текущая дата в формате дд.мм.гггг
можно изменить вручную
```

Поле `Получено/Потрачено`:

```text
Получено ОГ
Потрачено ОГ
```

Поле `К-во ОГ`:

```text
разрешено: 1, 2, 3
если введено -1, -2 или -3, система берёт модуль числа
0 запрещён
больше 3 запрещено
```

Поле `Ссылка`:

```text
обязательно для обычных записей
должно начинаться с https://
для support-записи блокируется и очищается
```

Поле `Название ссылки`:

```text
обязательно для обычных записей
используется как текст активной ссылки
для support-записи блокируется и очищается
```

Галочка `Поддержка`:

```text
видна только пользователям с правом character_profile.manageHeroSupport
при включении блокирует operation_type, source_url и source_title
operation_type = support
is_support = 1
```

### Вид сохранённой записи ОГ

Запись должна выглядеть как на референсе:

```text
Дата - Получено 1 ОГ Квест «Название»
Дата - Потрачено 1 ОГ БМ «Название»
Дата - Получено 1 ОГ за поддержку
```

Если есть ссылка, кликабельным текстом становится `source_title`.

Пример:

```text
26.04.2023 - Получено 1 ОГ Квест «Падение»
```

Редактирование и удаление:

```text
у каждой записи есть иконка редактирования
у каждой записи есть иконка удаления/ластика
```

---

## 21.8. UI репутации

Репутация находится на вкладке «Репутация» **под блоком очков геройства**, а не в отдельной большой вкладке.

Копируется логика из `guildM`:

```text
таблица влияния по биомам
подвкладки АРАМИДИС / КОРЗУС / ЮНИОН
спойлеры по фракциям с записями
форма добавления положительной/отрицательной репутации
inline-edit через details
иконки edit/delete как в guildM
визуальные relation pill / tooltips как в guildM
```

Что изменить относительно `guildM`:

```text
guild_id -> user_id
строку «Мировая» / «Мировая известность» не показывать
source_title в профиле должен быть кастомным текстом ссылки, а не всегда «Ссылка на источник»
CSS-классы не копировать с префиксом gm-, а сделать аналоги с новым префиксом cp-
```

### Таблица влияния

Показывать только:

```text
АРАМИДИС
КОРЗУС
ЮНИОН
```

Строку:

```text
Мир / Мировая / Мировая известность
```

не выводить.

---

## 21.9. UI рюкзака

Вкладка «Рюкзак» содержит две подвкладки:

```text
Предметы за активности
Созданные предметы
```

### Предметы за активности

Перед названием предмета ставится иконка:

```html
<i class="fa-solid fa-box"></i>
```

Рекомендуемые колонки сохранённой таблицы:

```text
Название | Тип | Ур | Редкость | Причина / Источник
```

Колонка «Причина» = поле `reason`. Если есть `source_url`, текст `reason` (или `source_title`, если `reason` пуст) — кликабельная ссылка.

Если заполнено `item_description`, в колонке «Тип» или рядом показывается `<details><summary>Описание</summary>...</details>`.

Иконка перед названием — **только** `fa-solid fa-box` (без gift и других вариантов).

Бейджи редкости — по аналогии с `guildM`, но с префиксом `cp-`:

```text
cp-itemQuality cp-itemQuality--common
cp-itemQuality cp-itemQuality--uncommon
cp-itemQuality cp-itemQuality--rare
cp-itemQuality cp-itemQuality--unique
```

Сортировка:

```text
display_order ASC, created_date DESC, activity_item_id DESC
```

### Созданные предметы

Перед названием предмета ставится иконка:

```html
<i class="fa-solid fa-screwdriver-wrench"></i>
```

Колонки формы добавления:

```text
Название предмета | Ссылка на предмет | Тип предмета | Уровень | Ссылка | Автор | Сохранить
```

Колонки сохранённой таблицы:

```text
Название | Тип | Ур | Ссылка | Автор
```

В колонке `Название` выводится:

```text
иконка + кликабельное название предмета + уровень в скобках, если это нужно визуально
```

Пример:

```text
<i class="fa-solid fa-screwdriver-wrench"></i> Камень эонов [Жемчужно-белый валик] (3)
```

Поле `Ссылка` в сохранённой таблице всегда отображается словом:

```text
Заявка
```

### Дополнительное поле сортировки для созданных предметов

- Поле: `display_order`
- Тип: `int unsigned`
- Где: `xf_char_profile_backpack_crafted_item`
- Default: `0`
- Меняет: администратор/пользователь с правом `manageBackpack`
- История: нет
- Индексация: да

Обновлённая минимальная схема для `xf_char_profile_backpack_crafted_item`:

```sql
CREATE TABLE xf_char_profile_backpack_crafted_item (
  crafted_item_id int unsigned NOT NULL AUTO_INCREMENT,
  user_id int unsigned NOT NULL,
  item_name varchar(255) NOT NULL DEFAULT '',
  item_url varchar(500) NOT NULL DEFAULT '',
  item_type varchar(100) NOT NULL DEFAULT '',
  item_level smallint unsigned NOT NULL DEFAULT 0,
  request_url varchar(500) NOT NULL DEFAULT '',
  author_name varchar(100) NOT NULL DEFAULT '',
  display_order int unsigned NOT NULL DEFAULT 0,
  created_by_user_id int unsigned NOT NULL DEFAULT 0,
  created_date int unsigned NOT NULL DEFAULT 0,
  last_edit_user_id int unsigned NOT NULL DEFAULT 0,
  last_edit_date int unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (crafted_item_id),
  KEY user_id (user_id),
  KEY item_name (item_name),
  KEY item_type (item_type),
  KEY item_level (item_level),
  KEY author_name (author_name),
  KEY display_order (display_order),
  KEY created_by_user_id (created_by_user_id),
  KEY created_date (created_date)
);
```

---

## 21.10. ACP и логи

Для логов нужна отдельная страница в ACP.

### ACP menu

```text
Профиль игрока -> Логи действий
```

### Admin permission

Добавить отдельное admin permission:

```text
characterProfile
```

Или более точечно:

```text
characterProfileLogs
```

### Route ACP

```text
admin.php?character-profile/logs
```

### Фильтры логов

```text
target_user_id - чей профиль изменили
actor_user_id - кто изменил
content_type - hero / reputation / backpack_activity / backpack_crafted / character_sheet
action - add / edit / delete
date_from
date_to
```

### Пагинация

Да, обязательна.

Рекомендуемый размер страницы:

```text
50 записей на страницу
```

---

## 21.11. Стили

Решение: использовать новый CSS-префикс.

```text
cp-
```

или более длинный:

```text
charProfile-
```

Рекомендованный вариант:

```text
cp-
```

Примеры:

```text
cp-hero
cp-heroHeader
cp-heroLog
cp-heroLog--loss
cp-heroLog--overflow
cp-relationPill
cp-backpackTable
cp-reputationTable
cp-itemQuality
cp-itemQuality--common
cp-itemQuality--uncommon
cp-itemQuality--rare
cp-itemQuality--unique
cp-heroDot
cp-heroDot--filled
cp-heroDot--empty
```

Внешний вид можно копировать из `guildM`, но не нужно напрямую использовать классы `gm-*`, чтобы стили двух аддонов не конфликтовали.

Font Awesome уже подключён на форуме.

---

## 21.12. Сортировка журналов

### Очки геройства

Отображение:

```text
event_date DESC, created_date DESC, hero_log_id DESC
```

Расчёт сгорания:

```text
event_date ASC, created_date ASC, hero_log_id ASC
```

### Репутация

Как в `guildM`:

```text
created_date DESC, reputation_log_id DESC
```

### Рюкзак

Предметы за активности:

```text
display_order ASC, created_date DESC, activity_item_id DESC
```

Созданные предметы:

```text
display_order ASC, created_date DESC, crafted_item_id DESC
```

---

## 21.13. Обязательность полей

### ОГ

```text
source_url обязателен, кроме support-записей
source_title обязателен, кроме support-записей
amount обязателен
operation_type обязателен
event_date обязателен
```

### Репутация

```text
character_name обязателен
faction_name обязателен
amount обязателен
operation_type обязателен
source_url обязателен
source_title обязателен
region_key обязателен
```

### Рюкзак

Предметы за активности:

```text
item_name обязателен
reason обязателен (текст колонки «Причина»)
item_type желателен, но может быть пустым
item_level может быть 0
source_url необязателен; если заполнен — reason/source_title становится ссылкой
```

Созданные предметы:

```text
item_name обязателен
item_url обязателен
item_type обязателен
item_level обязателен
request_url обязателен
author_name обязателен
```

---

## 21.14. Пагинация

Пагинация нужна для всех больших списков.

```text
ОГ: да, при 50+ записях
Репутация: да, при 50+ записях
Предметы за активности: да, при 50+ записях
Созданные предметы: да, при 50+ записях
ACP-логи: да, всегда
```

Рекомендуемый option:

- Поле: `charProfileItemsPerPage`
- Тип: XenForo option / `int unsigned`
- Где: ACP options
- Default: `50`
- Меняет: администратор
- История: нет
- Индексация: нет

---

## 21.15. Подсказка @ник

Для поля `author_name` нужна подсказка по пользователям.

```text
минимум символов после @: 2
```

Логика:

```text
пользователь вводит @На
появляется список подходящих ников
при клике ник вставляется в поле author_name
в БД сохраняется текст, не user_id
```

Можно использовать endpoint по аналогии с `guildM` / стандартным поиском пользователей XenForo.

---

## 21.16. Лист персонажа

На текущем этапе вкладка остаётся заглушкой.

При этом на странице можно уже показать иконки будущих подвкладок:

```html
<i class="fa-solid fa-person"></i> Персонаж
<i class="fa-solid fa-wand-magic-sparkles"></i> Колдовство
<i class="fa-solid fa-hand"></i> Мастерство
<i class="fa-solid fa-medal"></i> Способности
<i class="fa-solid fa-clipboard-list"></i> Дополнительно
```

Под ними текст:

```text
Скоро
```

Таблицу `xf_char_profile_sheet` сейчас не создавать. Отложить до v2.

---

## 21.17. `operation_type` и `is_support`

Решение: нужны оба поля, они должны быть синхронны.

```text
operation_type = support -> is_support = 1
operation_type = gain/loss -> is_support = 0
is_support = 1 -> operation_type принудительно support
```

`is_support` нужен для быстрых проверок и отдельной стилизации/фильтрации.

`operation_type` нужен для общей логики операций.

---

## 21.18. Ошибки валидации

Тексты ошибок должны быть свои, на русском языке.

Примеры:

```text
Введите ссылку, начинающуюся с https://
Количество ОГ должно быть от 1 до 3.
Нельзя сохранить запись с 0 ОГ.
Укажите название ссылки.
Укажите имя персонажа.
Укажите название фракции.
Укажите автора предмета.
У вас нет прав на выполнение этого действия.
```

Фразы оформить через phrase system XenForo.

---

# 22. Обновлённый минимальный набор для разработки

## Add-on

```text
Namespace: Enterum/CharacterProfile
version_id: 1
version_string: 0.0.1 Alpha
```

## Обязательные таблицы

```text
xf_char_profile
xf_char_profile_hero_log
xf_char_profile_reputation_log
xf_char_profile_backpack_activity_item
xf_char_profile_backpack_crafted_item
xf_char_profile_action_log
```

## Таблицу отложить до v2

```text
xf_char_profile_sheet
```

## Permissions

```text
character_profile.view
character_profile.manageHero
character_profile.manageHeroSupport
character_profile.manageReputation
character_profile.manageBackpack
character_profile.manageCharacterSheet
character_profile.manageCharacterSheetOwn
character_profile.viewLogs
```

## Admin permission

```text
characterProfile
```

## ACP options

```text
charProfileAcceptedGroupId = 16
charProfileHeroMax = 3
charProfileEnableActionLog = 1
charProfileItemsPerPage = 50
```

## Технические решения

```text
Предметы за активности: расширенная схема
Шкала отношений: из guildM
ОГ amount: в БД всегда положительный, знак через operation_type
Траты ОГ: красная строка
Overflow ОГ: #DD5000
Вкладки видят: гости и зарегистрированные, если владелец профиля в группе 16
Создание профиля: listener на группу + batch при установке
Lazy-init: нет
Рюкзак: две подвкладки
Репутация: блок под ОГ
Лист персонажа: заглушка с иконками, таблицу sheet отложить до v2
CSS-префикс: cp-
```
