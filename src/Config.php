<?php

class Config
{
    public static function load(): void
    {
        $configFilePath = dirname(__DIR__, 2) . '/.pair-friend';
        if (file_exists($configFilePath)) {
            $configuration = file_get_contents($configFilePath);
            $lines = explode("\n", $configuration);
            foreach ($lines as $line) {
                $parts = explode('=', $line);
                if (count($parts) === 2) {
                    putenv("$parts[0]=$parts[1]");
                }
            }
        }
    }
}
