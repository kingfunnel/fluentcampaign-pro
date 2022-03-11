<?php

namespace FluentCampaign\App\Services\Integrations\LifterLms;

use FluentCampaign\App\Services\Funnel\BaseCondition;
use FluentCrm\App\Services\Funnel\FunnelProcessor;
use FluentCrm\Includes\Helpers\Arr;

class LifterCoursePurchased extends BaseCondition
{
    public function __construct()
    {
        $this->conditionName = 'fcrm_lifter_is_in_course';
        $this->priority = 30;
        parent::__construct();
    }

    public function getBlock()
    {
        return [
            'title'            => __('Check if the contact enroll a course', 'fluentcampaign-pro'),
            'description'      => __('Conditionally check if contact enrolled or completed a course', 'fluentcampaign-pro'),
            'icon'             => fluentCrmMix('images/funnel_icons/lifter_in_course.svg'),
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
            'title'     => __('[LifterLMS] Check if the contact enroll or completed a course', 'fluentcampaign-pro'),
            'sub_title' => __('Conditionally check if contact enrolled or completed a course', 'fluentcampaign-pro'),
            'fields'    => [
                'course_ids'        => [
                    'type'        => 'multi-select',
                    'label'       => __('Target Courses', 'fluentcampaign-pro'),
                    'help'        => __('Select Which Course you want to match for checking enrollment', 'fluentcampaign-pro'),
                    'options'     => Helper::getCourses(),
                    'inline_help' => __('If any of the courses has been enrolled by the contact it will result as YES', 'fluentcampaign-pro')
                ],
                'require_completed' => [
                    'label' => __('Require completed?', 'fluentcampaign-pro'),
                    'type' => 'yes_no_check',
                    'check_label' => __('Also check if the contact completed the course or not.', 'fluentcampaign-pro'),
                    'inline_help' => __('If you enable this then the course need to completed by the contact to result as "YES"', 'fluentcampaign-pro')
                ]
            ]
        ];
    }

    public function handle($subscriber, $sequence, $funnelSubscriberId, $funnelMetric)
    {
        $productIds = Arr::get($sequence->settings, 'course_ids', []);
        $isPurchased = Helper::isInCourses($productIds, $subscriber, Arr::get($sequence->settings, 'require_completed') == 'yes');

        (new FunnelProcessor())->initChildSequences($sequence, $isPurchased, $subscriber, $funnelSubscriberId, $funnelMetric);
    }
}
