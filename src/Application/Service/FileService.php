<?php

namespace Application\Service;

class FileService
{
    private string $projectRoot;

    public function __construct()
    {
        $this->projectRoot = dirname(__DIR__, 2);
    }

    public function getFileContent(string $path): false|string
    {
        return file_get_contents($path);
    }

    public function getAttachmentsCode(): array
    {
        $attachmentsCode = [];
        $permanentAttachments = explode(',', getenv('PERMANENT_ATTACHMENTS'));

        foreach ($permanentAttachments as $attachment) {
            $attachment = str_replace('./', '', $attachment);
            $attachmentPath = $this->projectRoot . '/' . $attachment;
            if (!file_exists($attachmentPath)) {
                continue;
            }
            $content = file_get_contents($attachmentPath);
            $attachmentsCode[] = "```\n$content\n```";
        }
        return $attachmentsCode;
    }

    public function createFilePath(string $path, string $className): string
    {
        return $this->projectRoot . '/' . $path . '/' . $className . '.php';
    }

    public function createFile(string $filePath, string $content): void
    {
        if (!file_exists(dirname($filePath))) {
            mkdir(dirname($filePath), 0777, true);
        }
        file_put_contents($filePath, $content);
    }

    public function createErrorLogPath(): string
    {
        return dirname(__DIR__, 2) . '/tmp/' . time() . '.log';
    }

    public function deleteEmptyDirectories(string $path): void
    {
        while ($path !== $this->projectRoot && $path !== dirname($this->projectRoot)) {
            if (is_dir($path) && count(scandir($path)) == 2) {
                rmdir($path);
            }
            $path = dirname($path);
        }
    }

    public function logError(string $errorLogPath, string $filePath, string $code): void
    {
        if (!file_exists($errorLogPath)) {
            touch($errorLogPath);
        }
        $errorLog = file_get_contents($errorLogPath);
        $errorLog .= 'filePath: ' . $filePath . "\n";
        $errorLog .= 'proposed code: ' . $code . "\n";
        file_put_contents($errorLogPath, $errorLog);
    }
}
