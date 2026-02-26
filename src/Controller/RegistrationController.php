<?php

namespace App\Controller;

use App\Entity\User;
use App\Form\RegistrationFormType;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Form\FormError;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;

class RegistrationController extends AbstractController
{
    #[Route('/register', name: 'app_register')]
    public function register(Request $request, UserPasswordHasherInterface $userPasswordHasher, EntityManagerInterface $entityManager): Response
    {
        $returnTo = $this->sanitizeReturnTo((string) ($request->request->get('return_to') ?? $request->query->get('return_to', '')));

        $user = new User();
        $form = $this->createForm(RegistrationFormType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var string $plainPassword */
            $plainPassword = $form->get('plainPassword')->getData();

            // encode the plain password
            $user->setPassword($userPasswordHasher->hashPassword($user, $plainPassword));

            try {
                $entityManager->persist($user);
                $entityManager->flush();
            } catch (UniqueConstraintViolationException) {
                $form->get('email')->addError(new FormError('Ya existe una cuenta con este email'));

                return $this->render('registration/register.html.twig', [
                    'registrationForm' => $form,
                    'return_to' => $returnTo,
                ]);
            }

            $this->addFlash('success', 'Cuenta creada correctamente. Ahora podés iniciar sesión.');
            if ($returnTo !== null) {
                return $this->redirectToRoute('app_sign_in', ['return_to' => $returnTo]);
            }

            return $this->redirectToRoute('app_sign_in');
        }

        return $this->render('registration/register.html.twig', [
            'registrationForm' => $form,
            'return_to' => $returnTo,
        ]);
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
