<?php

declare(strict_types=1);

namespace BigBridge\ProductImport\Model\Resource\ThirdParty;

use BigBridge\ProductImport\Api\Data\Product;
use Ess\M2ePro\PublicServices\Product\SqlChange;
use Exception;
use Magento\Framework\App\ObjectManager;

class M2EProNotification
{
    /**
     * @param Product[] $products
     */
    public function notify(array $products)
    {
        if (empty($products)) { return; }

        try {
            $model = ObjectManager::getInstance()->get(SqlChange::class);
        } catch (Exception $exception) {
            // M2EPro is not installed
            return;
        }

        foreach ($products as $product) {
            $model->markProductChanged($product->id);
        }

        try {
            $model->applyChanges();
        } catch (Exception $exception) {
            // choose last product for error message
            $product->addError("M2EPro error for all products: " . $exception->getMessage());
            return;
        }
    }
}