<?php

namespace FluentCampaign\App\Services\DynamicSegments;

use FluentCrm\App\Models\Subscriber;
use FluentCrm\Includes\Helpers\Arr;

class WooCustomerSegment extends BaseSegment
{
    private $model = null;

    public $slug = 'wc_customers';

    public function getInfo()
    {
        return [
            'id'          => 0,
            'slug'        => $this->slug,
            'is_system'   => true,
            'title'       => __('WooCommerce Customers', 'fluentcampaign-pro'),
            'subtitle' => __('WooCommerce customers who are also in the contact list as subscribed', 'fluentcampaign-pro'),
            'description' => __('This segment contains all your Subscribed contacts which are also your WooCommerce Customers', 'fluentcampaign-pro'),
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
            ->table('wc_customer_lookup')
            ->innerJoin('fc_subscribers', 'fc_subscribers.email', '=', 'wc_customer_lookup.email')
            ->select('fc_subscribers.email');

        $this->model = $query->whereIn('email', $query->subQuery($subQuery));


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