<?php

declare(strict_types=1);

namespace AdzerCMS\Core\Tests;

use AdzerCMS\Core\Setting\Language;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\ConsoleEvents;
use Symfony\Component\Console\Event\ConsoleCommandEvent;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Symfony\Contracts\Translation\TranslatableInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

trait Container
{
    /**
     * @template T of object
     *
     * @param class-string<T> $class
     *
     * @return T
     */
    protected static function service(string $class, ?string $id = null): object
    {
        $service = self::getContainer()->get($id ?? $class);

        return $service instanceof $class
            ? $service
            : self::fail(\sprintf('service %s is missing in the test container', $id ?? $class));
    }

    protected static function parameter(string $name): string
    {
        $value = self::getContainer()->getParameter($name);

        return \is_string($value) ? $value : self::fail(\sprintf('parameter %s is not a string', $name));
    }

    protected static function command(string $name): CommandTester
    {
        $command = (new Application(self::$kernel ?? self::bootKernel()))->find($name);
        self::service(EventDispatcherInterface::class, 'event_dispatcher')
            ->dispatch(new ConsoleCommandEvent($command, new ArrayInput([]), new NullOutput()), ConsoleEvents::COMMAND);

        return new CommandTester($command);
    }

    /** @param array<string, mixed> $parameters */
    protected static function phrase(TranslatableInterface|string $message, array $parameters = [], ?string $locale = null): string
    {
        $translator = self::service(TranslatorInterface::class, 'translator');
        $locale ??= self::service(Language::class)->instance();

        return $message instanceof TranslatableInterface ? $message->trans($translator, $locale) : $translator->trans($message, $parameters, null, $locale);
    }
}
