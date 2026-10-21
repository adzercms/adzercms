# Установка

[English](installation.md) | Русский

## Новый проект

```sh
composer create-project adzercms/skeleton my-site --stability=dev
cd my-site
```

Шаблон сразу ставит ядро, админку, личный кабинет и тему и включает их в `.env`.

## Окружение

Настройки сайта — в `.env.local`:

```dotenv
APP_SECRET=change-me-to-a-random-string
DEFAULT_URI=https://example.com
DATABASE_URL="postgresql://adzercms:password@127.0.0.1:5432/adzercms?serverVersion=17.6&charset=utf8"
SESSION_DSN=redis://127.0.0.1:6379
GEOIP_CITY_DB=/usr/share/GeoIP/GeoLite2-City.mmdb
```

- `APP_SECRET` — случайная строка, её даёт `openssl rand -hex 32`
- `DEFAULT_URI` — адрес сайта; с него начинаются ссылки в письмах, которые отправляет планировщик
- `DATABASE_URL` — пустая база данных. Для MySQL и MariaDB укажите `charset=utf8mb4` и полную версию сервера в `serverVersion`; базу PostgreSQL создайте с провайдером локали ICU и локалью `und`
- `SESSION_DSN` и `GEOIP_CITY_DB` — по желанию: без них сессии хранятся в файлах, а город входа не показывается
- `FILES_DIR` — по желанию: каталог для файлов, которые создаёт сайт; по умолчанию — `var/files/<окружение>`

Какие модули включены, задают переменные окружения: [Модули](modules.ru.md). Тесты и статический анализ `.env.local` не читают, поэтому изменения списка модулей повторите в `.env.test`.

## Обновление страниц в реальном времени

Чтобы открытые страницы показывали изменения без перезагрузки, включите модуль `realtime` и модуль сервера обновлений. Модуль Centrifugo берёт адрес сервера из `.env.local`:

```dotenv
HUB_MODULE=Centrifugo
REALTIME_URL=http://127.0.0.1:8000
REALTIME_WS_PATH=/connection/websocket
REALTIME_TOKEN_SECRET=
REALTIME_API_KEY=
```

- `REALTIME_URL` — адрес API сервера, куда сайт отправляет события
- `REALTIME_WS_PATH` — путь на домене сайта, который веб-сервер проксирует на WebSocket Centrifugo
- `REALTIME_TOKEN_SECRET` — `client.token.hmac_secret_key` сервера, `REALTIME_API_KEY` — `http_api.key`
- На сервере нужны пространства `personal` с `allow_user_limited_channels` и `allow_subscribe_for_client` и `staff`, у обоих включены история и `force_recovery`

Без модуля сервера или его настроек страницы обновляются только перезагрузкой.

## Git и обновления

```sh
git init
git config core.hooksPath .githooks
git config adzercms.php /usr/bin/php8.4
git add -A
git commit -m 'Site'
```

- `adzercms.php` — PHP для скриптов `bin/` и хуков, если его не задаёт переменная окружения `PHP_BINARY`; без обоих запускается `php`
- Хук `pre-commit` не даст закоммитить PHP с ошибками стиля, миграцию с нарушением правил, сломанный шаблон или файл перевода и секрет в `.env`
- Хуки `post-commit` и `post-merge` после каждого коммита и `git pull` запускают `vendor/bin/adzercms-refresh`. Он приводит сайт в соответствие с кодом: ставит зависимости из изменившегося `composer.lock` и пакеты с новыми патчами из `patches.lock.json` (cweagans/composer-patches), накатывает миграции, собирает новый кеш, библиотеки importmap и статику и переключает сайт на новую сборку. Без `.env.local` скрипт ничего не делает
- Скрипт начинает работу, когда закончатся идущие запросы и задания, и держит сайт до переключения: запрос получает 503 с `Retry-After`, задание ждёт конца. Оба входа подключают проверку `config/hold.php` до зависимостей
- Если сайт лежит в подкаталоге репозитория, укажите `git config core.hooksPath <подкаталог>/.githooks`
- Корень сайта для веб-сервера — `public/`

Создать администратора (пароль передаётся через stdin):

```sh
printf '%s' "$PASSWORD" | bin/console adzercms:admin admin@example.com --stdin
```

Движок обновляется через `composer update`. Сразу после него запустите `vendor/bin/adzercms-refresh` и закоммитьте `composer.lock`: пока скрипт не отработал, текущая сборка с новыми зависимостями не работает.

## Планировщик

Задания добавляются в crontab пользователя, от которого работает сайт; `/path` — каталог сайта. При ошибке задание завершается с ненулевым кодом, вывод не подавляется.

```crontab
*/15 * * * * /path/bin/php /path/bin/console adzercms:notices
15 4 * * *   /path/bin/php /path/bin/console adzercms:sweep
* * * * *    /path/bin/php /path/bin/console adzercms:modules
```

- `adzercms:notices` отправляет уведомления, отложенные из-за тихих часов
- `adzercms:sweep` удаляет завершённые сеансы, старые записи журнала и подтверждения, а также кеш наборов модулей, с которых сайт ушёл
- `adzercms:modules` исполняет заявки модулей из админки и раз в сутки обновляет каталог модулей из его репозиториев с отметками «проверен»
- У сайта без git нет `adzercms.php`: впишите `PHP_BINARY=/usr/bin/php8.4` в начало crontab
- Заявкам модулей нужны `composer` и `rsync` в `PATH` crontab

Задания модулей, если они включены:

```crontab
*/5 * * * *  /path/bin/php /path/bin/console adzercms:payments
10 3 * * *   /path/bin/php /path/bin/console adzercms:support
20 3 * * *   /path/bin/php /path/bin/console adzercms:extensions
30 * * * *   /path/bin/php /path/bin/console adzercms:opotopo
```

- `adzercms:payments` — проверяет у платёжного сервиса статус незавершённых платежей
- `adzercms:support` — закрывает обращения, по которым пользователь долго не отвечает
- `adzercms:extensions` — обновляет описания пакетов каталога расширений из их репозиториев Composer
- `adzercms:opotopo` — выгружает каталоги модулей на [OPOTOPO](modules.ru.md#продавать-на-opotopo): отправляет новые и изменённые позиции, снимает в архив объявления выпавших; `--full` отправляет все позиции, `--catalog <имя>` — только один каталог
