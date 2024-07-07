<?php

require __DIR__ . '/vendor/autoload.php';

use Application\Service\TestService;
use Infrastructure\Persistence\CodeRepository;
use Infrastructure\Persistence\TestRepository;

$projectRoot = dirname(__DIR__);
$containerProjectRoot = getenv('CONTAINER_PROJECT_ROOT');
$phpunitXmlPath = getenv('PHPUNIT_XML_PATH');
$baseNamespace = getenv('BASE_NAMESPACE');
$testsBaseNamespace = getenv('TESTS_BASE_NAMESPACE');
$openaiApiKey = getenv('OPENAI_API_KEY');
$maxAttempts = getenv('MAX_ATTEMPTS');
$dockerContainer = getenv('PHP_DOCKER_CONTAINER_NAME');
$permanentAttachments = explode(',', getenv('PERMANENT_ATTACHMENTS'));

$testService = new TestService(
    $projectRoot,
    $containerProjectRoot,
    $phpunitXmlPath,
    $baseNamespace,
    $testsBaseNamespace,
    $openaiApiKey,
    $maxAttempts,
    $dockerContainer,
    $permanentAttachments,
    new CodeRepository(),
    new TestRepository()
);

$phpTestPath = $argv[1];
$result = $testService->run($phpTestPath);

exit($result ? 0 : 1);
