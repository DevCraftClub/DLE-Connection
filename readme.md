# Связи (Connections)

Плагин для DataLife Engine: новости собираются в **сборки** (группы связанных материалов) и показываются читателю на полной новости. В админке — дерево сборок, вкладка на форме новости и готовый вывод в теме сайта.

Ставится поверх [DevCraft Admin](https://readme.devcraft.club/dev/dle/devcraft_admin/getting_started), каталог модуля — `Connections`. Редактор перетаскивает новости, задаёт тип связи и скрывает отдельные элементы. На сайте текущая новость из списка не дублируется; пустые и скрытые позиции не показываются. Направленные пары по правилам категории можно поручить сателлиту [Связи: Автоматизация](https://readme.devcraft.club/dev/dle/connections_automation/getting_started).

**Версия:** 210.1.0

## Документация на сайте

| Страница | Адрес |
| -------- | ----- |
| Начало работы | https://readme.devcraft.club/dev/dle/connections/210.1.0/getting_started |
| Установка (этот модуль) | https://readme.devcraft.club/dev/dle/connections/210.1.0/install |
| Публичный вывод | https://readme.devcraft.club/dev/dle/connections/210.1.0/guides/public-include |
| Общая установка плагинов | https://readme.devcraft.club/instructions/install_instructions |
| Composer | https://readme.devcraft.club/instructions/composer |
| Установка DevCraft Admin | https://readme.devcraft.club/dev/dle/devcraft_admin/install |

## Требования

Как на [странице модуля](https://readme.devcraft.club/dev/dle/connections/210.1.0/getting_started):

| Компонент | Минимум |
| --------- | ------- |
| DataLife Engine | **≥ 21.0** |
| PHP | **≥ 8.3** |
| DevCraft Admin | **≥ 200.4.1** |
| Права на запись | каталог `devcraft/cache` (создание таблиц модуля) |

Сначала установите и включите DevCraft Admin, затем плагин **Connections** («Связи»).

## Возможности

- дерево сборок и элементов в админке с перетаскиванием порядка;
- вкладка «Связи» на формах добавления и правки новости;
- каталог **типов связей** и **категорий сборок**;
- публичный вывод через вставку в шаблон полной новости;
- форма связей на публичном добавлении новости (тег `{dc-connections}` в теме).

## Установка

Как собрать архив, залить содержимое `upload/` или поставить zip через менеджер плагинов — в общей инструкции: [Установка плагинов](https://readme.devcraft.club/instructions/install_instructions). Кратко три способа:

1. Скрипт `install_archive.sh` / `install_archive.bat` (Windows: нужен 7-Zip), затем **Панель управления → Плагины**.
2. Упаковать содержимое `upload/` в zip так, чтобы в **корне** архива были `install.xml` и каталоги (`engine/`, `devcraft/`, шаблоны темы).
3. Скопировать содержимое `upload/` в корень сайта (структуру папок сохранить), затем установить или включить плагин в менеджере.

Библиотеки PHP обычно ставятся сами (скрипт установки или раздел Composer в DevCraft Admin). В терминал заходить только если автоматически не получилось — [Composer](https://readme.devcraft.club/instructions/composer).

### После установки (только «Связи»)

Как на [странице установки модуля](https://readme.devcraft.club/dev/dle/connections/210.1.0/install):

1. В менеджере плагинов DLE должен появиться **Connections** с зависимостью от DevCraft Admin.
2. Откройте раздел **Связи** (`?mod=dle_connections`). При первом заходе создадутся [таблицы модуля](https://readme.devcraft.club/dev/dle/connections/210.1.0/getting_started).
3. Создайте нужные [типы связей](https://readme.devcraft.club/dev/dle/connections/210.1.0/guides/relation-types) и при необходимости [категории сборок](https://readme.devcraft.club/dev/dle/connections/210.1.0/guides/collection-types). Они не появляются сами.
4. Чтобы выключить модуль, отключите плагин в менеджере плагинов DLE (отдельного флажка «Включено» в настройках нет).

После правок PHP в `devcraft/` обновите автозагрузку Composer — см. общую инструкцию.

Установщик сам подключает вкладку «Связи» к формам новости в админке и тег `{dc-connections}` на публичной форме. Файлы ядра вручную править не нужно. В шаблоне публичного добавления тег `{dc-connections}` должен быть **внутри формы**, иначе черновик сборок не уйдёт вместе с новостью.

Группы с доступом к админке «Связи» задаются при установке плагина. Группа с id `1` всегда допускается оболочкой DevCraft.

## Публичный вывод

Разбор параметров, фильтров и шаблонов — [Публичный вывод](https://readme.devcraft.club/dev/dle/connections/210.1.0/guides/public-include). В шаблон полной новости (путь от корня сайта; относительные `../` DataLife Engine вырезает):

```smarty
{include file="devcraft/src/modules/Connections/Controller/show_connections.php?news_id={news-id}"}
```

На странице — сборки с текущей новостью: только видимые элементы, без самой открытой новости и без пустых сборок.

Без `template` берутся `templates/{skin}/devcraft/connections/list.tpl` и `item.tpl`. Если в текущей теме файлов нет — тема `Air`, затем `Default` (`Default` — запасной путь для DataLife Engine 20.0).

## Идентификация

| Поле | Значение |
| ---- | -------- |
| Каталог модуля | `Connections` |
| Код в админке | `dle_connections` |
| Версия | `210.1.0` |
| Точка входа | `engine/inc/dle_connections.php` |
| Пространство имён | `DevCraft\Modules\Connections` |

Публичная логика — в `Controller/`, не в толстых файлах `engine/modules/`.

## Лицензия

MIT — см. [LICENSE](LICENSE).
