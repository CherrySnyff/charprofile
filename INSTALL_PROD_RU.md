# Установка через FTP — Enterum/CharacterProfile

Аддон ставится **только через FTP**: копируете папку на сервер, затем жмёте «Установить» / «Обновить» в ACP.

**Версия:** 1.0.1

---

## Первая установка

1. На сервере создайте папку (если её ещё нет):
   ```
   src/addons/Enterum/CharacterProfile/
   ```

2. Скопируйте **всё содержимое** локальной папки:
   ```
   upload/src/addons/Enterum/CharacterProfile/
   ```
   в эту папку на сервере.

3. Проверьте, что на сервере есть, например:
   ```
   src/addons/Enterum/CharacterProfile/addon.json
   src/addons/Enterum/CharacterProfile/Setup.php
   src/addons/Enterum/CharacterProfile/XF/Entity/User.php
   src/addons/Enterum/CharacterProfile/_data/templates.xml
   ```

4. Убедитесь, что **нет** файла `hashes.json` в папке аддона.  
   При установке через FTP он **не нужен** и вызывает ошибку «отсутствуют N файлов».

5. ACP → **Аддоны** → «Профиль игрока» → **Установить**.

6. **Инструменты → Пересборка кэша** (шаблоны, CSS, права, маршруты).

7. Настройте опции: **Настройки → Опции → Профиль игрока**.

8. Выдайте права `character_profile.*` нужным группам.

---

## Обновление

1. Скопируйте обновлённые файлы поверх старых в:
   ```
   src/addons/Enterum/CharacterProfile/
   ```
   (можно заменить всю папку целиком, кроме пользовательских правок, если вы их делали).

2. Удалите `hashes.json`, если он появился на сервере.

3. ACP → **Аддоны** → **Обновить** → пересборка кэша.

---

## Частые ошибки

### «Отсутствуют N файлов»

На сервере лежит `hashes.json`. Удалите:
```
src/addons/Enterum/CharacterProfile/hashes.json
```
Повторите установку / обновление.

### Could not find class Enterum\CharacterProfile\XF\Entity\User

Файлы не в той папке или неполная загрузка. Проверьте путь:
```
src/addons/Enterum/CharacterProfile/XF/Entity/User.php
```

Не должно быть вложенности вида:
```
❌ .../CharacterProfile/upload/src/addons/Enterum/CharacterProfile/...
```

### Регистр имён (Linux)

Папки и файлы с **заглавными** буквами, как в репозитории:
- `Admin/Controller/`
- `Pub/Controller/`
- `XF/Entity/`

Не `admin`, не `pub`.

### ACP не открывается после сбоя установки

В БД `xf_class_extension` отключите расширение  
`Enterum\CharacterProfile\XF\Entity\User` (`active = 0`), исправьте файлы, установите снова.

---

## Что копировать (кратко)

| Локально | На сервере |
|----------|------------|
| `upload/src/addons/Enterum/CharacterProfile/*` | `src/addons/Enterum/CharacterProfile/*` |

Альтернатива: скопировать всё содержимое `upload/` в **корень** XenForo — структура путей сохранится.
