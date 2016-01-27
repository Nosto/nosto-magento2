<?php

namespace Nosto\Tagging\Block;

use Magento\CatalogSearch\Block\Result;

/** @noinspection PhpIncludeInspection */
require_once 'app/code/Nosto/Tagging/vendor/nosto/php-sdk/autoload.php';

/**
 * Search block used for outputting meta-data on the stores search pages.
 * This meta-data is sent to Nosto via JavaScript when users are browsing the
 * pages in the store.
 */
class Search extends Result
{
    /**
     * @inheritdoc
     */
    protected $_template = 'search.phtml';

    /**
     * Returns the current escaped search term
     *
     * @return string the search term
     */
    public function getNostoSearchTerm()
    {
        return $this->catalogSearchData->getEscapedQueryText();
    }
}
