<?php

declare(strict_types=1);

namespace AdzerCMS\Core\Site;

use Symfony\Component\Routing\Generator\CompiledUrlGenerator;

final class HostUrls extends CompiledUrlGenerator
{
    /**
     * @param array<mixed> $variables
     * @param array<mixed> $defaults
     * @param array<mixed> $requirements
     * @param array<mixed> $tokens
     * @param array<mixed> $parameters
     * @param array<mixed> $hostTokens
     * @param array<mixed> $requiredSchemes
     */
    protected function doGenerate(array $variables, array $defaults, array $requirements, array $tokens, array $parameters, string $name, int $referenceType, array $hostTokens, array $requiredSchemes = []): string
    {
        $hosts = $this->context->getParameter(Hosts::PARAMETER);
        $role = $defaults[Hosts::ROLE] ?? null;
        $target = $hosts instanceof Hosts ? $hosts->target(\is_string($role) ? $role : null, $this->context) : null;
        if (null === $target) {
            return parent::doGenerate($variables, $defaults, $requirements, $tokens, $parameters, $name, $referenceType, $hostTokens, $requiredSchemes);
        }

        $own = $this->context;
        $this->context = $target;
        try {
            return parent::doGenerate($variables, $defaults, $requirements, $tokens, $parameters, $name, self::ABSOLUTE_URL, $hostTokens, $requiredSchemes);
        } finally {
            $this->context = $own;
        }
    }
}
