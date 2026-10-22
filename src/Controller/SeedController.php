<?php

namespace Wexample\SymfonyDev\Controller;

use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Wexample\SymfonyDev\Service\SeedService;
use Wexample\SymfonyHelpers\Controller\AbstractController;

/**
 * The demonstration data loaded again from the development menu, as `dev:seed`
 * does. Everything is emptied, the account signed in included: the session
 * ends with the rows it stood on, and the visitor lands wherever the
 * application sends a stranger.
 *
 * Reached by a program and not read by anyone, hence the `/_dev` path; routed
 * in dev and test only, where this bundle is registered at all.
 */
final class SeedController extends AbstractController
{
    public const string ROUTE_SEED = 'dev_seed';

    public const string CSRF_SEED = 'dev_seed';

    #[Route(path: '/_dev/seed', name: self::ROUTE_SEED, methods: [Request::METHOD_POST], env: ['dev', 'test'])]
    public function seed(
        Request $request,
        SeedService $seedService
    ): RedirectResponse {
        // No seeder, nothing to load: the database is not emptied for nothing.
        if (! $seedService->hasSeeders()) {
            throw new NotFoundHttpException('This application declares no demonstration data.');
        }

        // Asked here rather than with #[IsGranted]: without the security
        // bundle the attribute is read by nobody, where isGranted() throws —
        // a route emptying a database fails closed.
        if (! $this->isGranted('IS_AUTHENTICATED_FULLY')) {
            throw new AccessDeniedHttpException('Reloading the data is a signed-in account\'s.');
        }

        if (! $this->isCsrfTokenValid(self::CSRF_SEED, $request->request->getString('_token'))) {
            throw new AccessDeniedHttpException('Invalid token.');
        }

        $seedService->seed();

        // The accounts were replaced with the rest, so wherever the visitor
        // stood they are a stranger now: the root sends them to the sign-in.
        return $this->redirect('/');
    }
}
