<?php

namespace Nosto\Tagging\Test\Unit\Block;

use Magento\Framework\View\Element\Template\Context;
use Magento\Store\Model\Store;
use Nosto\Tagging\Block\Embed;
use Nosto\Tagging\Helper\Account as NostoHelperAccount;
use Nosto\Tagging\Helper\Scope as NostoHelperScope;
use PHPUnit\Framework\TestCase;
use Nosto\Model\Signup\Account as NostoAccount;

class EmbedTest extends TestCase
{
    /** @var Embed */
    private $block;
    
    /** @var Context|\PHPUnit\Framework\MockObject\MockObject */
    private $contextMock;
    
    /** @var NostoHelperAccount|\PHPUnit\Framework\MockObject\MockObject */
    private $accountHelperMock;
    
    /** @var NostoHelperScope|\PHPUnit\Framework\MockObject\MockObject */
    private $scopeHelperMock;
    
    /** @var Store|\PHPUnit\Framework\MockObject\MockObject */
    private $storeMock;
    
    /** @var NostoAccount|\PHPUnit\Framework\MockObject\MockObject */
    private $nostoAccountMock;
    
    protected function setUp(): void
    {
        $this->contextMock = $this->createMock(Context::class);
        $this->accountHelperMock = $this->createMock(NostoHelperAccount::class);
        $this->scopeHelperMock = $this->createMock(NostoHelperScope::class);
        $this->storeMock = $this->createMock(Store::class);
        $this->nostoAccountMock = $this->createMock(NostoAccount::class);
        
        $this->block = new Embed(
            $this->contextMock,
            $this->accountHelperMock,
            $this->scopeHelperMock
        );
    }
    /**
     * @covers Embed::getAccountName()
     * @return void
     */
    public function testGetAccountName()
    {
        $accountName = 'test-account-name';
        
        $this->scopeHelperMock->expects($this->once())
            ->method('getStore')
            ->with(true)
            ->willReturn($this->storeMock);
            
        $this->accountHelperMock->expects($this->once())
            ->method('findAccount')
            ->with($this->storeMock)
            ->willReturn($this->nostoAccountMock);
            
        $this->nostoAccountMock->expects($this->once())
            ->method('getName')
            ->willReturn($accountName);
            
        $result = $this->block->getAccountName();
        $this->assertEquals($accountName, $result);
    }

    /**
     * @covers Embed::getAccountName()
     * @return void
     */
    public function testGetAccountNameWithNoAccount()
    {
        $this->scopeHelperMock->expects($this->once())
            ->method('getStore')
            ->with(true)
            ->willReturn($this->storeMock);
            
        $this->accountHelperMock->expects($this->once())
            ->method('findAccount')
            ->with($this->storeMock)
            ->willReturn(null);
            
        $result = $this->block->getAccountName();
        $this->assertEquals('', $result);
    }

    /**
     * @covers Embed::getAbstractObject()
     * @return void
     */
    public function testGetAbstractObject()
    {
        $this->assertNull($this->block->getAbstractObject());
    }
}
