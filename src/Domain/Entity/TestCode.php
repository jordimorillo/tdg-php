<?php

namespace Domain\Entity;

class TestCode
{
    private string $testContent;

    public function __construct(string $testContent)
    {
        $this->testContent = $testContent;
    }

    public function getTestContent(): string
    {
        return $this->testContent;
    }
}
