# Свой модуль

[English](module-development.md) | Русский

Модуль — пакет Composer с бандлом Symfony. Он зависит от `adzercms/contracts`, а не от реализации ядра.

## Пакет

```json
{
    "name": "acme/pigeon",
    "type": "adzercms-module",
    "license": "MIT",
    "require": {
        "adzercms/contracts": "^1.0",
        "symfony/http-kernel": "7.4.*",
        "symfony/translation": "7.4.*"
    },
    "provide": {
        "adzercms/sms-implementation": "1.0.0"
    },
    "autoload": {
        "psr-4": {"Acme\\Pigeon\\": "src/"}
    },
    "extra": {
        "adzercms": {"bundle": "Acme\\Pigeon\\PigeonBundle"}
    }
}
```

`extra.adzercms.bundle` называет класс бандла. Его имя без `Bundle` — ключ модуля, уникальный среди установленных. Модулю, требующему пакет другого модуля, нужен и выбор того модуля.

Род требуется его виртуальным пакетом `adzercms/<ключ рода>-implementation`, а не пакетом модуля: каждый модуль рода называет его в `provide` версией движка, чьи контракты он реализует, а модуль рода без него останавливает сборку. Composer ставит требующий модуль только рядом с модулем рода, а экземпляр собирается только с выбранным. Модулю со страницами нужна тема: `"adzercms/theme-implementation": "^1.0"`. Модуль, нужный тестам, например админка, идёт в `require-dev`: набор модуля выбирает его сам.

## Ссылки на админку и кабинет

Страницы админки и кабинета берутся входами: функции Twig `admin('user', {id: id})` и `cabinet('profile')`, в коде — `AdzerCMS\Core\Ui\Entries::url()` с `AdminEntry` либо `CabinetEntry`. Без модуля рода вход — `null`: страница ссылку не выводит.

## Страницы админки и кабинета

Модуль функции объявляет свои части админки и кабинета службами `AdzerCMS\Contracts\Ui`, выбранный модуль админки либо кабинета рисует их в своём каркасе. Служба с одним из этих интерфейсов регистрируется автоконфигурацией.

- `Menu` — `items()` отдаёт пункты меню области `Area`, админки либо кабинета: `MenuItem` с маршрутом, подписью, весом, ведомыми маршрутами (на них пункт остаётся текущим), группой, счётчиком, признаком диалога, а для нижней панели кабинета — значком набора темы с краткой подписью.
- `Tabs` — вкладки страницы по её ключу `Tabs::ADMIN_DASHBOARD`, `Tabs::ADMIN_USER` либо `Tabs::CABINET_HOME`: `tabs()` отдаёт `Tab` с ключом, подписью, весом и необязательным числом, `tab()` строит шаблон и контекст только активной вкладки.
- `Fragment` — фрагмент страницы другого владельца по месту: `Fragment::HEAD`, `TOP` — шапка и навигация раскладки `top` вместо панельных, `HEADER`, `CABINET_HOME`, `ADMIN_DASHBOARD`, `ADMIN_DASHBOARD_ACTIONS`, `ADMIN_USER`; `null` оставляет место пустым.
- `Columns` — колонки модуля в перечне другого владельца, `Columns::ADMIN_USERS`: `cells()` отдаёт значения `Cell` всех строк страницы одним вызовом, `search()` — строки, найденные модулем по своим полям. По таким колонкам перечень не сортируется.
- `ContentSource` — источники сверх своего происхождения для директив `form-action`, `script-src`, `connect-src`, `frame-src` и `style-src` Content Security Policy.

Плашка состояния — `Badge::good()`, `warn()`, `bad()` либо `idle()`. Действие отказывает через `AdzerCMS\Contracts\Refusal`: причина и маршрут устранения.

## Бандл

