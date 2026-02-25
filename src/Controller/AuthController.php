<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

final class AuthController extends AbstractController
{
    #[Route('/sign-in', name: 'app_sign_in')]
    public function app_sign_in(Request $request, AuthenticationUtils $authenticationUtils): Response
    {
        // get the login error if there is one
        $error = $authenticationUtils->getLastAuthenticationError();

        // last username entered by the user
        $lastUsername = $authenticationUtils->getLastUsername();
        $returnTo = $this->sanitizeReturnTo((string) $request->query->get('return_to', ''));

        return $this->render('auth/sign-in.html.twig', [
            'last_username' => $lastUsername,
            'error'         => $error,
            'return_to'     => $returnTo,
        ]);
    }

    #[Route('/sign-out', name: 'app_sign_out')]
    public function app_sign_out(): Response
    {
        // controller can be blank: it will never be called!
        throw new \Exception('Don\'t forget to activate logout in security.yaml');
    }

    private function sanitizeReturnTo(string $returnTo): ?string
    {
        $returnTo = trim($returnTo);

        if ($returnTo === '' || str_starts_with($returnTo, '//')) {
            return null;
        }

        $parts = parse_url($returnTo);
        if ($parts === false) {
            return null;
        }

        if (
            isset($parts['scheme'])
            || isset($parts['host'])
            || isset($parts['port'])
            || isset($parts['user'])
            || isset($parts['pass'])
        ) {
            return null;
        }

        $path = (string) ($parts['path'] ?? '');
        if ($path === '' || !str_starts_with($path, '/')) {
            return null;
        }

        return $returnTo;
    }
}
