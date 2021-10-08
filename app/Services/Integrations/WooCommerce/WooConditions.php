<?php

namespace FluentCampaign\App\Services\Integrations\WooCommerce;

use FluentCampaign\App\Services\Funnel\BaseCondition;
use FluentCrm\App\Models\FunnelSubscriber;
use FluentCrm\App\Services\Funnel\FunnelProcessor;
use FluentCrm\Includes\Helpers\Arr;
use FluentCrm\Includes\Helpers\ConditionAssesor;

class WooConditions extends BaseCondition
{

    public function __construct()
    {
        $this->conditionName = 'fcrm_woo_conditions';
        $this->priority = 40;
        parent::__construct();
    }

    public function pushBlock($blocks, $funnel)
    {
        $block = $this->getBlock();

        if ($block) {
            $block['type'] = 'conditional';
            $blocks[$this->conditionName] = $block;
        }

        return $blocks;
    }

    public function pushBlockFields($fields, $funnel)
    {
        $fields[$this->conditionName] = $this->getWooFields($funnel);
        return $fields;
    }

    public function getBlock()
    {
        return [
            'title'            => __('Woocommerce Conditions', 'fluentcampaign-pro'),
            'description'      => __('Check customer / Order properties', 'fluentcampaign-pro'),
            'icon'             => fluentCrmMix('images/funnel_icons/woo_purchased.svg'),
            'settings'         => [
                'conditional_groups' => [
                    [
                        'conditions' => [
                            [
                                'data_key'   => '',
                                'operator'   => '=',
                                'data_value' => ''
                            ]
                        ],
                        'match_type' => 'match_all'
                    ]
                ]
            ],
            'reload_on_insert' => true
        ];
    }

    public function getBlockFields()
    {
        return [];
    }

    public function getWooFields($funnel)
    {
        $conditionalOptions = [];

        if (Helper::isWooTrigger($funnel->trigger_name)) {
            $conditionalOptions['order'] = [
                'label'   => 'Reference Order',
                'options' => $this->getOrderOptions()
            ];
        }

        $conditionalOptions['customer'] = [
            'label'   => 'Customer Properties',
            'options' => $this->getCustomerOptions()
        ];

        return [
            'title'     => __('Check if the contact purchased a specific product', 'fluentcampaign-pro'),
            'sub_title' => __('Check If user purchased selected products and run sequences conditionally', 'fluentcampaign-pro'),
            'fields'    => [
                'conditional_groups' => [
                    'type'                  => 'condition_groups',
                    'label'                 => __('Configure Conditions', 'fluentcampaign-pro'),
                    'help'                  => __('Set the condition groups as many as you like', 'fluentcampaign-pro'),
                    'condition_properties'  => $conditionalOptions,
                    'is_grouped_properties' => 'yes',
                    'labels'                => [
                        'match_type_all_label' => __('True if all conditions match', 'fluentcampaign-pro'),
                        'match_type_any_label' => __('True if any of the conditions match', 'fluentcampaign-pro'),
                        'data_key_label'       => __('IF', 'fluentcampaign-pro'),
                        'condition_label'      => __('Condition', 'fluentcampaign-pro'),
                        'data_value_label'     => __('Match Value', 'fluentcampaign-pro')
                    ],
                    'hide_match_type' => true,
                    'is_multiple_grouping' => true
                ]
            ]
        ];
    }

    public function handle($subscriber, $sequence, $funnelSubscriberId, $funnelMetric)
    {
        $conditionalGroups = Arr::get($sequence->settings, 'conditional_groups', []);

        $funnelSub = FunnelSubscriber::find($funnelSubscriberId);
        $order = false;
        if(Helper::isWooTrigger($funnelSub->source_trigger_name)) {
            $orderId = $funnelSub->source_ref_id;
            $order = wc_get_order($orderId);
        }

        $wooCustomer = wpFluent()->table('wc_customer_lookup')
            ->where('email', $subscriber->email)
            ->when($subscriber->user_id, function ($q) use ($subscriber) {
                $q->orWhere('user_id', $subscriber->user_id);
            })
            ->first();

        $matched = $this->isValidConditions($conditionalGroups, $wooCustomer, $order, $subscriber);

        (new FunnelProcessor())->initChildSequences($sequence, $matched, $subscriber, $funnelSubscriberId, $funnelMetric);
    }

