<?php

namespace FluentCampaign\App\Services\Integrations\TutorLms;

use FluentCrm\App\Services\Funnel\FunnelHelper;

class Helper
{
    public static function getCourses()
    {
        $courses = get_posts(array(
            'post_type'   => 'courses',
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


    public static function isInCourses($courseIds, $subscriber)
    {
        if (!$courseIds) {
            return false;
        }

        $userId = $subscriber->user_id;
        if (!$userId) {
            $user = get_user_by('email', $subscriber->email);
            if ($user) {
                $userId = $user->ID;
            } else {
                return false;
            }
        }

        $course = wpFluent()->table('posts')
            ->where('post_type', 'tutor_enrolled')
            ->whereIn('post_parent', $courseIds)
            ->where('post_author', $userId)
            ->first();

        if($course) {
            return true;
        }

        return false;
    }

    public static function getUserCourses($userId)
    {
        $courses = wpFluent()->table('posts')
            ->select(['post_parent'])
            ->where('post_type', 'tutor_enrolled')
            ->where('post_author', $userId)
            ->get();

        $courseIds = [];
        foreach ($courses as $course) {
            $courseIds[] = $course->post_parent;
        }

        return $courseIds;
    }

    public static function createContactFromTutor($userId, $tags = [])
    {
        $subscriberData = FunnelHelper::prepareUserData($userId);
        if (empty($subscriberData['email'])) {
            return false;
        }

        $subscriber = FunnelHelper::getSubscriber($subscriberData['email']);

        if(!$subscriber) {
            $subscriberData['source'] = 'TutorLMS';
            $subscriber = FunnelHelper::createOrUpdateContact($subscriberData);
        }

        if($tags) {
            $subscriber->attachTags($tags);
        }

        return $subscriber;
    }
}
