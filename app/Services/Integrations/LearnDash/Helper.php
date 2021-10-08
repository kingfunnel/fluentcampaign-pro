<?php

namespace FluentCampaign\App\Services\Integrations\LearnDash;

use FluentCrm\App\Services\Funnel\FunnelHelper;
use FluentCrm\App\Services\Funnel\FunnelProcessor;

class Helper
{
    public static function getCourses()
    {
        $courses = get_posts(array(
            'post_type' => 'sfwd-courses',
            'numberposts' => -1
        ));

        $formattedCourses = [];
        foreach ($courses as $course) {
            $formattedCourses[] = [
                'id'    => strval($course->ID),
                'title' => $course->post_title
            ];
        }

        return $formattedCourses;
    }

    public static function getLessonsByCourse($courseId)
    {
        if(!$courseId) {
            return [];
        }


        $lessons = learndash_get_lesson_list($courseId);;
        $formattedLessons = [];
        foreach ($lessons as $lesson) {
            $formattedLessons[] = [
                'id'    => strval($lesson->ID),
                'title' => $lesson->post_title
            ];
        }
        return $formattedLessons;
    }


    public static function getTopicsByCourseLesson($courseId, $lessonId)
    {
        if(!$courseId || !$lessonId) {
            return [];
        }

        $topics = learndash_get_topic_list($lessonId, $courseId);

        $formattedTopics = [];
        foreach ($topics as $topic) {
            $formattedTopics[] = [
                'id' => strval($topic->ID),
                'title' => $topic->post_title
            ];
        }

        return $formattedTopics;

    }

    public static function getGroups()
    {
        $groups = get_posts(array(
            'post_type' => 'groups',
            'numberposts' => -1
        ));

        $formattedGroups = [];
        foreach ($groups as $group) {
            $formattedGroups[] = [
                'id'    => strval($group->ID),
                'title' => $group->post_title
            ];
        }

        return $formattedGroups;
    }

    public static function getStudentAddress($userId)
    {
        if(!$userId) {
            return [];
        }
        return [
            'address_line_1' => get_user_meta($userId, 'billing_address_1', true),
            'address_line_2' => get_user_meta($userId, 'billing_address_2', true),
            'postal_code'    => get_user_meta($userId, 'billing_postcode', true),
            'city'           => get_user_meta($userId, 'billing_city', true),
            'state'          => get_user_meta($userId, 'billing_state', true),
            'country'        => get_user_meta($userId, 'billing_country', true),
        ];
    }

    public static function getLessonsByCourseGroup()
    {
        $courses = get_posts(array(
            'post_type' => 'sfwd-courses',
            'numberposts' => -1
        ));

        $groups = [];
        foreach ($courses as $course) {
            $group = [
                'title'   => $course->post_title .' - '. $course->ID,
                'slug'    => $course->post_name.$course->ID,
                'options' => []
            ];

            $lessons = learndash_get_lesson_list($course->ID);;

            foreach ($lessons as $lesson) {
                $group['options'][] = [
                    'id'    => strval($lesson->ID),
                    'title' => $lesson->post_title
                ];
            }
            $groups[] = $group;
        }
        return $groups;
    }

    public static function startProcessing($triggerName, $willProcess, $funnel, $subscriberData, $originalArgs, $sourceRef = 0)
    {
        $willProcess = apply_filters('fluentcrm_funnel_will_process_' . $triggerName, $willProcess, $funnel, $subscriberData, $originalArgs);
        if (!$willProcess) {
            return;
        }

        $subscriberData = wp_parse_args($subscriberData, $funnel->settings);

        $subscriberData['status'] = $subscriberData['subscription_status'];
        unset($subscriberData['subscription_status']);

        (new FunnelProcessor())->startFunnelSequence($funnel, $subscriberData, [
            'source_trigger_name' => $triggerName,
            'source_ref_id'       => $sourceRef,
        ]);

    }

    public static function isInCourses($courseIds, $subscriber)
    {
        if(!$courseIds) {
            return false;
        }

        $userId = $subscriber->user_id;
        if(!$userId) {
            $user = get_user_by('email', $subscriber->email);
            if($user) {
                $userId = $user->ID;
            } else {
                return false;
            }
        }

        $courses = learndash_user_get_enrolled_courses($userId);

        if(!$courses) {
            return false;
        }

        return !!array_intersect($courseIds, $courses);
    }

    public static function isInGroups($groupIds, $subscriber)
    {
        if(!$groupIds) {
            return false;
        }

        $userId = $subscriber->user_id;
        if(!$userId) {
            $user = get_user_by('email', $subscriber->email);
            if($user) {
                $userId = $user->ID;
            } else {
                return false;
            }
        }

        $groups = learndash_get_users_group_ids($userId);

        if(!$groups) {
            return false;
        }

        return !!array_intersect($groups, $groupIds);
    }

    public static function createContactFromLd($userId, $tags)
    {
        $subscriberData = FunnelHelper::prepareUserData($userId);
        if (empty($subscriberData['email']) || !is_email($subscriberData['email'])) {
            return false;
        }

        $subscriber = FunnelHelper::getSubscriber($subscriberData['email']);

        if(!$subscriber) {
            $subscriberData['source'] = 'LearnDash';
            $subscriber = FunnelHelper::createOrUpdateContact($subscriberData);
        }

        if($tags) {
            $subscriber->attachTags($tags);
        }

        return $subscriber;
    }
}
