<?php

namespace FluentCampaign\App\Services\Integrations\LifterLms;


use FluentCrm\App\Models\Tag;
use FluentCrm\App\Services\Funnel\FunnelHelper;
use FluentCrm\App\Services\Html\TableBuilder;

class LifterInit
{
    public function init()
    {
        new \FluentCampaign\App\Services\Integrations\LifterLms\CourseEnrollTrigger();
        new \FluentCampaign\App\Services\Integrations\LifterLms\MembershipEnrollTrigger();
        new \FluentCampaign\App\Services\Integrations\LifterLms\LessonCompletedTrigger();
        new \FluentCampaign\App\Services\Integrations\LifterLms\CourseCompletedTrigger();
        new \FluentCampaign\App\Services\Integrations\LifterLms\LifterCoursePurchased();
        new \FluentCampaign\App\Services\Integrations\LifterLms\LifterHasMembership();

        // push profile section
        add_filter('fluentcrm_profile_sections', array($this, 'pushCoursesOnProfile'));

        add_filter('fluencrm_profile_section_lifter_profile_courses', array($this, 'pushCoursesContent'), 10, 2);


        // if wp fusion installed we don't want to boot this
        // As they have already integration with FluentCRM
        $usingWpFusion = apply_filters('fluentcrm_using_wpfusion', defined('WP_FUSION_VERSION'));

        if (!$usingWpFusion) {
            /*
             * Course
             */
            add_filter('llms_metabox_fields_lifterlms_course_options', array($this, 'addCourseMetaBox'));
            add_action('llms_metabox_after_save_lifterlms-course-options', array($this, 'saveCourseMetaBoxData'), 20, 1);
            add_action('llms_user_enrolled_in_course', array($this, 'maybeCourseEnrolledTags'), 50, 2);
            add_action('lifterlms_course_completed', array($this, 'maybeCourseCompletedTags'), 50, 2);

            // lesson
            add_filter( 'llms_metabox_fields_lifterlms_lesson', array( $this, 'addLessonMetaBox' ) );
            add_action('llms_metabox_after_save_lifterlms-lesson', array($this, 'saveLessonMetaBoxData'), 20, 1);
            add_action( 'lifterlms_lesson_completed', array( $this, 'maybeLessonCompletedTags' ), 10, 2 );

        }
    }

    public function pushCoursesOnProfile($sections)
    {
        $sections['lifter_contact_courses'] = [
            'name'    => 'fluentcrm_profile_extended',
            'title'   => __('Courses', 'fluent-crm'),
            'handler' => 'route',
            'query'   => [
                'handler' => 'lifter_profile_courses'
            ]
        ];

        return $sections;
    }

    public function pushCoursesContent($content, $subscriber)
    {
        $content['heading'] = 'LifterLMS Courses';

        $student_id = $subscriber->user_id;

        if (!$student_id) {
            $content['content_html'] = '<p>No enrolled courses found for this contact</p>';
            return $content;
        }


        if (!llms_current_user_can('view_lifterlms_reports', $student_id)) {
            $content['content_html'] = '<p>You do not have permission to access this student\'s reports</p>';
            return $content;
        }

        $student = llms_get_student($student_id);

        if (!$student) {
            $content['content_html'] = '<p>No enrolled courses found for this contact</p>';
            return $content;
        }

        $courses = $student->get_courses();

        if (empty($courses['results'])) {
            $content['content_html'] = '<p>No enrolled courses found for this contact</p>';
            return $content;
        }

        $enrolledCourses = get_posts([
            'post_status'    => 'publish',
            'post_type'      => 'course',
            'posts_per_page' => 100,
            'post__in'       => $courses['results'],
        ]);

        $tableBuilder = new TableBuilder();
        foreach ($enrolledCourses as $course) {
            $tableBuilder->addRow([
                'id'              => $course->ID,
                'title'           => $course->post_title,
                'enrollment_date' => $student->get_enrollment_date($course->ID, 'enrolled'),
                'status'          => llms_get_enrollment_status_name($student->get_enrollment_status($course->ID)),
                'grade'           => $student->get_grade($course->ID),
                'progress'        => $student->get_progress($course->ID, 'course') . '%',
                'completed_at'    => $student->get_completion_date($course->ID)
            ]);
        }

        $tableBuilder->setHeader([
            'id'              => 'ID',
            'title'           => 'Course Name',
            'enrollment_date' => 'Enrolled At',
            'status'          => 'Status',
            'grade'           => 'Grade',
            'progress'        => 'Progress',
            'completed_at'    => 'Completed At'
        ]);

        $content['content_html'] = $tableBuilder->getHtml();
        return $content;
    }

