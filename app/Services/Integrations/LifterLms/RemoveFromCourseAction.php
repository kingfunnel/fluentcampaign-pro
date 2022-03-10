<?php

namespace FluentCampaign\App\Services\Integrations\LifterLms;

use FluentCrm\App\Services\Funnel\BaseAction;
use FluentCrm\App\Services\Funnel\FunnelHelper;
use FluentCrm\Framework\Support\Arr;

class RemoveFromCourseAction extends BaseAction
{
    public function __construct()
    {
        $this->actionName = 'lifter_remove_from_course';
        $this->priority = 20;
        parent::__construct();
    }

    public function getBlock()
    {
        return [
            'category'    => __('LifterLMS', 'fluent-crm'),
            'title'       => __('Remove From a Course', 'fluent-crm'),
            'description' => __('Remove the contact from a specific LMS Course', 'fluent-crm'),
            'icon'        => 'fc-icon-remove_from_course_lms',
            'settings'    => [
                'course_id' => ''
            ]
        ];
    }

    public function getBlockFields()
    {
        return [
            'title'     => __('Remove From a Course', 'fluent-crm'),
            'sub_title' => __('Remove the contact from a specific LMS Course', 'fluent-crm'),
            'fields'    => [
                'course_id' => [
                    'type'        => 'rest_selector',
                    'option_key'  => 'product_selector_lifterlms',
                    'is_multiple' => false,
                    'clearable'   => true,
                    'label'       => __('Select a course that you want to remove from', 'fluent-crm'),
                    'placeholder' => __('Select Course', 'fluent-crm')
                ]
            ]
        ];
    }

    public function handle($subscriber, $sequence, $funnelSubscriberId, $funnelMetric)
    {
        $settings = $sequence->settings;
        $userId = $subscriber->getWpUserId();

        $courseId = Arr::get($settings, 'course_id');

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

        $student->unenroll($courseId);
    }
}