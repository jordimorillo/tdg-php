<?php

namespace Application\Service;

use Infrastructure\Persistence\CodeRepository;
use Infrastructure\Persistence\TestRepository;

class TestService
{
    private $projectRoot;
    private $containerProjectRoot;
    private $phpunitXmlPath;
    private $baseNamespace;
    private $testsBaseNamespace;
    private $openaiApiKey;
    private $maxAttempts;
    private $dockerContainer;
    private $permanentAttachments;
    private $codeRepository;
    private $testRepository;

    public function __construct(
        $projectRoot,
        $containerProjectRoot,
        $phpunitXmlPath,
        $baseNamespace,
        $testsBaseNamespace,
        $openaiApiKey,
        $maxAttempts,
        $dockerContainer,
        $permanentAttachments,
        CodeRepository $codeRepository,
        TestRepository $testRepository
    ) {
        $this->projectRoot = $projectRoot;
        $this->containerProjectRoot = $containerProjectRoot;
        $this->phpunitXmlPath = $phpunitXmlPath;
        $this->baseNamespace = $baseNamespace;
        $this->testsBaseNamespace = $testsBaseNamespace;
        $this->openaiApiKey = $openaiApiKey;
        $this->maxAttempts = $maxAttempts;
        $this->dockerContainer = $dockerContainer;
        $this->permanentAttachments = $permanentAttachments;
        $this->codeRepository = $codeRepository;
        $this->testRepository = $testRepository;
    }

    public function run($phpTestPath): bool
    {
        $attempt = 0;

        while ($attempt < $this->maxAttempts) {
            $attempt++;
            echo "Intento $attempt de $this->maxAttempts\n";

            $testContent = $this->testRepository->getTestContent($phpTestPath);
            $attachmentsCode = $this->codeRepository->getAttachmentsCode($this->permanentAttachments, $this->projectRoot);
            $relatedCode = $this->codeRepository->getRelatedCode($testContent, $this->projectRoot, $this->baseNamespace, $this->testsBaseNamespace);
            $apiCallData = $this->generateApiCallData($relatedCode, $attachmentsCode, $testContent);
            $response = $this->callLlamaApi($apiCallData);

            if ($response === null) {
                echo "La solicitud a la API falló. Reintentando...\n";
                continue;
            }

            $code = $this->extractCode($response);
            $path = $this->extractTestedFilePath($testContent);
            $className = $this->getClassname($code);
            $filePath = $this->projectRoot . '/' . $path . '/' . $className . '.php';
            $containerFilePath = $this->containerProjectRoot . '/' . $path . '/' . $className . '.php';

            if (empty($code) || empty($path)) {
                echo "La respuesta de la API no contiene el código esperado. Reintentando...\n";
                continue;
            }

            $this->codeRepository->createFile($filePath, $code);

            $errorLogPath = $this->projectRoot . '/tmp/' . time() . '.log';

            if ($this->testRepository->runTests($this->dockerContainer, $phpTestPath, $this->phpunitXmlPath, $errorLogPath)) {
                echo "¡Implementación correcta y pruebas exitosas!\n";
                return true;
            } else {
                echo "Implementación fallida. Borrando archivo y reintentando...\n";
                $this->codeRepository->deleteFile($filePath, $containerFilePath);
            }
        }

        echo "No se pudo obtener una implementación que pase todas las pruebas después de $this->maxAttempts intentos.\n";
        return false;
    }

    private function generateApiCallData($relatedCode, $attachmentsCode, $testContent)
    {
        $testNamespace = '';
        if (preg_match('/namespace\s+([a-zA-Z0-9_\\\\]+)\s*;/', $testContent, $matches)) {
            $testNamespace = $matches[1];
        }

        return [
            'model' => 'llama3:latest',
            'prompt' => implode("\n\n", [
                "The following code is existing code in the project:",
                implode("\n\n", $relatedCode),
                implode("\n\n", $attachmentsCode),
                "Take as strict reference the following test:",
                "**$testNamespace**```php\n$testContent```\n\n"
                . "Develop the subject under test class to meet the conditions of the test and verify if they are met.\n"
            ]),
            'stream' => false,
            'temperature' => 0.9,
        ];
    }

    private function callLlamaApi($data)
    {
        $ch = curl_init('http://localhost:11434/api/generate'); // Cambia esta URL si tu endpoint es diferente
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json'
        ]);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));

        $response = curl_exec($ch);

        if (curl_errno($ch)) {
            echo "Error al hacer la solicitud HTTP: " . curl_error($ch) . "\n";
            return null;
        }

        curl_close($ch);
        return json_decode($response, true);
    }

    private function extractCode($response): string
    {
        if (preg_match('/```php\\n(.*)\\n```/s', $response['response'], $matches)) {
            $match = str_replace('<?php', '', $matches[1]);
            return '<?php' . "\n" . $match;
        } else {
            return '';
        }
    }

    private function getClassname($code)
    {
        $pattern = '/class\s+([a-zA-Z_][\w]*)/';
        preg_match($pattern, $code, $matches);
        return $matches[1];
    }

    private function extractTestedFilePath($testContent)
    {
        $testFilePath = '';

        if (preg_match('/namespace\s+([a-zA-Z0-9_\\\\]+)\s*;/', $testContent, $matches)) {
            $testFilePath = $matches[1];
            $testFilePath = str_replace('\\', '/', $testFilePath);
        }

        return 'src/' . $testFilePath;
    }
}
