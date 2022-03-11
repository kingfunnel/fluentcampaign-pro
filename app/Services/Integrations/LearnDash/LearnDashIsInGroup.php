<?php

namespace FluentCampaign\App\Services\Integrations\LearnDash;

use FluentCampaign\App\Services\Funnel\BaseCondition;
use FluentCrm\App\Services\Funnel\FunnelProcessor;
use FluentCrm\Includes\Helpers\Arr;

class LearnDashIsInGroup extends BaseCondition
{
    public function __construct()
    {
        $this->conditionName = 'fcrm_learndhash_is_in_group';
        $this->priority = 30;
        parent::__construct();
    }

    public function getBlock()
    {
        return [
            'title'            => __('[LearnDash] Check if the contact is in a group', 'fluentcampaign-pro'),
            'description'      => __('Conditionally check if contact is in a group', 'fluentcampaign-pro'),
            'icon'             => fluentCrmMix('images/funnel_icons/ld_in_group.svg'),
            'settings'         => [
                'group_ids' => []
            ],
            'reload_on_insert' => true
        ];
    }

    public function getBlockFields()
    {
        return [
            'title'     => __('[LearnDash] Check if the contact is in a group', 'fluentcampaign-pro'),
            'sub_title' => __('Conditionally check if contact is in a group', 'fluentcampaign-pro'),
            'fields'    => [
                'group_ids'        => [
                    'type'        => 'multi-select',
                    'label'       => __('Target Groups', 'fluentcampaign-pro'),
                    'help'        => __('Select Which Group you want to match for checking', 'fluentcampaign-pro'),
                    'options'     => Helper::getGroups(),
                    'inline_help' => __('If any of the groups has been enrolled by the contact it will result as YES', 'fluentcampaign-pro')
                ]
            ]
        ];
    }

    public function handle($subscriber, $sequence, $funnelSubscriberId, $funnelMetric)
    {
        $groupIds = Arr::get($sequence->settings, 'group_ids', []);
        $isTrue = Helper::isInGroups($groupIds, $subscriber);

        (new FunnelProcessor())->initChildSequences($sequence, $isTrue, $subscriber, $funnelSubscriberId, $funnelMetric);
    }
}
