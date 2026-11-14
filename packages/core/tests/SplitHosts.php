<?php

declare(strict_types=1);

namespace AdzerCMS\Core\Tests;

trait SplitHosts
{
    private const SITE_URI = 'SITE_URI';
    private const SITE = 'http://site.localhost';
    private const CABINET = 'http://localhost';

    protected function setUp(): void
    {
        $_SERVER[self::SITE_URI] = $_ENV[self::SITE_URI] = self::SITE;
        parent::setUp();
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        unset($_SERVER[self::SITE_URI], $_ENV[self::SITE_URI]);
    }
}
