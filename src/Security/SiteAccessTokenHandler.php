<?php

declare(strict_types=1);

namespace App\Security;

use App\Repository\SiteRepository;
use Symfony\Component\Security\Core\Exception\BadCredentialsException;
use Symfony\Component\Security\Http\AccessToken\AccessTokenHandlerInterface;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;

class SiteAccessTokenHandler implements AccessTokenHandlerInterface
{
    public function __construct(
        private readonly SiteRepository $siteRepository,
        private readonly SiteTokenFactory $tokenFactory,
    ) {
    }

    public function getUserBadgeFrom(string $accessToken): UserBadge
    {
        $site = $this->siteRepository->findOneByTokenHash($this->tokenFactory->hash($accessToken));

        if (null === $site) {
            throw new BadCredentialsException('Invalid site token.');
        }

        return new UserBadge((string) $site->getId(), static fn () => $site);
    }
}
