<?php

namespace Nosto\Tagging\Model\MockOperation\Result;

use Nosto\Request\Http\HttpResponse;
use Nosto\Result\Graphql\Recommendation\ResultSet;
use Nosto\Result\ResultHandler;

class MockResultHandler extends ResultHandler
{
    public function parse(HttpResponse $response): ResultSet|array|bool|string
    {
        return $this->parseResponse($response);
    }

    /**
     * @return array<string, mixed>
     */
    protected function parseResponse(HttpResponse $response): array
    {
        if ($response->getCode() > 400) {
            $result = json_decode($response->getResult(), true);

            return [
                'success' => false,
                'message' => empty($result['message']) ? 'Unautorized' : $result['message'],
            ];
        }

        return [
            'success' => true,
        ];
    }
}