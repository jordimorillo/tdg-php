<?php

namespace Infrastructure\File;

class TestFileRepository
{
    private FileManager $fileManager;

    public function __construct(FileManager $fileManager)
    {
        $this->fileManager = $fileManager;
    }

    public function getContent(string $testFilePath)
    {
        return file_get_contents($testFilePath);
    }

    public function getRelatedCode(string $testContent): array
    {
        preg_match_all('/use\s+([a-zA-Z0-9_\\\\]+);/', $testContent, $matches);
        $relatedFiles = [];

        foreach ($matches[1] as $namespace) {
            $path = str_replace(getenv('TESTS_BASE_NAMESPACE'), 'tests', $namespace);
            $path = str_replace(getenv('BASE_NAMESPACE'), 'src', $path);
            $relativePath = str_replace('\\', '/', $path) . '.php';
            $filePath = getenv('PROJECT_ROOT') . '/' . $relativePath;
            if (file_exists($filePath) && !str_contains($filePath, 'vendor')) {
                $relatedFiles[$relativePath] = file_get_contents($filePath);
            }
        }

        return $relatedFiles;
    }
}
