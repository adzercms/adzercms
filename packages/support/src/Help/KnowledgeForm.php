<?php

declare(strict_types=1);

namespace AdzerCMS\Support\Help;

use AdzerCMS\Contracts\Refusal;
use AdzerCMS\Core\Ui\Order;
use AdzerCMS\Core\Ui\Split;
use AdzerCMS\Support\Entity\HelpArticle;
use AdzerCMS\Support\Entity\HelpArticlePath;
use AdzerCMS\Support\Entity\HelpCategory;
use AdzerCMS\Support\Repository\HelpArticlePathRepository;
use AdzerCMS\Support\Repository\HelpArticleRepository;
use AdzerCMS\Support\Repository\HelpCategoryRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\InputBag;

use function Symfony\Component\Clock\now;
use function Symfony\Component\Translation\t;

final readonly class KnowledgeForm
{
    public const TITLE = 'title';
    public const SLUG = 'slug';
    public const DESCRIPTION = 'description';
    public const CATEGORY = 'category';
    public const TAGS = 'tags';
    public const BODY = 'body';
    public const PATHS = 'paths';
    public const PUBLISHED = 'published';

    private const PATH = '~^/[^\s?#]*$~u';

    public function __construct(
        private HelpCategoryRepository $categories,
        private HelpArticleRepository $articles,
        private HelpArticlePathRepository $paths,
        private EntityManagerInterface $em,
    ) {
    }

    /**
     * @param InputBag<string> $input
     *
     * @return HelpCategory|array<string, Refusal>
     */
    public function addCategory(InputBag $input): HelpCategory|array
    {
        $title = self::title($input);
        $slug = $this->categorySlug($input, null);
        if ($title instanceof Refusal || $slug instanceof Refusal) {
            return self::refusals([self::TITLE => $title, self::SLUG => $slug]);
        }

        $category = new HelpCategory($title, $slug);
        $category->place($this->categories->count() + 1);
        $this->em->persist($category);
        $this->em->flush();

        return $category;
    }

    /**
     * @param InputBag<string> $input
     *
     * @return array<string, Refusal>
     */
    public function saveCategory(HelpCategory $category, InputBag $input): array
    {
        $title = self::title($input);
        $slug = $this->categorySlug($input, $category);
        if ($title instanceof Refusal || $slug instanceof Refusal) {
            return self::refusals([self::TITLE => $title, self::SLUG => $slug]);
        }

        $category->edit($title, $slug, self::optional($input, self::DESCRIPTION));
        $this->em->flush();

        return [];
    }

    /**
     * @param InputBag<string> $input
     *
     * @return HelpArticle|array<string, Refusal>
     */
    public function addArticle(HelpCategory $category, InputBag $input): HelpArticle|array
    {
        $title = self::title($input);
        $slug = $this->articleSlug($input, $category, null);
        if ($title instanceof Refusal || $slug instanceof Refusal) {
            return self::refusals([self::TITLE => $title, self::SLUG => $slug]);
        }

        $article = new HelpArticle($category, $title, $slug, now());
        $article->place($this->articles->count(['category' => $category]) + 1);
        $this->em->persist($article);
        $this->em->flush();

        return $article;
    }

    /**
     * @param InputBag<string> $input
     *
     * @return array<string, Refusal>
     */
    public function saveArticle(HelpArticle $article, InputBag $input): array
    {
        $category = $this->categories->find($input->getInt(self::CATEGORY)) ?? $article->getCategory();
        $title = self::title($input);
        $slug = $this->articleSlug($input, $category, $article);
        $paths = self::paths($input);
        if ($title instanceof Refusal || $slug instanceof Refusal || $paths instanceof Refusal) {
            return self::refusals([self::TITLE => $title, self::SLUG => $slug, self::PATHS => $paths]);
        }

        if ($category !== $article->getCategory()) {
            $article->place($this->articles->count(['category' => $category]) + 1);
        }
        $article->edit(
            $category,
            $title,
            $slug,
            self::optional($input, self::DESCRIPTION),
            Split::items($input->getString(self::TAGS)),
            $input->getString(self::BODY),
            $input->getBoolean(self::PUBLISHED),
            now(),
        );
        $this->keep($article, $paths);
        $this->em->flush();

        return [];
    }

    public function lines(HelpArticle $article): string
    {
        return implode("\n", array_map(static fn (HelpArticlePath $path): string => $path->getPath(), $this->paths->of($article)));
    }

    public function drop(HelpCategory|HelpArticle $record): void
    {
        $this->em->remove($record);
        $this->em->flush();
    }

    /** @param list<int> $moved */
    public function arrange(?HelpCategory $category, array $moved): void
    {
        Order::place(null === $category ? $this->categories->ordered() : $this->articles->of($category), $moved);
        $this->em->flush();
    }

    /**
     * @param array<string, mixed> $fields
     *
     * @return array<string, Refusal>
     */
    private static function refusals(array $fields): array
    {
        return array_filter($fields, static fn (mixed $field): bool => $field instanceof Refusal);
    }

    /** @param InputBag<string> $input */
    private static function title(InputBag $input): string|Refusal
    {
        $title = trim($input->getString(self::TITLE));

        return '' === $title || mb_strlen($title) > HelpCategory::TITLE ? new Refusal(t('support.knowledge.refusal.title', ['limit' => HelpCategory::TITLE])) : $title;
    }

    /** @param InputBag<string> $input */
    private static function optional(InputBag $input, string $name): ?string
    {
        $value = trim($input->getString($name));

        return '' === $value ? null : $value;
    }

    /**
     * @param InputBag<string> $input
     *
     * @return list<string>|Refusal
     */
    private static function paths(InputBag $input): array|Refusal
    {
        $paths = [];
        foreach (Split::lines($input->getString(self::PATHS)) as $line) {
            $path = '/' === $line ? $line : rtrim($line, '/');
            if (1 !== preg_match(self::PATH, $path) || mb_strlen($path) > HelpArticlePath::PATH) {
                return new Refusal(t('support.knowledge.refusal.path', ['path' => $line]));
            }
            $paths[$path] = $path;
        }

        return array_values($paths);
    }

    /** @param InputBag<string> $input */
    private function categorySlug(InputBag $input, ?HelpCategory $category): string|Refusal
    {
        $slug = HelpCategory::slug($input->getString(self::SLUG));
        if (null === $slug) {
            return new Refusal(t('support.knowledge.refusal.slug', ['limit' => HelpCategory::SLUG]));
        }
        $holder = $this->categories->findOneBy(['slug' => $slug]);

        return null !== $holder && $holder !== $category ? new Refusal(t('support.knowledge.refusal.slug_category', ['title' => $holder->getTitle()])) : $slug;
    }

    /** @param InputBag<string> $input */
    private function articleSlug(InputBag $input, HelpCategory $category, ?HelpArticle $article): string|Refusal
    {
        $slug = HelpCategory::slug($input->getString(self::SLUG));
        if (null === $slug) {
            return new Refusal(t('support.knowledge.refusal.slug', ['limit' => HelpCategory::SLUG]));
        }
        $holder = $this->articles->findOneBy(['category' => $category, 'slug' => $slug]);

        return null !== $holder && $holder !== $article ? new Refusal(t('support.knowledge.refusal.slug_article', ['title' => $holder->getTitle()])) : $slug;
    }

    /** @param list<string> $paths */
    private function keep(HelpArticle $article, array $paths): void
    {
        foreach ($this->paths->of($article) as $kept) {
            $this->em->remove($kept);
        }
        foreach ($paths as $path) {
            $this->em->persist(new HelpArticlePath($article, $path));
        }
    }
}
