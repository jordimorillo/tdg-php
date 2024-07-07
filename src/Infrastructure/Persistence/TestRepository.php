<?php

namespace Infrastructure\Persistence;

class TestRepository
{
    public function getTestContent(string $phpTestPath): string
    {
        return file_get_contents($phpTestPath);
    }

    public function runTests(string $dockerContainer, string $phpTestPath, string $phpunitXmlPath, string $errorLogPath): bool
    {
        $command = sprintf(
            'docker exec %s vendor/bin/phpunit --configuration %s %s 2> %s',
            escapeshellarg($dockerContainer),
            escapeshellarg($phpunitXmlPath),
            escapeshellarg($phpTestPath),
            escapeshellarg($errorLogPath)
        );
        exec($command, $output, $returnVar);

        return $returnVar === 0;
    }
}
