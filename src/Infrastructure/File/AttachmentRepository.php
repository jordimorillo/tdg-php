<?php

namespace Infrastructure\File;

class AttachmentRepository
{
    private FileManager $fileManager;

    public function __construct(FileManager $fileManager)
    {
        $this->fileManager = $fileManager;
    }

    public function getCode(): array
    {
        $attachmentsCode = [];
        foreach (explode(',', getenv('PERMANENT_ATTACHMENTS')) as $attachment) {
            $attachmentPath = getenv('PROJECT_ROOT') . '/' . str_replace('./', '', $attachment);
            if (file_exists($attachmentPath)) {
                $content = file_get_contents($attachmentPath);
                $attachmentsCode[] = $content;
            }
        }
        return $attachmentsCode;
    }
}