    private function getCustomerOptions()
    {
        return [
            'customer_total_spend'     => [
                'label' => 'Lifetime Order Value',
                'type'  => 'number'
            ],
            'customer_order_count'     => [
                'label' => 'Total Order Count',
                'type'  => 'number'
            ],
            'customer_guest_user'      => [
                'label'   => 'Is guest customer',
                'type'    => 'select',
                'options' => [
                    [
                        'id' => 'yes',
                        'title' => 'Yes'
                    ],
                    [
                        'id' => 'no',
                        'title' => 'No'
                    ]
                ]
            ],
            'customer_billing_country' => [
                'label'      => __('Country', 'fluentcampaign-pro'),
                'type'       => 'option_selector',
                'option_key' => 'countries',
                'multiple'   => true
            ],
            'customer_cat_purchased'   => [
                'label'      => 'Purchased Products from Category',
                'type'       => 'rest_selector',
                'option_key' => 'woo_categories',
                'multiple'   => true
            ],
            'customer_purchased_products' => [
                'label' => 'Purchased Products',
                'type' => 'rest_selector',
                'option_key' => 'woo_products',
                'multiple' => true
            ]
        ];
    }

    private function getOrderOptions()
    {
        return [
            'order_total_value'     => [
                'label' => 'Total Order Value',
                'type'  => 'number'
            ],
            'order_cat_purchased'   => [
                'label'       => 'Purchased From Categories',
                'type'        => 'rest_selector',
                'option_key'  => 'woo_categories',
                'multiple' => true,
                'default'     => []
            ],
            'order_product_ids'   => [
                'label'       => 'Purchased Products',
                'type'        => 'rest_selector',
                'option_key'  => 'woo_products',
                'multiple' => true,
                'default'     => []
            ],
            'order_billing_country' => [
                'label'      => 'Billing Country in the purchase',
                'type'       => 'option_selector',
                'option_key' => 'countries',
                'multiple'   => true,
                'default'    => []
            ],
            'order_shipping_method' => [
                'label'   => 'Shipping Method in the purchase',
                'type'    => 'select',
                'options' => Helper::getShippingMethods(true)
            ],
            'order_payment_gateway' => [
                'label'   => 'Payment Gateway in the purchase',
                'type'    => 'select',
                'options' => Helper::getPaymentGateways(true)
            ]
        ];
    }


    private function isValidConditions($conditionGroups, $wooCustomer, $order, $subscriber)
    {
        if(!$wooCustomer) {
            return false;
        }
        $customerDataCache = [];
        $orderDataCache = [];

        foreach ($conditionGroups as $conditionGroup) {
            $dataValues = [];
            foreach ( $conditionGroup['conditions'] as $item) {
                if(empty($item['data_key']) || empty($item['operator']) || ($item['data_value'] === '' || $item['data_value'] === [])) {
                    continue;
                }
                $key = $item['data_key'];
                if(strpos($key, 'customer_') !== false) {
                    // it's a customer key
                    $dataKey = str_replace('customer_', '', $key);
                    if(!isset($customerDataCache[$dataKey])) {
                        $customerDataCache[$dataKey] = WooDataHelper::getCustomerItem($dataKey, $wooCustomer);
                    }
                    $item['data_key'] = 'customer_'.$dataKey;
                    $dataValues[ $item['data_key']] = $customerDataCache[$dataKey];
                } else if(strpos($key, 'order_') !== false) {
                    if(!$order) {
                        continue;
                    }
                    $dataKey = str_replace('order_', '', $key);
                    if(!isset($orderDataCache[$dataKey])) {
                        $orderDataCache[$dataKey] = WooDataHelper::getOrderItem($dataKey, $order);
                    }
                    $dataValues[ $item['data_key']] = $orderDataCache[$dataKey];
                }
            }

            if( ConditionAssesor::matchAllGroups([$conditionGroup], $dataValues) ) {
                return true;
            }
        }

        return false;
    }

}