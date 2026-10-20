# AdzerCMS

[![CI](https://github.com/adzercms/adzercms/actions/workflows/ci.yml/badge.svg)](https://github.com/adzercms/adzercms/actions/workflows/ci.yml)

[English](README.md) | Русский

Модульный движок веб-приложений на Symfony. Ядро отвечает за учётные записи и вход, настройки, оповещения и реестр модулей. Всё остальное — модули: пакеты Composer, которые экземпляр выбирает в своём окружении.

Статус: разработка версии 1.0. Контракты могут меняться до её выпуска.

## Возможности

- Регистрация, вход по почте, телефону или идентификатору, объявленному модулем, и паролю, восстановление доступа, вход оператора под пользователем
- Вход модулями сервисов входа: авторизация OAuth 2.0 с `state` и PKCE либо `nonce`, вход подключённым профилем либо его телефоном или почтой, подключение сервиса к вошедшей записи
- Реестр входов: устройство, город и активность каждого входа, завершение любого из них
- Подтверждение контакта кодом или ссылкой со сроком, пределом попыток и паузой повторной отправки
- Настройки в базе данных с зашифрованными секретами, правка в админке и командой `bin/console adzercms:setting`
- Оповещения письмами, шлюзами сообщений на телефон и ботами мессенджеров, тихие часы, общий журнал
- Модули админки и кабинета на одном каркасе, сменная тема
- Модули функций: платежи, заявки в поддержку, рассылка, обновление страниц в реальном времени, каталог расширений
- MySQL, MariaDB или PostgreSQL; сессии в Valkey или Redis, без них — файлы
- Переводы на английский и русский

## Требования

- PHP 8.4 или 8.5 с расширениями `intl` и `sodium`
- Composer 2
- MySQL, MariaDB или PostgreSQL
- Необязательно: Valkey или Redis для сессий, [GeoLite2-City](https://dev.maxmind.com/geoip/geolite2-free-geolocation-data) для мест входа

## Репозиторий

Пакет `packages/<имя>` — `adzercms/<имя>`:

- `contracts` — интерфейсы, на которые опираются модули; `core` — бандл ядра Symfony; `skeleton` — шаблон проекта
- `admin`, `cabinet`, `theme` — админка, кабинет и тема по умолчанию
- `payments`, `support`, `mailing`, `realtime`, `extensions` — модули функций
- `vk-id`, `yandex-id`, `sber-id`, `telegram` — вход через сервис
- `sms-pilot`, `mts-marketolog` — шлюзы сообщений на телефон; `telegram-bot`, `max-bot` — боты мессенджеров
- `smart-captcha`, `turnstile`, `hcaptcha` — проверка «не робот»
- `tbank`, `yookassa` — эквайринг; `centrifugo` — сервер обновлений в реальном времени
- корень — приложение разработки: требует все пакеты из `packages/*`, запускает тесты и проверки

Каждый push в `main` и каждый тег `v*` копируют пакеты в их репозитории только для чтения `adzercms/<имя>`. Правки принимает только этот репозиторий.

## Создать экземпляр

```sh
composer create-project adzercms/skeleton my-site --stability=dev
```

Окружение, хуки и задания планировщика: [Установка](docs/installation.ru.md).

## Разработка

```sh
git clone https://github.com/adzercms/adzercms.git
cd adzercms
composer install
git config core.hooksPath .githooks
```

Приложение разработки работает экземпляром: окружение и администратор — как в [Установке](docs/installation.ru.md#окружение), схема выбора — `bin/console doctrine:migrations:migrate`. Скрипты `bin/` запускают PHP, заданный `git config adzercms.php`, без него — `php`.

## Консоль

- `adzercms:admin <login>` — дать записи роль администратора, завести её с паролем через `--stdin`
- `adzercms:setting [key] [value]` — перечень, чтение и правка настроек; секреты — только через `--stdin`
- `adzercms:cache` — переключить экземпляр на сборку кеша процесса и убрать сборки без процессов
- `adzercms:notices`, `adzercms:sweep` и задания модулей — [планировщик](docs/installation.ru.md#планировщик)

## Тесты

Пустая тестовая база — в `.env.test.local`: `DATABASE_URL=...`. Её делят все наборы, первый тест прогона сносит и заново накатывает схему.

```sh
bin/phpunit --testsuite Core
vendor/bin/adzercms-check
```

Наборы — имена `<testsuite>` в `phpunit.dist.xml`, каждый гоняется по имени. `vendor/bin/adzercms-check` проверяет стиль кода, PHPStan, XLIFF, ICU, Twig и требования каждого пакета. Хук pre-commit проверяет вносимые файлы. [CI](.github/workflows/ci.yml) гоняет все наборы на MySQL, MariaDB и PostgreSQL с PHP 8.4 и 8.5. Правила кода, гейты и тесты: [Разработка движка](docs/engine-development.ru.md).

## Документация

- [Установка](docs/installation.ru.md)
- [Модули: выбор и настройки](docs/modules.ru.md)
- [Свой модуль](docs/module-development.ru.md)
- [Разработка движка](docs/engine-development.ru.md)

## Участие

Правила участия, уязвимости, нормы поведения и журнал изменений — на английском: [CONTRIBUTING.md](CONTRIBUTING.md), [SECURITY.md](SECURITY.md), [CODE_OF_CONDUCT.md](CODE_OF_CONDUCT.md), [CHANGELOG.md](CHANGELOG.md).

## Планы

Установка модулей из админки, проверка модулей сторонних авторов.

## Лицензия

[MIT](LICENSE)
