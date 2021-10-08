<?php

namespace FluentCampaign\App\Hooks\Handlers;

use FluentCampaign\App\Models\Sequence;
use FluentCampaign\App\Models\SequenceMail;
use FluentCampaign\App\Models\SequenceTracker;
use FluentCrm\App\Models\Subscriber;

class EmailScheduleHandler
{
    private $subscribersCache = [];

    private $sequenceCache = [];

    public function handle()
    {
        $processTrackers = SequenceTracker::ofNextTrackers()->limit(200)->get();

        $sequenceModel = new Sequence();

        foreach ($processTrackers as $tracker) {
            $nextItems = $this->getNextItems($tracker);
            if (!empty($nextItems['currents'])) {
                $subscriber = $this->getSubscriber($tracker->subscriber_id);
                $sequenceModel->attachEmails([$subscriber], $nextItems['currents'], $nextItems['next'], $tracker);
            } else {
                $tracker->status = 'completed';
                $tracker->save();
            }
        }
    }

    private function getSubscriber($id)
    {
        if (!isset($this->subscribersCache[$id])) {
            $this->subscribersCache[$id] = Subscriber::find($id);
        }

        return $this->subscribersCache[$id];
    }

    private function getNextItems($tracker)
    {
        $lastSequenceId = $tracker->last_sequence_id;

        if (isset($this->sequenceCache[$lastSequenceId])) {
            return $this->sequenceCache[$lastSequenceId];
        }

        $lastSequence = $tracker->last_sequence;

        $sequenceEmails = SequenceMail::where('parent_id', $tracker->campaign_id)
            ->where('delay', '>', $lastSequence->delay)
            ->orderBy('delay', 'ASC')
            ->get();

        if ($sequenceEmails->isEmpty()) {
            return [
                'next'     => null,
                'currents' => []
            ];
        }

        $firstSequence = $sequenceEmails[0];
        $immediateSequences = [];
        $nextSequence = null;

        foreach ($sequenceEmails as $sequence) {
            if ($sequence->delay == $firstSequence->delay) {
                $immediateSequences[] = $sequence;
            } else {
                if (!$nextSequence) {
                    $nextSequence = $sequence;
                }
                if ($sequence->delay < $nextSequence->delay) {
                    $nextSequence = $sequence;
                }
            }
        }

        $this->sequenceCache[$lastSequenceId] = [
            'next'     => $nextSequence,
            'currents' => $immediateSequences
        ];

        return $this->sequenceCache[$lastSequenceId];
    }
}
