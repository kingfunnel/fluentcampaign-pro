<?php

namespace FluentCampaign\App\Services\Integrations\PMPro;

use FluentCampaign\App\Services\Funnel\BaseCondition;
use FluentCrm\App\Services\Funnel\FunnelProcessor;
use FluentCrm\Includes\Helpers\Arr;

class PMProIsInMembership extends BaseCondition
{
    public function __construct()
    {
        $this->conditionName = 'fcrm_pmpro_is_in_membership';
        $this->priority = 22;
        parent::__construct();
    }

    public function getBlock()
    {
        return [
            'title'            => __('[PMPro] Check if the contact is in a Membership Level', 'fluentcampaign-pro'),
            'description'      => __('Check If user in a membership level and run sequences conditionally', 'fluentcampaign-pro'),
            'icon'             => fluentCrmMix('images/funnel_icons/pmpro_in_membership.svg'),
            'settings'         => [
                'level_ids' => []
            ],
            'reload_on_insert' => true
        ];
    }

    public function getBlockFields()
    {
        return [
            'title'     => __('[PMPro] Check if the contact is in a Membership Level', 'fluentcampaign-pro'),
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
        $levels = \pmpro_getAllLevels(false, false);
        $formattedLevels = [];
        foreach ($levels as $level) {
            $formattedLevels[] = [
                'id' => strval($level->id),
                'title' => $level->name
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

        $levels = pmpro_getMembershipLevelsForUser($user->ID);

        if(!$levels) {
            return false;
        }

        foreach ($levels as $level) {
            if(in_array($level->id, $levelIds)) {
                return true;
            }
        }

        return false;
    }
}