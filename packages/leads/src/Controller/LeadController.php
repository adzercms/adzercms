<?php

declare(strict_types=1);

namespace AdzerCMS\Leads\Controller;

use AdzerCMS\Contracts\Refusal;
use AdzerCMS\Core\Ui\Back;
use AdzerCMS\Core\Ui\Fragment;
use AdzerCMS\Leads\Intake;
use AdzerCMS\Leads\Subjects;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\ParameterBag;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

use function Symfony\Component\Translation\t;

#[Route('/lead')]
final class LeadController extends AbstractController
{
    private const HOME = '/';

    public function __construct(
        private readonly Intake $intake,
        private readonly Subjects $subjects,
    ) {
    }

    #[Route('', name: 'leads_window', methods: ['GET'])]
    public function window(Request $request): Response
    {
        return $this->form($request->query, []);
    }

    #[Route('', name: 'leads_take', methods: ['POST'])]
    public function take(Request $request): Response
    {
        $page = Back::of($request, self::HOME);
        $refusals = $this->intake->take($request->request, (string) parse_url($page, \PHP_URL_PATH), (string) $request->getClientIp());

        if ([] !== $refusals) {
            return $this->form($request->request, $refusals);
        }

        $this->addFlash('success', t('leads.flash.taken'));

        return Fragment::go($page);
    }

    /** @param array<string, Refusal> $refusals */
    private function form(ParameterBag $input, array $refusals): Response
    {
        return $this->render('@Leads/_lead.html.twig', [
            'topic' => Intake::topic($input),
            'input' => $input->all(),
            'refusals' => $refusals,
            'subjects' => $this->subjects->fields($input),
        ]);
    }
}
