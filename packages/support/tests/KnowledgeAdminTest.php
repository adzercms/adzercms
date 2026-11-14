<?php

declare(strict_types=1);

namespace AdzerCMS\Support\Tests;

use AdzerCMS\Core\Account\Role;
use AdzerCMS\Core\Tests\Accounts;
use AdzerCMS\Core\Tests\Browser;
use AdzerCMS\Core\Tests\SchemaTestCase;
use AdzerCMS\Core\Ui\Order;
use AdzerCMS\Support\Entity\HelpArticle;
use AdzerCMS\Support\Entity\HelpCategory;
use AdzerCMS\Support\Help\KnowledgeForm;
use AdzerCMS\Support\Repository\HelpArticleRepository;
use AdzerCMS\Support\Repository\HelpCategoryRepository;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\DomCrawler\Crawler;

final class KnowledgeAdminTest extends SchemaTestCase
{
    use Accounts;
    use Browser;

    private const LIST = '/admin/help';

    protected function setUp(): void
    {
        parent::setUp();
        $this->client->loginUser($this->record('79990000001')->setRole(Role::Admin));
        $this->em->flush();
    }

    #[Group('ACMS-SUP-0027')]
    #[Group('ACMS-SUP-0028')]
    #[Group('ACMS-SUP-0029')]
    #[Group('ACMS-FLOW-0021')]
    #[Group('ACMS-US-0076')]
    public function testOwnerKeepsCategoriesAndArticlesInManualOrder(): void
    {
        $hosting = $this->category('Hosting', 'Hosting ');
        $domains = $this->category('Domains', 'domains');
        self::assertCount(1, $this->post(self::LIST.'/new', ['title' => 'Copy', 'slug' => 'domains'])->filter('.field-refusal'), 'a category address is unique');
        $this->post(self::LIST.'/order', [Order::FIELD => [(string) $domains->getId(), (string) $hosting->getId()]]);
        self::assertSame([['Domains', 'domains', '0'], ['Hosting', 'hosting', '0']], self::rows($this->fragment(self::LIST)));

        self::mockTime('2026-11-01 10:00');
        $ssl = $this->article($hosting, 'SSL', 'ssl');
        $mail = $this->article($hosting, 'Mail', 'mail');
        self::assertCount(1, $this->post(self::LIST.'/'.$hosting->getId().'/new', ['title' => 'Copy', 'slug' => 'ssl'])->filter('.field-refusal'), 'an article address is unique within its category');
        $this->post(self::LIST.'/'.$hosting->getId().'/order', [Order::FIELD => [(string) $mail->getId(), (string) $ssl->getId()]]);
        self::assertSame([['Mail', 'mail', '✗'], ['SSL', 'ssl', '✗']], self::rows($this->arrive(self::LIST.'/'.$hosting->getId())->filter('#articles')));

        self::mockTime('2026-11-02 10:00');
        $this->post(self::LIST.'/articles/'.$ssl->getId(), ['paths' => "/hosting/\n/hosting\n/"] + $this->fields($hosting, 'SSL', 'ssl'));
        self::assertResponseRedirects(self::LIST.'/articles/'.$ssl->getId());
        $saved = $this->stored($ssl);
        self::assertSame(['tls', 'certificate'], $saved->getTags());
        self::assertSame('2026-11-02', $saved->getUpdatedAt()->format('Y-m-d'), 'a changed text moves the moment of the article');
        self::assertSame("/hosting\n/", self::service(KnowledgeForm::class)->lines($saved), 'cabinet paths keep one path without the trailing slash');
        $refused = $this->post(self::LIST.'/articles/'.$ssl->getId(), ['body' => 'Issue it in {{core.nowhere}}'] + $this->fields($hosting, 'SSL', 'ssl'));
        self::assertStringContainsString('{{core.nowhere}}', $refused->filter('.field-refusal')->text(), 'a text with an unknown value is not saved');

        self::mockTime('2026-11-03 10:00');
        $this->post(self::LIST.'/articles/'.$ssl->getId(), $this->fields($hosting, 'SSL', 'ssl', published: false));
        self::assertSame('2026-11-02', $this->stored($ssl)->getUpdatedAt()->format('Y-m-d'), 'publication alone keeps the moment');

        $this->article($domains, 'SSL of a domain', 'ssl');
        $refused = $this->post(self::LIST.'/articles/'.$ssl->getId(), $this->fields($domains, 'SSL', 'ssl'));
        self::assertCount(1, $refused->filter('.field-refusal'), 'a moved article keeps its address unique in the new category');
        $this->post(self::LIST.'/articles/'.$ssl->getId(), $this->fields($domains, 'SSL', 'tls'));
        self::assertSame(['SSL of a domain', 'SSL'], array_column(self::rows($this->arrive(self::LIST.'/'.$domains->getId())->filter('#articles')), 0), 'a moved article goes last');

        $this->post(self::LIST.'/'.$domains->getId().'/drop');
        self::assertResponseRedirects(self::LIST);
        $this->em->clear();
        self::assertSame(['mail'], array_map(static fn (HelpArticle $article): string => $article->getSlug(), self::service(HelpArticleRepository::class)->findAll()), 'a dropped category takes its articles');
    }

    private function category(string $title, string $slug): HelpCategory
    {
        $this->post(self::LIST.'/new', ['title' => $title, 'slug' => $slug]);
        $category = self::service(HelpCategoryRepository::class)->findOneBy(['title' => $title]) ?? self::fail('no category was created');
        self::assertResponseHeaderSame('HX-Redirect', self::LIST.'/'.$category->getId());

        return $category;
    }

    private function article(HelpCategory $category, string $title, string $slug): HelpArticle
    {
        $this->post(self::LIST.'/'.$category->getId().'/new', ['title' => $title, 'slug' => $slug]);
        $article = self::service(HelpArticleRepository::class)->findOneBy(['category' => $category, 'slug' => $slug]) ?? self::fail('no article was created');
        self::assertResponseHeaderSame('HX-Redirect', self::LIST.'/articles/'.$article->getId());

        return $article;
    }

    private function stored(HelpArticle $article): HelpArticle
    {
        $this->em->clear();

        return self::service(HelpArticleRepository::class)->find($article->getId()) ?? self::fail('the article is gone');
    }

    /** @return array<string, string> */
    private function fields(HelpCategory $category, string $title, string $slug, bool $published = true): array
    {
        return ['category' => (string) $category->getId(), 'title' => $title, 'slug' => $slug, 'description' => 'Certificates of sites', 'tags' => 'tls, certificate', 'body' => 'Issue a certificate.', 'paths' => '/hosting'] + ($published ? ['published' => '1'] : []);
    }

    /** @return list<list<string>> */
    private static function rows(Crawler $listing): array
    {
        return $listing->filter('tbody tr')->each(static fn (Crawler $row): array => $row->filter('td:not(.row-handle)')->each(static fn (Crawler $cell): string => trim($cell->text())));
    }
}
