<?php
namespace CravenDunnill\ProductNavCollectionThumbnail\Helper;

use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory;
use Magento\Catalog\Model\Product\Visibility;
use Magento\Catalog\Model\Product\Attribute\Source\Status;
use Magento\Eav\Model\Config;
use Magento\ConfigurableProduct\Model\Product\Type\Configurable;

class Data extends AbstractHelper
{
	/**
	 * @var CollectionFactory
	 */
	protected $productCollectionFactory;

	/**
	 * @var Visibility
	 */
	protected $productVisibility;

	/**
	 * @var Status
	 */
	protected $productStatus;
	
	/**
	 * @var Config
	 */
	protected $eavConfig;
	
	/**
	 * @var Configurable
	 */
	protected $configurableType;

	/**
	 * Data constructor.
	 *
	 * @param Context $context
	 * @param CollectionFactory $productCollectionFactory
	 * @param Visibility $productVisibility
	 * @param Status $productStatus
	 * @param Config $eavConfig
	 * @param Configurable $configurableType
	 */
	public function __construct(
		Context $context,
		CollectionFactory $productCollectionFactory,
		Visibility $productVisibility,
		Status $productStatus,
		Config $eavConfig,
		Configurable $configurableType
	) {
		$this->productCollectionFactory = $productCollectionFactory;
		$this->productVisibility = $productVisibility;
		$this->productStatus = $productStatus;
		$this->eavConfig = $eavConfig;
		$this->configurableType = $configurableType;
		parent::__construct($context);
	}

	/**
	 * Get products in the same collection
	 *
	 * @param string $collectionName
	 * @param int $currentProductId
	 * @return \Magento\Catalog\Model\ResourceModel\Product\Collection
	 */
	public function getCollectionProducts($collectionName, $currentProductId)
	{
		$collection = $this->productCollectionFactory->create();
		$collection->addAttributeToSelect('*')
			->addAttributeToFilter('tile_collection_name', $collectionName)
			->addAttributeToFilter('entity_id', ['neq' => $currentProductId])
			->addAttributeToFilter('status', ['in' => $this->productStatus->getVisibleStatusIds()])
			->addAttributeToFilter('visibility', ['in' => $this->productVisibility->getVisibleInSiteIds()]);
		
		// Only include simple products, excluding configurables
		$collection->addAttributeToFilter(
			'type_id',
			[
				'eq' => \Magento\Catalog\Model\Product\Type::TYPE_SIMPLE
			]
		);
		
		try {
			$attribute = $this->eavConfig->getAttribute('catalog_product', 'tile_colour_name');
			$tableAlias = 'tile_colour_table';
			
			$collection->getSelect()->joinLeft(
				[$tableAlias => $attribute->getBackendTable()],
				"e.entity_id = {$tableAlias}.entity_id AND {$tableAlias}.attribute_id = {$attribute->getAttributeId()}",
				[$tableAlias . '.value']
			);
			
			// Join with attribute option value table to get the actual text
			$collection->getSelect()->joinLeft(
				['option_value_table' => $collection->getTable('eav_attribute_option_value')],
				"{$tableAlias}.value = option_value_table.option_id",
				['color_name' => 'option_value_table.value']
			);
			
			// Sort by the text value
			$collection->getSelect()->order('color_name ASC');
		} catch (\Exception $e) {
			// Fallback to option id sorting if the joins fail
			$collection->addAttributeToSort('tile_colour_name', 'ASC');
			$this->_logger->error('Error joining tile_colour_name attribute: ' . $e->getMessage());
		}
		
		return $collection;
	}
	
	/**
	 * Get attribute option text from option ID
	 *
	 * @param string $attributeCode
	 * @param string|int $optionId
	 * @return string
	 */
	public function getAttributeOptionText($attributeCode, $optionId)
	{
		if (empty($optionId)) {
			return '';
		}
		
		try {
			$attribute = $this->eavConfig->getAttribute('catalog_product', $attributeCode);
			if ($attribute && $attribute->usesSource()) {
				return $attribute->getSource()->getOptionText($optionId);
			}
		} catch (\Exception $e) {
			$this->_logger->error('Error getting attribute option text: ' . $e->getMessage());
		}
		
		return $optionId;
	}
	
	/**
	 * Check if a product is configurable
	 *
	 * @param \Magento\Catalog\Model\Product $product
	 * @return bool
	 */
	public function isConfigurable($product)
	{
		return $product->getTypeId() === Configurable::TYPE_CODE;
	}
}