# Чеклист аудита аддона «Профиль игрока» (Enterum/CharacterProfile)

**Версия аддона:** 0.0.5 Alpha 6 (version_id 33)  
**Дата проверки:** 9 июля 2026  
**Метод:** статический анализ исходного кода (40 PHP-файлов), сверка с ТЗ, проверка цепочек прав/CSRF/SQL.

---

## Сводка

| Категория | Результат |
|-----------|-----------|
| Критические уязвимости | **не обнаружены** |
| Высокий риск | **не обнаружены** |
| Средний риск | 2 замечания (DDL в runtime, перечисление ников) |
| Низкий риск | 3 замечания |
| Информационные | 4 замечания |

**Общий вывод:** аддон можно использовать на продакшене при корректной настройке прав XenForo. Прямого доступа к ACP, SQL-инъекций и обхода прав на изменение чужих данных не найдено.

---

## 1. Авторизация и права доступа

| # | Проверка | Как проверено | Результат |
|---|----------|---------------|-----------|
| 1.1 | Просмотр вкладок только при группе-триггере + `character_profile.view` | `PermissionGuard::profileTabsVisibleFor`, `AbstractProfileAction::loadProfileUser` | ✅ OK |
| 1.2 | ОГ: только `manageHero` / `manageHeroSupport` | `HeroPointManager::assertCanManage`, шаблон скрывает формы | ✅ OK |
| 1.3 | Репутация: только `manageReputation` | `ReputationLogManager::assertCanManage` | ✅ OK |
| 1.4 | Рюкзак (активности/созданное): только `manageBackpack` | `ActivityItemManager`, `CraftedItemManager` | ✅ OK |
| 1.5 | Рюкзак «Прочее»: `manageBackpack` или свой профиль + `manageBackpackOwn` | `PermissionGuard::canManageBackpackOther`, `OtherContentManager` | ✅ OK |
| 1.6 | Лист персонажа: заглушка, права зарезервированы | `CharacterSheet.php`, `canManageCharacterSheet` в шаблонах | ✅ OK (v2) |
| 1.7 | ACP-логи: admin permission `characterProfile` + (`is_super_admin` / `is_admin` / `viewLogs`) | `Admin\Controller\Logs::preDispatchController` | ✅ OK |
| 1.8 | Гость не может менять данные | Все `canManage*` требуют `$visitor->user_id` | ✅ OK |
| 1.9 | Просмотр профиля XenForo (`canViewFullProfile`) | `assertViewableProfileUser` | ✅ OK |

---

## 2. CSRF и HTTP-методы

| # | Проверка | Как проверено | Результат |
|---|----------|---------------|-----------|
| 2.1 | Все POST-действия с CSRF | `Hero`, `ReputationLog`, `BackpackActivity`, `BackpackCrafted`, `BackpackOther` — `assertPostOnly` + `assertValidCsrfToken` | ✅ OK |
| 2.2 | GET только для чтения | Просмотр вкладок, `BackpackFindUsers` (поиск) | ✅ OK |
| 2.3 | Формы в шаблонах с `xf:csrf` | `templates.xml` | ✅ OK (при сборке XF) |

---

## 3. IDOR (изменение чужих записей по ID)

| # | Проверка | Как проверено | Результат |
|---|----------|---------------|-----------|
| 3.1 | ОГ: запись привязана к `user_id` из URL | `Hero::assertHeroLog` — сравнение `log->user_id` и `$user->user_id` | ✅ OK |
| 3.2 | Репутация: то же | `ReputationLog::assertReputationLog` | ✅ OK |
| 3.3 | Активности рюкзака | `BackpackActivity::assertActivityItem` | ✅ OK |
| 3.4 | Созданные предметы | `BackpackCrafted::assertCraftedItem` | ✅ OK |
| 3.5 | «Прочее» привязано к профилю из URL | `BackpackOther::actionSave` + `loadProfileUser` | ✅ OK |

---

## 4. SQL-инъекции

| # | Проверка | Как проверено | Результат |
|---|----------|---------------|-----------|
| 4.1 | ACP-логи: параметризованные запросы | `CharProfileActionLog::fetchLogsForAdmin` — `?` placeholders | ✅ OK |
| 4.2 | Сортировка логов — whitelist | `buildAdminOrderBy` — фиксированные поля | ✅ OK |
| 4.3 | Фильтр типа контента — whitelist | `Logs::actionIndex` + `ActionLogDisplay::getContentTypeTabs` | ✅ OK |
| 4.4 | Поиск ников — `escapeLike` | `BackpackFindUsers`, репозиторий логов | ✅ OK |
| 4.5 | Entity Finder для остального CRUD | XenForo ORM | ✅ OK |

---

## 5. XSS (межсайтовый скриптинг)

