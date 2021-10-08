<?php

namespace FluentCampaign\App\Hooks\Handlers;


use FluentCrm\App\Models\Funnel;
use FluentCrm\App\Models\FunnelSequence;
use FluentCrm\App\Models\Subscriber;
use FluentCrm\App\Services\Funnel\FunnelHelper;
use FluentCrm\App\Services\Helper;
use FluentCrm\App\Services\PermissionManager;
use FluentCrm\Includes\Helpers\Arr;
use FluentCrm\Includes\Request\Request;
use League\Csv\Writer;

class DataExporter
{
    private $request;

    public function exportContacts()
    {
        $this->verifyRequest();

        $this->request = $request = FluentCrm('request');
        $columns = $request->get('columns');
        $customFields = $request->get('custom_fields', []);
        $with = [];
        if(in_array('tags', $columns)) {
            $with[] = 'tags';
        }

        if(in_array('lists', $columns)) {
            $with[] = 'lists';
        }

        $shortBy = $request->get('sort_by', 'id');
        $shortType = $request->get('sort_type', 'ASC');

        $subscribers = Subscriber::orderBy($shortBy, $shortType)
            ->when($this->request->has('tags'), function ($query) {
                $query->filterByTags($this->request->get('tags'));
            })
            ->when($this->request->has('statuses'), function ($query) {
                $query->filterByStatues($this->request->get('statuses'));
            })
            ->when($this->request->has('lists'), function ($query) {
                $query->filterByLists($this->request->get('lists'));
            })
            ->when($this->request->has('search'), function ($query) {
                $query->searchBy($this->request->get('search'));
            })
            ->when($this->request->has('sort_by'), function ($query) {
                $query->orderBy($this->request->get('sort_by'), $this->request->get('sort_type'));
            });

        if($with) {
            $subscribers->with($with);
        }

        if($limit = $request->get('limit')) {
            $subscribers->limit($limit);
        }

        if($offset = $request->get('offset')) {
            $subscribers->offset($offset);
        }

        $subscribers = $subscribers->get();

        $maps = $this->contactColumnMaps();
        $header = Arr::only($maps, $columns);
        $header = array_intersect($maps, $header);

        $insertHeaders = $header;
        $customHeaders = [];
        if($customFields) {
            $allCustomFields = fluentcrm_get_custom_contact_fields();
            foreach ($allCustomFields as $field) {
                if(in_array($field['slug'], $customFields)) {
                    $insertHeaders[$field['slug']] = $field['label'];
                    $customHeaders[] = $field['slug'];
                }
            }
        }

        $writer = Writer::createFromFileObject(new \SplTempFileObject());
        $writer->insertOne(array_values($insertHeaders));

        $rows = [];
        foreach ($subscribers as $subscriber) {
            $row = [];
            foreach ($header as $headerKey => $column) {
                if($headerKey == 'lists' || $headerKey == 'tags') {
                    $strings = [];
                    foreach ($subscriber->{$headerKey} as $list) {
                        $strings[] = $list->title;
                    }
                    $row[] = implode(', ', $strings);
                } else {
                    $row[] = $subscriber->{$headerKey};
                }
            }
            if($customHeaders) {
                $customValues = $subscriber->custom_fields();
                foreach ($customHeaders as $valueKey) {
                    $value = Arr::get($customValues, $valueKey, '');
                    if(is_array($value)) {
                        $value = implode(', ', $value);
                    }
                    $row[] = $value;
                }
            }

            $rows[] = $row;
        }

        $writer->insertAll($rows);
        $writer->output('contact-'.date('Y-m-d_H-i-s').'.csv');
        die();
    }

    public function importFunnel()
    {
        $this->verifyRequest();
        $this->request = FluentCrm('request');
        $files = $this->request->files();
        $file = $files['file'];
        $content = file_get_contents($file);
        $funnel = json_decode($content, true);


        if(empty($funnel['type']) || $funnel['type'] != 'funnels') {
            wp_send_json([
                'message' => __('The provided JSON file is not valid', 'fluentcampaign-pro')
            ], 423);
        }

        $funnelTrigger = $funnel['trigger_name'];
        $triggers = apply_filters('fluentcrm_funnel_triggers', []);

        $funnel['title'] .= ' (Imported @ '.current_time('mysql').')';

        if(!isset($triggers[$funnelTrigger])) {
            wp_send_json([
                'message' => __('The trigger defined in the JSON file is not available on your site.', 'fluentcampaign-pro'),
                'requires' => [
                    'Trigger Name Required: '.$funnelTrigger
                ]
            ], 423);
        }

        $sequences = $funnel['sequences'];
        $formattedSequences = [];

        $blocks = apply_filters('fluentcrm_funnel_blocks', [], (object) $funnel);
        foreach ($sequences as $sequence) {
            $actionName = $sequence['action_name'];

            if(!isset($blocks[$actionName])) {
                wp_send_json([
                    'message' => __('The Block Action defined in the JSON file is not available on your site.', 'fluentcampaign-pro'),
                    'requires' => [
                        'Missing Action: '.$actionName
                    ]
                ], 423);
            }

            $formattedSequences[] = $sequence;
        }

        unset($funnel['sequences']);

        $data = [
            'funnel' => $funnel,
            'blocks' => $blocks,
            'block_fields' => apply_filters('fluentcrm_funnel_block_fields', [], (object) $funnel),
            'funnel_sequences' => $formattedSequences
        ];
        wp_send_json($data, 200);
    }

    private function contactColumnMaps()
    {
        return [
            'id' => __('ID', 'fluentcampaign-pro'),
            'user_id' => __('User ID', 'fluentcampaign-pro'),
            'prefix' => __('Title', 'fluentcampaign-pro'),
            'first_name' => __('First Name', 'fluentcampaign-pro'),
            'last_name' => __('Last Name', 'fluentcampaign-pro'),
            'email' => __('Email', 'fluentcampaign-pro'),
            'timezone' => __('Timezone', 'fluentcampaign-pro'),
            'address_line_1' => __('Address Line 1', 'fluentcampaign-pro'),
            'address_line_2' => __('Address Line 2', 'fluentcampaign-pro'),
            'postal_code' => __('Postal Code', 'fluentcampaign-pro'),
            'city' => __('City', 'fluentcampaign-pro'),
            'state' => __('State', 'fluentcampaign-pro'),
            'country' => __('Country', 'fluentcampaign-pro'),
            'ip' => __('IP Address', 'fluentcampaign-pro'),
            'phone' => __('Phone', 'fluentcampaign-pro'),
            'status' => __('Status', 'fluentcampaign-pro'),
            'contact_type' => __('Contact Type', 'fluentcampaign-pro'),
            'source' => __('Source', 'fluentcampaign-pro'),
            'date_of_birth' => __('Date Of Birth', 'fluentcampaign-pro'),
            'last_activity' => __('Last Activity', 'fluentcampaign-pro'),
            'created_at' => __('Created At', 'fluentcampaign-pro'),
            'updated_at' => __('Created At', 'fluentcampaign-pro'),
            'lists' => __('Lists', 'fluentcampaign-pro'),
            'tags' => __('Tags', 'fluentcampaign-pro')
        ];
    }

    private function verifyRequest()
    {
        $permission = 'fcrm_manage_contacts';
        if(  PermissionManager::currentUserCan($permission) ) {
            return true;
        }

        die('You do not have permission');
    }
}