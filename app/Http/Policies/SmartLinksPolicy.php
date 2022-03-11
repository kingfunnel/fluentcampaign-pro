<?php

namespace FluentCampaign\App\Http\Policies;

use FluentCrm\Includes\Core\Policy;
use FluentCrm\Includes\Request\Request;

class SmartLinksPolicy extends Policy
{
    /**
     * Check user permission for any method
     * @param  \FluentCrm\Includes\Request\Request $request
     * @return Boolean
     */
    public function verifyRequest(Request $request)
    {
        if($request->method() == 'GET') {
            return $this->currentUserCan('fcrm_manage_settings') || $this->currentUserCan('fcrm_manage_contacts');
        }

        return $this->currentUserCan('fcrm_manage_settings');
    }
}
