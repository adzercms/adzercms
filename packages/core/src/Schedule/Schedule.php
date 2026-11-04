<?php

declare(strict_types=1);

namespace AdzerCMS\Core\Schedule;

use AdzerCMS\Contracts\Schedule\Scheduled;
use Cron\CronExpression;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\ContainerBuilder;

final class Schedule
{
    public const PARAMETER = 'adzercms.schedule';

    public static function declare(ContainerBuilder $builder): void
    {
        $builder->registerAttributeForAutoconfiguration(Scheduled::class, static function (ChildDefinition $definition, Scheduled $scheduled): void {
            $definition->addTag(Scheduled::class, ['cron' => $scheduled->cron]);
        });
    }

    public static function collect(ContainerBuilder $container): void
    {
        $schedule = [];
        foreach ($container->findTaggedServiceIds(Scheduled::class) as $id => $tags) {
            $class = $container->getParameterBag()->resolveValue($container->findDefinition($id)->getClass() ?? $id);
            $command = \is_string($class) && class_exists($class) ? ((new \ReflectionClass($class))->getAttributes(AsCommand::class)[0] ?? null) : null;
            $names = null === $command ? [] : array_values(array_filter(explode('|', $command->newInstance()->name)));
            if ([] === $names) {
                throw new \LogicException(\sprintf('Service "%s" is scheduled without being a named console command.', $id));
            }
            foreach ($tags as $tag) {
                $cron = $tag['cron'] ?? null;
                if (!\is_string($cron) || !CronExpression::isValidExpression($cron)) {
                    throw new \LogicException(\sprintf('Console "%s" has an invalid schedule.', $names[0]));
                }
                $schedule[$names[0]] = $cron;
            }
        }
        ksort($schedule);
        $container->setParameter(self::PARAMETER, $schedule);
    }
}
