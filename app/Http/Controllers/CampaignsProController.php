<?php

namespace FluentCampaign\App\Http\Controllers;

use FluentCrm\App\Http\Controllers\Controller;
use FluentCrm\App\Models\Campaign;
use FluentCrm\App\Models\CampaignEmail;
use FluentCrm\App\Models\CampaignUrlMetric;
use FluentCrm\App\Models\Subscriber;
use FluentCrm\Includes\Request\Request;

class CampaignsProController extends Controller
{
    public function resendFailedEmails(Request $request, $campaignId)
    {
        $failedCount = CampaignEmail::where('campaign_id', $campaignId)->where('status', 'failed')->count();

        if (!$failedCount) {
            return $this->sendError([
                'message' => __('Sorry no failed campaign emails found', 'fluentcampaign-pro')
            ]);
        }

        $campaign = Campaign::findOrFail($campaignId);

        CampaignEmail::where('campaign_id', $campaignId)->where('status', 'failed')
            ->update([
                'status'       => 'scheduled',
                'note'         => __('Added to resend from failed', 'fluentcampaign-pro'),
                'scheduled_at' => current_time('mysql')
            ]);

        $campaign->status = 'working';
        $campaign->save();

        return [
            'message' =>  sprintf(__('%d Emails has been scheduled to resend', 'fluentcampaign-pro'), $failedCount)
        ];
    }

    public function resendEmails(Request $request, $campaignId)
    {
        $campaign = Campaign::withoutGlobalScopes()->findOrFail($campaignId);
        $emailIds = $request->get('email_ids');
        if (!$emailIds) {
            return $this->sendError([
                'message' => __('Sorry! No emails found', 'fluentcampaign-pro')
            ]);
        }
        $emails = CampaignEmail::where('campaign_id', $campaignId)
            ->with('subscriber')
            ->whereIn('status', ['sent', 'failed'])
            ->whereIn('id', $emailIds)->get();

        if ($emails->isEmpty()) {
            return $this->sendError([
                'message' => __('Sorry! No emails found', 'fluentcampaign-pro')
            ]);
        }

        foreach ($emails as $email) {
            if (!$email->subscriber) {
                continue;
            }
            if (!$email->email_body) {
                $email->email_body = $campaign->email_body;
            }
            if (!$email->email_body) {
                continue;
            }
            $email->status = 'scheduled';
            $email->is_parsed = 0;
            $email->note = 'Manually resent';
            $email->scheduled_at = current_time('mysql');
            $email->save();
            do_action('fluentcrm_process_contact_jobs', $email->subscriber);
        }

        return [
            'message' => __('Email has been resent', 'fluentcampaign-pro')
        ];
    }

    public function doTagActions(Request $request, $campaignId)
    {
        $campaign = Campaign::findOrFail($campaignId);
        if ($campaign->status != 'archived') {
            return $this->sendError([
                'message' => __('You can do this action if campaign is in archived status only', 'fluentcampaign-pro')
            ]);
        }

        $this->validate($request->all(), [
            'action_type'     => 'required',
            'tags'            => 'required',
            'activity_type'   => 'required',
            'processing_page' => 'required|integer'
        ]);

        $actionType = $request->get('action_type');
        $tags = $request->get('tags');
        $activityType = $request->get('activity_type');
        $linkIds = $request->get('link_ids');

        $processingPage = intval($request->get('processing_page'));
        $limit = apply_filters('fluentcrm_campaign_action_limit', 50);
        $offset = ($processingPage - 1) * $limit;
        $subscriberIds = [];
        $count = false;
        // Let's filter our subscribers
        if ($activityType == 'email_clicked') {
            if(!$linkIds) {
                return $this->sendError([
                    'message' => __('Links are required', 'fluentcampaign-pro')
                ]);
            }

            $urlMetricsQuery = CampaignUrlMetric::where('campaign_id', $campaignId)
                ->select('subscriber_id')
                ->whereIn('url_id', $linkIds)
                ->groupBy('subscriber_id');

            if ($processingPage == 1) {
                $count = $urlMetricsQuery->count();
            }

            $subscriberIds = $urlMetricsQuery->offset($offset)
                ->limit($limit)
                ->get()->pluck('subscriber_id');

            $subscribers = Subscriber::whereIn('id', $subscriberIds)
                ->where('status', 'subscribed')
                ->get();
        }
        else if ($activityType == 'email_open' || $activityType == 'email_not_open') {
            $isOpenValue = 0;
            if($activityType == 'email_open') {
                $isOpenValue = 1;
            }

            $campaignEmailQuery = CampaignEmail::where('campaign_id', $campaignId)
                ->select('subscriber_id')
                ->groupBy('subscriber_id')
                ->where('is_open', $isOpenValue);
            if ($processingPage == 1) {
                $count = $campaignEmailQuery->count();
            }

            $subscriberIds = $campaignEmailQuery->offset($offset)
                ->limit($limit)
                ->get()->pluck('subscriber_id');

            $subscribers = Subscriber::whereIn('id', $subscriberIds)
                ->where('status', 'subscribed')
                ->get();
        }
        else {
            return $this->sendError([
                'message' => __('invalid selection', 'fluentcampaign-pro')
            ]);
        }

        if($actionType == 'add_tags') {
            foreach ($subscribers as $subscriber) {
                $subscriber->attachTags($tags);
            }
        } else if($actionType == 'remove_tags') {
            foreach ($subscribers as $subscriber) {
                $subscriber->detachTags($tags);
            }
        }

        $totalSubscribers = count($subscribers);

        return [
            'processed_page' => $processingPage,
            'processed_contacts' => $totalSubscribers,
            'has_more' => !!$totalSubscribers,
            'total_count' => $count,
            'subscriber_ids' => $subscriberIds
        ];
    }
}
