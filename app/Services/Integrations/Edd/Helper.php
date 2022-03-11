<?php

namespace FluentCampaign\App\Services\Integrations\Edd;

use FluentCrm\App\Services\Funnel\FunnelHelper;

class Helper
{
    public static function getProducts()
    {
        $args = array(
            'post_type' => 'download',
            'numberposts' => -1
        );

        $downloads = get_posts($args);

        $formattedProducts = [];
        foreach ($downloads as $download) {
            $formattedProducts[] = [
                'id'    => strval($download->ID),
                'title' => $download->post_title
            ];
        }

        return $formattedProducts;
    }

    public static function getCategories()
    {
        $categories = get_terms('download_category', array(
            'orderby'    => 'name',
            'order'      => 'asc',
            'hide_empty' => true,
        ));

        $formattedOptions = [];
        foreach ($categories as $category) {
            $formattedOptions[] = [
                'id'    => strval($category->term_id),
                'title' => $category->name
            ];
        }

        return $formattedOptions;
    }

    public static function purchaseTypeOptions()
    {
        return [
            [
                'id'    => 'all',
                'title' => __('Any type of purchase', 'fluentcampaign-pro')
            ],
            [
                'id'    => 'first_purchase',
                'title' => __('Only for first purchase', 'fluentcampaign-pro')
            ],
            [
                'id'    => 'from_second',
                'title' => __('From 2nd Purchase', 'fluentcampaign-pro')
            ]
        ];
    }

    /**
     * @param $payment \EDD_Payment
     * @param $conditions
     * @return bool
     */
    public static function isProductIdCategoryMatched($payment, $conditions)
    {
        $purchaseProductIds = [];
        foreach ($payment->cart_details as $item) {
            $purchaseProductIds[] = $item['id'];
        }

        if ($conditions['product_ids']) {
            if (!array_intersect($purchaseProductIds, $conditions['product_ids'])) {
                return false;
            }
        }

        if ($targetCategories = $conditions['product_categories']) {
            $categoryMatch = wpFluent()->table('term_relationships')
                ->whereIn('object_id', $purchaseProductIds)
                ->whereIn('term_taxonomy_id', $targetCategories)
                ->count();

            if (!$categoryMatch) {
                return false;
            }
        }
        return true;
    }

    /**
     * @param $payment \EDD_Payment
     * @param $purchaseType
     * @return bool
     */
    public static function isPurchaseTypeMatch($payment, $purchaseType)
    {
        if (!$purchaseType) {
            return true;
        }

        $customer = new \EDD_Customer($payment->customer_id);

        if ($purchaseType == 'from_second') {
            if ($customer->purchase_count < 2) {
                return false;
            }
        } else if ($purchaseType == 'first_purchase') {
            if ($customer->purchase_count > 1) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param $payment \EDD_Payment
     * @return array
     */
    public static function prepareSubscriberData($payment)
    {
        if ($payment->user_id) {
            $subscriberData = FunnelHelper::prepareUserData($payment->user_id);
        } else {
            $subscriberData = [
                'first_name' => $payment->first_name,
                'last_name'  => $payment->last_name,
                'email'      => $payment->email,
                'ip'         => $payment->ip
            ];
        }

        return FunnelHelper::maybeExplodeFullName($subscriberData);
    }

    public static function isProductPurchased($productIds, $subscriber)
    {
        if(!$productIds) {
            return false;
        }

        $args = [
            'output' => 'payments',
            'status' => ['publish', 'processing']
        ];

        $userId = $subscriber->user_id;

        if(!$userId) {
            $user = get_user_by('email', $subscriber->email);
            if($user) {
                $args['user'] = $user->ID;
            }
        }

        if(!isset($args['user'])) {
            $customer = wpFluent()->table('edd_customers')
                ->where('email', $subscriber->email)
                ->first();
            if(!$customer) {
                return false;
            }
            $args['customer'] = $customer->id;
        }

        $payments = edd_get_payments($args);

        if (!$payments) {
            return false;
        }

        foreach ($payments as $payment) {
           foreach ($payment->cart_details as $cart_detail) {
               if(in_array($cart_detail['id'], $productIds)) {
                   return true;
               }
           }
        }

        return false;
    }
}
