<?php

namespace FluentCampaign\App\Services\Funnel\Actions;

use FluentCrm\App\Services\Funnel\BaseAction;
use FluentCrm\App\Services\Funnel\FunnelHelper;
use FluentCrm\Includes\Helpers\Arr;
use FluentCrm\Includes\Parser\Parser;

class UpdateUserMetaAction extends BaseAction
{
    public function __construct()
    {
        $this->actionName = 'fcrm_update_user_meta';
        $this->priority = 99;
        parent::__construct();
    }

    public function getBlock()
    {
        return [
            'category'    => 'WordPress',
            'title'       => __('Update WP User Meta', 'fluentcampaign-pro'),
            'description' => __('Update WordPress User Meta Data', 'fluentcampaign-pro'),
            'icon'        => fluentCrmMix('images/funnel_icons/wp_user_meta.svg'),
            'settings'    => [
                'meta_properties' => [
                    [
                        'data_key'   => '',
                        'data_value' => ''
                    ]
                ]
            ]
        ];
    }

    public function getBlockFields()
    {

        return [
            'title'     => __('Update WP User Meta', 'fluentcampaign-pro'),
            'sub_title' => __('Update WordPress User Meta Data', 'fluentcampaign-pro'),
            'fields'    => [
                'meta_properties'              => [
                    'label'                  => __('User Meta Mapping', 'fluentcampaign-pro'),
                    'type'                   => 'text-value-multi-properties',
                    'data_key_label'         => 'User Meta Key',
                    'data_value_label'       => 'User Meta Value',
                    'data_value_placeholder' => 'Meta Value',
                    'data_key_placeholder'   => 'Meta key',
                    'help'                   => 'Please provide the meta key and meta value. You can use smart tags too',
                    'value_input_type'       => 'text-popper'
                ],
            ]
        ];
    }

    public function handle($subscriber, $sequence, $funnelSubscriberId, $funnelMetric)
    {
        $user = get_user_by('email', $subscriber->email);
        if (!$user) {
            $funnelMetric->notes = 'Funnel Skipped because no user found with the email address';
            $funnelMetric->save();
            FunnelHelper::changeFunnelSubSequenceStatus($funnelSubscriberId, $sequence->id, 'skipped');
            return false;
        }

        $settings = $sequence->settings;

        $userMetas = [];

        foreach ($settings['meta_properties'] as $pair) {
            if (empty($pair['data_key']) || empty($pair['data_value'])) {
                continue;
            }
            $userMetas[sanitize_text_field($pair['data_key'])] = Parser::parse($pair['data_value'], $subscriber);
        }

        $userMetas = array_filter($userMetas);
        foreach ($userMetas as $metaKey => $metaValue) {
            update_user_meta($user->ID, $metaKey, $metaValue);
        }

        // FunnelHelper::changeFunnelSubSequenceStatus($funnelSubscriberId, $sequence->id);
    }
}