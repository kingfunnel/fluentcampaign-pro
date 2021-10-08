<?php

namespace FluentCampaign\App\Services\Integrations\RCP;

use FluentCampaign\App\Services\Funnel\BaseCondition;
use FluentCrm\App\Services\Funnel\FunnelProcessor;
use FluentCrm\Includes\Helpers\Arr;

class RCPIsInMembership extends BaseCondition
{
    public function __construct()
    {
        $this->conditionName = 'fcrm_rcp_is_in_membership';
        $this->priority = 22;
        parent::__construct();
    }

    public function getBlock()
    {
        return [
            'title'            => __('[RCP] Check if the contact is in a Membership Level', 'fluentcampaign-pro'),
            'description'      => __('Check If user in a membership level and run sequences conditionally', 'fluentcampaign-pro'),
            'icon'             => fluentCrmMix('images/funnel_icons/rcp_in_membership.svg'),
            'settings'         => [
                'level_ids' => []
            ],
            'reload_on_insert' => true
        ];
    }

    public function getBlockFields()
    {
        return [
            'title'     => __('[RCP] Check if the contact is in a Membership Level', 'fluentcampaign-pro'),
            'sub_title' => __('Check If user in a membership level and run sequences conditionally', 'fluentcampaign-pro'),
            'fields'    => [
                'level_ids'        => [
                    'type'        => 'multi-select',
                    'label'       => __('Target Levels', 'fluentcampaign-pro'),
                    'help'        => __('Select Which Level you want to match for this conditional split', 'fluentcampaign-pro'),
                    'options'     => $this->getMembershipLevels(),
                    'inline_help' => __('If the contact is in any of the selected levels then the result will be as YES', 'fluentcampaign-pro')
                ]
            ]
        ];
    }

    public function handle($subscriber, $sequence, $funnelSubscriberId, $funnelMetric)
    {
        $levelIds = Arr::get($sequence->settings, 'level_ids', []);

        $isTrue = $this->isInLevel($subscriber, $levelIds);

        (new FunnelProcessor())->initChildSequences($sequence, $isTrue, $subscriber, $funnelSubscriberId, $funnelMetric);
    }

    private function getMembershipLevels()
    {
        $memberships = \rcp_get_subscription_levels();

        $formattedLevels = [];
        foreach ($memberships as $membership) {
            $formattedLevels[] = [
                'id' => strval($membership->id),
                'title' => $membership->name
            ];
        }

        return $formattedLevels;
    }

    private function isInLevel($subscriber, $levelIds)
    {
        if(!$levelIds) {
            return false;
        }
        $user = false;
        if($subscriber->user_id) {
            $user = get_user_by('ID', $subscriber->user_id);
        }
        if(!$user) {
            $user = get_user_by('email', $subscriber->email);
        }

        if(!$user) {
            return false;
        }

        $customer = rcp_get_customer_by_user_id($user->ID);
        $levels = $customer->get_memberships([
            'status' => 'active'
        ]);

        if(!$levels) {
            return false;
        }

        foreach ($levels as $level) {
            $id = $level->get_id();
            if(in_array($id, $levelIds)) {
                return true;
            }
        }

        return false;
    }
}