<?php

// Load environment variables from configuration file
loadEnvironment();

// Main execution loop
$attempt = 0;
while ($attempt < getenv('MAX_ATTEMPTS')) {
    $attempt++;
    echo "Attempt $attempt of " . getenv('MAX_ATTEMPTS') . "\n";

    $testContent = getTestContent($argv[1]);
    echo "Test content retrieved from file: $argv[1]\n";

    $attachmentsCode = getAttachmentsCode(explode(',', getenv('PERMANENT_ATTACHMENTS')));
    echo "Attachments code retrieved:\n";
    print_r($attachmentsCode);

    $relatedCode = getRelatedCode($testContent);
    echo "Related code files found:\n";
    print_r(array_keys($relatedCode));

    $apiCallData = generateApiCallData($relatedCode, $attachmentsCode, $testContent);
    echo "API call data generated:\n";
    print_r($apiCallData);

    $response = callLlamaApi($apiCallData);

    if (!$response) {
        echo "API call failed. Retrying...\n";
        continue;
    }

    $generatedCode = extractCode($response);
    echo "Code extracted from API response:\n";
    echo "$generatedCode\n";

    if (empty($generatedCode)) {
        echo "No code generated. Retrying...\n";
        continue;
    }

    $className = getClassname($generatedCode);
    echo "Class name extracted from generated code: $className\n";

    $testedFilePath = extractTestedFilePath($testContent);
    echo "Tested file path extracted: $testedFilePath\n";

    if (!$className) {
        echo "Failed to extract classname from generated code. Retrying...\n";
        continue;
    }

    $filePath = dirname(__DIR__, 3) . $testedFilePath . '/' . $className . '.php';
    echo "File path for generated code: $filePath\n";

    createFile($filePath, $generatedCode);
    echo "Generated file created successfully.\n";

    $phpunitXmlPath = dirname(__DIR__, 3) . '/' . getenv('PHPUNIT_XML_PATH');
    $errorLogPath = dirname(__DIR__, 3) . '/logs/error.log';
    if (runTests($argv[1], $phpunitXmlPath, $errorLogPath)) {
        echo "Test passed!\n";
        break;
    } else {
        echo "Test failed. Check error log for details.\n";
        unlink($filePath);
        deleteEmptyDirectories($filePath, dirname(__DIR__, 3));
    }
}

/**
 * Loads environment variables from configuration file.
 */
function loadEnvironment(): void
{
    $configPath = dirname(__DIR__, 3) . '/.tdg-php'; // Adjusted path relative to bootstrap.php location
    echo "Loading environment from config file: $configPath\n";
    if (file_exists($configPath)) {
        $configuration = file_get_contents($configPath);
        $lines = explode("\n", $configuration);
        foreach ($lines as $line) {
            $parts = explode('=', $line);
            if (count($parts) === 2) {
                $key = $parts[0];
                $value = $parts[1];
                putenv("$key=$value");
                echo "Setting environment variable: $key=$value\n";
            }
        }
    }
}

/**
 * Retrieves the content of a PHP test file.
 *
 * @param string $phpTestPath
 * @return false|string
 */
function getTestContent(string $phpTestPath): bool|string
{
    echo "Retrieving test content from: $phpTestPath\n";
    return file_get_contents($phpTestPath);
}

/**
 * Retrieves related code files based on test content.
 *
 * @param string $testContent
 * @return array
 */
function getRelatedCode(string $testContent): array
{
    echo "Finding related code based on test content...\n";
    preg_match_all('/use\s+([a-zA-Z0-9_\\\\]+);/', $testContent, $matches);
    $relatedFiles = [];

    foreach ($matches[1] as $namespace) {
        $path = str_replace(getenv('TESTS_BASE_NAMESPACE'), 'tests', $namespace);
        $path = str_replace(getenv('BASE_NAMESPACE'), 'src', $path);
        $relativePath = str_replace('\\', '/', $path) . '.php';
        $filePath = getenv('PROJECT_ROOT') . '/' . $relativePath;
        if (file_exists($filePath) && !str_contains($filePath, 'vendor')) {
            $relatedFiles[$relativePath] = file_get_contents($filePath);
            echo "Related file found: $relativePath\n";
        }
    }

    return $relatedFiles;
}

/**
 * Retrieves attachment code from permanent attachments.
 *
 * @param array $permanentAttachments
 * @return array
 */
