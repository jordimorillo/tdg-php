<?php

function loadEnvironment(): void
{
    $configPath = dirname(__DIR__, 3) . '/.tdg-php';
    if (file_exists($configPath)) {
        $configuration = file_get_contents($configPath);
        $lines = explode("\n", $configuration);
        foreach ($lines as $line) {
            $parts = explode('=', $line);
            if (count($parts) === 2) {
                $key = trim($parts[0]);
                $value = trim($parts[1]);
                putenv("$key=$value");
            }
        }
    }
}
