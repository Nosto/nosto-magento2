<?php

namespace Nosto\Tagging\Model\MockOperation;

use Nosto\Operation\AbstractGraphQLOperation;
use Nosto\Tagging\Model\MockOperation\Result\MockResultHandler;

class MockGraphQLOperation extends AbstractGraphQLOperation
{
    public function getQuery(): string
    {
        return '';
    }

    /**
     * @return array<string, mixed>
     */
    public function getVariables(): array
    {
        return [];
    }

    protected function getResultHandler(): MockResultHandler
    {
        return new MockResultHandler();
    }
}
