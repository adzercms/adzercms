<?php

declare(strict_types=1);

namespace AdzerCMS\Opotopo\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * @phpstan-type Failure array{tag: ?string, commit: ?string, code: string, details: array<string, string>}
 */
#[ORM\Entity]
#[ORM\Table(name: 'opotopo_git_source')]
class GitSource
{
    #[ORM\Id]
    #[ORM\OneToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Item $item;

    #[ORM\Column(type: Types::TEXT)]
    private string $repositoryUrl = '';

    #[ORM\Column(type: Types::TEXT)]
    private string $deployKey = '';

    #[ORM\Column(type: Types::TEXT)]
    private string $webhookUrl = '';

    /** @var list<Failure> */
    #[ORM\Column(type: Types::JSON)]
    private array $failures = [];

    public function __construct(Item $item)
    {
        $this->item = $item;
    }

    /** @param array<mixed> $source */
    public function absorb(array $source): void
    {
        $this->repositoryUrl = self::text($source['repositoryUrl'] ?? null);
        $this->deployKey = self::text($source['deployPublicKey'] ?? null);
        $this->webhookUrl = self::text($source['webhookUrl'] ?? null);
        $this->failures = [];

        if (\is_array($source['failure'] ?? null)) {
            $this->failures[] = self::failure(null, null, $source['failure']);
        }

        foreach (\is_array($source['tagFailures'] ?? null) ? $source['tagFailures'] : [] as $tag) {
            if (\is_array($tag) && \is_array($tag['failure'] ?? null)) {
                $this->failures[] = self::failure(self::text($tag['tag'] ?? null), self::text($tag['commit'] ?? null), $tag['failure']);
            }
        }
    }

    public function getItem(): Item
    {
        return $this->item;
    }

    public function getRepositoryUrl(): string
    {
        return $this->repositoryUrl;
    }

    public function getDeployKey(): string
    {
        return $this->deployKey;
    }

    public function getWebhookUrl(): string
    {
        return $this->webhookUrl;
    }

    /** @return list<Failure> */
    public function getFailures(): array
    {
        return $this->failures;
    }

    /**
     * @param array<mixed> $failure
     *
     * @return Failure
     */
    private static function failure(?string $tag, ?string $commit, array $failure): array
    {
        $details = [];
        foreach (\is_array($failure['details'] ?? null) ? $failure['details'] : [] as $name => $value) {
            $details[(string) $name] = self::text($value);
        }

        return ['tag' => $tag, 'commit' => $commit, 'code' => self::text($failure['code'] ?? null), 'details' => $details];
    }

    private static function text(mixed $value): string
    {
        return \is_scalar($value) ? (string) $value : '';
    }
}
