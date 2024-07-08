<?php

namespace Domain\Model;

class Test
{
    private string $filePath;
    private string $content;

    public function __construct(string $filePath, string $content)
    {
        $this->filePath = $filePath;
        $this->content = $content;
    }

    public function getFilePath(): string
    {
        return $this->filePath;
    }

    public function getContent(): string
    {
        return $this->content;
    }

    public function getRelatedNamespaces(): array
    {
        preg_match_all('/use\s+([a-zA-Z0-9_\\\\]+);/', $this->content, $matches);
        return $matches[1];
    }

    public function getNamespace(): ?string
    {
        if (preg_match('/namespace\s+([a-zA-Z0-9_\\\\]+)\s*;/', $this->content, $matches)) {
            return $matches[1];
        }
        return null;
    }
}
