<?php

namespace FluentCampaign\App\Http\Policies;

use FluentCrm\Includes\Core\Policy;
use FluentCrm\Includes\Request\Request;

class DynamicSegmentPolicy extends Policy
{
    /**
     * Check user permission for any method
     * @param  \FluentCrm\Includes\Request\Request $request
     * @return Boolean
     */
    public function verifyRequest(Request $request)
    {
        return $this->currentUserCan('fcrm_manage_contact_cats');
    }
}
