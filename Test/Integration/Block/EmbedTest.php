<?php

namespace Nosto\Tagging\Test\Integration\Block;

use Nosto\Tagging\Block\Embed;
use Nosto\Tagging\Test\Integration\TestCase;

/**
 * Tests for Nosto embed script
 *
 * @magentoAppArea frontend
 */
class EmbedTest extends TestCase
{
    /**
     * @var Embed
     */
    private $embedBlock;

    /**
     * @inheritDoc
     */
    public function setUp()
    {
        parent::setUp();
        $this->embedBlock = $this->getObjectManager()->create(Embed::class);
        $this->embedBlock->setTemplate('Nosto_Tagging::embed.phtml');
    }
    /**
     * Test that we generate the Nosto script correctly
     */
    public function testEmbedScript()
    {
        $html = self::stripAllWhiteSpace($this->embedBlock->toHtml());
        $needle = self::stripAllWhiteSpace(
            sprintf('/include/%s" async></script>', self::DEFAULT_NOSTO_ACCOUNT)
        );
        $this->assertContains($needle, $html);
    }
}