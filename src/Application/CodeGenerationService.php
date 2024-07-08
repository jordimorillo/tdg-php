<?php

namespace Application;

use Domain\Model\Code;
use Domain\Model\Test;
use Infrastructure\Api\LlamaApiClient;
use Infrastructure\File\FileManager;
use Infrastructure\File\TestFileRepository;
use Infrastructure\File\AttachmentRepository;

class CodeGenerationService
{
    private TestFileRepository $testFileRepository;
    private AttachmentRepository $attachmentRepository;
    private LlamaApiClient $llamaApiClient;
    private FileManager $fileManager;

    public function __construct(
        TestFileRepository $testFileRepository,
        AttachmentRepository $attachmentRepository,
        LlamaApiClient $llamaApiClient,
        FileManager $fileManager
    ) {
        $this->testFileRepository = $testFileRepository;
        $this->attachmentRepository = $attachmentRepository;
        $this->llamaApiClient = $llamaApiClient;
        $this->fileManager = $fileManager;
    }

    public function generate(string $testFilePath, string $projectRoot, string $phpunitXmlPath, string $errorLogPath): bool
    {
        $testContent = $this->testFileRepository->getContent($testFilePath);
        $test = new Test($testFilePath, $testContent);

        $attachmentsCode = $this->attachmentRepository->getCode();
        $relatedCode = $this->testFileRepository->getRelatedCode($test->getContent());

        $apiCallData = $this->generateApiCallData($relatedCode, $attachmentsCode, $test);
        $response = $this->llamaApiClient->call($apiCallData);

        if (!$response) {
            return false;
        }

        $generatedCode = $this->extractCode($response);
        if (empty($generatedCode)) {
            return false;
        }

        $className = $this->getClassName($generatedCode);
        if (!$className) {
            return false;
        }

        $testedFilePath = $this->extractTestedFilePath($test);
        $filePath = $projectRoot . $testedFilePath . '/' . $className . '.php';
        $code = new Code($className, $filePath, $generatedCode);

        $this->fileManager->createFile($code->getFilePath(), $code->getContent());

        if ($this->runTests($test->getFilePath(), $phpunitXmlPath, $errorLogPath)) {
            return true;
        } else {
            $this->fileManager->deleteFile($code->getFilePath());
            $this->fileManager->deleteEmptyDirectories($code->getFilePath(), $projectRoot);
            return false;
        }
    }

    private function generateApiCallData(array $relatedCode, array $attachmentsCode, Test $test): array
    {
        $testNamespace = $test->getNamespace() ?? '';

        return [
            'model' => 'llama3:latest',
            'prompt' => implode("\n\n", [
                "The following code is existing code in the project:",
                implode("\n\n", $relatedCode),
                implode("\n\n", $attachmentsCode),
                "Take as strict reference the following test:",
                "**$testNamespace**\n```php\n{$test->getContent()}\n```\n\n"
                . "Develop the subject under test class to meet all the conditions of the test and verify if they are met. The use of composer extensions is prohibited.\n"
            ]),
            'stream' => false,
            'temperature' => 0.9,
        ];
    }

    private function extractCode(array $response): string
    {
        if (preg_match('/```php\\n(.*)\\n```/s', $response['response'], $matches)) {
            $match = str_replace('<?php', '', $matches[1]);
            return '<?php' . "\n" . $match;
        } else {
            return '';
        }
    }

    private function getClassName(string $code): ?string
    {
        if (preg_match('/class\s+([a-zA-Z_][\w]*)\s*{/', $code, $matches)) {
            return $matches[1];
        }
        return null;
    }

    private function extractTestedFilePath(Test $test): string
    {
        $testNamespace = $test->getNamespace();
        if ($testNamespace) {
            $source = str_replace(getenv('TESTS_BASE_NAMESPACE'), getenv('SOURCE_DIRECTORY'), $testNamespace);
            $source = str_replace('\\', '/', $source);
            return getenv('PROJECT_ROOT') . '/' . $source;
        }
        return '';
    }

    private function runTests(string $phpTestPath, string $phpunitXmlPath, string $errorLogPath): bool
    {
        $command = "php ./vendor/bin/phpunit --configuration=" . $phpunitXmlPath . " " . $phpTestPath;
        exec($command, $output, $returnVar);
        if ($returnVar !== 0) {
            file_put_contents($errorLogPath, "Test failure details:\n" . implode("\n", $output));
        }
        return $returnVar === 0;
    }
}
