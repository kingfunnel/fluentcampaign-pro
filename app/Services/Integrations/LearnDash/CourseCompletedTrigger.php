<?php

namespace FluentCampaign\App\Services\Integrations\LearnDash;

use FluentCrm\App\Services\Funnel\BaseTrigger;
use FluentCrm\App\Services\Funnel\FunnelHelper;
use FluentCrm\App\Services\Funnel\FunnelProcessor;
use FluentCrm\Includes\Helpers\Arr;

class CourseCompletedTrigger extends BaseTrigger
{
    public function __construct()
    {
        $this->triggerName = 'learndash_course_completed';
        $this->priority = 18;
        $this->actionArgNum = 1;
        parent::__construct();
    }

    public function getTrigger()
    {
        return [
            'category'    => 'LearnDash',
            'label'       => __('Completes a Course', 'fluentcampaign-pro'),
            'description' => 'This Funnel will start when a student completes a course'
        ];
    }

    public function getFunnelSettingsDefaults()
    {
        return [
            'subscription_status' => 'subscribed'
        ];
    }

    public function getSettingsFields($funnel)
    {
        return [
            'title'     => __('Completes a Course', 'fluentcampaign-pro'),
            'sub_title' => __('This Funnel will start when a student completes a Course', 'fluentcampaign-pro'),
            'fields'    => [
                'subscription_status' => [
                    'type'        => 'option_selectors',
                    'option_key'  => 'editable_statuses',
                    'is_multiple' => false,
                    'label'       => __('Subscription Status', 'fluentcampaign-pro'),
                    'placeholder' => __('Select Status', 'fluentcampaign-pro')
                ],
                'subscription_status_info' => [
                    'type' => 'html',
                    'info' => '<b>'.__('An Automated double-optin email will be sent for new subscribers', 'fluentcampaign-pro').'</b>',
                    'dependency'  => [
                        'depends_on'    => 'subscription_status',
                        'operator' => '=',
                        'value'    => 'pending'
                    ]
                ]
            ]
        ];
    }

    public function getFunnelConditionDefaults($funnel)
    {
        return [
            'update_type'   => 'update', // skip_all_actions, skip_update_if_exist
            'course_ids'    => []
        ];
    }

    public function getConditionFields($funnel)
    {
        return [
            'update_type'   => [
                'type'    => 'radio',
                'label'   => __('If Contact Already Exist?', 'fluentcampaign-pro'),
                'help'    => __('Please specify what will happen if the subscriber already exist in the database', 'fluentcampaign-pro'),
                'options' => FunnelHelper::getUpdateOptions()
            ],
            'course_ids'    => [
                'type'        => 'multi-select',
                'label'       => __('Target Courses', 'fluentcampaign-pro'),
                'help'        => __('Select for which Courses this automation will run', 'fluentcampaign-pro'),
                'options'     => Helper::getCourses(),
                'is_multiple' => true,
                'inline_help' => __('Keep it blank to run to any Lesson', 'fluentcampaign-pro')
            ]
        ];
    }

    public function handle($funnel, $originalArgs)
    {
        $data = $originalArgs[0];

        $subscriberData = FunnelHelper::prepareUserData($data['user']);

        $subscriberData['source'] = 'LearnDash';

        if (empty($subscriberData['email']) || !is_email($subscriberData['email'])) {
            return;
        }

        $courseId = $data['course']->ID;

        $willProcess = $this->isProcessable($funnel, $courseId, $subscriberData);

        Helper::startProcessing($this->triggerName, $willProcess, $funnel, $subscriberData, $originalArgs, $courseId);
    }

    private function isProcessable($funnel, $courseId, $subscriberData)
    {
        $conditions = $funnel->conditions;
        // check update_type
        $updateType = Arr::get($conditions, 'update_type');

        $subscriber = FunnelHelper::getSubscriber($subscriberData['email']);
        if ($subscriber && $updateType == 'skip_all_if_exist') {
            return false;
        }

        // check run_only_one
        if ($subscriber && FunnelHelper::ifAlreadyInFunnel($funnel->id, $subscriber->id)) {
            return false;
        }

        // check the products ids
        if ($conditions['course_ids']) {
            return in_array($courseId, $conditions['course_ids']);
        }

        return true;
    }
}
