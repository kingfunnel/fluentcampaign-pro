<?php

namespace FluentCampaign\App\Services\Integrations\BuddyPress;

use FluentCrm\App\Services\Html\TableBuilder;

class BBInit
{
    public function init()
    {
        (new Group())->init();
        (new BBMemberType())->init();

        new BbImporter();

        add_filter('fluentcrm_profile_sections', array($this, 'pushCoursesOnProfile'));
        add_filter('fluencrm_profile_section_buddypress_profile', array($this, 'pushCoursesContent'), 10, 2);


    }


    public function pushCoursesOnProfile($sections)
    {

        $title = __('BuddyPress Groups', 'fluentcampaign-pro');

        if (defined('BP_PLATFORM_VERSION')) {
            $title = __('BuddyBoss Groups', 'fluentcampaign-pro');
        }

        $sections['buddypress_profile'] = [
            'name'    => 'fluentcrm_profile_extended',
            'title'   => $title,
            'handler' => 'route',
            'query'   => [
                'handler' => 'buddypress_profile'
            ]
        ];

        return $sections;
    }

    public function pushCoursesContent($content, $subscriber)
    {

        $title = __('BuddyPress Groups', 'fluentcampaign-pro');

        if (defined('BP_PLATFORM_VERSION')) {
            $title = __('BuddyBoss Groups', 'fluentcampaign-pro');
        }

        $content['heading'] = $title;

        $userId = $subscriber->user_id;

        if (!$userId) {
            $content['content_html'] = '<p>No groups found for this contact</p>';
            return $content;
        }

        $groups = wpFluent()->table('bp_groups_members')
            ->select(['bp_groups_members.*', 'bp_groups.name'])
            ->where('bp_groups_members.user_id', $subscriber->user_id)
            ->join('bp_groups', 'bp_groups.id', '=', 'bp_groups_members.group_id')
            ->orderBy('bp_groups_members.id', 'DESC')
            ->get();

        if (empty($groups)) {
            $content['content_html'] = '<p>No groups found for this contact</p>';
            return $content;
        }

        $tableBuilder = new TableBuilder();
        foreach ($groups as $group) {
            $memberType = 'Member';

            if ($group->is_banned) {
                $memberType = 'Banned';
            } else if ($group->is_admin) {
                $memberType = 'Admin';
            } else if ($group->is_mod) {
                $memberType = 'Moderator';
            }

            $tableBuilder->addRow([
                'id'           => $group->group_id,
                'group'        => $group->name,
                'type'         => $memberType,
                'is_confirmed' => ($group->is_confirmed) ? 'Confirmed' : 'Not Confirmed',
                'last_update'  => date_i18n(get_option('date_format'), strtotime($group->date_modified)),
            ]);
        }

        $tableBuilder->setHeader([
            'id'           => 'Group ID',
            'group'        => 'Group Name',
            'type'         => 'Type',
            'is_confirmed' => 'Confirmation Status',
            'last_update'   => 'Last Update',
        ]);

        $content['content_html'] = $tableBuilder->getHtml();
        return $content;
    }

}