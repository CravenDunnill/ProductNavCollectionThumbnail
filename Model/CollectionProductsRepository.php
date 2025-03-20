<?php
namespace CravenDunnill\ProductNavCollectionThumbnail\Model;

use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory;
use Magento\Catalog\Model\Product\Visibility;
use Magento\Catalog\Model\Product\Attribute\Source\Status;

class CollectionProductsRepository
{
	/**
	 * @var ProductRepositoryInterface
	 */
	protected $productRepository;

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
	 * CollectionProductsRepository constructor.
	 *
	 * @param ProductRepositoryInterface $productRepository
	 * @param CollectionFactory $productCollectionFactory
	 * @param Visibility $productVisibility
	 * @param Status $productStatus
	 */
	public function __construct(
		ProductRepositoryInterface $productRepository,
		CollectionFactory $productCollectionFactory,
		Visibility $productVisibility,
		Status $productStatus
	) {
		$this->productRepository = $productRepository;
		$this->productCollectionFactory = $productCollectionFactory;
		$this->productVisibility = $productVisibility;
		$this->productStatus = $productStatus;
	}

	/**
	 * Get products in the same collection, sorted alphabetically by tile_colour_name
	 *
	 * @param string $collectionName
	 * @param int $currentProductId
	 * @param int $limit
	 * @return \Magento\Catalog\Model\ResourceModel\Product\Collection
	 */
	public function getCollectionProducts($collectionName, $currentProductId, $limit = 10)
	{
		$collection = $this->productCollectionFactory->create();
		$collection->addAttributeToSelect('*')
			->addAttributeToFilter('tile_collection_name', $collectionName)
			->addAttributeToFilter('entity_id', ['neq' => $currentProductId])
			->addAttributeToFilter('status', ['in' => $this->productStatus->getVisibleStatusIds()])
			->addAttributeToFilter('visibility', ['in' => $this->productVisibility->getVisibleInSiteIds()])
			->addAttributeToSort('tile_colour_name', 'ASC')
			->setPageSize($limit)
			->setCurPage(1);
		
		return $collection;
	}
}