<?php

namespace Nosto\Tagging\Test\Unit\Block;

use Magento\Framework\View\Element\Template\Context;
use Nosto\Tagging\Block\Element;
use Nosto\Tagging\Helper\Account as NostoHelperAccount;
use Nosto\Tagging\Helper\Scope as NostoHelperScope;
use PHPUnit\Framework\TestCase;

class ElementTest extends TestCase
{
    /** @var Element */
    private $block;
    
    /** @var Context|\PHPUnit\Framework\MockObject\MockObject */
    private $contextMock;
    
    /** @var NostoHelperAccount|\PHPUnit\Framework\MockObject\MockObject */
    private $accountHelperMock;
    
    /** @var NostoHelperScope|\PHPUnit\Framework\MockObject\MockObject */
    private $scopeHelperMock;
    
    protected function setUp(): void
    {
        $this->contextMock = $this->createMock(Context::class);
        $this->accountHelperMock = $this->createMock(NostoHelperAccount::class);
        $this->scopeHelperMock = $this->createMock(NostoHelperScope::class);
        
        $this->block = new Element(
            $this->contextMock,
            $this->accountHelperMock,
            $this->scopeHelperMock
        );
    }

    /**
     * @covers Element::getElementId()
     * @return void
     */
    public function testGetElementId()
    {
        $testId = 'test-element-id';
        $this->block->setData('nostoId', $testId);
        
        $this->assertEquals($testId, $this->block->getElementId());
    }

    /**
     * @covers Element::getAbstractObject()
     * @return void
     */
    public function testGetAbstractObject()
    {
        $this->assertNull($this->block->getAbstractObject());
    }

    /**
     * @covers Element::getAbstractObject()
     * @return void
     */
    public function testToHtmlWithoutAccount()
    {
        $storeMock = $this->createMock(\Magento\Store\Model\Store::class);
        
        $this->scopeHelperMock->expects($this->once())
            ->method('getStore')
            ->willReturn($storeMock);
            
        $this->accountHelperMock->expects($this->once())
            ->method('nostoInstalledAndEnabled')
            ->with($storeMock)
            ->willReturn(false);
            
        $result = $this->block->_toHtml();
        $this->assertEquals('', $result);
    }
}
