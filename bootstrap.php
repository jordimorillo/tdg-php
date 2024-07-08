<?php

require_once 'config.php';
require_once 'src/Application/CodeGenerationService.php';
require_once 'src/Infrastructure/Api/LlamaApiClient.php';
require_once 'src/Infrastructure/File/FileManager.php';
require_once 'src/Infrastructure/File/TestFileRepository.php';
require_once 'src/Infrastructure/File/AttachmentRepository.php';

use Application\CodeGenerationService;
use Infrastructure\Api\LlamaApiClient;
use Infrastructure\File\FileManager;
use Infrastructure\File\TestFileRepository;
use Infrastructure\File\AttachmentRepository;

// Cargar variables de entorno
loadEnvironment();

// Crear dependencias
$fileManager = new FileManager();
$testFileRepository = new TestFileRepository($fileManager);
$attachmentRepository = new AttachmentRepository($fileManager);
$llamaApiClient = new LlamaApiClient();

// Crear servicio de generación de código
$codeGenerationService = new CodeGenerationService(
    $testFileRepository,
    $attachmentRepository,
    $llamaApiClient,
    $fileManager
);

// Ejecutar el servicio de generación de código
$attempt = 0;
$maxAttempts = getenv('MAX_ATTEMPTS');
$testFilePath = $argv[1];
$projectRoot = dirname(__DIR__, 3);
$phpunitXmlPath = $projectRoot . '/' . getenv('PHPUNIT_XML_PATH');
$errorLogPath = $projectRoot . '/logs/error.log';

while ($attempt < $maxAttempts) {
    $attempt++;
    echo "Attempt $attempt of $maxAttempts\n";

    if ($codeGenerationService->generate($testFilePath, $projectRoot, $phpunitXmlPath, $errorLogPath)) {
        echo "Test passed!\n";
        break;
    } else {
        echo "Test failed. Retrying...\n";
    }
}
