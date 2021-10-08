<?php

namespace FluentCampaign\App\Services\Integrations\WooCommerce;

class WooDataHelper
{

    static $customerCache = [];

    public static function getCustomerItem($key, $wooCustomer)
    {
        if($key == 'total_spend') {
            return self::getTotalSpend($wooCustomer);
        } else if($key == 'order_count') {
            return self::getOrderCount($wooCustomer);
        } else if($key == 'guest_user') {
            return !$wooCustomer->user_id;
        } else if($key == 'billing_country') {
            return $wooCustomer->country;
        } else if($key == 'cat_purchased') {
            return self::getPurchasedCategoryIds($wooCustomer->customer_id);
        } else if($key == 'purchased_products') {
            return self::getCustomerProductIds($wooCustomer->customer_id);
        }

        return '';
    }

    /**
     * @param $key string
     * @param $order \WC_Order
     * @return mixed
     */
    public static function getOrderItem($key, $order)
    {
        if(!$order) {
            return '';
        }

        if($key == 'total_value') {
            return $order->get_total('edit');
        } else if($key == 'cat_purchased') {
            $items = $order->get_items();
            $purchaseProductIds = [];
            foreach ($items as $item) {
                $purchaseProductIds[] = $item->get_product_id();
            }
            return self::getCatIdsByProductIds($purchaseProductIds);
        } else if($key == 'product_ids') {
            $items = $order->get_items();
            $purchaseProductIds = [];
            foreach ($items as $item) {
                $purchaseProductIds[] = $item->get_product_id();
            }
            return array_unique($purchaseProductIds);
        } else if($key == 'billing_country') {
            return $order->get_billing_country('edit');
        } else if($key == 'shipping_method') {
            return $order->get_shipping_method();
        } else if($key == 'payment_gateway') {
            return $order->get_payment_method('edit');
        }

        return '';
    }

    /**
     * @param int|object wc_customer_lookup table row
     * @return mixed
     */
    public static function getTotalSpend($wooCustomer)
    {
        if($wooCustomer->user_id) {
            $customer = self::getCustomer($wooCustomer->user_id);
            return $customer->get_total_spent();
        }

        $orderStat = wpFluent()->table('wc_order_stats')
            ->select(wpFluent()->raw('SUM(net_total) as total_spent'))
            ->where('customer_id', $wooCustomer->customer_id)
            ->first();

        return $orderStat->total_spent;

    }

    public static function getOrderCount($wooCustomer)
    {
        if($wooCustomer->user_id) {
            $customer = self::getCustomer($wooCustomer->user_id);
            return $customer->get_order_count();
        }

        return wpFluent()->table('wc_order_stats')
            ->where('customer_id', $wooCustomer->customer_id)
            ->count();
    }

    public static function getPurchasedCategoryIds($customerId)
    {
        $productIds = self::getCustomerProductIds($customerId);
        if(!$productIds) {
            return [];
        }
        return self::getCatIdsByProductIds($productIds);
    }

    public static function getCatIdsByProductIds($productIds)
    {
        if(!$productIds) {
            return [];
        }
        $allCategories = wpFluent()->table('term_taxonomy')
            ->select(['term_taxonomy_id'])
            ->where('taxonomy', 'product_cat')
            ->get();

        $allCategoryIds = [];

        foreach ($allCategories as $allCategory) {
            $allCategoryIds[] = $allCategory->term_taxonomy_id;
        }

        if(!$allCategoryIds) {
            return [];
        }

        $relationships = wpFluent()->table('term_relationships')
            ->whereIn('object_id', $productIds)
            ->whereIn('term_taxonomy_id', $allCategoryIds)
            ->get();

        if(!$relationships) {
            return [];
        }

        $catIds = [];

        foreach ($relationships as $relationship) {
            $catIds[] = $relationship->term_taxonomy_id;
        }

        return array_unique($catIds);
    }

    public static function getCustomerProductIds($customerId)
    {
        $productLookUps = wpFluent()->table('wc_order_product_lookup')
            ->select(['product_id'])
            ->groupBy('product_id')
            ->where('customer_id', $customerId)
            ->get();

        if(!$productLookUps) {
            return [];
        }

        $productIds = [];

        foreach ($productLookUps as $productLookUp) {
            $productIds[] = $productLookUp->product_id;
        }

        return $productIds;
    }

    private static function getCustomer($userId)
    {
        if(isset(self::$customerCache[$userId])) {
            return self::$customerCache[$userId];
        }

        self::$customerCache[$userId] = new \WC_Customer($userId);

        return self::$customerCache[$userId];
    }
}
