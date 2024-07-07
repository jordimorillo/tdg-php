<?php

namespace Console;

use Application\Service\CodeGenerationService;
use Application\Service\FileService;
use Application\Service\TestService;
use Config;
use Infrastructure\Http\LlamaApiClient;
use Infrastructure\Persistence\CodeRepository;
use Infrastructure\Persistence\TestRepository;

class PairFriendCommand
{
    private string $phpTestPath;
    private CodeGenerationService $codeGenerationService;
    private FileService $fileService;
    private TestService $testService;

    public function __construct(string $phpTestPath)
    {
        Config::load();
        $this->phpTestPath = $phpTestPath;
        $this->codeGenerationService = new CodeGenerationService(new LlamaApiClient());
        $this->fileService = new FileService();
        $this->testService = new TestService(new CodeRepository(), new TestRepository());
    }

    public function execute(): void
    {
        $attempt = 0;
        $maxAttempts = (int)getenv('MAX_ATTEMPTS');

        while ($attempt < $maxAttempts) {
            $attempt++;
            echo "Intento $attempt de $maxAttempts\n";

            $testContent = $this->fileService->getFileContent($this->phpTestPath);
            $attachmentsCode = $this->fileService->getAttachmentsCode();
            $relatedCode = $this->testService->getRelatedCode($testContent);

            $apiCallData = $this->codeGenerationService->generateApiCallData($relatedCode, $attachmentsCode, $testContent);
            $response = $this->codeGenerationService->callLlamaApi($apiCallData);

            if ($response === null) {
                echo "La solicitud a la API falló. Reintentando...\n";
                continue;
            }

            $code = $this->codeGenerationService->extractCode($response);
            $path = $this->testService->extractTestedFilePath($testContent);
            $className = $this->testService->getClassname($code);
            $filePath = $this->fileService->createFilePath($path, $className);

            if (empty($code) || empty($path)) {
                echo "La respuesta de la API no contiene el código esperado. Reintentando...\n";
                $this->codeGenerationService->debugOutput($response, $code, $path, $apiCallData);
                continue;
            }

            $this->fileService->createFile($filePath, $code);

            $errorLogPath = $this->fileService->createErrorLogPath();

            if ($this->testService->runTests($this->phpTestPath, $errorLogPath)) {
                echo "¡Implementación correcta y pruebas exitosas!\n";
                exit(0);
            } else {
                echo "Implementación fallida. Borrando archivo y reintentando...\n";
                $this->fileService->logError($errorLogPath, $filePath, $code);
                unlink($filePath);
                $this->testService->deleteFileFromContainer($filePath);
                $this->fileService->deleteEmptyDirectories(dirname($filePath));
            }
        }

        echo "No se pudo obtener una implementación que pase todas las pruebas después de $maxAttempts intentos.\n";
        exit(1);
    }
}
