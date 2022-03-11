<?php

namespace FluentCampaign\App\Services\Funnel\Benchmarks;

use FluentCrm\App\Models\Subscriber;
use FluentCrm\App\Services\Funnel\BaseBenchMark;
use FluentCrm\App\Services\Funnel\FunnelProcessor;
use FluentCrm\Includes\Helpers\Arr;

class EmailSequenceCompletedBenchmark extends BaseBenchMark
{
    public function __construct()
    {
        $this->triggerName = 'fluentcrm_email_sequence_completed';
        $this->actionArgNum = 2;
        $this->priority = 43;
        parent::__construct();
    }

    public function getBlock()
    {
        return [
            'title'            => __('Email Sequence Completed', 'fluentcampaign-pro'),
            'description'      => __('This will run once a selected email sequence is completed for a contact', 'fluentcampaign-pro'),
            'icon'             => fluentCrmMix('images/funnel_icons/link_clicked.svg'),
            'settings'         => [
                'sequence_ids' => '',
                'type'         => 'optional'
            ],
            'reload_on_insert' => false
        ];
    }

    public function getBlockFields($funnel)
    {
        return [
            'title'     => __('Email Sequence Completed', 'fluentcampaign-pro'),
            'sub_title' => __('This will run once a selected email sequence is completed for a contact', 'fluentcampaign-pro'),
            'fields'    => [
                'sequence_ids' => [
                    'type'        => 'option_selectors',
                    'option_key'  => 'email_sequences',
                    'is_multiple' => true,
                    'placeholder' => __('Select Sequences', 'fluentcampaign-pro'),
                    'inline_help' => __('Please select the email sequences that need to be watched for completed', 'fluentcampaign-pro')
                ],
                'type'         => $this->benchmarkTypeField()
            ]
        ];
    }

    public function handle($benchMark, $originalArgs)
    {
        $sequenceId = absint($originalArgs[1]);

        $targetSequenceIds = Arr::get($benchMark->settings, 'sequence_ids', []);

        if (!in_array($sequenceId, $targetSequenceIds)) {
            return false; // Not our sequence
        }

        $subscriberId = absint($originalArgs[0]);
        $subscriber = Subscriber::find($subscriberId);

        if (!$subscriber) {
            return false;
        }

        $funnelProcessor = new FunnelProcessor();
        $funnelProcessor->startFunnelFromSequencePoint($benchMark, $subscriber);
    }
}