<?php

namespace FluentCampaign\App\Services\DynamicSegments;

use FluentCrm\App\Models\Subscriber;
use FluentCrm\Includes\Helpers\Arr;

class PMProMembersSegment extends BaseSegment
{

    private $model = null;

    public $slug = 'pmpro_memberships';

    public function getInfo()
    {
        return [
            'id'          => 0,
            'slug'        => $this->slug,
            'is_system'   => true,
            'title'       => __('Paid Membership Members', 'fluentcampaign-pro'),
            'subtitle' => __('Paid Membership Members customers who are also in the contact list as subscribed', 'fluentcampaign-pro'),
            'description' => __('This segment contains all your Subscribed contacts which are also your Paid Membership Members', 'fluentcampaign-pro'),
            'settings'    => []
        ];
    }

    public function getCount()
    {
        return $this->getModel()->count();
    }

    public function getModel($segment = [])
    {
        if($this->model) {
            return $this->model;
        }

        $query = Subscriber::where('status', 'subscribed');
        $subQuery = $query->getQuery()
            ->table('pmpro_memberships_users')
            ->innerJoin('fc_subscribers', 'fc_subscribers.user_id', '=', 'pmpro_memberships_users.user_id')
            ->select('fc_subscribers.user_id');

        $this->model = $query->whereIn( 'user_id', $query->subQuery($subQuery) );

        return $this->model;
    }

    public function getSegmentDetails($segment, $id, $config)
    {
        $segment = $this->getInfo();

        if(Arr::get($config, 'model')) {
            $segment['model'] = $this->getModel($segment);
        }

        if(Arr::get($config, 'subscribers')) {
            $segment['subscribers'] = $this->getSubscribers($config);
        }
        $segment['contact_count'] = $this->getCount();
        return $segment;
    }
}