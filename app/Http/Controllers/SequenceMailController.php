<?php

namespace FluentCampaign\App\Http\Controllers;

use FluentCrm\App\Http\Controllers\Controller;
use FluentCrm\App\Models\CampaignUrlMetric;
use FluentCampaign\App\Models\Sequence;
use FluentCampaign\App\Models\SequenceMail;
use FluentCrm\App\Models\CampaignEmail;
use FluentCrm\Includes\Helpers\Arr;
use FluentCrm\Includes\Request\Request;

class SequenceMailController extends Controller
{

    public function get(Request $request, $sequenceId, $emailId = 0)
    {
        $with = $request->get('with');

        $sequence = [];
        if (in_array('sequence', $with)) {
            $sequence = Sequence::find($sequenceId);
        }

        $email = SequenceMail::getEmpty();
        if ($emailId) {
            $email = SequenceMail::find($emailId);
        }

        return $this->sendSuccess([
            'sequence' => $sequence,
            'email'    => $email
        ]);

    }

    public function create(Request $request, $sequenceId = 0)
    {
        $email = $request->get('email');

        $emailData = Arr::only($email, [
            'title',
            'design_template',
            'email_subject',
            'email_pre_header',
            'email_body',
            'template_id',
            'settings'
        ]);

        $emailData['title'] = $emailData['email_subject'];
        $emailData['template_id'] = intval($emailData['template_id']);

        $sequence = Sequence::findOrFail($sequenceId);
        $emailData['parent_id'] = $sequence->id;
        $email = SequenceMail::create($emailData);

        $mailerSettings = $sequence->settings['mailer_settings'];
        $email->updateMailerSettings($mailerSettings);

        $this->resetIndexes($sequenceId);

        return $this->sendSuccess([
            'message' => __('Sequence email has been created', 'fluentcampaign-pro'),
            'email'   => $email
        ]);
    }

    public function update(Request $request, $sequenceId, $emailId)
    {
        $email = $request->get('email');

        $emailData = Arr::only($email, [
            'title',
            'design_template',
            'email_subject',
            'email_pre_header',
            'email_body',
            'template_id',
            'settings'
        ]);

        $emailData['title'] = $emailData['email_subject'];
        $emailData['template_id'] = intval($emailData['template_id']);

        $sequence = Sequence::findOrFail($sequenceId);
        $mailerSettings = $sequence->settings['mailer_settings'];
        $emailData['settings']['mailer_settings'] = $mailerSettings;

        $email = SequenceMail::where('parent_id', $sequenceId)->findOrFail($emailId);

        $email->fill($emailData)->save();

        $this->resetIndexes($sequenceId);

        return $this->sendSuccess([
            'message' => __('Sequence email has been updated', 'fluentcampaign-pro'),
            'email'   => $email
        ]);
    }

    public function delete(Request $request, $sequenceId, $emailId)
    {
        SequenceMail::where('parent_id', $sequenceId)->where('id', $emailId)->delete();
        CampaignEmail::where('campaign_id', $emailId)->delete();
        CampaignUrlMetric::where('campaign_id', $emailId)->delete();
        
        do_action('fluentcrm_sequence_email_deleted', $emailId);

        $this->resetIndexes($sequenceId);

        return $this->sendSuccess([
            'message' => __('Email sequence successfully deleted', 'fluentcampaign-pro')
        ]);
    }

    private function resetIndexes($sequenceId)
    {
        
    }
}