Бандл наследует `AdzerCMS\Contracts\Module\Module`: `kind()` называет род модуля, `kinds()` перечисляет роды, которые модуль объявляет сам, по умолчанию ни одного. Такой бандл Symfony Flex не вписывает в `config/bundles.php`: модули экземпляр выбирает в своём окружении.

```php
namespace Acme\Pigeon;

use AdzerCMS\Contracts\Module\CoreKind;
use AdzerCMS\Contracts\Module\Kind;
use AdzerCMS\Contracts\Module\Module;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

final class PigeonBundle extends Module
{
    public static function kind(): Kind
    {
        return CoreKind::Sms;
    }

    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        $container->services()->set(PigeonGateway::class)->autowire()->autoconfigure();
    }
}
```

Модуль функции возвращает `CoreKind::Feature`. Его собственные сменные части — роды: enum с контрактом `AdzerCMS\Contracts\Module\Kind`, у рода ключ, ключ окружения и признак «один либо список».

## Модуль рода со списком

Шлюзы сообщений, проверки «не робот», сервисы входа и боты реализуют контракт своего рода. Контракт расширяет `AdzerCMS\Contracts\Module\Switchable`: модуль объявляет поля данных доступа, ядро хранит их настройками `<род>.<модуль>.<поле>` рядом с переключателем `enabled` и передаёт значения в каждый вызов.

```php
namespace Acme\Pigeon;

use AdzerCMS\Contracts\Module\Credentials;
use AdzerCMS\Contracts\Notice\SmsFailed;
use AdzerCMS\Contracts\Notice\SmsGateway;
use AdzerCMS\Contracts\Setting\Field;
use AdzerCMS\Contracts\Setting\Type;

use function Symfony\Component\Translation\t;

final class PigeonGateway implements SmsGateway
{
    public function credentials(): array
    {
        return [new Field('token', t('pigeon.token'), Type::Secret, required: true)];
    }

    public function send(Credentials $credentials, string $phone, string $text): void
    {
        // вызов провайдера с $credentials->get('token'), отказ — SmsFailed
    }

    public function contract(Credentials $credentials): array
    {
        return [];
    }
}
```

Сервис лежит в namespace своего бандла: так ядро связывает его с модулем.

## Сервис входа

Сервис входа реализует `AdzerCMS\Contracts\Login\LoginService`. `authorization()` отдаёт адрес авторизации с client id и scope, `pkce: false` у сервиса без PKCE, `nonce: true` у сервиса, сверяющего `nonce` в своём `id_token`, и дополнительные параметры запроса. Ядро добавляет `state`, redirect URI и одноразовый секрет: `code_challenge` для PKCE, значение `nonce` как есть.

`profile()` получает возврат как `Callback` — код, `state`, секрет как `code_verifier` либо ожидаемый `nonce`, redirect URI и любой параметр запроса через `parameter()` — и вызывает сервис через `Exchange`: ответ не 200, ответ с `error` либо отсутствие ответа бросает `LoginFailed` с причиной. Ответ — `Answer`: `text('user', 'email')` читает поле по пути ключей, отсутствующее, пустое либо нескалярное поле — `null`. `claims()` принимает `id_token` с адресом JWKS, издателем и client id: проверяет подпись ключами сервиса, `iss`, `aud` и срок и отдаёт claims как `Answer`. `Client::basic()` даёт заголовок `Authorization` с идентификатором и секретом приложения. Свои отказы модуль бросает `LoginFailed` и отдаёт `Profile` с id профиля сервиса. Идентификатор и секрет приложения — поля `AdzerCMS\Contracts\Login\Client::Id->field()` и `Client::Secret->field()` с подписями ядра. Знак сервиса — официальный файл `public/icon.svg` пакета.

Redirect URI `https://<хост>/login/<ключ модуля в нижнем регистре>/callback` вводится в приложении сервиса. Вход начинается с `/login/<ключ модуля в нижнем регистре>`.

## Раскладка пакета

