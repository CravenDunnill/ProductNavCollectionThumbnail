<?php
namespace CravenDunnill\ProductNavCollectionThumbnail\Block\Product\View;

use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;
use Magento\Framework\Registry;
use CravenDunnill\ProductNavCollectionThumbnail\Helper\Data;
use Magento\Catalog\Helper\Image;
use Magento\Eav\Model\Entity\Attribute\Source\Table;
use Magento\Eav\Api\AttributeRepositoryInterface;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Exception\LocalizedException;
use Magento\ConfigurableProduct\Model\Product\Type\Configurable;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Store\Model\StoreManagerInterface;

class CollectionNavigation extends Template
{
	/**
	 * @var Registry
	 */
	protected $registry;

	/**
	 * @var Data
	 */
	protected $helper;

	/**
	 * @var Image
	 */
	protected $imageHelper;
	
	/**
	 * @var AttributeRepositoryInterface
	 */
	protected $attributeRepository;
	
	/**
	 * @var SearchCriteriaBuilder
	 */
	protected $searchCriteriaBuilder;
	
	/**
	 * @var Configurable
	 */
	protected $configurableType;
	
	/**
	 * @var ProductRepositoryInterface
	 */
	protected $productRepository;
	
	/**
	 * @var StoreManagerInterface
	 */
	protected $storeManager;
	
	/**
	 * Cache for attribute options
	 * 
	 * @var array
	 */
	protected $attributeOptions = [];

	/**
	 * CollectionNavigation constructor.
	 *
	 * @param Context $context
	 * @param Registry $registry
	 * @param Data $helper
	 * @param Image $imageHelper
	 * @param AttributeRepositoryInterface $attributeRepository
	 * @param SearchCriteriaBuilder $searchCriteriaBuilder
	 * @param Configurable $configurableType
	 * @param ProductRepositoryInterface $productRepository
	 * @param StoreManagerInterface $storeManager
	 * @param array $data
	 */
	public function __construct(
		Context $context,
		Registry $registry,
		Data $helper,
		Image $imageHelper,
		AttributeRepositoryInterface $attributeRepository,
		SearchCriteriaBuilder $searchCriteriaBuilder,
		Configurable $configurableType,
		ProductRepositoryInterface $productRepository,
		StoreManagerInterface $storeManager,
		array $data = []
	) {
		$this->registry = $registry;
		$this->helper = $helper;
		$this->imageHelper = $imageHelper;
		$this->attributeRepository = $attributeRepository;
		$this->searchCriteriaBuilder = $searchCriteriaBuilder;
		$this->configurableType = $configurableType;
		$this->productRepository = $productRepository;
		$this->storeManager = $storeManager;
		parent::__construct($context, $data);
	}

	/**
	 * Get current product
	 *
	 * @return \Magento\Catalog\Model\Product|null
	 */
	public function getCurrentProduct()
	{
		return $this->registry->registry('current_product');
	}

	/**
	 * Get collection name label instead of option ID
	 *
	 * @return string|null
	 */
	public function getCollectionName()
	{
		$product = $this->getCurrentProduct();
		if ($product) {
			$collectionId = $product->getTileCollectionName();
			if ($collectionId) {
				return $this->getAttributeOptionText('tile_collection_name', $collectionId);
			}
		}
		return null;
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
		
		// Return from cache if available
		if (isset($this->attributeOptions[$attributeCode][$optionId])) {
			return $this->attributeOptions[$attributeCode][$optionId];
		}
		
		try {
			// Get attribute options
			$attribute = $this->attributeRepository->get('catalog_product', $attributeCode);
			$options = $attribute->getSource()->getAllOptions();
			
			// Cache all options for this attribute
			foreach ($options as $option) {
				if ($option['value']) {
					$this->attributeOptions[$attributeCode][$option['value']] = $option['label'];
				}
			}
			
			// Return the requested option text
			if (isset($this->attributeOptions[$attributeCode][$optionId])) {
				return $this->attributeOptions[$attributeCode][$optionId];
			}
			
		} catch (LocalizedException $e) {
			// If attribute doesn't exist or has no options, return the original value
			$this->_logger->error($e->getMessage());
		}
		
		// Fallback to original option ID if not found
		return $optionId;
	}

	/**
	 * Get color name label instead of option ID
	 *
	 * @param \Magento\Catalog\Model\Product $product
	 * @return string
	 */
	public function getColorName($product)
	{
		$colorId = $product->getTileColourName();
		if ($colorId) {
			return $this->getAttributeOptionText('tile_colour_name', $colorId);
		}
		return '';
	}

	/**
	 * Get products in the same collection
	 *
	 * @return \Magento\Catalog\Model\ResourceModel\Product\Collection
	 */
	public function getCollectionProducts()
	{
		$product = $this->getCurrentProduct();
		$collectionName = $product ? $product->getTileCollectionName() : null;
		
		if ($product && $collectionName) {
			return $this->helper->getCollectionProducts($collectionName, $product->getId());
		}
		
		return [];
	}

	/**
	 * Get image url for product
	 *
	 * @param \Magento\Catalog\Model\Product $product
	 * @return string
	 */
	public function getProductImageUrl($product)
	{
		// For configurable products, try to get the first simple product's image if available
		if ($this->helper->isConfigurable($product)) {
			$children = $this->configurableType->getUsedProducts($product);
			if (!empty($children)) {
				// Use the first child product for the image
				$firstChild = reset($children);
				if ($firstChild->getSmallImage() && $firstChild->getSmallImage() != 'no_selection') {
					// Load the full child product to ensure we have all attributes
					try {
						$childProduct = $this->productRepository->getById(
							$firstChild->getId(), 
							false, 
							$this->storeManager->getStore()->getId()
						);
						
						return $this->imageHelper->init($childProduct, 'product_small_image')
							->setImageFile($childProduct->getSmallImage())
							->resize(100, 100)
							->getUrl();
					} catch (\Exception $e) {
						$this->_logger->error('Error loading child product: ' . $e->getMessage());
					}
				}
			}
		}
		
		// Fallback to current product image
		return $this->imageHelper->init($product, 'product_small_image')
			->setImageFile($product->getSmallImage())
			->resize(100, 100)
			->getUrl();
	}
	
	/**
	 * Check if the current product is a configurable product
	 *
	 * @return bool
	 */
	public function isCurrentProductConfigurable()
	{
		$product = $this->getCurrentProduct();
		return $product && $this->helper->isConfigurable($product);
	}
}