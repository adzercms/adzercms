<?php

declare(strict_types=1);

namespace AdzerCMS\Core\Tests;

use Symfony\Component\HttpClient\Exception\TransportException;
use Symfony\Contracts\HttpClient\ResponseInterface;

final class HttpAnswers
{
    private \Closure|\Iterator|null $answers = null;

    /**
     * @param callable|iterable<ResponseInterface|callable>|ResponseInterface $answers
     */
    public function respond(callable|iterable|ResponseInterface $answers): void
    {
        $this->answers = match (true) {
            $answers instanceof ResponseInterface => new \ArrayIterator([$answers]),
            \is_callable($answers) => $answers(...),
            default => (static fn (): \Generator => yield from $answers)(),
        };
    }

    /**
     * @param array<string, mixed> $options
     */
    public function __invoke(string $method, string $url, array $options): ResponseInterface
    {
        if ($this->answers instanceof \Closure) {
            return ($this->answers)($method, $url, $options);
        }
        if (null === $this->answers || !$this->answers->valid()) {
            throw new TransportException(\sprintf('No answer for %s %s', $method, $url));
        }
        $answer = $this->answers->current();
        $this->answers->next();

        return \is_callable($answer) ? $answer($method, $url, $options) : $answer;
    }
}
