<?php

namespace FluentCampaign\App\Services\Integrations\LifterLms;

use FluentCampaign\App\Services\Funnel\BaseCondition;
use FluentCrm\App\Services\Funnel\FunnelProcessor;
use FluentCrm\Includes\Helpers\Arr;

class LifterHasMembership extends BaseCondition
{
    public function __construct()
    {
        $this->conditionName = 'fcrm_lifter_is_in_membership';
        $this->priority = 30;
        parent::__construct();
    }

    public function getBlock()
    {
        return [
            'title'            => __('Check if the contact has a Membership', 'fluentcampaign-pro'),
            'description'      => __('Conditionally check if contact has an active membership level', 'fluentcampaign-pro'),
            'icon'             => fluentCrmMix('images/funnel_icons/lifter_has_membership.svg'),
            'settings'         => [
                'course_ids' => [],
                'require_completed' => 'no'
            ],
            'reload_on_insert' => true
        ];
    }

    public function getBlockFields()
    {
        return [
            'title'     => __('Check if the contact has a Membership', 'fluentcampaign-pro'),
            'sub_title' => __('Conditionally check if contact has an active membership level', 'fluentcampaign-pro'),
            'fields'    => [
                'membership_ids'        => [
                    'type'        => 'multi-select',
                    'label'       => __('Target Memberships', 'fluentcampaign-pro'),
                    'help'        => __('Select for which Memberships this automation will run', 'fluentcampaign-pro'),
                    'options'     => Helper::getMemberships(),
                    'inline_help' => __('If the contact is in any of the membership levels (active) then it will result as YES', 'fluentcampaign-pro')
                ]
            ]
        ];
    }

    public function handle($subscriber, $sequence, $funnelSubscriberId, $funnelMetric)
    {
        $membershipIds = Arr::get($sequence->settings, 'membership_ids', []);
        $isMember = Helper::isInActiveMembership($membershipIds, $subscriber);

        (new FunnelProcessor())->initChildSequences($sequence, $isMember, $subscriber, $funnelSubscriberId, $funnelMetric);
    }
}
