<?php

namespace Domain\Repository;

interface CodeRepositoryInterface
{
    public function findRelatedCode(string $testContent): array;
}
