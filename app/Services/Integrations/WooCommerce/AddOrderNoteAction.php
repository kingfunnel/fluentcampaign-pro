<?php

namespace FluentCampaign\App\Services\Integrations\WooCommerce;

use FluentCrm\App\Models\FunnelSubscriber;
use FluentCrm\App\Services\Funnel\BaseAction;
use FluentCrm\App\Services\Funnel\FunnelHelper;

class AddOrderNoteAction extends BaseWooAction
{
    public function __construct()
    {
        $this->actionName = 'fcrm_add_order_note';
        $this->priority = 99;
        parent::__construct();
    }

    public function getBlock()
    {
        return [
            'category'    => 'WooCommerce',
            'title'       => __('Add Order Note', 'fluentcampaign-pro'),
            'description' => __('Add Note to WooCommerce Order', 'fluentcampaign-pro'),
            'icon'        => fluentCrmMix('images/funnel_icons/change_woo_status.svg'),
            'settings'    => [
                'note'      => '',
                'note_type' => 'private'
            ]
        ];
    }

    public function getBlockFields()
    {
        $orderStatuses = wc_get_order_statuses();

        $formattedOptions = [
            [
                'id'    => 'private',
                'title' => 'Private Note'
            ],
            [
                'id'    => 'customer',
                'title' => 'Note to Customer'
            ]
        ];

        return [
            'title'     => __('Add Order Note', 'fluentcampaign-pro'),
            'sub_title' => __('Add Note to WooCommerce Order', 'fluentcampaign-pro'),
            'fields'    => [
                'note'      => [
                    'type'       => 'input-text-popper',
                    'field_type' => 'textarea',
                    'label'      => __('Order Note', 'fluentcampaign-pro'),
                    'help'       => __('Type the note that you want to add to the reference order. You can also use smart tags', 'fluentcampaign-pro')
                ],
                'note_type' => [
                    'type'    => 'radio',
                    'label'   => __('New Order Status', 'fluentcampaign-pro'),
                    'help'    => __('Select Note Type for the reference Order.', 'fluentcampaign-pro'),
                    'options' => $formattedOptions
                ]
            ]
        ];
    }

    /**
     * @param $order \WC_Order
     * @param $subscriber \FluentCrm\App\Models\Subscriber
     * @param $sequence \FluentCampaign\App\Models\Sequence
     * @param $funnelSubscriberId int
     * @param $funnelMetric \FluentCrm\App\Models\FunnelMetric
     * @return boolean
     */
    public function handleAction($order, $subscriber, $sequence, $funnelSubscriberId, $funnelMetric)
    {
        $settings = $sequence->settings;
        if (empty($settings['note'])) {
            FunnelHelper::changeFunnelSubSequenceStatus($funnelSubscriberId, $sequence->id, 'skipped');
            return false;
        }

        $note = sanitize_textarea_field($settings['note']);

        $note = \FluentCrm\Includes\Parser\Parser::parse($note, $subscriber);

        $noteType = $settings['note_type'];

        $byCustomer = $noteType == 'customer';

        $order->add_order_note($note, $byCustomer);

        //FunnelHelper::changeFunnelSubSequenceStatus($funnelSubscriberId, $sequence->id);
    }

}