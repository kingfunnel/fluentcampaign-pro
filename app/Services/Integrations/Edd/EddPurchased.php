<?php

namespace FluentCampaign\App\Services\Integrations\Edd;

use FluentCampaign\App\Services\Funnel\BaseCondition;
use FluentCrm\App\Services\Funnel\FunnelProcessor;
use FluentCrm\Includes\Helpers\Arr;

class EddPurchased extends BaseCondition
{
    public function __construct()
    {
        $this->conditionName = 'fcrm_edd_is_purchased';
        $this->priority = 22;
        parent::__construct();
    }

    public function getBlock()
    {
        return [
            'title'            => __('Check if the contact purchased a specific product', 'fluentcampaign-pro'),
            'description'      => __('Check If user purchased selected products and run sequences conditionally', 'fluentcampaign-pro'),
            'icon'             => fluentCrmMix('images/funnel_icons/edd_purchased.svg'),
            'settings'         => [
                'product_ids' => []
            ],
            'reload_on_insert' => true
        ];
    }

    public function getBlockFields()
    {
        return [
            'title'     => __('Check if the contact purchased a specific product', 'fluentcampaign-pro'),
            'sub_title' => __('Check If user purchased selected products and run sequences conditionally', 'fluentcampaign-pro'),
            'fields'    => [
                'product_ids'        => [
                    'type'        => 'multi-select',
                    'label'       => __('Target Products', 'fluentcampaign-pro'),
                    'help'        => __('Select Which Product you want to match for checking Purchase', 'fluentcampaign-pro'),
                    'options'     => Helper::getProducts(),
                    'inline_help' => __('If any of the product has been purchased by the contact it will result as YES', 'fluentcampaign-pro')
                ]
            ]
        ];
    }

    public function handle($subscriber, $sequence, $funnelSubscriberId, $funnelMetric)
    {
        $productIds = Arr::get($sequence->settings, 'product_ids', []);
        $isPurchased = Helper::isProductPurchased($productIds, $subscriber);

        (new FunnelProcessor())->initChildSequences($sequence, $isPurchased, $subscriber, $funnelSubscriberId, $funnelMetric);
    }
}