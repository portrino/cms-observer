<?php

declare(strict_types=1);

namespace App\Tests\Unit\Security;

use App\Entity\Site;
use App\Repository\SiteRepository;
use App\Security\SiteAccessTokenHandler;
use App\Security\SiteTokenFactory;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Exception\BadCredentialsException;

class SiteAccessTokenHandlerTest extends TestCase
{
    public function testValidTokenResolvesToItsSite(): void
    {
        [$token] = new SiteTokenFactory()->generate();
        $site = self::createStub(Site::class);
        $site->method('getId')->willReturn(42);

        $siteRepository = self::createStub(SiteRepository::class);
        $siteRepository->method('findOneByTokenHash')->willReturn($site);

        $badge = new SiteAccessTokenHandler($siteRepository, new SiteTokenFactory())->getUserBadgeFrom($token);

        self::assertSame($site, $badge->getUser());
    }

    public function testUnknownTokenIsRejected(): void
    {
        $siteRepository = self::createStub(SiteRepository::class);
        $siteRepository->method('findOneByTokenHash')->willReturn(null);

        $handler = new SiteAccessTokenHandler($siteRepository, new SiteTokenFactory());

        $this->expectException(BadCredentialsException::class);
        $handler->getUserBadgeFrom('whatever');
    }
}
