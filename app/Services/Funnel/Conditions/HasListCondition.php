<?php

namespace FluentCampaign\App\Services\Funnel\Conditions;

use FluentCampaign\App\Services\Funnel\BaseCondition;
use FluentCrm\App\Services\Funnel\FunnelProcessor;
use FluentCrm\Includes\Helpers\Arr;

class HasListCondition extends BaseCondition
{
    public function __construct()
    {
        $this->conditionName = 'fcrm_has_contact_list';
        $this->priority = 22;
        parent::__construct();
    }

    public function getBlock()
    {
        return [
            'title'            => __('Has in Selected Lists', 'fluentcampaign-pro'),
            'description'      => __('Check If the contact has specific lists', 'fluentcampaign-pro'),
            'icon'             => fluentCrmMix('images/funnel_icons/has_list.svg'),
            'settings'         => [
                'tags' => []
            ],
            'reload_on_insert' => true
        ];
    }

    public function getBlockFields()
    {
        return [
            'title'     => __('Check If the contact has specific lists', 'fluentcampaign-pro'),
            'sub_title' => __('Select the lists that you want to check for a contact', 'fluentcampaign-pro'),
            'fields'    => [
                'lists' => [
                    'type'        => 'option_selectors',
                    'option_key'  => 'lists',
                    'is_multiple' => true,
                    'creatable'   => true,
                    'label'       => __('Select Lists', 'fluentcampaign-pro'),
                    'placeholder' => __('Select List', 'fluentcampaign-pro'),
                    'inline_help' => __('If any of the selected lists found in that contact then it will be as "Yes"', 'fluentcampaign-pro')
                ]
            ]
        ];
    }

    public function handle($subscriber, $sequence, $funnelSubscriberId, $funnelMetric)
    {
        $requiredLists = Arr::get($sequence->settings, 'lists', []);
        $hasList = $subscriber->hasAnyListId($requiredLists);

        (new FunnelProcessor())->initChildSequences($sequence, $hasList, $subscriber, $funnelSubscriberId, $funnelMetric);
    }
}
