<?php
/**
 * Copyright (c) 2020, Nosto Solutions Ltd
 * All rights reserved.
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 */

declare(strict_types=1);

namespace Nosto\Tagging\Test\Unit\Block;

use Magento\CatalogSearch\Helper\Data;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;
use Nosto\Model\SearchTerm;
use Nosto\Tagging\Block\Search;
use Nosto\Tagging\Helper\Account as NostoHelperAccount;
use Nosto\Tagging\Helper\Scope as NostoHelperScope;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Nosto\Tagging\Block\Search
 */
class SearchTest extends TestCase
{
    private const SEARCH_TERM = 'winter boots';

    /**
     * @var Data
     */
    private $catalogSearchData;

    /**
     * @var Search
     */
    private $searchBlock;

    protected function setUp(): void
    {
        $context = $this->createMock(Context::class);
        $this->catalogSearchData = $this->createMock(Data::class);

        $this->searchBlock = new Search(
            $context,
            $this->catalogSearchData,
            $this->createMock(NostoHelperAccount::class),
            $this->createMock(NostoHelperScope::class)
        );
    }

    public function testUsesTemplateAsItsDirectParent(): void
    {
        self::assertSame(Template::class, get_parent_class($this->searchBlock));
    }

    public function testReturnsEscapedSearchTermFromCatalogSearchHelper(): void
    {
        $this->catalogSearchData->expects(self::once())
            ->method('getEscapedQueryText')
            ->willReturn(self::SEARCH_TERM);

        self::assertSame(self::SEARCH_TERM, $this->searchBlock->getNostoSearchTerm());
    }

    public function testBuildsNostoSearchTermObject(): void
    {
        $this->catalogSearchData->expects(self::once())
            ->method('getEscapedQueryText')
            ->willReturn(self::SEARCH_TERM);

        $searchTerm = $this->searchBlock->getAbstractObject();

        self::assertInstanceOf(SearchTerm::class, $searchTerm);
        self::assertSame(self::SEARCH_TERM, $searchTerm->getValue());
    }
}
