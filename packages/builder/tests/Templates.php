<?php

declare(strict_types=1);

namespace AdzerCMS\Builder\Tests;

use AdzerCMS\Builder\Entity\Project;
use AdzerCMS\Builder\Entity\ProjectPage;
use AdzerCMS\Builder\Entity\ProjectVersion;
use AdzerCMS\Builder\ProjectAccess;
use AdzerCMS\Builder\Versions;
use AdzerCMS\Core\Entity\User;
use AdzerCMS\Core\Moderation\Decision;

trait Templates
{
    private function home(User $owner, string $name, ProjectAccess $access): ProjectPage
    {
        $project = new Project($owner, $name);
        $project->setAccess($access);
        $home = new ProjectPage($project, $name, '/');
        $home->place(1);
        $this->em->persist($project);
        $this->em->persist($home);
        $this->em->flush();

        return $home;
    }

    private function version(Project $project, Decision $decision): ProjectVersion
    {
        $project = $this->reread($project);
        $version = self::service(Versions::class)->submit($project, $project->getName(), $this->picture('cover.png'));
        self::assertInstanceOf(ProjectVersion::class, $version);
        $version->decide($decision);
        $this->em->flush();

        return $version;
    }
}