- `src/Entity/` — сущности Doctrine атрибутами; таблицы с приставкой имени модуля
- `migrations/` — миграции в namespace `<namespace бандла>\Migrations`
- `src/Controller/` — контроллеры с атрибутами маршрутов
- `templates/` — шаблоны Twig под `@<Модуль>`
- `translations/` — каталоги XLIFF `messages+intl-icu.<язык>.xlf`, ключи с приставкой имени модуля; английский обязателен, набор ключей у всех языков один
- `tests/` — тесты PHPUnit

Сущности, миграции и маршруты выбранного модуля ядро подключает само.

## Тесты

Тесты модуля идут в экземпляре, который держит модуль в `modules/`. Composer читает `autoload-dev` только корневого пакета: экземпляр отображает namespace тестов модуля и называет набор ключом модуля в `phpunit.dist.xml`.

```json
"autoload-dev": {
    "psr-4": {"Acme\\Pigeon\\Tests\\": "modules/pigeon/tests/"}
}
```

```xml
<testsuites>
    <testsuite name="Pigeon">
        <directory>modules/pigeon/tests</directory>
    </testsuite>
</testsuites>
```

Набор выбирает свой модуль, требуемые им модули и модули его `require-dev`, и модуль каждого требуемого рода. Модуль с сущностями добавляет в свой набор тест миграций ядра: `<file>vendor/adzercms/core/tests/Schema/MigrationsTest.php</file>`. Пустая тестовая база данных — в `.env.test.local`: её делят все наборы, первый тест прогона сносит её и накатывает миграции.

```sh
composer dump-autoload
bin/phpunit --testsuite Pigeon
vendor/bin/adzercms-check
```

Тест на базе данных наследует `AdzerCMS\Core\Tests\SchemaTestCase`; ответы внешних сервисов задаёт `AdzerCMS\Core\Tests\HttpAnswers`.

## Миграции

```sh
bin/console doctrine:migrations:generate --namespace='Acme\Shop\Migrations'
```

Сгенерированный класс прерывает себя, пока его не дописать. Таблицы строятся объектами `Table` DBAL и SQL платформы `$this->platform`, не строками SQL: тогда модуль работает на MySQL, MariaDB и PostgreSQL. Классов модуля в миграции нет.

## Настройки

Сервис с контрактом `AdzerCMS\Contracts\Setting\Owner` объявляет поля настроек. Ключи несут приставку модуля, ключ двух владельцев останавливает каталог. Значения правятся командой `bin/console adzercms:setting`.

## Обновление страниц в реальном времени

Часть страницы, которая меняется без участия пользователя, — живой блок: элемент с `id` и `data-live`. При установленном модуле `realtime` сервис с контрактом `AdzerCMS\Realtime\LiveEntities` объявляет сущности, чьи изменения обновляют живые блоки: `fields()` сопоставляет классу сущности поля, которые считаются, пустой список — любое изменение; `owner()` называет пользователя, чьи страницы обновляются; `staff()` обновляет и страницы операторов. Создание и удаление объявленной сущности считается всегда. Модуль называет `adzercms/realtime` в `suggest` и регистрирует сервис, только когда интерфейс есть:

```php
if (interface_exists(LiveEntities::class)) {
    $container->services()->set(LiveShipments::class)->autowire()->autoconfigure();
}
```

Модуль сервера обновлений возвращает `AdzerCMS\Realtime\HubKind::Hub` из `kind()` и реализует `AdzerCMS\Realtime\RealtimeHub`. `client()` называет его клиент в браузере: ES-модуль в `public/` пакета, экспортирующий `connect(settings, event)`. `settings` несёт адрес подключения, токен подключения, каналы с токенами подписки и `renew(channel)` — новый токен, `null`, когда вход его не получает; `event()` вызывается на публикацию и на потерю истории.

## Контракты

`adzercms/contracts` следует SemVer: несовместимая правка контракта выходит новой мажорной версией. Роды и события модуля функции — контракты его собственного пакета.
