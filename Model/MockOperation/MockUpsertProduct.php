<?php

namespace Nosto\Tagging\Model\MockOperation;

use Nosto\Model\Product\ProductCollection;
use Nosto\Tagging\Model\MockOperation\Result\MockResultHandler;
use Nosto\Operation\UpsertProduct;
use Nosto\Request\Api\Token;
use Nosto\Result\Graphql\Recommendation\ResultSet;

class MockUpsertProduct extends UpsertProduct
{
    public function upsert(): ResultSet|bool|array|string
    {
        $request = $this->initRequest(
            $this->account->getApiToken(Token::API_PRODUCTS),
            $this->account->getName(),
            $this->activeDomain,
        );
        $response = $request->post(new ProductCollection());

        return $this->getResultHandler()->parse($response);
    }

    protected function getResultHandler(): MockResultHandler
    {
        return new MockResultHandler();
    }
}
