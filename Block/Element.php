<?php

namespace Nosto\Tagging\Block;

use Magento\Framework\View\Element\Template;

/**
 * Element block used for outputting a recommendation placeholders on the stores pages.
 * This placeholder is then populated with recommendations from Nosto on the
 * client side.
 */
class Element extends Template
{
    /**
     * @inheritdoc
     */
    protected $_template = 'element.phtml';

	/**
	 * Returns the Nosto recommendation placeholder ID.
	 *
	 * This ID needs to match an existing recommendation element in Nosto.
	 *
	 * @return string the ID.
	 */
	public function getElementId()
	{
		return $this->getData('nostoId');
	}
}
