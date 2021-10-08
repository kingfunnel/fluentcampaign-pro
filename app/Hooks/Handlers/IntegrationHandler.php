<?php

namespace FluentCampaign\App\Hooks\Handlers;

use FluentCampaign\App\Services\Funnel\Actions\AddActivityAction;
use FluentCampaign\App\Services\Funnel\Actions\AddEmailSequenceAction;
use FluentCampaign\App\Services\Funnel\Actions\ChangeUserRoleAction;
use FluentCampaign\App\Services\Funnel\Actions\EndFunnel;
use FluentCampaign\App\Services\Funnel\Actions\HTTPSendDataAction;
use FluentCampaign\App\Services\Funnel\Actions\RemoveFromEmailSequenceAction;
use FluentCampaign\App\Services\Funnel\Actions\RemoveFromFunnelAction;
use FluentCampaign\App\Services\Funnel\Actions\SendCampaignEmailAction;
use FluentCampaign\App\Services\Funnel\Actions\UpdateContactPropertyAction;
use FluentCampaign\App\Services\Funnel\Actions\UpdateUserMetaAction;
use FluentCampaign\App\Services\Funnel\Actions\UserRegistrationAction;
use FluentCampaign\App\Services\Funnel\Benchmarks\LinkClickBenchmark;
use FluentCampaign\App\Services\Funnel\Conditions\CheckUserPropCondition;
use FluentCampaign\App\Services\Funnel\Conditions\HasListCondition;
use FluentCampaign\App\Services\Funnel\Conditions\HasTagCondition;
use FluentCampaign\App\Services\Funnel\Conditions\HasUserRole;
use FluentCampaign\App\Services\Funnel\Triggers\UserLoginTrigger;
use FluentCampaign\App\Services\Integrations\Integrations;
use FluentCrm\App\Models\Subscriber;
use FluentCrm\App\Services\Funnel\FunnelHelper;
use FluentCrm\Includes\Helpers\Arr;

class IntegrationHandler
{
    public function init()
    {
        $this->initTriggers();
        $this->initAddons();
        $this->initFunnelActions();
        $this->initBenchmarks();
        $this->initConditionals();
    }

    private function initAddons()
    {
        (new Integrations())->init();;
    }

    private function initConditionals()
    {
        new HasTagCondition();
        new HasListCondition();
        new CheckUserPropCondition();
        new HasUserRole();
    }

    private function initFunnelActions()
    {
        new AddEmailSequenceAction();
        new RemoveFromEmailSequenceAction();
        new SendCampaignEmailAction();
        new RemoveFromFunnelAction();
        new EndFunnel();
        new  UpdateContactPropertyAction();
        new  UserRegistrationAction();
        new  UpdateUserMetaAction();
        new  ChangeUserRoleAction();
        new  HTTPSendDataAction();
        new  AddActivityAction();
    }

    private function initBenchmarks()
    {
        new LinkClickBenchmark();
    }

    public function maybeAutoAlterTags($userId, $newRole)
    {
        $settings = fluentcrm_get_option('role_based_tagging_settings', []);
        if(!$settings || Arr::get($settings, 'status') != 'yes' || !$tagMappings = Arr::get($settings, 'tag_mappings.'.$newRole)) {
            return false;
        }

        if(empty($tagMappings['add_tags']) && empty($tagMappings['remove_tags'])) {
            return false;
        }

        $user = get_user_by('ID', $userId);
        $subscriber = Subscriber::where('user_id', $userId)
            ->orWhere('email', $user->user_email)
            ->first();

        if(!$subscriber) {
            $subscriberData = FunnelHelper::prepareUserData($userId);
            $subscriber = FunnelHelper::createOrUpdateContact($subscriberData);
        } else {
            $subscriber->user_id = $user->ID;
            $subscriber->save();
        }

        $tagToBeAdded = Arr::get($tagMappings, 'add_tags', []);
        $tagsToBeRemoved = Arr::get($tagMappings, 'remove_tags', []);

        if($tagToBeAdded) {
            $subscriber->attachTags($tagToBeAdded);
        }

        if($tagsToBeRemoved) {
            $subscriber->detachTags($tagsToBeRemoved);
        }

        return true;
    }

    public function initTriggers()
    {
        new UserLoginTrigger();
    }
}