<?php

namespace App\Service;

use Psr\Log\LoggerInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class FileExcelService
{
    const UPLOAD_FILE = "/var/uploads";
    private LoggerInterface $logger;
    private string $projectDir;

    public function __construct(LoggerInterface $logger, string $projectDir)
    {
        $this->logger = $logger;
        $this->projectDir = $projectDir;
    }

    public function getUploadDirectory(): string
    {
        return $this->projectDir . self::UPLOAD_FILE;
    }

    public function saveUploadedFile(UploadedFile $file): ?string
    {
        $filesystem = new Filesystem();
        if (!$filesystem->exists($this->getUploadDirectory())) {
            $filesystem->mkdir($this->getUploadDirectory());
        }

        try {
            $filePath =
                $this->getUploadDirectory() .
                "/" .
                uniqid() .
                "-" .
                $file->getClientOriginalName();
            $file->move($this->getUploadDirectory(), basename($filePath));

            return $filePath;
        } catch (\Exception $e) {
            $this->logger->error(
                "Erreur lors de l'enregistrement du fichier : {$e->getMessage()}"
            );

            return null;
        }
    }
}
