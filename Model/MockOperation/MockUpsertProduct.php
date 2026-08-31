<?php

namespace Nosto\Tagging\Model\MockOperation;

use Nosto\Model\Product\ProductCollection;
use Nosto\NostoException;
use Nosto\Request\Http\Exception\HttpResponseException;
use Nosto\Tagging\Model\MockOperation\Result\MockResultHandler;
use Nosto\Operation\UpsertProduct;
use Nosto\Request\Api\Token;

class MockUpsertProduct extends UpsertProduct
{
    /**
     * @return array|true[]
     * @throws NostoException
     * @throws HttpResponseException
     */
    public function upsert()
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
