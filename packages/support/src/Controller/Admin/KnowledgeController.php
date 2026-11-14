<?php

declare(strict_types=1);

namespace AdzerCMS\Support\Controller\Admin;

use AdzerCMS\Contracts\Refusal;
use AdzerCMS\Core\Account\Role;
use AdzerCMS\Core\Ui\Column;
use AdzerCMS\Core\Ui\Fragment;
use AdzerCMS\Core\Ui\Order;
use AdzerCMS\Core\Ui\Table;
use AdzerCMS\Support\Controller\KnowledgeBaseController;
use AdzerCMS\Support\Entity\HelpArticle;
use AdzerCMS\Support\Entity\HelpCategory;
use AdzerCMS\Support\Help\KnowledgeBase;
use AdzerCMS\Support\Help\KnowledgeForm;
use AdzerCMS\Support\Repository\HelpArticleRepository;
use AdzerCMS\Support\Repository\HelpCategoryRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatableInterface;

use function Symfony\Component\Translation\t;

#[Route('/admin/help')]
#[IsGranted(Role::ADMIN)]
final class KnowledgeController extends AbstractController
{
    public const LIST = 'admin_help';
    public const CATEGORY = 'admin_help_category';
    public const ARTICLE = 'admin_help_article';

    private const ORDER = 'admin_help_order';
    private const ARTICLES_ORDER = 'admin_help_articles_order';
    private const ARTICLE_NEW = 'admin_help_article_new';
    private const CATEGORIES = '@Support/admin/_categories.html.twig';
    private const ARTICLES = '@Support/admin/_articles.html.twig';
    private const NEW = '@Support/admin/_new.html.twig';

    public function __construct(
        private readonly HelpCategoryRepository $categories,
        private readonly HelpArticleRepository $articles,
        private readonly KnowledgeForm $form,
        private readonly KnowledgeBase $base,
    ) {
    }

    #[Route('', name: self::LIST, methods: ['GET'])]
    public function index(Request $request): Response
    {
        return $this->render($request->headers->has('HX-Request') ? self::CATEGORIES : '@Support/admin/knowledge.html.twig', $this->listing($request));
    }

    #[Route('/order', name: self::ORDER, methods: ['POST'])]
    public function order(Request $request): Response
    {
        $this->form->arrange(null, Order::moved($request));

        return $this->render(self::CATEGORIES, $this->listing($request));
    }

    #[Route('/new', name: 'admin_help_category_new', methods: ['GET', 'POST'])]
    public function createCategory(Request $request): Response
    {
        $refusals = [];
        if ($request->isMethod('POST')) {
            $outcome = $this->form->addCategory($request->request);
            if ($outcome instanceof HelpCategory) {
                $this->addFlash('success', t('support.knowledge.category.added', ['title' => $outcome->getTitle()]));

                return Fragment::go($this->generateUrl(self::CATEGORY, ['id' => $outcome->getId()]));
            }
            $refusals = $outcome;
        }

        return $this->dialog(t('support.knowledge.category.new'), $this->generateUrl('admin_help_category_new'), $request, $refusals);
    }

    #[Route('/{id}', name: self::CATEGORY, requirements: ['id' => '\d+'], methods: ['GET'])]
    public function category(HelpCategory $category, Request $request): Response
    {
        return $this->categoryCard($category, $request, [], null);
    }

    #[Route('/{id}', name: 'admin_help_category_save', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function saveCategory(HelpCategory $category, Request $request): Response
    {
        $refusals = $this->form->saveCategory($category, $request->request);
        if ([] !== $refusals) {
            return $this->categoryCard($category, $request, $refusals, $request->request->all());
        }
        $this->addFlash('success', t('support.knowledge.category.saved', ['title' => $category->getTitle()]));

        return $this->redirectToRoute(self::CATEGORY, ['id' => $category->getId()]);
    }

    #[Route('/{id}/drop', name: 'admin_help_category_drop', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function dropCategory(HelpCategory $category): Response
    {
        $title = $category->getTitle();
        $this->form->drop($category);
        $this->addFlash('success', t('support.knowledge.category.dropped', ['title' => $title]));

        return $this->redirectToRoute(self::LIST);
    }

    #[Route('/{id}/order', name: self::ARTICLES_ORDER, requirements: ['id' => '\d+'], methods: ['POST'])]
    public function orderArticles(HelpCategory $category, Request $request): Response
    {
        $this->form->arrange($category, Order::moved($request));

        return $this->render(self::ARTICLES, $this->articleListing($category, $request));
    }

    #[Route('/{id}/new', name: self::ARTICLE_NEW, requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function createArticle(HelpCategory $category, Request $request): Response
    {
        $refusals = [];
        if ($request->isMethod('POST')) {
            $outcome = $this->form->addArticle($category, $request->request);
            if ($outcome instanceof HelpArticle) {
                $this->addFlash('success', t('support.knowledge.article.added', ['title' => $outcome->getTitle()]));

                return Fragment::go($this->generateUrl(self::ARTICLE, ['id' => $outcome->getId()]));
            }
            $refusals = $outcome;
        }

        return $this->dialog(t('support.knowledge.article.new'), $this->generateUrl(self::ARTICLE_NEW, ['id' => $category->getId()]), $request, $refusals);
    }

