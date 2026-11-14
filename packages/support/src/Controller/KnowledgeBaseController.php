<?php

declare(strict_types=1);

namespace AdzerCMS\Support\Controller;

use AdzerCMS\Core\Account\Role;
use AdzerCMS\Core\Site\Hosts;
use AdzerCMS\Support\Entity\HelpArticle;
use AdzerCMS\Support\Entity\HelpCategory;
use AdzerCMS\Support\Help\KnowledgeBase;
use AdzerCMS\Support\Repository\HelpArticleRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

final class KnowledgeBaseController extends AbstractController
{
    public const CONTENTS = 'help';
    public const CATEGORY = 'help_category';
    public const ARTICLE = 'help_article';
    public const PREVIEW = 'admin_help_article_preview';

    private const QUERY = 'q';
    private const UNINDEXED = 'noindex, nofollow';

    public function __construct(
        private readonly KnowledgeBase $base,
        private readonly HelpArticleRepository $articles,
    ) {
    }

    #[Route('/help', name: self::CONTENTS, methods: ['GET'], defaults: [Hosts::ROLE => Hosts::SITE])]
    public function contents(Request $request): Response
    {
        $query = $request->query->getString(self::QUERY);
        $sections = $this->base->contents($query);
        if ([] === $sections && '' === trim($query)) {
            throw $this->createNotFoundException();
        }
        if ($request->headers->has('HX-Request')) {
            return $this->render('@Support/help/_contents.html.twig', ['sections' => $sections]);
        }
        $trail = $this->base->trail(null);

        return $this->render('@Support/help/contents.html.twig', [
            'sections' => $sections,
            'query' => $query,
            'schema' => $this->base->schema(null, $trail),
        ]);
    }

    #[Route('/help/{category}', name: self::CATEGORY, requirements: ['category' => HelpCategory::SEGMENT], methods: ['GET'], defaults: [Hosts::ROLE => Hosts::SITE], options: ['utf8' => true])]
    public function category(string $category): Response
    {
        if (!$this->articles->anyPublished($category)) {
            throw $this->createNotFoundException();
        }

        return $this->redirect($this->generateUrl(self::CONTENTS).'#'.$category, Response::HTTP_MOVED_PERMANENTLY);
    }

    #[Route('/help/{category}/{article}', name: self::ARTICLE, requirements: ['category' => HelpCategory::SEGMENT, 'article' => HelpCategory::SEGMENT], methods: ['GET'], defaults: [Hosts::ROLE => Hosts::SITE], options: ['utf8' => true])]
    public function article(string $category, string $article): Response
    {
        return $this->show($this->articles->published($category, $article) ?? throw $this->createNotFoundException());
    }

    #[Route('/admin/help/articles/{id}/preview', name: self::PREVIEW, requirements: ['id' => '\d+'], methods: ['GET'])]
    #[IsGranted(Role::ADMIN)]
    public function preview(HelpArticle $article): Response
    {
        $response = $this->show($article);
        $response->headers->set('X-Robots-Tag', self::UNINDEXED);

        return $response;
    }

    private function show(HelpArticle $article): Response
    {
        $trail = $this->base->trail($article);

        return $this->render('@Support/help/article.html.twig', [
            'article' => $article,
            'trail' => $trail,
            'schema' => $this->base->schema($article, $trail),
        ]);
    }
}
