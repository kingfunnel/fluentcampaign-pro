<?php

namespace FluentCampaign\App\Services\Funnel\Actions;

use FluentCampaign\App\Models\Sequence;
use FluentCampaign\App\Models\SequenceTracker;
use FluentCrm\App\Services\Funnel\BaseAction;
use FluentCrm\App\Services\Funnel\FunnelHelper;
use FluentValidator\Arr;

class AddEmailSequenceAction extends BaseAction
{
    public function __construct()
    {
        $this->actionName = 'add_to_email_sequence';
        $this->priority = 15;
        parent::__construct();
    }

    public function getBlock()
    {
        return [
            'category' => 'Email',
            'title'       => __('Set Sequence Emails', 'fluentcampaign-pro'),
            'description' => __('Send Automated Emails based on your Sequence settings', 'fluentcampaign-pro'),
            'icon'        => fluentCrmMix('images/funnel_icons/set_sequence.svg'),
            'settings'    => [
                'sequence_id' => '',
                'restart_if_exist' => 'no'
            ]
        ];
    }

    public function getBlockFields()
    {
        return [
            'title'     => __('Set Sequence Emails', 'fluentcampaign-pro'),
            'sub_title' => __('Select which sequence will be assigned to this contact', 'fluentcampaign-pro'),
            'fields'    => [
                'sequence_id' => [
                    'type'        => 'option_selectors',
                    'option_key'  => 'email_sequences',
                    'is_multiple' => false,
                    'label'       => __('Select Email Sequence', 'fluentcampaign-pro'),
                    'placeholder' => __('Select Sequence Email', 'fluentcampaign-pro')
                ],
                'restart_if_exist' => [
                    'type'        => 'yes_no_check',
                    'label'       => 'Re-assign Sequence Emails?',
                    'check_label' => __('Restart the sequence emails if the contact already in the email sequence', 'fluentcampaign-pro')
                ]
            ]
        ];
    }

    public function handle($subscriber, $sequence, $funnelSubscriberId, $funnelMetric)
    {
        if (empty($sequence->settings['sequence_id'])) {
            FunnelHelper::changeFunnelSubSequenceStatus($funnelSubscriberId, $sequence->id, 'skipped');
            return;
        }

        $sequenceId = intval($sequence->settings['sequence_id']);

        $sequenceModel = Sequence::find($sequenceId);

        if ($sequenceModel) {
            $exist = SequenceTracker::where('campaign_id', $sequenceId)
                ->where('subscriber_id', $subscriber->id)
                ->count();

            if($exist) {
                $willRestart = Arr::get($sequence->settings, 'restart_if_exist') == 'yes';
                if($willRestart) {
                    SequenceTracker::where('campaign_id', $sequence->id)
                        ->where('subscriber_id', $subscriber->id)
                        ->delete();
                    $exist = false;
                }
            }

            if (!$exist) {
                $sequenceModel->subscribe([$subscriber]);
            }
        }

        //FunnelHelper::changeFunnelSubSequenceStatus($funnelSubscriberId, $sequence->id);
    }
}