    #[Route('/articles/{id}', name: self::ARTICLE, requirements: ['id' => '\d+'], methods: ['GET'])]
    public function article(HelpArticle $article): Response
    {
        return $this->articleCard($article, [], null);
    }

    #[Route('/articles/{id}', name: 'admin_help_article_save', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function saveArticle(HelpArticle $article, Request $request): Response
    {
        $refusals = $this->form->saveArticle($article, $request->request);
        if ([] !== $refusals) {
            return $this->articleCard($article, $refusals, $request->request->all());
        }
        $this->addFlash('success', t('support.knowledge.article.saved', ['title' => $article->getTitle()]));

        return $this->redirectToRoute(self::ARTICLE, ['id' => $article->getId()]);
    }

    #[Route('/articles/{id}/drop', name: 'admin_help_article_drop', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function dropArticle(HelpArticle $article): Response
    {
        $title = $article->getTitle();
        $category = $article->getCategory();
        $this->form->drop($article);
        $this->addFlash('success', t('support.knowledge.article.dropped', ['title' => $title]));

        return $this->redirectToRoute(self::CATEGORY, ['id' => $category->getId()]);
    }

    /** @return array<string, mixed> */
    private function listing(Request $request): array
    {
        $table = Table::of($request, self::LIST, [
            Column::plain(KnowledgeForm::TITLE, t('support.knowledge.field.title')),
            Column::plain(KnowledgeForm::SLUG, t('support.knowledge.field.slug')),
            Column::plain('articles', t('support.knowledge.column.articles')),
        ], KnowledgeForm::TITLE, t('support.knowledge.category.empty'), order: Order::of(self::ORDER, 'c.position', 'c.id'));
        $counts = $this->articles->counts();

        return ['table' => $table, 'rows' => $table->rows(array_map(static fn (HelpCategory $category): array => [
            'id' => $category->getId(),
            'title' => $category->getTitle(),
            'slug' => $category->getSlug(),
            'articles' => $counts[(int) $category->getId()] ?? 0,
        ], $this->categories->ordered()))];
    }

    /** @return array<string, mixed> */
    private function articleListing(HelpCategory $category, Request $request): array
    {
        $table = Table::of($request, self::CATEGORY, [
            Column::plain(KnowledgeForm::TITLE, t('support.knowledge.field.title')),
            Column::plain(KnowledgeForm::SLUG, t('support.knowledge.field.slug')),
            Column::plain(KnowledgeForm::PUBLISHED, t('support.knowledge.field.published')),
        ], KnowledgeForm::TITLE, t('support.knowledge.article.empty'), sticky: ['id' => $category->getId()], id: 'articles', order: Order::of(self::ARTICLES_ORDER, 'a.position', 'a.id'));

        return [
            'table' => $table,
            'rows' => $table->rows($this->articles->of($category)),
            'heading' => t('support.knowledge.articles'),
            'actions' => [['route' => self::ARTICLE_NEW, 'params' => ['id' => $category->getId()], 'label' => t('support.knowledge.article.add')]],
        ];
    }

    /** @param array<string, Refusal> $refusals */
    private function dialog(TranslatableInterface $heading, string $action, Request $request, array $refusals): Response
    {
        return $this->render(self::NEW, ['heading' => $heading, 'action' => $action, 'input' => $request->request->all(), 'refusals' => $refusals]);
    }

    /**
     * @param array<string, Refusal>    $refusals
     * @param array<string, mixed>|null $input
     */
    private function categoryCard(HelpCategory $category, Request $request, array $refusals, ?array $input): Response
    {
        return $this->render('@Support/admin/category.html.twig', [
            'category' => $category,
            'refusals' => $refusals,
            'input' => $input,
            'articles' => $this->articleListing($category, $request),
        ], new Response(status: [] === $refusals ? Response::HTTP_OK : Response::HTTP_UNPROCESSABLE_ENTITY));
    }

    /**
     * @param array<string, Refusal>    $refusals
     * @param array<string, mixed>|null $input
     */
    private function articleCard(HelpArticle $article, array $refusals, ?array $input): Response
    {
        return $this->render('@Support/admin/article.html.twig', [
            'article' => $article,
            'open' => $article->isPublished() ? $this->base->url($article) : $this->generateUrl(KnowledgeBaseController::PREVIEW, ['id' => $article->getId()]),
            'categories' => $this->categories->ordered(),
            'paths' => $this->form->lines($article),
            'refusals' => $refusals,
            'input' => $input,
        ], new Response(status: [] === $refusals ? Response::HTTP_OK : Response::HTTP_UNPROCESSABLE_ENTITY));
    }
}
