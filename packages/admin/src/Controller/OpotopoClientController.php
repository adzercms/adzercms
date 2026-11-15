<?php

declare(strict_types=1);

namespace AdzerCMS\Admin\Controller;

use AdzerCMS\Core\Setting\Brand;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class OpotopoClientController extends AbstractController
{
    public const CLIENT = 'opotopo_client';

    public function __construct(private readonly Brand $brand)
    {
    }

    #[Route('/opotopo/client', name: self::CLIENT, methods: ['GET'])]
    public function __invoke(): JsonResponse
    {
        return new JsonResponse([
            'client_id' => $this->generateUrl(self::CLIENT, referenceType: UrlGeneratorInterface::ABSOLUTE_URL),
            'client_name' => $this->brand->title(),
            'redirect_uris' => [$this->generateUrl(OpotopoController::CALLBACK, referenceType: UrlGeneratorInterface::ABSOLUTE_URL)],
            'grant_types' => ['authorization_code', 'refresh_token'],
            'response_types' => ['code'],
            'token_endpoint_auth_method' => 'none',
        ]);
    }
}
