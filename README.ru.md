# AdzerCMS

[![CI](https://github.com/adzercms/adzercms/actions/workflows/ci.yml/badge.svg)](https://github.com/adzercms/adzercms/actions/workflows/ci.yml)

[English](README.md) | Русский

Бесплатный модульный движок для сайтов и веб-приложений на Symfony. В ядре — аккаунты и вход, настройки, уведомления и список модулей. Всё остальное подключается модулями: это обычные пакеты Composer, вы ставите только нужные.

Версия 1.0 ещё в разработке: до её выхода API для модулей может меняться.

## Что умеет

- Регистрация и вход по почте или телефону с паролем, восстановление доступа, вход администратора под пользователем
- Вход через VK ID, Яндекс ID, Сбер ID и Telegram (OAuth 2.0 с PKCE), привязка сервиса к уже созданному аккаунту
- Список активных сеансов с устройством и городом, выход из любого из них
- Подтверждение почты и телефона кодом или ссылкой
- Настройки хранятся в базе, секреты — в зашифрованном виде; менять их можно в админке или из консоли
- Уведомления по почте, SMS и через ботов Telegram и MAX, тихие часы, журнал отправки
- Админка и личный кабинет, сменная тема оформления
- Готовые модули: платежи, поддержка, рассылки, обновление страниц в реальном времени, каталог расширений
- MySQL, MariaDB или PostgreSQL; сессии в Valkey или Redis, без них — в файлах
- Английский и русский языки

## Что нужно

- PHP 8.4 или 8.5 с расширениями `intl` и `sodium`
- Composer 2
- MySQL, MariaDB или PostgreSQL
- По желанию: Valkey или Redis для сессий, [GeoLite2-City](https://dev.maxmind.com/geoip/geolite2-free-geolocation-data), чтобы показывать город входа

## Что в репозитории

Каталог `packages/<имя>` — это пакет `adzercms/<имя>`:

- `contracts` — интерфейсы для модулей, `core` — ядро (бандл Symfony), `skeleton` — шаблон нового проекта
- `admin`, `cabinet`, `theme` — админка, личный кабинет и тема по умолчанию
- `payments`, `support`, `mailing`, `realtime`, `extensions` — платежи, поддержка, рассылки, обновления в реальном времени, каталог расширений
- `vk-id`, `yandex-id`, `sber-id`, `telegram` — вход через эти сервисы
- `sms-pilot`, `mts-marketolog` — отправка SMS; `telegram-bot`, `max-bot` — боты мессенджеров
- `smart-captcha`, `turnstile`, `hcaptcha` — капча
- `tbank`, `yookassa` — приём оплаты; `centrifugo` — сервер для обновлений в реальном времени
- в корне — приложение для разработки: подключает все пакеты и запускает тесты и проверки

После каждого push в `main` и каждого тега `v*` пакеты копируются в свои репозитории `adzercms/<имя>`, они только для чтения. Pull request'ы принимаются здесь.

## Новый проект

```sh
composer create-project adzercms/skeleton my-site --stability=dev
```

Настройка окружения, хуков и планировщика — в [установке](docs/installation.ru.md).

## Разработка

```sh
git clone https://github.com/adzercms/adzercms.git
cd adzercms
composer install
git config core.hooksPath .githooks
```

Приложение для разработки настраивается как обычный проект: окружение и администратор — по [установке](docs/installation.ru.md#окружение), база — `bin/console doctrine:migrations:migrate`. Скрипты из `bin/` запускают PHP из переменной окружения `PHP_BINARY`, без неё — указанный в `git config adzercms.php`, а если не задан и он — `php`.

## Консоль

- `adzercms:admin <login>` — сделать пользователя администратором или создать нового, пароль передаётся через `--stdin`
- `adzercms:setting [key] [value]` — посмотреть и изменить настройки; секреты — только через `--stdin`
- `adzercms:cache` — перевести сайт на свежую сборку кеша и удалить сборки, которыми не пользуется ни один процесс
- `adzercms:notices`, `adzercms:sweep` и задания модулей — для [планировщика](docs/installation.ru.md#планировщик)

## Тесты

Укажите пустую тестовую базу в `.env.test.local`: `DATABASE_URL=...`. Её используют все наборы тестов, в начале прогона схема создаётся заново.

```sh
bin/phpunit --testsuite Core
vendor/bin/adzercms-check
```

Названия наборов — в `<testsuite>` файла `phpunit.dist.xml`. `vendor/bin/adzercms-check` проверяет стиль кода, PHPStan, файлы переводов, Twig и зависимости каждого пакета. Перед коммитом хук проверяет изменённые файлы. [CI](.github/workflows/ci.yml) прогоняет все наборы на MySQL, MariaDB и PostgreSQL с PHP 8.4 и 8.5. Правила кода и тестов — в [разработке движка](docs/engine-development.ru.md).

## Документация

- [Установка](docs/installation.ru.md)
- [Модули: подключение и настройки](docs/modules.ru.md)
- [Как написать свой модуль](docs/module-development.ru.md)
- [Разработка движка](docs/engine-development.ru.md)

## Участие

Как предложить правку, сообщить об уязвимости, правила общения и журнал изменений — на английском: [CONTRIBUTING.md](CONTRIBUTING.md), [SECURITY.md](SECURITY.md), [CODE_OF_CONDUCT.md](CODE_OF_CONDUCT.md), [CHANGELOG.md](CHANGELOG.md).

## Планы

Установка модулей из админки и проверка модулей сторонних авторов.

## Лицензия

[MIT](LICENSE)
