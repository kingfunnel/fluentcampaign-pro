<?php

namespace FluentCampaign\App\Services\Integrations\TutorLms;

use FluentCrm\App\Services\Funnel\BaseTrigger;
use FluentCrm\App\Services\Funnel\FunnelHelper;
use FluentCrm\App\Services\Funnel\FunnelProcessor;
use FluentCrm\Includes\Helpers\Arr;

class LessonCompletedTrigger extends BaseTrigger
{
    public function __construct()
    {
        $this->triggerName = 'lifterlms_lesson_completed';
        $this->priority = 12;
        $this->actionArgNum = 2;
        parent::__construct();
    }

    public function getTrigger()
    {
        return [
            'category'    => 'TutorLMS',
            'label'       => __('Student Complete a Lesson', 'fluentcampaign-pro'),
            'description' => __('This Funnel will start a student completes a lesson', 'fluentcampaign-pro')
        ];
    }

    public function getFunnelSettingsDefaults()
    {
        return [
            'lists'               => [],
            'tags'                => [],
            'subscription_status' => 'subscribed'
        ];
    }

    public function getSettingsFields($funnel)
    {
        return [
            'title'     => __('Student Complete a Lesson in LifterLMS', 'fluentcampaign-pro'),
            'sub_title' => __('This Funnel will start when a student completes a lesson', 'fluentcampaign-pro'),
            'fields'    => [
                'lists'               => [
                    'type'        => 'option_selectors',
                    'option_key'  => 'lists',
                    'is_multiple' => true,
                    'label'       => __('Assign to Lists', 'fluentcampaign-pro'),
                    'placeholder' => __('Select Lists', 'fluentcampaign-pro')
                ],
                'tags'                => [
                    'type'        => 'option_selectors',
                    'option_key'  => 'tags',
                    'is_multiple' => true,
                    'label'       => __('Assign to Tags', 'fluentcampaign-pro'),
                    'placeholder' => __('Select Tags', 'fluentcampaign-pro')
                ],
                'subscription_status' => [
                    'type'        => 'option_selectors',
                    'option_key'  => 'editable_statuses',
                    'is_multiple' => false,
                    'label'       => __('Subscription Status', 'fluentcampaign-pro'),
                    'placeholder' => __('Select Status', 'fluentcampaign-pro')
                ]
            ]
        ];
    }

    public function getFunnelConditionDefaults($funnel)
    {
        return [
            'update_type'   => 'update', // skip_all_actions, skip_update_if_exist
            'lesson_ids'    => []
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
            'lesson_ids'    => [
                'type'        => 'grouped-select',
                'label'       => __('Target Lessons', 'fluentcampaign-pro'),
                'help'        => __('Select for which Lessons this automation will run', 'fluentcampaign-pro'),
                'options'     => Helper::getLessonsByCourseGroup(),
                'is_multiple' => true,
                'inline_help' => __('Keep it blank to run to any Lesson', 'fluentcampaign-pro')
            ]
        ];
    }

    public function handle($funnel, $originalArgs)
    {
        $userId = $originalArgs[0];
        $lessonId = $originalArgs[1];

        $subscriberData = FunnelHelper::prepareUserData($userId);

        $subscriberData['source'] = 'LifterLMS';

        $subscriberData = array_merge($subscriberData, Helper::getStudentAddress($userId));

        if (empty($subscriberData['email'])) {
            return;
        }

        $willProcess = $this->isProcessable($funnel, $lessonId, $subscriberData);

        $willProcess = apply_filters('fluentcrm_funnel_will_process_' . $this->triggerName, $willProcess, $funnel, $subscriberData, $originalArgs);
        if (!$willProcess) {
            return;
        }

        $subscriberData = wp_parse_args($subscriberData, $funnel->settings);

        $subscriberData['status'] = $subscriberData['subscription_status'];
        unset($subscriberData['subscription_status']);

        // it's new so let's create new subscriber
        $subscriber = FunnelHelper::createOrUpdateContact($subscriberData);

        (new FunnelProcessor())->startSequences($subscriber, $funnel, [
            'source_trigger_name' => $this->triggerName,
            'source_ref_id' => $lessonId
        ]);
    }

    private function isProcessable($funnel, $lessonId, $subscriberData)
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
        if ($conditions['lesson_ids']) {
            return in_array($lessonId, $conditions['lesson_ids']);
        }

        return true;
    }
}
