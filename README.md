# Character Profile — Enterum/CharacterProfile

Аддон XenForo 2.2: вкладки **Репутация** и **Рюкзак** в профиле пользователя (очки героизма, репутация по биомам, предметы, ACP-логи).

**Версия:** 1.0.1

## Установка (FTP)

1. Скопируйте папку `upload/src/addons/Enterum/CharacterProfile/` на сервер в  
   `src/addons/Enterum/CharacterProfile/`
2. Убедитесь, что **нет** `hashes.json` в папке аддона
3. ACP → Аддоны → Установить / Обновить → пересборка кэша

Подробно: [INSTALL_PROD_RU.md](INSTALL_PROD_RU.md)

## Структура проекта

```
upload/src/addons/Enterum/CharacterProfile/
  addon.json              — метаданные аддона
  icon.webp               — иконка в ACP
  Setup.php               — таблицы + batch init группы-триггера
  Entity/                 — сущности БД
  Repository/             — выборки
  Service/                — ОГ, репутация, рюкзак, права, логи
  Pub/Controller/         — публичные страницы и POST-действия
  Admin/Controller/       — ACP-логи
  Listener/               — события шаблонов и User
  _data/                  — routes, permissions, templates, phrases
```

Папка `upload/` — это то, что вы заливаете на форум (содержимое `src/` кладётся в `src/` на сервере).

## Вкладки

| Вкладка | Статус |
|---------|--------|
| Репутация (ОГ + биомы) | Готово |
| Рюкзак (активности / созданные / прочее) | Готово |
| Лист персонажа | Скрыта до v2 (маршрут сохранён) |

Документация: [FAQ_RU.md](FAQ_RU.md) · [USER_GUIDE_RU.md](USER_GUIDE_RU.md) · ТЗ: [TZ_CHARACTER_PROFILE_RU.md](TZ_CHARACTER_PROFILE_RU.md)
