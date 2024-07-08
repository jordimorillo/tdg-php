<?php

namespace Domain\Model;

class Code
{
    private string $className;
    private string $filePath;
    private string $content;

    public function __construct(string $className, string $filePath, string $content)
    {
        $this->className = $className;
        $this->filePath = $filePath;
        $this->content = $content;
    }

    public function getClassName(): string
    {
        return $this->className;
    }

    public function getFilePath(): string
    {
        return $this->filePath;
    }

    public function getContent(): string
    {
        return $this->content;
    }
}
