<?php
/**
 * Copyright (c) 2020, Nosto Solutions Ltd
 * All rights reserved.
 *
 * Redistribution and use in source and binary forms, with or without modification,
 * are permitted provided that the following conditions are met:
 *
 * 1. Redistributions of source code must retain the above copyright notice,
 * this list of conditions and the following disclaimer.
 *
 * 2. Redistributions in binary form must reproduce the above copyright notice,
 * this list of conditions and the following disclaimer in the documentation
 * and/or other materials provided with the distribution.
 *
 * 3. Neither the name of the copyright holder nor the names of its contributors
 * may be used to endorse or promote products derived from this software without
 * specific prior written permission.
 *
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS" AND
 * ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED
 * WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE
 * DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT HOLDER OR CONTRIBUTORS BE LIABLE FOR
 * ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES
 * (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES;
 * LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON
 * ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
 * (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF THIS
 * SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
 *
 * @author Nosto Solutions Ltd <contact@nosto.com>
 * @copyright 2020 Nosto Solutions Ltd
 * @license http://opensource.org/licenses/BSD-3-Clause BSD 3-Clause
 *
 */

namespace Nosto\Tagging\Util;

use Iterator;
use Magento\Framework\Data\Collection;
use Nosto\NostoException;

class PagingIterator implements Iterator
{
    private Collection $collection;

    /** @var int */
    private int $currentPageNumber;

    /** @var int */
    private int $lastPageNumber;

    /**
     * Iterator constructor.
     * @param Collection $collection
     * @throws NostoException
     */
    public function __construct(Collection $collection)
    {
        if (!is_numeric($collection->getPageSize())) {
            throw new NostoException('Page size not defined or not an integer');
        }
        $this->collection = $collection;
        $this->lastPageNumber = $this->collection->getLastPageNumber();
    }

    /**
     * @inheritDoc
     */
    #[\ReturnTypeWillChange]
    public function current()
    {
        return $this->collection;
    }

    /**
     * @inheritDoc
     */
    public function next()
    {
        ++$this->currentPageNumber;
        $this->page($this->currentPageNumber);
    }

    /**
     * @inheritDoc
     */
    public function key()
    {
        return $this->collection->getCurPage();
    }

    /**
     * @inheritDoc
     */
    public function valid(): bool
    {
        return $this->currentPageNumber <= $this->lastPageNumber;
    }

    /**
     * @inheritDoc
     */
    public function rewind()
    {
        $this->page(1);
    }

    /**
     * @param int $pageNumber
     */
    private function page(int $pageNumber)
    {
        $this->collection->clear();
        $this->collection->setCurPage($pageNumber);
        $this->currentPageNumber = $pageNumber;
    }

    /**
     * @return int
     */
    public function getLastPageNumber(): int
    {
        return $this->lastPageNumber;
    }

    /**
     * @return int
     */
    public function getCurrentPageNumber(): int
    {
        return $this->currentPageNumber;
    }
}
