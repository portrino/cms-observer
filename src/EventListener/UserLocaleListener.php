<?php

declare(strict_types=1);

namespace App\EventListener;

use App\Entity\User;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Translation\LocaleSwitcher;

/**
 * The language of each request: the one chosen in the user's profile, else the
 * browser's preferred one of the enabled locales. After the firewall (priority 8),
 * which knows the user.
 */
#[AsEventListener(event: KernelEvents::REQUEST, priority: 7)]
final readonly class UserLocaleListener
{
    /**
     * @param list<string> $enabledLocales
     */
    public function __construct(
        private Security $security,
        private LocaleSwitcher $localeSwitcher,
        #[Autowire(param: 'kernel.enabled_locales')]
        private array $enabledLocales,
    ) {
    }

    public function __invoke(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $user = $this->security->getUser();
        $chosen = $user instanceof User ? $user->getLocale() : null;
        $locale = null !== $chosen && in_array($chosen, $this->enabledLocales, true)
            ? $chosen
            : $event->getRequest()->getPreferredLanguage($this->enabledLocales);

        if (null !== $locale) {
            $event->getRequest()->setLocale($locale);
            // the translator, the intl formats and the generated URLs; it leaves out the request
            $this->localeSwitcher->setLocale($locale);
        }
    }
}
