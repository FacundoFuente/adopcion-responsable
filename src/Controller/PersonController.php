<?php

namespace App\Controller;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use App\Repository\PersonRepository;
use Symfony\Component\HttpFoundation\JsonResponse;
use Doctrine\Persistence\ManagerRegistry;
use App\Entity\Person;
use App\Entity\User;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class PersonController extends AbstractController
{
    private const PHOTO_ENTRY_PREFIX = '[FOTO] ';

    #[Route('/person', name: 'get.person', methods: 'GET')]
    public function getPerson(
        PersonRepository $personRepository,
        Request $request
    ): JsonResponse
    {
        $dni = $request->query->get('dni');

        if (!$dni) {
            return new JsonResponse(['status' => 'error', 'message' => 'DNI es requerido'], 400);
        }

        $dni = (int) preg_replace('/\D+/', '', (string) $dni);

        $entries = $personRepository->findBy(
            ['dni' => $dni],
            ['createdAt' => 'DESC', 'id' => 'DESC']
        );

        if (!$entries) {
            return new JsonResponse(['status' => '404', 'message' => 'Persona sin prontuario'], 404);
        }
        $photoUrl = $this->findPhotoUrlByDni($dni);

        return new JsonResponse([
            'status' => 'ok',
            'dni' => $dni,
            'photoUrl' => $photoUrl,
            'entries' => array_map(static function (Person $p) use ($photoUrl) {
                $isPhotoEntry = str_starts_with($p->getDescription() ?? '', self::PHOTO_ENTRY_PREFIX);

                return [
                    'id' => $p->getId(),
                    'type' => $isPhotoEntry ? 'photo' : 'text',
                    'description' => $isPhotoEntry
                        ? trim(substr((string) $p->getDescription(), strlen(self::PHOTO_ENTRY_PREFIX)))
                        : $p->getDescription(),
                    'createdAt' => $p->getCreatedAt()?->format('Y-m-d H:i:s'),
                    'ownerEmail' => $p->getOwner()?->getEmail() ?? 'Usuario desconocido',
                    'photoUrl' => $isPhotoEntry ? $photoUrl : null,
                ];
            }, $entries),
        ]);
    }

    #[Route('/add', name: 'add.person', methods: 'POST')]
    public function addPerson(
        Request $request,
        ManagerRegistry $doctrine,
        PersonRepository $personRepository
    ): JsonResponse
    {
        $user = $this->getUser();

        if (!$user instanceof User) {
            return new JsonResponse([
                'status' => 'error',
                'message' => 'Debés iniciar sesión'
            ], 401);
        }

        $dni = (int) preg_replace('/\D+/', '', (string) $request->request->get('dni'));
        $description = trim((string) $request->request->get('description'));

        if ($dni === 0 || $description === '') {
            return new JsonResponse([
                'status' => 'error',
                'message' => 'DNI y descripción son requeridos'
            ], 400);
        }

        // La primera entrada del DNI define el dueño 
        $ownerEntry = $personRepository->findOneBy(['dni' => $dni], ['id' => 'ASC']);

        // Si ya existe dueño y no soy yo -> prohibido
        if ($ownerEntry && $ownerEntry->getOwner()?->getId() !== $user->getId()) {
            return new JsonResponse([
                'status' => 'error',
                'message' => 'No podés agregar entradas a este DNI'
            ], 403);
        }

        $person = new Person();
        $person->setDni($dni);
        $person->setDescription($description);
        $person->setCreatedAt(new \DateTime());

        // Si ya existía prontuario, hereda el dueño original 
        // Si no existía, el dueño pasa a ser el usuario actual
        $person->setOwner($ownerEntry?->getOwner() ?? $user);

        $em = $doctrine->getManager();
        $em->persist($person);
        $em->flush();

        $owner = $person->getOwner();

        return new JsonResponse([
            'status' => 'ok',
            'id' => $person->getId(),
            'ownerId' => $owner?->getId(),
            'ownerEmail' => $owner?->getEmail() ?? 'Usuario desconocido',
            'me' => $user->getId(),
        ]);
    }

    #[Route('/person/photo', name: 'add.person.photo', methods: 'POST')]
    public function addPhoto(
        Request $request,
        PersonRepository $personRepository,
        ManagerRegistry $doctrine
    ): JsonResponse
    {
        $user = $this->getUser();

        if (!$user instanceof User) {
            return new JsonResponse([
                'status' => 'error',
                'message' => 'Debés iniciar sesión'
            ], 401);
        }

        $dni = (int) preg_replace('/\D+/', '', (string) $request->request->get('dni'));
        $photoFile = $request->files->get('photo');

        if ($dni === 0) {
            return new JsonResponse([
                'status' => 'error',
                'message' => 'DNI inválido'
            ], 400);
        }

        if (!$photoFile instanceof UploadedFile) {
            return new JsonResponse([
                'status' => 'error',
                'message' => 'Debés seleccionar una imagen'
            ], 400);
        }

        // Solo puede subir la foto el creador del prontuario (dueño del DNI)
        $ownerEntry = $personRepository->findOneBy(['dni' => $dni], ['id' => 'ASC']);
        if (!$ownerEntry) {
            return new JsonResponse([
                'status' => 'error',
                'message' => 'El prontuario no existe todavía'
            ], 404);
        }

        if ($ownerEntry->getOwner()?->getId() !== $user->getId()) {
            return new JsonResponse([
                'status' => 'error',
                'message' => 'No podés agregar foto a este prontuario'
            ], 403);
        }

        $allowedMimeTypes = [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
        ];
        $mimeType = $photoFile->getMimeType();
        $extension = $allowedMimeTypes[$mimeType] ?? null;

        if (!$extension) {
            return new JsonResponse([
                'status' => 'error',
                'message' => 'Formato inválido. Usá JPG, PNG o WEBP'
            ], 400);
        }

        if (($photoFile->getSize() ?? 0) > 5 * 1024 * 1024) {
            return new JsonResponse([
                'status' => 'error',
                'message' => 'La imagen no puede superar los 5MB'
            ], 400);
        }

        if ($this->findPhotoUrlByDni($dni) !== null) {
            return new JsonResponse([
                'status' => 'error',
                'message' => 'Este prontuario ya tiene una foto cargada'
            ], 400);
        }

        $uploadDir = $this->getParameter('kernel.project_dir').'/public/uploads/prontuarios';
        if (!is_dir($uploadDir) && !mkdir($uploadDir, 0775, true) && !is_dir($uploadDir)) {
            return new JsonResponse([
                'status' => 'error',
                'message' => 'No se pudo crear la carpeta de uploads'
            ], 500);
        }

        try {
            $photoFile->move($uploadDir, $dni.'.'.$extension);
        } catch (\Throwable $e) {
            return new JsonResponse([
                'status' => 'error',
                'message' => 'No se pudo guardar la imagen'
            ], 500);
        }

        // La foto se registra como una nueva entrada del prontuario.
        $photoEntry = new Person();
        $photoEntry->setDni($dni);
        $photoEntry->setOwner($ownerEntry->getOwner());
        $photoEntry->setCreatedAt(new \DateTime());
        $photoEntry->setDescription(self::PHOTO_ENTRY_PREFIX.'Se agregó la foto del prontuario');

        $em = $doctrine->getManager();
        $em->persist($photoEntry);
        $em->flush();

        return new JsonResponse([
            'status' => 'ok',
            'dni' => $dni,
            'entryId' => $photoEntry->getId(),
            'photoUrl' => '/uploads/prontuarios/'.$dni.'.'.$extension,
        ]);
    }

    private function findPhotoUrlByDni(int $dni): ?string
    {
        $basePath = $this->getParameter('kernel.project_dir').'/public/uploads/prontuarios/';

        foreach (['jpg', 'png', 'webp'] as $extension) {
            if (is_file($basePath.$dni.'.'.$extension)) {
                return '/uploads/prontuarios/'.$dni.'.'.$extension;
            }
        }

        return null;
    }
}
