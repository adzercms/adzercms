<?php

declare(strict_types=1);

namespace AdzerCMS\Admin\Controller;

use AdzerCMS\Admin\ModuleSources;
use AdzerCMS\Contracts\Refusal;
use AdzerCMS\Core\Account\Role;
use AdzerCMS\Core\Module\Opotopo;
use AdzerCMS\Core\Security\ModuleVoter;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatableInterface;

use function Symfony\Component\Translation\t;

#[Route('/admin/opotopo')]
#[IsGranted(Role::ADMIN)]
#[IsGranted(ModuleVoter::MANAGE)]
final class OpotopoController extends AbstractController
{
    public const CALLBACK = 'admin_opotopo_callback';

    public function __construct(private readonly Opotopo $opotopo)
    {
    }

    #[Route('/connect', name: 'admin_opotopo_connect', methods: ['POST'])]
    public function connect(Request $request): Response
    {
        $authorization = $this->opotopo->authorize($request->getSession(), $this->address(OpotopoClientController::CLIENT), $this->address(self::CALLBACK));

        return $authorization instanceof Refusal ? $this->page($authorization) : new RedirectResponse($authorization);
    }

    #[Route('/callback', name: self::CALLBACK, methods: ['GET'])]
    public function callback(Request $request): Response
    {
        $answer = array_filter($request->query->all(), \is_string(...));
        $refusal = $this->opotopo->connect($request->getSession(), $answer, $this->address(OpotopoClientController::CLIENT), $this->address(self::CALLBACK));

        return $this->page($refusal, t('core.login.connected', ['service' => t('core.opotopo.name')]));
    }

    #[Route('/disconnect', name: 'admin_opotopo_disconnect', methods: ['POST'])]
    public function disconnect(): Response
    {
        return $this->page($this->opotopo->disconnect(), t('admin.opotopo.disconnected'));
    }

    private function page(?Refusal $refusal, ?TranslatableInterface $success = null): RedirectResponse
    {
        if (null !== $refusal) {
            $this->addFlash('error', $refusal->reason);
        } elseif (null !== $success) {
            $this->addFlash('success', $success);
        }

        return $this->redirectToRoute('admin_settings', ['page' => ModuleSources::PAGE->value]);
    }

    private function address(string $route): string
    {
        return $this->generateUrl($route, referenceType: UrlGeneratorInterface::ABSOLUTE_URL);
    }
}
