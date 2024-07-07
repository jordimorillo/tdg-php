<?php

namespace Infrastructure\Persistence;

class CodeRepository
{
    public function getAttachmentsCode(array $attachments, string $projectRoot): array
    {
        $code = [];
        foreach ($attachments as $attachment) {
            $filePath = $projectRoot . '/' . $attachment;
            if (file_exists($filePath)) {
                $code[] = file_get_contents($filePath);
            }
        }
        return $code;
    }

    public function getRelatedCode(string $testContent, string $projectRoot, string $baseNamespace, string $testsBaseNamespace): array
    {
        $relatedFiles = $this->findRelatedFiles($testContent, $projectRoot, $baseNamespace, $testsBaseNamespace);
        $code = [];
        foreach ($relatedFiles as $file) {
            if (file_exists($file)) {
                $code[] = file_get_contents($file);
            }
        }
        return $code;
    }

    public function createFile(string $filePath, string $code): void
    {
        file_put_contents($filePath, $code);
    }

    public function deleteFile(string $filePath, string $containerFilePath): void
    {
        unlink($filePath);
        if (file_exists($containerFilePath)) {
            unlink($containerFilePath);
        }
    }

    private function findRelatedFiles(string $testContent, string $projectRoot, string $baseNamespace, string $testsBaseNamespace): array
    {
        // Implementa la lógica para encontrar archivos relacionados
        return [];
    }
}
