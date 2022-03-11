<?php

namespace FluentCampaign\App\Services\Integrations\Edd;

use FluentCrm\App\Services\Funnel\BaseBenchMark;
use FluentCrm\App\Services\Funnel\FunnelHelper;
use FluentCrm\App\Services\Funnel\FunnelProcessor;
use FluentCrm\Includes\Helpers\Arr;

class EddOrderSuccessBenchmark extends BaseBenchMark
{
    public function __construct()
    {
        $this->triggerName = 'edd_update_payment_status';
        $this->actionArgNum = 3;
        $this->priority = 20;

        parent::__construct();
    }

    public function getBlock()
    {
        return [
            'title'       => __('New Order Success', 'fluentcampaign-pro'),
            'description' => __('This will run once new order will be placed as processing in EDD', 'fluentcampaign-pro'),
            'icon' => fluentCrmMix('images/funnel_icons/new_order_edd.svg'),
            'settings'    => [
                'product_ids'        => [],
                'product_categories' => [],
                'purchase_type'      => 'all',
                'type'               => 'required'
            ]
        ];
    }

    public function getDefaultSettings()
    {
        return [
            'product_ids'        => [],
            'product_categories' => [],
            'purchase_type'      => 'all',
            'type'               => 'required'
        ];
    }

    public function getBlockFields($funnel)
    {
        return [
            'title'     => __('New Order Success in EDD', 'fluentcampaign-pro'),
            'sub_title' => __('This will run once new order will be placed as processing in EDD', 'fluentcampaign-pro'),
            'fields'    => [
                'product_ids'        => [
                    'type'        => 'multi-select',
                    'label'       => __('Target Products', 'fluentcampaign-pro'),
                    'help'        => __('Select for which products this benchmark will run', 'fluentcampaign-pro'),
                    'options'     => Helper::getProducts(),
                    'inline_help' => __('Keep it blank to run to any product purchase', 'fluentcampaign-pro')
                ],
                'product_categories' => [
                    'type'        => 'multi-select',
                    'label'       => __('Target Product Categories', 'fluentcampaign-pro'),
                    'help'        => __('Select for which product category the benchmark will run', 'fluentcampaign-pro'),
                    'options'     => Helper::getCategories(),
                    'inline_help' => __('Keep it blank to run to any category products', 'fluentcampaign-pro')
                ],
                'purchase_type'      => [
                    'type'        => 'radio',
                    'label'       => __('Purchase Type', 'fluentcampaign-pro'),
                    'help'        => __('Select the purchase type', 'fluentcampaign-pro'),
                    'options'     => Helper::purchaseTypeOptions(),
                    'inline_help' => __('For what type of purchase you want to run this benchmark', 'fluentcampaign-pro')
                ],
                'type'               => $this->benchmarkTypeField()
            ]
        ];
    }

    public function handle($benchMark, $originalArgs)
    {
        $paymentId = $originalArgs[0];
        $newStatus = $originalArgs[1];
        $oldStatus = $originalArgs[2];
        if ($newStatus != 'publish' || $newStatus == $oldStatus) {
            return;
        }

        $payment = edd_get_payment($paymentId);

        $conditions = (array) $benchMark->setings;

        if (!$this->isMatched($conditions, $payment)) {
            return; // It's not a match
        }

        $subscriberData = Helper::prepareSubscriberData($payment);

        if (!is_email($subscriberData['email'])) {
            return;
        }
        $subscriberData['status'] = 'subscribed';

        $subscriber = FunnelHelper::createOrUpdateContact($subscriberData);

        $funnelProcessor = new FunnelProcessor();
        $funnelProcessor->startFunnelFromSequencePoint($benchMark, $subscriber, [], [
            'benchmark_value'    => intval($payment->total * 100), // converted to cents
            'benchmark_currency' => $payment->currency,
        ]);
    }

    private function isMatched($conditions, $order)
    {
        $purchaseType = Arr::get($conditions, 'purchase_type');
        return Helper::isPurchaseTypeMatch($order, $purchaseType) && Helper::isPurchaseTypeMatch($order, $purchaseType);
    }
}