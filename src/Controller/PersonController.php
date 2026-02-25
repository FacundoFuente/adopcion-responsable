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
    private const PHOTO_ENTRY_SEPARATOR = '|';
    private const PHOTO_MAX_INPUT_BYTES = 10485760; // 10MB
    private const PHOTO_TARGET_MAX_DIMENSION = 1600;
    private const PHOTO_TARGET_MAX_BYTES = 1572864; // 1.5MB aprox

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

        $mappedEntries = array_map(function (Person $p) use ($dni) {
            $isPhotoEntry = str_starts_with($p->getDescription() ?? '', self::PHOTO_ENTRY_PREFIX);
            $description = (string) ($p->getDescription() ?? '');
            $photoUrl = null;
            $publicDescription = $description;

            if ($isPhotoEntry) {
                $payload = trim(substr($description, strlen(self::PHOTO_ENTRY_PREFIX)));
                $parts = explode(self::PHOTO_ENTRY_SEPARATOR, $payload, 2);

                if (count($parts) === 2 && trim($parts[0]) !== '') {
                    $filename = basename(trim($parts[0]));
                    $photoUrl = $this->resolvePhotoUrlByFilename($filename);
                    $publicDescription = trim($parts[1]);
                } else {
                    // Compatibilidad con entradas viejas
                    $publicDescription = $payload;
                    $photoUrl = $this->findLegacyPhotoUrlByDni($dni);
                }
            }

            return [
                'id' => $p->getId(),
                'type' => $isPhotoEntry ? 'photo' : 'text',
                'description' => $isPhotoEntry ? $publicDescription : $p->getDescription(),
                'createdAt' => $p->getCreatedAt()?->format('Y-m-d H:i:s'),
                'ownerEmail' => $p->getOwner()?->getEmail() ?? 'Usuario desconocido',
                'photoUrl' => $isPhotoEntry ? $photoUrl : null,
            ];
        }, $entries);

        $latestPhotoUrl = null;
        foreach ($mappedEntries as $entry) {
            if (($entry['type'] ?? null) === 'photo' && is_string($entry['photoUrl'] ?? null)) {
                $latestPhotoUrl = $entry['photoUrl'];
                break;
            }
        }

        return new JsonResponse([
            'status' => 'ok',
            'dni' => $dni,
            'photoUrl' => $latestPhotoUrl ?? $this->findLegacyPhotoUrlByDni($dni),
            'entries' => $mappedEntries,
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
        $ownerEntry = $personRepository->findOwnerByDni($dni);

        // Si ya existe dueño y no soy yo -> prohibido
        if ($ownerEntry && !$this->canManageRecord($user, $ownerEntry)) {
            return new JsonResponse([
                'status' => 'error',
                'message' => 'Solo el email creador puede agregar entradas a este DNI'
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
        $photoDescription = trim((string) $request->request->get('description', ''));
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

        // Si el prontuario ya existe, solo puede subir la foto su dueño.
        // Si no existe, la foto crea la primera entrada y el dueño pasa a ser el usuario actual.
        $ownerEntry = $personRepository->findOwnerByDni($dni);
        if ($ownerEntry && !$this->canManageRecord($user, $ownerEntry)) {
            return new JsonResponse([
                'status' => 'error',
                'message' => 'Solo el email creador puede agregar foto a este DNI'
            ], 403);
        }

        $mimeType = (string) $photoFile->getMimeType();
        $allowedMimeTypes = ['image/jpeg', 'image/png', 'image/webp'];
        if (!in_array($mimeType, $allowedMimeTypes, true)) {
            return new JsonResponse([
                'status' => 'error',
                'message' => 'Formato inválido. Usá JPG, PNG o WEBP'
            ], 400);
        }

        if (($photoFile->getSize() ?? 0) > self::PHOTO_MAX_INPUT_BYTES) {
            return new JsonResponse([
                'status' => 'error',
                'message' => 'La imagen no puede superar los 10MB'
            ], 400);
        }

        $uploadDir = $this->getParameter('kernel.project_dir').'/public/uploads/prontuarios';
        if (!is_dir($uploadDir) && !mkdir($uploadDir, 0775, true) && !is_dir($uploadDir)) {
            return new JsonResponse([
                'status' => 'error',
                'message' => 'No se pudo crear la carpeta de uploads'
            ], 500);
        }

        $outputExtension = $this->resolveOutputExtension();
        if ($outputExtension === null) {
            return new JsonResponse([
                'status' => 'error',
                'message' => 'El servidor no soporta procesamiento de imágenes'
            ], 500);
        }

        $filename = $this->generateHashedPhotoFilename($outputExtension, $uploadDir);
        $targetPath = $uploadDir.'/'.$filename;

        if (!$this->processAndStorePhoto($photoFile, $mimeType, $targetPath)) {
            return new JsonResponse([
                'status' => 'error',
                'message' => 'No se pudo procesar la imagen'
            ], 500);
        }

        // La foto se registra como una nueva entrada del prontuario
        $photoEntry = new Person();
        $photoEntry->setDni($dni);
        $photoEntry->setOwner($ownerEntry?->getOwner() ?? $user);
        $photoEntry->setCreatedAt(new \DateTime());
        $photoEntry->setDescription(
            self::PHOTO_ENTRY_PREFIX
            .$filename
            .self::PHOTO_ENTRY_SEPARATOR
            .($photoDescription !== '' ? $photoDescription : 'Se agregó la foto del prontuario')
        );

        $em = $doctrine->getManager();
        $em->persist($photoEntry);
        $em->flush();

        return new JsonResponse([
            'status' => 'ok',
            'dni' => $dni,
            'entryId' => $photoEntry->getId(),
            'photoUrl' => '/uploads/prontuarios/'.$filename,
        ]);
    }

    private function generateHashedPhotoFilename(string $extension, string $uploadDir): string
    {
        $secret = (string) $this->getParameter('kernel.secret');

        do {
            try {
                $randomNumber = random_int(100000000, 999999999);
            } catch (\Throwable) {
                $randomNumber = (int) (microtime(true) * 1000000);
            }
            $hashedToken = hash_hmac('sha256', (string) $randomNumber, $secret);
            $filename = substr($hashedToken, 0, 24).'.'.$extension;
        } while (is_file($uploadDir.'/'.$filename));

        return $filename;
    }

    private function resolvePhotoUrlByFilename(string $filename): ?string
    {
        $safeFilename = basename($filename);
        $fullPath = rtrim($this->getParameter('kernel.project_dir').'/public/uploads/prontuarios', '/').'/'.$safeFilename;

        if (!is_file($fullPath)) {
            return null;
        }

        return '/uploads/prontuarios/'.$safeFilename;
    }

    private function findLegacyPhotoUrlByDni(int $dni): ?string
    {
        $basePath = rtrim($this->getParameter('kernel.project_dir').'/public/uploads/prontuarios', '/');
        $files = [];

        foreach (['jpg', 'png', 'webp'] as $extension) {
            $legacyFile = $basePath.'/'.$dni.'.'.$extension;
            if (is_file($legacyFile)) {
                $files[] = $legacyFile;
            }

            $pattern = $basePath.'/'.$dni.'_*'.'.'.$extension;
            $patternFiles = glob($pattern) ?: [];
            foreach ($patternFiles as $file) {
                if (is_file($file)) {
                    $files[] = $file;
                }
            }
        }

        if ($files === []) {
            return null;
        }

        usort($files, static fn (string $a, string $b): int => filemtime($b) <=> filemtime($a));
        $latestFile = basename($files[0]);

        return '/uploads/prontuarios/'.$latestFile;
    }

    private function resolveOutputExtension(): ?string
    {
        if (function_exists('imagewebp')) {
            return 'webp';
        }

        if (function_exists('imagejpeg')) {
            return 'jpg';
        }

        if (function_exists('imagepng')) {
            return 'png';
        }

        return null;
    }

    private function processAndStorePhoto(UploadedFile $photoFile, string $mimeType, string $targetPath): bool
    {
        $sourceImage = $this->createImageResource($photoFile->getPathname(), $mimeType);
        if (!$sourceImage) {
            return false;
        }

        $sourceWidth = imagesx($sourceImage);
        $sourceHeight = imagesy($sourceImage);

        if ($sourceWidth < 1 || $sourceHeight < 1) {
            imagedestroy($sourceImage);
            return false;
        }

        $maxSourceSide = max($sourceWidth, $sourceHeight);
        $scale = $maxSourceSide > self::PHOTO_TARGET_MAX_DIMENSION
            ? self::PHOTO_TARGET_MAX_DIMENSION / $maxSourceSide
            : 1.0;

        $targetWidth = (int) max(1, round($sourceWidth * $scale));
        $targetHeight = (int) max(1, round($sourceHeight * $scale));
        $targetImage = imagecreatetruecolor($targetWidth, $targetHeight);

        if (!$targetImage) {
            imagedestroy($sourceImage);
            return false;
        }

        $targetExtension = strtolower((string) pathinfo($targetPath, PATHINFO_EXTENSION));
        if ($targetExtension === 'jpg' || $targetExtension === 'jpeg') {
            $background = imagecolorallocate($targetImage, 255, 255, 255);
            imagefilledrectangle($targetImage, 0, 0, $targetWidth, $targetHeight, $background);
        } else {
            imagealphablending($targetImage, false);
            imagesavealpha($targetImage, true);
            $transparent = imagecolorallocatealpha($targetImage, 0, 0, 0, 127);
            imagefilledrectangle($targetImage, 0, 0, $targetWidth, $targetHeight, $transparent);
        }

        imagecopyresampled(
            $targetImage,
            $sourceImage,
            0,
            0,
            0,
            0,
            $targetWidth,
            $targetHeight,
            $sourceWidth,
            $sourceHeight
        );

        $saved = $this->saveCompressedImage($targetImage, $targetPath);

        imagedestroy($targetImage);
        imagedestroy($sourceImage);

        if (!$saved) {
            @unlink($targetPath);
        }

        return $saved;
    }

    private function createImageResource(string $path, string $mimeType)
    {
        return match ($mimeType) {
            'image/jpeg' => function_exists('imagecreatefromjpeg') ? @imagecreatefromjpeg($path) : false,
            'image/png' => function_exists('imagecreatefrompng') ? @imagecreatefrompng($path) : false,
            'image/webp' => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($path) : false,
            default => false,
        };
    }

    private function saveCompressedImage($image, string $targetPath): bool
    {
        $extension = strtolower((string) pathinfo($targetPath, PATHINFO_EXTENSION));

        if ($extension === 'webp' && function_exists('imagewebp')) {
            foreach ([82, 74, 66, 58] as $quality) {
                if (!@imagewebp($image, $targetPath, $quality)) {
                    continue;
                }

                if ($this->isWithinTargetSize($targetPath) || $quality === 58) {
                    return true;
                }
            }
        }

        if (($extension === 'jpg' || $extension === 'jpeg') && function_exists('imagejpeg')) {
            foreach ([85, 78, 70, 62] as $quality) {
                if (!@imagejpeg($image, $targetPath, $quality)) {
                    continue;
                }

                if ($this->isWithinTargetSize($targetPath) || $quality === 62) {
                    return true;
                }
            }
        }

        if ($extension === 'png' && function_exists('imagepng')) {
            foreach ([7, 8, 9] as $compression) {
                if (!@imagepng($image, $targetPath, $compression)) {
                    continue;
                }

                if ($this->isWithinTargetSize($targetPath) || $compression === 9) {
                    return true;
                }
            }
        }

        return false;
    }

    private function isWithinTargetSize(string $path): bool
    {
        clearstatcache(true, $path);
        $size = filesize($path);

        return is_int($size) && $size <= self::PHOTO_TARGET_MAX_BYTES;
    }

    private function canManageRecord(User $user, Person $ownerEntry): bool
    {
        $owner = $ownerEntry->getOwner();
        if (!$owner instanceof User) {
            return false;
        }

        return $this->normalizeEmail($owner->getEmail()) === $this->normalizeEmail($user->getEmail());
    }

    private function normalizeEmail(?string $email): string
    {
        return mb_strtolower(trim((string) $email));
    }
}