| # | Проверка | Как проверено | Результат |
|---|----------|---------------|-----------|
| 5.1 | Вывод в шаблонах через XF (`{$var}`) | `templates.xml` — автоэкранирование | ✅ OK |
| 5.2 | BB-код «Прочее» через рендерер XenForo | `BbCodeContent::renderToHtml` | ✅ OK (зависит от настроек BB-кода форума) |
| 5.3 | ACP «Детали» лога — текст, не HTML | `{$row.details}` в admin-шаблоне | ✅ OK |
| 5.4 | JSON в логах укорочен | `ActionLogDisplay::truncate` | ✅ OK |

---

## 6. Доступ к ACP и админке

| # | Проверка | Как проверено | Результат |
|---|----------|---------------|-----------|
| 6.1 | Нет публичных маршрутов в admin | `routes.xml` — admin только `character-profile/logs` | ✅ OK |
| 6.2 | `assertAdminPermission('characterProfile')` | `Logs::preDispatchController` | ✅ OK |
| 6.3 | Право `viewLogs` не открывает ACP само по себе | Нужен вход в admin.php + admin permission | ✅ OK |
| 6.4 | Нет обхода через API без авторизации | Все pub-контроллеры наследуют `AbstractProfileAction` | ✅ OK |

---

## 7. Утечка данных и перечисление

| # | Проверка | Как проверено | Результат |
|---|----------|---------------|-----------|
| 7.1 | `BackpackFindUsers` — только с `manageBackpack` | `BackpackFindUsers::actionIndex` | ⚠️ **Низкий риск** — модераторы могут искать ники по префиксу (нужно для @автор) |
| 7.2 | Логи ACP видны только админам с правами | см. п. 1.7 | ✅ OK |
| 7.3 | Данные профиля видны при `view` + группа 16 | По ТЗ — задумано | ℹ️ Инфо |
| 7.4 | `old_data` / `new_data` в логах — полные снимки JSON | `ActionLogger` | ℹ️ Инфо — не отдавать логи гостям (уже в ACP) |

---

## 8. Mass assignment и валидация ввода

| # | Проверка | Как проверено | Результат |
|---|----------|---------------|-----------|
| 8.1 | Ввод через `$this->filter()` с типами | Все контроллеры | ✅ OK |
| 8.2 | Entity verify-методы (URL https, amount 1–3) | `CharProfileHeroLog`, др. entities | ✅ OK |
| 8.3 | Поля задаются явно в сервисах, не из `$_POST` целиком | Managers | ✅ OK |
| 8.4 | Регион репутации — resolve из whitelist | `ReputationLogManager::resolveRegion` | ✅ OK |

---

## 9. Схема БД и DDL в runtime

| # | Проверка | Как проверено | Результат |
|---|----------|---------------|-----------|
| 9.1 | Основные таблицы — через `Setup.php` | Install/upgrade steps | ✅ OK |
| 9.2 | `BackpackOtherSchema::ensureColumns` при просмотре рюкзака | `OtherContentManager::buildViewData` | ⚠️ **Средний** — ALTER на GET любым зрителем вкладки (если у MySQL-пользователя есть ALTER). Рекомендация: полагаться только на upgrade аддона |
| 9.3 | `Logs::ensureActionLogTableExists` в ACP | Только admin, CREATE IF NOT EXISTS | ⚠️ **Средний** — аналогично; только для админов |
| 9.4 | Удаление аддона — drop tables | `Setup::uninstallStep1` | ℹ️ Стандартное поведение XF |

---

## 10. Логирование действий

| # | Проверка | Как проверено | Результат |
|---|----------|---------------|-----------|
| 10.1 | Логи add/edit/delete для ОГ, репутации, рюкзака | Managers + `ActionLogger` | ✅ OK |
| 10.2 | Отключение логов через опцию | `charProfileEnableActionLog` | ✅ OK |
| 10.3 | `actor_user_id` из сессии, не из POST | Managers передают `$actor->user_id` | ✅ OK |

---

## 11. Комментирование кода

| # | Проверка | Как проверено | Результат |
|---|----------|---------------|-----------|
| 11.1 | Русские комментарии в PHP-файлах | Добавлены file/class docblocks во все 40 файлов | ✅ Выполнено |
| 11.2 | Понятна структура каталогов | См. `USER_GUIDE_RU.md`, раздел «Архитектура» | ✅ OK |

---

## Рекомендации (не блокируют релиз)

1. **DDL в runtime** — после стабильного деплоя можно убрать `BackpackOtherSchema::ensureColumns` из `buildViewData` и оставить только upgrade-шаги (снижает риск лишних ALTER).
2. **Фильтр `action` в ACP** — уже ограничен в репозитории whitelist `add|edit|delete` (усилено при аудите).
3. **Права на проде** — выдать `manage*` только модераторам/админам; `view` — гостям по необходимости; `viewLogs` — узкому кругу.
4. **Резервное копирование** — перед обновлением аддона бэкап таблиц `xf_char_profile_*`.

---

## Подпись проверки

| Поле | Значение |
|------|----------|
| Проверил | Cursor AI (статический аудит) |
| Объём | 40 PHP, routes, permissions, templates (выборочно) |
| Статус фазы 8 | **Приёмочное тестирование** — см. `ACCEPTANCE_TEST_RU.md` |
