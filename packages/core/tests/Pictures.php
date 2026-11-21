<?php

declare(strict_types=1);

namespace AdzerCMS\Core\Tests;

use AdzerCMS\Core\Account\Avatars;
use AdzerCMS\Core\Entity\User;
use Symfony\Component\HttpClient\Response\MockResponse;

trait Pictures
{
    protected function avatar(User $user): string
    {
        self::service(HttpAnswers::class)->respond(new MockResponse(self::dot()));
        self::service(Avatars::class)->fill($user, 'https://93.184.216.34/avatar.png');

        return $user->getAvatar() ?? self::fail('the picture fills the avatar');
    }
}
