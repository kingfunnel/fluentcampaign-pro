<?php

namespace FluentCampaign\App\Services\Funnel\Conditions;

use FluentCampaign\App\Services\Funnel\BaseCondition;
use FluentCrm\App\Services\Funnel\FunnelHelper;
use FluentCrm\App\Services\Funnel\FunnelProcessor;
use FluentCrm\Includes\Helpers\Arr;

class HasUserRole extends BaseCondition
{
    public function __construct()
    {
        $this->conditionName = 'fcrm_has_user_role';
        $this->priority = 30;
        parent::__construct();
    }

    public function getBlock()
    {
        return [
            'title'            => __('Has User Role', 'fluentcampaign-pro'),
            'description'      => __('Check If the contact has specific user role', 'fluentcampaign-pro'),
            'icon'             => fluentCrmMix('images/funnel_icons/has_wp_role.svg'),
            'settings'         => [
                'roles' => []
            ],
            'reload_on_insert' => true
        ];
    }

    public function getBlockFields()
    {
        return [
            'title'     => __('Check If the contact has specific user role', 'fluentcampaign-pro'),
            'sub_title' => __('Check If the contact has specific user role', 'fluentcampaign-pro'),
            'fields'    => [
                'roles' => [
                    'type'        => 'multi-select',
                    'options'     => FunnelHelper::getUserRoles(),
                    'label'       => __('Select Roles', 'fluentcampaign-pro'),
                    'placeholder' => __('Select Roles', 'fluentcampaign-pro'),
                    'inline_help' => __('If any of the selected user roles found in that contact then it will be as "Yes"', 'fluentcampaign-pro')
                ]
            ]
        ];
    }

    public function handle($subscriber, $sequence, $funnelSubscriberId, $funnelMetric)
    {
        $checkingRoles = Arr::get($sequence->settings, 'roles', []);

        $isTrue = false;

        if ($checkingRoles) {
            $user = false;
            if ($subscriber->user_id) {
                $user = get_user_by('ID', $subscriber->user_id);
            }
            if (!$user) {
                $user = get_user_by('email', $subscriber->email);
            }

            if ($user && !$subscriber->user_id) {
                $subscriber->user_id = $user->ID;
                $subscriber->save();
            }

            if ($user) {
                $roles = $user->roles;
                $isTrue = !!array_intersect($roles, $checkingRoles);
            }
        }

        (new FunnelProcessor())->initChildSequences($sequence, $isTrue, $subscriber, $funnelSubscriberId, $funnelMetric);
    }
}
