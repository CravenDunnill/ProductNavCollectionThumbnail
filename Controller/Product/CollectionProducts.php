<?php
namespace CravenDunnill\ProductNavCollectionThumbnail\Controller\Product;

use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\Controller\Result\JsonFactory;
use CravenDunnill\ProductNavCollectionThumbnail\Helper\Data;
use Magento\Catalog\Helper\Image;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Catalog\Model\Product\Type;

class CollectionProducts extends Action
{
	/**
	 * @var JsonFactory
	 */
	protected $resultJsonFactory;

	/**
	 * @var Data
	 */
	protected $helper;

	/**
	 * @var Image
	 */
	protected $imageHelper;

	/**
	 * @var StoreManagerInterface
	 */
	protected $storeManager;

	/**
	 * CollectionProducts constructor.
	 *
	 * @param Context $context
	 * @param JsonFactory $resultJsonFactory
	 * @param Data $helper
	 * @param Image $imageHelper
	 * @param StoreManagerInterface $storeManager
	 */
	public function __construct(
		Context $context,
		JsonFactory $resultJsonFactory,
		Data $helper,
		Image $imageHelper,
		StoreManagerInterface $storeManager
	) {
		$this->resultJsonFactory = $resultJsonFactory;
		$this->helper = $helper;
		$this->imageHelper = $imageHelper;
		$this->storeManager = $storeManager;
		parent::__construct($context);
	}

	/**
	 * Execute controller action
	 *
	 * @return \Magento\Framework\Controller\Result\Json
	 */
	public function execute()
	{
		$result = $this->resultJsonFactory->create();
		
		$collectionName = $this->getRequest()->getParam('collection_name');
		$currentProductId = (int)$this->getRequest()->getParam('current_product_id');
		
		if (!$collectionName || !$currentProductId) {
			return $result->setData(['success' => false, 'message' => 'Missing parameters']);
		}
		
		$products = $this->helper->getCollectionProducts($collectionName, $currentProductId);
		$productData = [];
		
		foreach ($products as $product) {
			// Only include simple products
			if ($product->getTypeId() !== Type::TYPE_SIMPLE) {
				continue;
			}
			
			$productData[] = [
				'id' => $product->getId(),
				'name' => $product->getName(),
				'url' => $product->getProductUrl(),
				'image' => $this->imageHelper->init($product, 'product_small_image')
					->setImageFile($product->getSmallImage())
					->resize(100, 100)
					->getUrl(),
				'color_name' => $product->getTileColourName(),
				'tile_size' => $product->getTileSize()
			];
		}
		
		return $result->setData([
			'success' => true,
			'collection_name' => $collectionName,
			'products' => $productData
		]);
	}
}