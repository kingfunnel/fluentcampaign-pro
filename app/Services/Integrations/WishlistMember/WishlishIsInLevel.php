<?php

namespace FluentCampaign\App\Services\Integrations\WishlistMember;

use FluentCampaign\App\Services\Funnel\BaseCondition;
use FluentCrm\App\Services\Funnel\FunnelProcessor;
use FluentCrm\Includes\Helpers\Arr;

class WishlishIsInLevel extends BaseCondition
{
    public function __construct()
    {
        $this->conditionName = 'fcrm_wishlist_is_in_level';
        $this->priority = 22;
        parent::__construct();
    }

    public function getBlock()
    {
        return [
            'title'            => __('[WishList Member] Check if the contact is in a Membership Level', 'fluentcampaign-pro'),
            'description'      => __('Check If user in a membership level and run sequences conditionally', 'fluentcampaign-pro'),
            'icon'             => fluentCrmMix('images/funnel_icons/wishlist_in_level.svg'),
            'settings'         => [
                'level_ids' => []
            ],
            'reload_on_insert' => true
        ];
    }

    public function getBlockFields()
    {
        return [
            'title'     => __('[WishList Member] Check if the contact is in a Membership Level', 'fluentcampaign-pro'),
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
        $levels = \wlmapi_get_levels();
        $formattedLevels = [];
        foreach (Arr::get($levels, 'levels.level') as $level) {
            $formattedLevels[] = [
                'id' => strval($level['id']),
                'title' => $level['name']
            ];
        }

        return $formattedLevels;
    }

    private function isInLevel($subscriber, $levelIds)
    {
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
        $levels = wlmapi_get_member_levels($user->ID);

        foreach ($levels as $level) {
            if(in_array($level->Level_ID, $levelIds) && in_array('Active', $level->Status)) {
                return true;
            }
        }

        return false;
    }
}