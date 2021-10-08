<?php

namespace FluentCampaign\App\Hooks\Handlers;

use FluentCampaign\App\Models\SequenceMail;
use FluentCrm\App\Models\CampaignEmail;
use FluentCrm\App\Models\CampaignUrlMetric;

class Cleanup
{
    public function deleteSequenceAssets($sequenceId)
    {
        $sequenceEmails = SequenceMail::where('parent_id', $sequenceId)
            ->get();

        SequenceMail::whereIn('id', $sequenceEmails->pluck('id'))->delete();

        foreach ($sequenceEmails as $sequenceEmail) {
            $this->deleteCampaignAssets($sequenceEmail->id);
        }
    }

    public function deleteCampaignAssets($campaignId)
    {
        CampaignEmail::where('id', $campaignId)->delete();
        CampaignUrlMetric::where('campaign_id', $campaignId)->delete();
    }
}