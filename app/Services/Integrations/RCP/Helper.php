<?php

namespace FluentCampaign\App\Services\Integrations\RCP;

class Helper
{
    public static function getMembershipLevels()
    {
        $memberships = \rcp_get_subscription_levels();

        $formattedLevels = [];
        foreach ($memberships as $membership) {
            $formattedLevels[] = [
                'id'    => strval($membership->id),
                'title' => $membership->name
            ];
        }

        return $formattedLevels;
    }

    public static function getUserLevels($userId)
    {
        $customer = \rcp_get_customer_by_user_id($userId);
        $levels = $customer->get_memberships([
            'status' => 'active'
        ]);

        if(!$levels) {
            return [];
        }

        $levelIds = [];

        foreach ($levels as $level) {
            $levelIds[] = $level->get_id();
        }

        return array_unique($levelIds);
    }
}