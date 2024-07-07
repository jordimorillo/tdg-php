<?php

namespace Application\Service;

use Infrastructure\Http\LlamaApiClient;

class CodeGenerationService
{
    private LlamaApiClient $apiClient;

    public function __construct(LlamaApiClient $apiClient)
    {
        $this->apiClient = $apiClient;
    }

    public function generateApiCallData(array $relatedCode, array $attachmentsCode, string $testContent): array
    {
        $testNamespace = $this->extractNamespace($testContent);

        return [
            'model' => 'llama3:latest',
            'prompt' => implode("\n\n", [
                "The following code is existing code in the project:",
                implode("\n\n", $relatedCode),
                implode("\n\n", $attachmentsCode),
                "Take as strict reference the following test:",
                "**$testNamespace**```php\n$testContent```\n\n" . "Develop the subject under test class to meet the conditions of the test and verify if they are met.\n"
            ]),
            'stream' => false,
            'temperature' => 0.9,
        ];
    }

    public function callLlamaApi(array $data): ?array
    {
        return $this->apiClient->sendRequest($data);
    }

    public function extractCode(array $response): string
    {
        if (preg_match('/```php\\n(.*)\\n```/s', $response['response'], $matches)) {
            $match = str_replace('<?php', '', $matches[1]);
            return '<?php' . "\n" . $match;
        } else {
            return '';
        }
    }

    public function extractNamespace(string $content): string
    {
        if (preg_match('/namespace\s+([a-zA-Z0-9_\\\\]+)\s*;/', $content, $matches)) {
            return $matches[1];
        }
        return '';
    }

    public function debugOutput(array $response, string $code, string $path, array $apiCallData): void
    {
        if (getenv('DEBUG') === 'true') {
            echo "Response: " . json_encode($response) . "\n";
            echo "Code: " . $code . "\n";
            echo "Path: " . $path . "\n";
            echo "ApiCallData: " . json_encode($apiCallData) . "\n";
        }
    }
}
