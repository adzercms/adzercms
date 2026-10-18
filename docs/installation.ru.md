# Установка

[English](installation.md) | Русский

## Создать экземпляр

```sh
composer create-project adzercms/skeleton my-site --stability=dev
cd my-site
```

Шаблон требует ядро с модулями админки, кабинета и темы и выбирает их в `.env`.

## Окружение

Значения экземпляра — в `.env.local`:

```dotenv
APP_SECRET=change-me-to-a-random-string
DEFAULT_URI=https://example.com
DATABASE_URL="postgresql://adzercms:password@127.0.0.1:5432/adzercms?serverVersion=17.6&charset=utf8"
SESSION_DSN=redis://127.0.0.1:6379
GEOIP_CITY_DB=/usr/share/GeoIP/GeoLite2-City.mmdb
```

- `APP_SECRET` — случайная строка, например вывод `openssl rand -hex 32`
- `DEFAULT_URI` — публичный адрес экземпляра: с него начинаются ссылки в сообщениях заданий планировщика
- `DATABASE_URL` — пустая база данных; адрес MySQL или MariaDB несёт `charset=utf8mb4` и полную версию сервера, например `serverVersion=8.4.6`
- `SESSION_DSN` и `GEOIP_CITY_DB` необязательны: без них сессии хранятся в файлах, а у входов нет места

Модули выбираются ключами окружения: [Модули](modules.ru.md). Тесты и статический анализ `.env.local` не читают: выбор, изменённый там, повторяется в `.env.test`.

## Обновление страниц в реальном времени

Открытые страницы показывают изменения без перезагрузки, когда выбраны модуль `realtime` и модуль сервера обновлений. Модуль Centrifugo берёт сервер из `.env.local`:

```dotenv
HUB_MODULE=Centrifugo
REALTIME_URL=http://127.0.0.1:8000
REALTIME_WS_PATH=/connection/websocket
REALTIME_TOKEN_SECRET=
REALTIME_API_KEY=
```

- `REALTIME_URL` — адрес API сервера, куда экземпляр публикует события
- `REALTIME_WS_PATH` — путь на хосте экземпляра, который веб-сервер проксирует на WebSocket Centrifugo
- `REALTIME_TOKEN_SECRET` — `client.token.hmac_secret_key` сервера, `REALTIME_API_KEY` — `http_api.key`
- Пространства сервера: `personal` с `allow_user_limited_channels` и `allow_subscribe_for_client`, `staff`; у обоих история и `force_recovery`

Без модуля сервера обновлений или его значений страницы обновляются только перезагрузкой.

## Git и обновления

```sh
git init
git config core.hooksPath .githooks
git config adzercms.php /usr/bin/php8.4
git add -A
git commit -m 'Instance'
```

- `adzercms.php` — PHP скриптов `bin/` и хуков; без него они запускают `php`
- Хук `pre-commit` останавливает коммит с ошибкой стиля в его файлах PHP, миграцией вопреки правилам миграций, сломанным шаблоном либо каталогом, секретом в `.env`
- Хуки `post-commit` и `post-merge` запускают `vendor/bin/adzercms-refresh` после каждого коммита и `git pull`: он приводит экземпляр к его дереву — зависимости изменившегося `composer.lock`, пакеты со сменившимися патчами `patches.lock.json` cweagans/composer-patches, миграции, новая сборка кеша, библиотеки importmap и собранная статика, затем переключение экземпляра на новую сборку; без `.env.local` он ничего не делает
- Экземпляр в подкаталоге git-репозитория берёт `git config core.hooksPath <подкаталог>/.githooks`
- Корень сайта веб-сервера — `public/`

Администратор, пароль — из stdin:

```sh
printf '%s' "$PASSWORD" | bin/console adzercms:admin admin@example.com --stdin
```

Обновления движка приходят через `composer update`, сразу за ним `vendor/bin/adzercms-refresh` и коммит `composer.lock`: до запуска скрипта идущая сборка отказывает на новых зависимостях.

## Планировщик

Задания — в crontab пользователя экземпляра, `/path` — каталог экземпляра. Задание завершается ненулевым кодом при отказе; его вывод не глушится.

```crontab
*/15 * * * * /path/bin/php /path/bin/console adzercms:notices
15 4 * * *   /path/bin/php /path/bin/console adzercms:sweep
```

- `adzercms:notices` отправляет оповещения, отложенные тихими часами
- `adzercms:sweep` убирает вышедшие входы, старые записи журнала и подтверждения

Задания выбранных модулей:

```crontab
*/5 * * * *  /path/bin/php /path/bin/console adzercms:payments
10 3 * * *   /path/bin/php /path/bin/console adzercms:support
```

- `adzercms:payments` (платежи) доводит начатые платежи опросом провайдера
- `adzercms:support` (поддержка) закрывает заявки, ждущие пользователя дольше срока модуля
