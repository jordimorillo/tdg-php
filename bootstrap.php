<?php

define('DEBUG', true); // Cambia a false para desactivar el modo debug

// Load environment variables from configuration file
loadEnvironment();

// Main execution loop
$attempt = 0;
while ($attempt < getenv('MAX_ATTEMPTS')) {
    $attempt++;
    debug("Attempt $attempt of " . getenv('MAX_ATTEMPTS'));

    $testContent = getTestContent($argv[1]);
    debug("Test content retrieved from file: $argv[1]");

    $attachmentsCode = getAttachmentsCode(explode(',', getenv('PERMANENT_ATTACHMENTS')));
    debug("Attachments code retrieved:", $attachmentsCode);

    $relatedCode = getRelatedCode($testContent);
    debug("Related code files found:", array_keys($relatedCode));

    $apiCallData = generateApiCallData($relatedCode, $attachmentsCode, $testContent);
    debug("API call data generated:", $apiCallData);

    $response = callLlamaApi($apiCallData);

    if (!$response) {
        debug("API call failed. Retrying...");
        continue;
    }

    $generatedCode = extractCode($response);
    debug("Code extracted from API response:", $generatedCode);

    if (empty($generatedCode)) {
        debug("No code generated. Retrying...");
        continue;
    }

    $className = getClassname($generatedCode);
    debug("Class name extracted from generated code: $className");

    $testedFilePath = extractTestedFilePath($testContent);
    debug("Tested file path extracted: $testedFilePath");

    if (!$className) {
        debug("Failed to extract classname from generated code. Retrying...");
        continue;
    }

    $projectRoot = dirname(__DIR__, 3);
    $filePath = $projectRoot . $testedFilePath . '/' . $className . '.php';
    debug("File path for generated code: $filePath");

    createFile($filePath, $generatedCode);
    debug("Generated file created successfully.");

    $phpunitXmlPath = $projectRoot . '/' . getenv('PHPUNIT_XML_PATH');
    $errorLogPath = $projectRoot . '/logs/error.log';
    if (runTests($argv[1], $phpunitXmlPath, $errorLogPath)) {
        debug("Test passed!");
        break;
    } else {
        debug("Test failed. Check error log for details.");
        unlink($filePath);
        deleteEmptyDirectories($filePath, $projectRoot);
    }
}

/**
 * Debugging function to print messages if DEBUG is true.
 */
function debug($message, $data = null): void
{
    if (DEBUG) {
        if (is_array($data)) {
            echo $message . "\n";
            print_r($data);
        } else {
            echo $message . ($data ? ": $data" : "") . "\n";
        }
    }
}

/**
 * Loads environment variables from configuration file.
 */
function loadEnvironment(): void
{
    $configPath = dirname(__DIR__, 3) . '/.tdg-php'; // Adjusted path relative to bootstrap.php location
    debug("Loading environment from config file: $configPath");
    if (file_exists($configPath)) {
        $configuration = file_get_contents($configPath);
        $lines = explode("\n", $configuration);
        foreach ($lines as $line) {
            $parts = explode('=', $line);
            if (count($parts) === 2) {
                $key = $parts[0];
                $value = $parts[1];
                putenv("$key=$value");
                debug("Setting environment variable: $key=$value");
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
function getTestContent(string $phpTestPath)
{
    debug("Retrieving test content from: $phpTestPath");
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
    debug("Finding related code based on test content...");
    preg_match_all('/use\s+([a-zA-Z0-9_\\\\]+);/', $testContent, $matches);
    $relatedFiles = [];

    foreach ($matches[1] as $namespace) {
        $path = str_replace(getenv('TESTS_BASE_NAMESPACE'), 'tests', $namespace);
        $path = str_replace(getenv('BASE_NAMESPACE'), 'src', $path);
        $relativePath = str_replace('\\', '/', $path) . '.php';
        $filePath = getenv('PROJECT_ROOT') . '/' . $relativePath;
        if (file_exists($filePath) && !str_contains($filePath, 'vendor')) {
            $relatedFiles[$relativePath] = file_get_contents($filePath);
            debug("Related file found: $relativePath");
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
    debug("Retrieving attachment code from permanent attachments...");
    $attachmentsCode = [];
    foreach ($permanentAttachments as $attachment) {
        $attachmentPath = getenv('PROJECT_ROOT') . '/' . str_replace('./', '', $attachment);
        if (file_exists($attachmentPath)) {
            $content = file_get_contents($attachmentPath);
            $attachmentsCode[] = $content;
            debug("Attachment file found: $attachmentPath");
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
function callLlamaApi(array $data)
{
    debug("Calling LLAMA API with data:", $data);

    $ch = curl_init('http://localhost:11434/api/generate');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));

    $response = curl_exec($ch);

    if (curl_errno($ch)) {
        debug("Error making HTTP request: " . curl_error($ch));
        return null;
    }

    curl_close($ch);
    debug("API response received:", $response);

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
    debug("Generating API call data...");
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
            "**$testNamespace**\n```php\n$testContent\n```\n\n"
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
    debug("Extracting code from API response...");
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
    debug("Extracting class name from generated code...");
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
    debug("Extracting tested file path from test content...");
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
    debug("Creating file: $filePath");
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
    debug("Running PHPUnit tests...");
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
    debug("Deleting empty directories up to project root...");
    while ($path !== $root && $path !== dirname($root)) {
        if (is_dir($path) && count(scandir($path)) == 2) {
            rmdir($path);
            debug("Empty directory deleted: $path");
        }
        $path = dirname($path);
    }
}
