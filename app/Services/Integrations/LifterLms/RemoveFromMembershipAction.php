<?php

namespace FluentCampaign\App\Services\Integrations\LifterLms;

use FluentCrm\App\Services\Funnel\BaseAction;
use FluentCrm\App\Services\Funnel\FunnelHelper;
use FluentCrm\Framework\Support\Arr;

class RemoveFromMembershipAction extends BaseAction
{
    public function __construct()
    {
        $this->actionName = 'lifter_remove_from_membership';
        $this->priority = 20;
        parent::__construct();
    }

    public function getBlock()
    {
        return [
            'category'    => __('LifterLMS', 'fluent-crm'),
            'title'       => __('Remove From a LMS Membership', 'fluent-crm'),
            'description' => __('Remove the contact from a specific LMS Membership Group', 'fluent-crm'),
            'icon'        => 'fc-icon-remove_from_membership_lms',
            'settings'    => [
                'membership_id' => ''
            ]
        ];
    }

    public function getBlockFields()
    {
        return [
            'title'     => __('Remove From a Membership', 'fluent-crm'),
            'sub_title' => __('Remove the contact from a specific LMS Membership', 'fluent-crm'),
            'fields'    => [
                'membership_id' => [
                    'type'        => 'rest_selector',
                    'option_key'  => 'product_selector_lifterlms_groups',
                    'is_multiple' => false,
                    'clearable'   => true,
                    'label'       => __('Select a Membership Group that you want to remove from', 'fluent-crm'),
                    'placeholder' => __('Select Membership', 'fluent-crm')
                ]
            ]
        ];
    }

    public function handle($subscriber, $sequence, $funnelSubscriberId, $funnelMetric)
    {
        $settings = $sequence->settings;
        $userId = $subscriber->getWpUserId();

        $groupId = Arr::get($settings, 'membership_id');

        if (!$userId) {
            $funnelMetric->notes = __('Funnel Skipped because user could not be found', 'fluentcampaign-pro');
            $funnelMetric->save();
            FunnelHelper::changeFunnelSubSequenceStatus($funnelSubscriberId, $sequence->id, 'skipped');
            return false;
        }

        $student = llms_get_student($userId);
        if (!$student) {
            return false;
        }

        $student->unenroll($groupId);
    }
}
