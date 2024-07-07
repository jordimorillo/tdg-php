<?php

namespace Domain\Repository;

interface TestRepositoryInterface
{
    public function extractClassname(string $code): ?string;

    public function findTestedFilePath(string $testContent): string;

    public function executeTests(string $phpTestPath, string $errorLogPath): bool;

    public function removeFileFromContainer(string $filePath): void;
}
