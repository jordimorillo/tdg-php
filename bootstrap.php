<?php

if (file_exists(dirname(__DIR__, 3) . '/.tdg-php')) {
    $configuration = file_get_contents(dirname(__DIR__, 3) . '/.tdg-php');
    $lines = explode("\n", $configuration);
    foreach ($lines as $line) {
        $parts = explode('=', $line);
        if (count($parts) === 2) {
            $key = $parts[0];
            $value = $parts[1];
            putenv("$key=$value");
        }
    }
}

// Variables
$phpTestPath = $argv[1];
$projectRoot = dirname(__DIR__, 2);
$phpunitXmlPath = getenv('PHPUNIT_XML_PATH');
$baseNamespace = getenv('BASE_NAMESPACE');
$testsBaseNamespace = getenv('TESTS_BASE_NAMESPACE');
$apiKey = getenv('API_KEY');
$maxAttempts = getenv('MAX_ATTEMPTS');
$permanentAttachments = explode(',', getenv('PERMANENT_ATTACHMENTS'));

// Functions
function getTestContent($phpTestPath): false|string
{
    return file_get_contents($phpTestPath);
}

function getRelatedCode($testContent, $projectRoot, $baseNamespace, $testsBaseNamespace): array
{
    preg_match_all('/use\s+([a-zA-Z0-9_\\\\]+);/', $testContent, $matches);
    $relatedFiles = [];

    foreach ($matches[1] as $namespace) {
        $path = str_replace($testsBaseNamespace, 'tests', $namespace);
        $path = str_replace($baseNamespace, 'src', $path);
        $relativePath = str_replace('\\', '/', $path) . '.php';
        $filePath = $projectRoot . '/' . $relativePath;
        if (file_exists($filePath) && !str_contains($filePath, 'vendor')) {
            $relatedFiles[$relativePath] = "```php\n" . file_get_contents($filePath) . "\n```";
        }
    }

    return $relatedFiles;
}

function callLlamaApi($data)
{
    $ch = curl_init('http://localhost:11434/api/generate'); // Change this URL if your endpoint is different
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json'
    ]);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));

    $response = curl_exec($ch);

    if (curl_errno($ch)) {
        echo "Error making HTTP request: " . curl_error($ch) . "\n";
        return null;
    }

    curl_close($ch);
    return json_decode($response, true);
}

/**
 * @param $relatedCode
 * @param $attachmentsCode
 * @param $testContent
 * @return array
 */
function generateApiCallData(
    $relatedCode,
    $attachmentsCode,
    $testContent
): array {
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

function extractCode($response): string
{
    if (preg_match('/```php\\n(.*)\\n```/s', $response['response'], $matches)) {
        $match = str_replace('<?php', '', $matches[1]);
        return '<?php' . "\n" . $match;
    } else {
        return '';
    }
}

function getClassname($code): ?string
{
    $pattern = '/class\s+([a-zA-Z_][\w]*)\s*{/';
    if (preg_match($pattern, $code, $matches)) {
        return $matches[1];
    }
    return null;
}

function extractTestedFilePath($testContent): array|string
{
    $source = '';
    if (preg_match('/namespace\s+([a-zA-Z0-9_\\\\]+)\s*;/', $testContent, $matches)) {
        $testNamespace = $matches[1];
        $source = str_replace(getenv('TESTS_BASE_NAMESPACE'), getenv('SOURCE_DIRECTORY'), $testNamespace);
        $source = str_replace('\\', '/', $source);
    }
    return $source;
}

function createFile($filePath, $fileContent): void
{
    if (!file_exists(dirname($filePath))) {
        mkdir(dirname($filePath), 0777, true);
    }
    file_put_contents($filePath, $fileContent);
}

function runTests($phpTestPath, $phpunitXmlPath, $errorLogPath): bool
{
    $command = "php ./vendor/bin/phpunit --configuration=" . $phpunitXmlPath . " " . $phpTestPath;
    exec($command, $output, $returnVar);
    if ($returnVar !== 0) {
        $implode = implode("\n", $output);
        file_put_contents(
            $errorLogPath,
            "Test failure details:\n" . $implode
        );
    }
    return $returnVar === 0;
}

function deleteEmptyDirectories($path, $root): void
{
    while ($path !== $root && $path !== dirname($root)) {
        if (is_dir($path) && count(scandir($path)) == 2) {
            rmdir($path);
        }
        $path = dirname($path);
    }
}

// Main
$attempt = 0;
/**
 * @param array $permanentAttachments
 * @param string $projectRoot
 * @return array
 */
function getAttachmentsCode(array $permanentAttachments, string $projectRoot): array
{
    $attachmentsCode = [];
    foreach ($permanentAttachments as $attachment) {
        $attachment = str_replace('./', '', $attachment);
        if (file_exists($projectRoot . '/' . $attachment) === false) {
            continue;
        }
        $content = file_get_contents($projectRoot . '/' . $attachment);
        $attachmentsCode[] = "```\n$content\n```";
    }
    return $attachmentsCode;
}

while ($attempt < $maxAttempts) {
    $attempt++;
    echo "Attempt $attempt of $maxAttempts\n";

    $testContent = getTestContent($phpTestPath);
    $attachmentsCode = getAttachmentsCode($permanentAttachments, $projectRoot);
    $relatedCode = getRelatedCode($testContent, $projectRoot, $baseNamespace, $testsBaseNamespace);

    $apiCallData = generateApiCallData($relatedCode, $attachmentsCode, $testContent);

    $response = callLlamaApi($apiCallData);

    if (!$response) {
        echo "API call failed. Retrying...\n";
        continue;
    }

    $generatedCode = extractCode($response);

    if (empty($generatedCode)) {
        echo "No code generated. Retrying...\n";
        continue;
    }

    $classname = getClassname($generatedCode);
    $testedFilePath = extractTestedFilePath($testContent);

    if (!$classname) {
        echo "Failed to extract classname from generated code. Retrying...\n";
        continue;
    }

    $filePath = $projectRoot . '/' . $testedFilePath . '/' . $classname . '.php';
    createFile($filePath, $generatedCode);

    $errorLogPath = $projectRoot . '/logs/error.log';

    if (runTests($phpTestPath, $phpunitXmlPath, $errorLogPath)) {
        echo "Test passed!\n";
        break;
    } else {
        echo "Test failed. Check error log for details.\n";
        unlink($filePath);
        deleteEmptyDirectories($filePath, $projectRoot);
    }
}
