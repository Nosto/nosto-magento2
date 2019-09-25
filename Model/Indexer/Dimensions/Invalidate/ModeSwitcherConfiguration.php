<?php
/**
 * Created by PhpStorm.
 * User: olsiqose
 * Date: 25/09/2019
 * Time: 11.02
 */

namespace Nosto\Tagging\Model\Indexer\Dimensions\Invalidate;

use Magento\Framework\App\Config\ConfigResource\ConfigInterface;
use Magento\Framework\App\Cache\TypeListInterface;
use Magento\Indexer\Model\Indexer;
use Nosto\Tagging\Model\Indexer\Invalidate as NostoInvalidateIndexer;

class ModeSwitcherConfiguration
{
    const XML_PATH_PRODUCT_INVALIDATE_DIMENSIONS_MODE = 'indexer/nosto_index_product_invalidate/dimensions_mode';

    /**
     * ConfigInterface
     *
     * @var ConfigInterface
     */
    private $configWriter;

    /**
     * TypeListInterface
     *
     * @var TypeListInterface
     */
    private $cacheTypeList;

    /**
     * @var Indexer $indexer
     */
    private $indexer;

    /**
     * ModeSwitcherConfiguration constructor.
     * @param ConfigInterface $configWriter
     * @param TypeListInterface $cacheTypeList
     * @param Indexer $indexer
     */
    public function __construct(
        ConfigInterface $configWriter,
        TypeListInterface $cacheTypeList,
        Indexer $indexer
    ) {
        $this->configWriter = $configWriter;
        $this->cacheTypeList = $cacheTypeList;
        $this->indexer = $indexer;
    }

    /**
     * Save switcher mode and invalidate reindex.
     *
     * @param string $mode
     * @return void
     * @throws \InvalidArgumentException
     */
    public function saveMode(string $mode)
    {
        $this->configWriter->saveConfig(self::XML_PATH_PRODUCT_INVALIDATE_DIMENSIONS_MODE, $mode);
        $this->cacheTypeList->cleanType('config');
        $this->indexer->load(NostoInvalidateIndexer::INDEXER_ID);
        $this->indexer->invalidate();
    }
}