    public function addCourseMetaBox($metabox)
    {
        global $post;
        if ($post->post_type != 'course') {
            return $metabox;
        }

        $formattedTags = [];
        foreach (Tag::get() as $tag) {
            $formattedTags[] = [
                'key'   => $tag->id,
                'title' => $tag->title
            ];
        }

        $tagSettings = wp_parse_args(get_post_meta($post->ID, '_fluentcrm_settings', true), [
            'enrolled_tags'  => [],
            'completed_tags' => []
        ]);

        $metabox['fluentcrm'] = array(
            'title'  => 'FluentCRM',
            'fields' => array(
                [
                    'class'           => 'select4',
                    'data_attributes' => array(
                        'placeholder' => 'Select Tags',
                    ),
                    'desc'            => __('Selected tags will be applied to the contact on course enrollment.', 'fluentcampaign-pro'),
                    'id'              => '_fluentcrm_settings[enrolled_tags]',
                    'label'           => __('Apply Tags on course enrollment', 'fluentcampaign-pro'),
                    'multi'           => true,
                    'type'            => 'select',
                    'value'           => $formattedTags,
                    'selected'        => $tagSettings['enrolled_tags'],
                ],
                [
                    'class'           => 'select4',
                    'data_attributes' => array(
                        'placeholder' => 'Select Tags',
                    ),
                    'desc'            => __('Selected tags will be applied to the contact on course completion.', 'fluentcampaign-pro'),
                    'id'              => '_fluentcrm_settings[completed_tags]',
                    'label'           => __('Apply Tags on course completion', 'fluentcampaign-pro'),
                    'multi'           => true,
                    'type'            => 'select',
                    'value'           => $formattedTags,
                    'selected'        => $tagSettings['completed_tags'],
                ]
            ),
        );

        return $metabox;
    }

    public function addLessonMetaBox($metabox)
    {
        global $post;

        global $post;
        if ($post->post_type != 'lesson') {
            return $metabox;
        }

        $formattedTags = [];
        foreach (Tag::get() as $tag) {
            $formattedTags[] = [
                'key'   => $tag->id,
                'title' => $tag->title
            ];
        }

        $tagSettings = wp_parse_args(get_post_meta($post->ID, '_fluentcrm_settings', true), [
            'lesson_completed_tags'  => []
        ]);

        $metabox['fluentcrm'] = array(
            'title'  => 'FluentCRM',
            'fields' => array(
                [
                    'class'           => 'select4',
                    'data_attributes' => array(
                        'placeholder' => 'Select Tags',
                    ),
                    'desc'            => __('Selected tags will be applied to the contact on lesson completed.', 'fluentcampaign-pro'),
                    'id'              => '_fluentcrm_settings[lesson_completed_tags]',
                    'label'           => __('Apply Tags on Course Completed', 'fluentcampaign-pro'),
                    'multi'           => true,
                    'type'            => 'select',
                    'value'           => $formattedTags,
                    'selected'        => $tagSettings['lesson_completed_tags'],
                ]
            ),
        );

        return $metabox;
    }

    public function saveCourseMetaBoxData($postId)
    {
        $action = llms_filter_input(INPUT_POST, 'action', FILTER_SANITIZE_STRING);
        if ('inline-save' === $action) {
            return null;
        }

        $settings = [
            'enrolled_tags'  => [],
            'completed_tags' => []
        ];

        if (isset($_POST['_fluentcrm_settings'])) {
            $settings = $_REQUEST['_fluentcrm_settings'];
        }

        update_post_meta($postId, '_fluentcrm_settings', $settings);
        return $settings;
    }

    public function saveLessonMetaBoxData($postId)
    {
        $action = llms_filter_input(INPUT_POST, 'action', FILTER_SANITIZE_STRING);
        if ('inline-save' === $action) {
            return null;
        }

        $settings = [
            'lesson_completed_tags'  => []
        ];

        if (isset($_POST['_fluentcrm_settings'])) {
            $settings = $_REQUEST['_fluentcrm_settings'];
        }

        update_post_meta($postId, '_fluentcrm_settings', $settings);
        return $settings;
    }

    public function maybeCourseEnrolledTags($userId, $courseId)
    {
        $settings = get_post_meta($courseId, '_fluentcrm_settings', true);
        if (!$settings || empty($settings['enrolled_tags']) || !is_array($settings['enrolled_tags'])) {
            return false;
        }

        Helper::createContactFromLifter($userId, $settings['enrolled_tags']);

        return true;
    }

    public function maybeCourseCompletedTags($userId, $courseId)
    {
        $settings = get_post_meta($courseId, '_fluentcrm_settings', true);
        if (!$settings || empty($settings['completed_tags']) || !is_array($settings['completed_tags'])) {
            return false;
        }

        Helper::createContactFromLifter($userId, $settings['completed_tags']);
        return true;
    }

    public function maybeLessonCompletedTags($userId, $courseId)
    {
        $settings = get_post_meta($courseId, '_fluentcrm_settings', true);
        if (!$settings || empty($settings['lesson_completed_tags']) || !is_array($settings['lesson_completed_tags'])) {
            return false;
        }

        Helper::createContactFromLifter($userId, $settings['lesson_completed_tags']);
        return true;
    }

}