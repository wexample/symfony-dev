<?php

namespace Wexample\SymfonyDev\DevMenu;

use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Routing\Exception\RouteNotFoundException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Wexample\SymfonyDesignSystem\Interface\DevMenuProviderInterface;
use Wexample\SymfonyDev\Controller\SeedController;
use Wexample\SymfonyDev\Service\SeedService;

/**
 * « Reload the demonstration data » in the development menu, for a signed-in
 * account — shown unavailable to the others: asked first, the page held under
 * a spinner while the database is filled, then the sign-in, since the accounts
 * were replaced too.
 *
 * Registered only where symfony-design-system is installed, by this bundle's
 * extension. The labels are words and not translation keys: this package ships
 * no asset tree, and the interface takes either.
 */
class SeedDevMenuProvider implements DevMenuProviderInterface
{
    public function __construct(
        private readonly SeedService $seedService,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly Security $security,
        private readonly CsrfTokenManagerInterface $csrf,
    ) {
    }

    public function getDevMenuItems(): array
    {
        // Nothing to load, or the bundle's routes left out of the
        // application's routing: no entry rather than one leading nowhere.
        if (! $this->seedService->hasSeeders()) {
            return [];
        }

        try {
            $url = $this->urlGenerator->generate(SeedController::ROUTE_SEED);
        } catch (RouteNotFoundException) {
            return [];
        }

        $item = [
            'icon' => 'ph:bold/arrow-counter-clockwise',
            'label' => 'Reload the demonstration data',
            'account' => true,
            // The heaviest action of the menu: at its very end.
            'order' => 100,
        ];

        // Signed out, the entry stays, unavailable: the reload is a signed-in
        // account's, and asks for its token.
        if (! $this->security->isGranted('IS_AUTHENTICATED_FULLY')) {
            return [$item + ['disabled' => true]];
        }

        return [$item + [
            'href' => $url,
            'method' => 'post',
            'token' => $this->csrf->getToken(SeedController::CSRF_SEED)->getValue(),
            'confirm' => [
                'title' => 'Reload the demonstration data',
                'message' => 'Everything is emptied and written again, the accounts included: you will be signed out.',
                'accept' => 'Reload',
            ],
            // The answer is a whole new page, slow to come.
            'busy' => true,
        ]];
    }
}
