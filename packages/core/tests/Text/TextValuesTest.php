<?php

declare(strict_types=1);

namespace AdzerCMS\Core\Tests\Text;

use AdzerCMS\Contracts\Setting\Field;
use AdzerCMS\Contracts\Setting\Type;
use AdzerCMS\Core\Module\Switches;
use AdzerCMS\Core\Setting\Settings;
use AdzerCMS\Core\Tests\Analytics\Fixture\Tally\TallyCounter;
use AdzerCMS\Core\Tests\Configuration;
use AdzerCMS\Core\Tests\SchemaTestCase;
use AdzerCMS\Core\Text\Markup;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\DomCrawler\Crawler;

use function Symfony\Component\Translation\t;

final class TextValuesTest extends SchemaTestCase
{
    use Configuration;

    protected function setUp(): void
    {
        parent::setUp();
        $this->configure([TallyCounter::SETTINGS.Switches::ENABLED => '1', TallyCounter::SETTINGS.TallyCounter::SITE => 'shop']);
    }

    #[Group('ACMS-CORE-1157')]
    #[Group('ACMS-CORE-1012')]
    public function testMarkupShowsCurrentValuesOutsideCode(): void
    {
        $html = self::service(Markup::class)->html("Site **{{tally.site}}**, {{ tally.site }}, {{tally.none}} and `{{tally.site}}` of {{tally.sites}}\n\n```\n{{tally.site}}\n```\n");

        self::assertStringContainsString('<strong>shop</strong>, shop, {{tally.none}} and <code>{{tally.site}}</code> of shop, mirror', $html);
        self::assertStringContainsString("<code>{{tally.site}}\n</code>", $html);
    }

    #[Group('ACMS-CORE-1190')]
    public function testInlineMarkupKeepsBlockMarkersAsText(): void
    {
        self::assertSame(
            '1. Site <strong>shop</strong> of <a href="https://shop.test">shop</a>',
            self::service(Markup::class)->inline('1. Site **{{tally.site}}** of [{{tally.site}}](https://shop.test)'),
        );
    }

    #[Group('ACMS-CORE-1209')]
    public function testMessageMarkupKeepsValuesBreaksLinesAndOpensHostLinksApart(): void
    {
        $html = new Crawler(self::service(Markup::class)->message("**{{tally.site}}**\nhttps://shop.test/a and [help](/help)"));

        self::assertSame('{{tally.site}}', $html->filter('strong')->text());
        self::assertCount(1, $html->filter('p > br'));
        $outer = $html->filter('a[href="https://shop.test/a"]');
        self::assertSame('_blank', $outer->attr('target'));
        self::assertEqualsCanonicalizing(['noopener', 'noreferrer'], explode(' ', (string) $outer->attr('rel')));
        self::assertNull($html->filter('a[href="/help"]')->attr('target'));
    }

    /** @return iterable<string, array{string, bool}> */
    public static function texts(): iterable
    {
        yield 'known value' => ['Site {{tally.site}}', true];
        yield 'unknown value' => ['Site {{tally.none}}', false];
        yield 'unknown value in code' => ["Site `{{tally.none}}`\n\n```\n{{tally.none}}\n```", true];
    }

    #[DataProvider('texts')]
    #[Group('ACMS-CORE-1158')]
    public function testMarkupFieldWithUnknownValueIsNotSaved(string $text, bool $saved): void
    {
        $fields = [
            [new Field('fixture.markup', t('Markup'), Type::Markup), $text],
            [new Field('fixture.rows', t('Questions'), Type::Rows, fields: [new Field('answer', t('Answer'), Type::Markup)]), json_encode([['answer' => $text]], \JSON_THROW_ON_ERROR)],
        ];

        foreach ($fields as [$field, $value]) {
            $refusal = self::service(Settings::class)->save($field, $value);
            self::assertSame($saved, null === $refusal, $field->name);
            if (null !== $refusal) {
                self::assertStringContainsString('{{tally.none}}', self::phrase($refusal->reason), 'the refusal names the unknown value');
            }
        }
    }
}
