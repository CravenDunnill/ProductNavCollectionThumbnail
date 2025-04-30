<?php
namespace CravenDunnill\ProductNavCollectionThumbnail\Controller\Product;

use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\Controller\Result\JsonFactory;
use CravenDunnill\ProductNavCollectionThumbnail\Helper\Data;
use Magento\Catalog\Helper\Image;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Catalog\Model\Product\Type;
use Magento\Framework\Pricing\Helper\Data as PricingHelper;

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
	 * @var PricingHelper
	 */
	protected $pricingHelper;

	/**
	 * CollectionProducts constructor.
	 *
	 * @param Context $context
	 * @param JsonFactory $resultJsonFactory
	 * @param Data $helper
	 * @param Image $imageHelper
	 * @param StoreManagerInterface $storeManager
	 * @param PricingHelper $pricingHelper
	 */
	public function __construct(
		Context $context,
		JsonFactory $resultJsonFactory,
		Data $helper,
		Image $imageHelper,
		StoreManagerInterface $storeManager,
		PricingHelper $pricingHelper
	) {
		$this->resultJsonFactory = $resultJsonFactory;
		$this->helper = $helper;
		$this->imageHelper = $imageHelper;
		$this->storeManager = $storeManager;
		$this->pricingHelper = $pricingHelper;
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
			
			// Determine which image to use (swatch first, then small image as fallback)
			$imageUrl = '';
			if ($product->getSwatchImage() && $product->getSwatchImage() != 'no_selection') {
				$imageUrl = $this->imageHelper->init($product, 'product_swatch_image')
					->setImageFile($product->getSwatchImage())
					->resize(140)
					->constrainOnly(false)
					->keepAspectRatio(false)
					->keepFrame(true)
					->keepTransparency(true)
					->getUrl();
			} else {
				$imageUrl = $this->imageHelper->init($product, 'product_small_image')
					->setImageFile($product->getSmallImage())
					->resize(140)
					->constrainOnly(false)
					->keepAspectRatio(false)
					->keepFrame(true)
					->keepTransparency(true)
					->getUrl();
			}
			
			// Get the regular price and apply VAT
			$price = $product->getPriceInfo()->getPrice('final_price')->getValue();
			$priceWithVat = $price * 1.2;
			
			// Get the price_m2 and apply VAT
			$priceM2 = $product->getData('price_m2');
			$priceM2WithVat = $priceM2 * 1.2;
			
			// Format prices with currency symbol
			$formattedPrice = $this->pricingHelper->currency($priceWithVat, true, false);
			$formattedPriceM2 = $this->pricingHelper->currency($priceM2WithVat, true, false);
			
			$productData[] = [
				'id' => $product->getId(),
				'name' => $product->getName(),
				'url' => $product->getProductUrl(),
				'image' => $imageUrl,
				'color_name' => $product->getTileColourName(),
				'tile_size' => $product->getTileSize(),
				'price' => $priceWithVat,
				'price_m2' => $priceM2WithVat,
				'formatted_price' => $formattedPrice,
				'formatted_price_m2' => $formattedPriceM2
			];
		}
		
		return $result->setData([
			'success' => true,
			'collection_name' => $collectionName,
			'products' => $productData
		]);
	}
}