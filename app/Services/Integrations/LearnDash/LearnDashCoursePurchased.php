<?php

namespace FluentCampaign\App\Services\Integrations\LearnDash;

use FluentCampaign\App\Services\Funnel\BaseCondition;
use FluentCrm\App\Services\Funnel\FunnelProcessor;
use FluentCrm\Includes\Helpers\Arr;

class LearnDashCoursePurchased extends BaseCondition
{
    public function __construct()
    {
        $this->conditionName = 'fcrm_learndhash_is_in_course';
        $this->priority = 30;
        parent::__construct();
    }

    public function getBlock()
    {
        return [
            'title'            => __('[LearnDash] Check if the contact enroll a course', 'fluentcampaign-pro'),
            'description'      => __('Conditionally check if contact enrolled or completed a course', 'fluentcampaign-pro'),
            'icon'             => fluentCrmMix('images/funnel_icons/ld_in_course.svg'),
            'settings'         => [
                'course_ids' => []
            ],
            'reload_on_insert' => true
        ];
    }

    public function getBlockFields()
    {
        return [
            'title'     => __('[LearnDash] Check if the contact enroll or completed a course', 'fluentcampaign-pro'),
            'sub_title' => __('Conditionally check if contact enrolled or completed a course', 'fluentcampaign-pro'),
            'fields'    => [
                'course_ids'        => [
                    'type'        => 'multi-select',
                    'label'       => __('Target Courses', 'fluentcampaign-pro'),
                    'help'        => __('Select Which Course you want to match for checking enrollment', 'fluentcampaign-pro'),
                    'options'     => Helper::getCourses(),
                    'inline_help' => __('If any of the courses has been enrolled by the contact it will result as YES', 'fluentcampaign-pro')
                ]
            ]
        ];
    }

    public function handle($subscriber, $sequence, $funnelSubscriberId, $funnelMetric)
    {
        $productIds = Arr::get($sequence->settings, 'course_ids', []);
        $isPurchased = Helper::isInCourses($productIds, $subscriber);

        (new FunnelProcessor())->initChildSequences($sequence, $isPurchased, $subscriber, $funnelSubscriberId, $funnelMetric);
    }
}
