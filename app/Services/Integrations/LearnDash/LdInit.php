<?php

namespace FluentCampaign\App\Services\Integrations\LearnDash;

use FluentCrm\App\Models\Tag;
use FluentCrm\App\Services\Html\TableBuilder;

class LdInit
{
    public function init()
    {
        new \FluentCampaign\App\Services\Integrations\LearnDash\CourseEnrollTrigger();
        new \FluentCampaign\App\Services\Integrations\LearnDash\LessonCompletedTrigger();
        new \FluentCampaign\App\Services\Integrations\LearnDash\TopicCompletedTrigger();
        new \FluentCampaign\App\Services\Integrations\LearnDash\CourseCompletedTrigger();
        new \FluentCampaign\App\Services\Integrations\LearnDash\GroupEnrollTrigger();
        new \FluentCampaign\App\Services\Integrations\LearnDash\LearnDashCoursePurchased();
        new \FluentCampaign\App\Services\Integrations\LearnDash\LearnDashIsInGroup();

        // push profile section
        add_filter('fluentcrm_profile_sections', array($this, 'pushCoursesOnProfile'));

        add_filter('fluencrm_profile_section_ld_profile_courses', array($this, 'pushCoursesContent'), 10, 2);

        $usingWpFusion = apply_filters('fluentcrm_using_wpfusion', defined('WP_FUSION_VERSION'));
        if (!$usingWpFusion) {
            add_filter('learndash_settings_fields', array($this, 'addCourseFields'), 10, 2);

            add_action('save_post_sfwd-courses', array($this, 'saveCourseMetaBox'));

            add_action('learndash_update_course_access', array($this, 'maybeCourseEnrolledTags'), 20, 4);
            add_action('learndash_course_completed', array($this, 'maybeCourseCompletedTags'), 20);
        }
    }

    public function pushCoursesOnProfile($sections)
    {
        $sections['ld_profile_courses'] = [
            'name'    => 'fluentcrm_profile_extended',
            'title'   => __('Courses', 'fluent-crm'),
            'handler' => 'route',
            'query'   => [
                'handler' => 'ld_profile_courses'
            ]
        ];

        return $sections;
    }

    public function pushCoursesContent($content, $subscriber)
    {
        $content['heading'] = 'LearnDash Courses';

        $userId = $subscriber->user_id;

        if (!$userId) {
            $content['content_html'] = '<p>No enrolled courses found for this contact</p>';
            return $content;
        }


        $courses = learndash_user_get_enrolled_courses($userId);


        if (empty($courses)) {
            $content['content_html'] = '<p>No enrolled courses found for this contact</p>';
            return $content;
        }

        $enrolledCourses = get_posts([
            'post_status'    => 'publish',
            'post_type'      => 'sfwd-courses',
            'posts_per_page' => 100,
            'post__in'       => $courses,
        ]);

        $tableBuilder = new TableBuilder();
        foreach ($enrolledCourses as $course) {
            $completedAt = get_user_meta($userId, 'course_completed_' . $course->ID, true);
            $startAt = get_user_meta($userId, 'course_' . $course->ID . '_access_from', true);
            $completedSteps = '2';
            $tableBuilder->addRow([
                'id'           => $course->ID,
                'title'        => $course->post_title,
                'status'       => learndash_course_status($course->ID, $userId, false),
                'completed_at' => ($completedAt) ? gmdate('Y-m-d H:i', $completedAt) : '',
                'started_at'   => ($startAt) ? gmdate('Y-m-d H:i', $startAt) : ''
            ]);
        }

        $tableBuilder->setHeader([
            'id'           => 'ID',
            'title'        => 'Course Name',
            'started_at'   => 'Started At',
            'status'       => 'Status',
            'completed_at' => 'Completed At'
        ]);

        $content['content_html'] = $tableBuilder->getHtml();
        return $content;
    }

    public function addCourseFields($fields, $metabox_key)
    {
        if ('learndash-course-access-settings' != $metabox_key) {
            return $fields;
        }

        global $post;

        if (empty($post) || empty($post->ID)) {
            return $fields;
        }

        $tagSettings = wp_parse_args(get_post_meta($post->ID, '_fluentcrm_settings', true), [
            'enrolled_tags'  => [],
            'completed_tags' => []
        ]);

        $formattedTags = [];
        foreach (Tag::get() as $tag) {
            $formattedTags[$tag->id . ' '] = $tag->title; //  WE NEED A SPACE not sure why they could not handle integer as value
        }

        $fields['fcrm_enrolled_tags'] = [
            'name'      => 'fcrm_enrolled_tags',
            'label'     => '[FluentCRM] Apply Tags on course enrollment',
            'type'      => 'multiselect',
            'multiple'  => true,
            'help_text' => 'Selected tags will be applied to the contact on course enrollment',
            'options'   => $formattedTags,
            'value'     => (array)$tagSettings['enrolled_tags'],
            'default'   => [],
        ];

        $fields['fcrm_completed_tags'] = [
            'name'          => 'fcrm_completed_tags',
            'label'         => '[FluentCRM] Apply Tags on course completion',
            'type'          => 'multiselect',
            'multiple'      => true,
            'select_option' => 'Select Tags',
            'help_text'     => 'Selected tags will be applied to the contact on course completion',
            'options'       => $formattedTags,
            'value'         => (array)$tagSettings['completed_tags'],
            'default'       => [],
        ];

        return $fields;
    }

    public function saveCourseMetaBox($postId)
    {
        if ($_POST['post_ID'] != $postId || (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE)) {
            return;
        }

        if (!empty($_POST['learndash-course-access-settings'])) {
            $data = [
                'enrolled_tags'  => [],
                'completed_tags' => []
            ];

            if (!empty($_POST['learndash-course-access-settings']['fcrm_enrolled_tags'])) {
                $data['enrolled_tags'] = $_POST['learndash-course-access-settings']['fcrm_enrolled_tags'];
                unset($_POST['learndash-course-access-settings']['fcrm_enrolled_tags']);
            }

            if (!empty($_POST['learndash-course-access-settings']['fcrm_completed_tags'])) {
                $data['completed_tags'] = $_POST['learndash-course-access-settings']['fcrm_completed_tags'];
                unset($_POST['learndash-course-access-settings']['fcrm_completed_tags']);
            }

            update_post_meta($postId, '_fluentcrm_settings', $data);
        }

    }

    public function maybeCourseEnrolledTags($userId, $courseId, $accessList = [], $isRemoved = false)
    {
        if ($isRemoved) {
            return;
        }
        $settings = get_post_meta($courseId, '_fluentcrm_settings', true);
        if (!$settings || empty($settings['enrolled_tags']) || !is_array($settings['enrolled_tags'])) {
            return false;
        }

        $tags = array_map(function ($tagId) {
            return intval($tagId);
        }, $settings['enrolled_tags']);

        $tags = array_filter($tags);
        if (!$tags) {
            return false;
        }

        Helper::createContactFromLd($userId, $tags);
        return true;
    }

    public function maybeCourseCompletedTags($data)
    {
        $settings = get_post_meta($data['course']->ID, '_fluentcrm_settings', true);
        if (!$settings || empty($settings['completed_tags']) || !is_array($settings['completed_tags'])) {
            return false;
        }

        $tags = array_map(function ($tagId) {
            return intval($tagId);
        }, $settings['completed_tags']);

        $tags = array_filter($tags);
        if (!$tags) {
            return false;
        }

        Helper::createContactFromLd($data['user'], $settings['completed_tags']);
        return true;
    }
}