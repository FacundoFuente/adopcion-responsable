<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\PersonRepository;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

class HomeController extends AbstractController //definimos nuestro controlador
{
    private const PHOTO_ENTRY_PREFIX = '[FOTO] ';
    private const PHOTO_ENTRY_SEPARATOR = '|';

    #[Route('/', name: 'homepage')] //definimos la ruta del controlador
    public function homepage(): Response
    {
        return $this->render('homepage/homepage.html.twig', []);
        //return $this->render('homepage/404.html.twig', []);
    }

    #[Route('/mis-prontuarios', name: 'app_my_records', methods: ['GET'])]
    public function myRecords(Request $request, PersonRepository $personRepository): Response
    {
        $user = $this->getUser();

        if (!$user instanceof User) {
            return $this->redirectToRoute('app_sign_in');
        }

        $dniQuery = trim((string) $request->query->get('dni', ''));
        $dniFilter = null;

        if ($dniQuery !== '') {
            $normalizedDni = preg_replace('/\D+/', '', $dniQuery);
            if ($normalizedDni !== '') {
                $dniFilter = (int) $normalizedDni;
            }
        }

        $rows = $personRepository->findProntuariosSummaryByOwner($user, $dniFilter);

        $records = array_map(function (array $row) use ($personRepository, $user): array {
            $dni = (int) ($row['dni'] ?? 0);
            $lastEntryAt = $row['lastEntryAt'] ?? null;
            $latestEntry = $personRepository->findOneBy(
                ['owner' => $user, 'dni' => $dni],
                ['createdAt' => 'DESC', 'id' => 'DESC']
            );

            if (is_string($lastEntryAt)) {
                try {
                    $lastEntryAt = new \DateTimeImmutable($lastEntryAt);
                } catch (\Throwable) {
                    $lastEntryAt = null;
                }
            }

            return [
                'dni' => $dni,
                'entriesCount' => (int) ($row['entriesCount'] ?? 0),
                'lastEntryAt' => $lastEntryAt instanceof \DateTimeInterface
                    ? $lastEntryAt->format('d/m/Y H:i')
                    : null,
                'photoUrl' => $this->findPhotoUrlByDni($personRepository, $dni),
                'lastEntryText' => $this->extractEntryText(
                    $latestEntry instanceof \App\Entity\Person ? (string) $latestEntry->getDescription() : ''
                ),
            ];
        }, $rows);

        return $this->render('homepage/my-records.html.twig', [
            'records' => $records,
            'dniFilter' => $dniQuery,
        ]);
    }

    private function findPhotoUrlByDni(PersonRepository $personRepository, int $dni): ?string
    {
        $latestPhotoEntry = $personRepository->findLatestPhotoEntryByDni($dni);
        if ($latestPhotoEntry instanceof \App\Entity\Person) {
            $url = $this->extractPhotoUrlFromDescription((string) $latestPhotoEntry->getDescription());
            if ($url !== null) {
                return $url;
            }
        }

        return $this->findLegacyPhotoUrlByDni($dni);
    }

    private function extractPhotoUrlFromDescription(string $description): ?string
    {
        if (!str_starts_with($description, self::PHOTO_ENTRY_PREFIX)) {
            return null;
        }

        $payload = trim(substr($description, strlen(self::PHOTO_ENTRY_PREFIX)));
        $parts = explode(self::PHOTO_ENTRY_SEPARATOR, $payload, 2);

        if (count($parts) !== 2 || trim($parts[0]) === '') {
            return null;
        }

        $filename = basename(trim($parts[0]));
        $fullPath = rtrim($this->getParameter('kernel.project_dir').'/public/uploads/prontuarios', '/').'/'.$filename;
        if (!is_file($fullPath)) {
            return null;
        }

        return '/uploads/prontuarios/'.$filename;
    }

    private function extractEntryText(string $description): ?string
    {
        $description = trim($description);
        if ($description === '') {
            return null;
        }

        if (!str_starts_with($description, self::PHOTO_ENTRY_PREFIX)) {
            return $description;
        }

        $payload = trim(substr($description, strlen(self::PHOTO_ENTRY_PREFIX)));
        $parts = explode(self::PHOTO_ENTRY_SEPARATOR, $payload, 2);

        if (count($parts) === 2) {
            $photoDescription = trim($parts[1]);
            return $photoDescription !== '' ? $photoDescription : 'Se agregó la foto del prontuario';
        }

        return $payload !== '' ? $payload : 'Se agregó la foto del prontuario';
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
}
