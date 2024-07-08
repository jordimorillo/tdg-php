<?php

namespace Infrastructure\File;

class FileManager
{
    public function createFile(string $filePath, string $fileContent): void
    {
        if (!file_exists(dirname($filePath))) {
            mkdir(dirname($filePath), 0777, true);
        }
        file_put_contents($filePath, $fileContent);
    }

    public function deleteFile(string $filePath): void
    {
        if (file_exists($filePath)) {
            unlink($filePath);
        }
    }

    public function deleteEmptyDirectories(string $path, string $root): void
    {
        while ($path !== $root && $path !== dirname($root)) {
            if (is_dir($path) && count(scandir($path)) == 2) {
                rmdir($path);
            }
            $path = dirname($path);
        }
    }
}
