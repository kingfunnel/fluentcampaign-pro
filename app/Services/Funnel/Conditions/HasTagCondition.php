<?php

namespace FluentCampaign\App\Services\Funnel\Conditions;

use FluentCampaign\App\Services\Funnel\BaseCondition;
use FluentCrm\App\Services\Funnel\FunnelProcessor;
use FluentCrm\Includes\Helpers\Arr;

class HasTagCondition extends BaseCondition
{
    public function __construct()
    {
        $this->conditionName = 'fcrm_has_contact_tag';
        $this->priority = 21;
        parent::__construct();
    }

    public function getBlock()
    {
        return [
            'title'            => __('Has in Selected tags', 'fluentcampaign-pro'),
            'description'      => __('Check If the contact has specific tags', 'fluentcampaign-pro'),
            'icon'             => fluentCrmMix('images/funnel_icons/has_tag.svg'),
            'settings'         => [
                'tags' => []
            ],
            'reload_on_insert' => true
        ];
    }

    public function getBlockFields()
    {
        return [
            'title'     => __('Check If the contact has specific tags', 'fluentcampaign-pro'),
            'sub_title' => __('Select the tags that you want to check for a contact', 'fluentcampaign-pro'),
            'fields'    => [
                'tags' => [
                    'type'        => 'option_selectors',
                    'option_key'  => 'tags',
                    'is_multiple' => true,
                    'creatable'   => true,
                    'label'       => __('Select Tags', 'fluentcampaign-pro'),
                    'placeholder' => __('Select Tag', 'fluentcampaign-pro'),
                    'inline_help' => __('If any of the selected tags found in that contact then it will be as "Yes"', 'fluentcampaign-pro')
                ]
            ]
        ];
    }

    public function handle($subscriber, $sequence, $funnelSubscriberId, $funnelMetric)
    {
        $requiredTags = Arr::get($sequence->settings, 'tags', []);
        $hasTag = $subscriber->hasAnyTagId($requiredTags);
        (new FunnelProcessor())->initChildSequences($sequence, $hasTag, $subscriber, $funnelSubscriberId, $funnelMetric);
    }
}