function getAttachmentsCode(array $permanentAttachments): array
{
    echo "Retrieving attachment code from permanent attachments...\n";
    $attachmentsCode = [];
    foreach ($permanentAttachments as $attachment) {
        $attachmentPath = getenv('PROJECT_ROOT') . '/' . str_replace('./', '', $attachment);
        if (file_exists($attachmentPath)) {
            $content = file_get_contents($attachmentPath);
            $attachmentsCode[] = $content;
            echo "Attachment file found: $attachmentPath\n";
        }
    }
    return $attachmentsCode;
}

/**
 * Calls the LLAMA API with provided data.
 *
 * @param array $data
 * @return mixed
 */
function callLlamaApi(array $data): mixed
{
    echo "Calling LLAMA API with data:\n";
    print_r($data);

    $ch = curl_init('http://localhost:11434/api/generate');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));

    $response = curl_exec($ch);

    if (curl_errno($ch)) {
        echo "Error making HTTP request: " . curl_error($ch) . "\n";
        return null;
    }

    curl_close($ch);
    echo "API response received:\n";
    print_r($response);

    return json_decode($response, true);
}

/**
 * Generates data for API call based on related and attachment code.
 *
 * @param array $relatedCode
 * @param array $attachmentsCode
 * @param string $testContent
 * @return array
 */
function generateApiCallData(array $relatedCode, array $attachmentsCode, string $testContent): array
{
    echo "Generating API call data...\n";
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

/**
 * Extracts PHP code from API response.
 *
 * @param array $response
 * @return string
 */
function extractCode(array $response): string
{
    echo "Extracting code from API response...\n";
    if (preg_match('/```php\\n(.*)\\n```/s', $response['response'], $matches)) {
        $match = str_replace('<?php', '', $matches[1]);
        return '<?php' . "\n" . $match;
    } else {
        return '';
    }
}

/**
 * Extracts class name from generated PHP code.
 *
 * @param string $code
 * @return string|null
 */
function getClassname(string $code): ?string
{
    echo "Extracting class name from generated code...\n";
    $pattern = '/class\s+([a-zA-Z_][\w]*)\s*{/';
    if (preg_match($pattern, $code, $matches)) {
        return $matches[1];
    }
    return null;
}

/**
 * Extracts tested file path from test content.
 *
 * @param string $testContent
 * @return string
 */
function extractTestedFilePath(string $testContent): string
{
    echo "Extracting tested file path from test content...\n";
    if (preg_match('/namespace\s+([a-zA-Z0-9_\\\\]+)\s*;/', $testContent, $matches)) {
        $testNamespace = $matches[1];
        $source = str_replace(getenv('TESTS_BASE_NAMESPACE'), getenv('SOURCE_DIRECTORY'), $testNamespace);
        $source = str_replace('\\', '/', $source);
        return getenv('PROJECT_ROOT') . '/' . $source;
    }
    return '';
}

/**
 * Creates a file with given content at specified path.
 *
 * @param string $filePath
 * @param string $fileContent
 */
function createFile(string $filePath, string $fileContent): void
{
    echo "Creating file: $filePath\n";
    if (!file_exists(dirname($filePath))) {
        mkdir(dirname($filePath), 0777, true);
    }
    file_put_contents($filePath, $fileContent);
}

/**
 * Runs PHPUnit tests.
 *
 * @param string $phpTestPath
 * @param string $phpunitXmlPath
 * @param string $errorLogPath
 * @return bool
 */
function runTests(string $phpTestPath, string $phpunitXmlPath, string $errorLogPath): bool
{
    echo "Running PHPUnit tests...\n";
    $command = "php ./vendor/bin/phpunit --configuration=" . $phpunitXmlPath . " " . $phpTestPath;
    exec($command, $output, $returnVar);
    if ($returnVar !== 0) {
        file_put_contents($errorLogPath, "Test failure details:\n" . implode("\n", $output));
    }
    return $returnVar === 0;
}

/**
 * Deletes empty directories recursively up to project root.
 *
 * @param string $path
 * @param string $root
 */
function deleteEmptyDirectories(string $path, string $root): void
{
    echo "Deleting empty directories up to project root...\n";
    while ($path !== $root && $path !== dirname($root)) {
        if (is_dir($path) && count(scandir($path)) == 2) {
            rmdir($path);
            echo "Empty directory deleted: $path\n";
        }
        $path = dirname($path);
    }
}
