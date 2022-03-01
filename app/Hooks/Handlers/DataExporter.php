<?php

namespace FluentCampaign\App\Hooks\Handlers;

use FluentCrm\App\Models\Subscriber;
use FluentCrm\App\Models\SubscriberNote;
use FluentCrm\App\Services\ContactsQuery;
use FluentCrm\App\Services\Funnel\FunnelHelper;
use FluentCrm\App\Services\PermissionManager;
use FluentCrm\Framework\Support\Arr;

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
        if (in_array('tags', $columns)) {
            $with[] = 'tags';
        }

        if (in_array('lists', $columns)) {
            $with[] = 'lists';
        }

        $filterType = $this->request->get('filter_type', 'simple');

        if ($filterType == 'advanced') {
            $queryArgs = [
                'filter_type'        => 'advanced',
                'filters_groups_raw' => $this->request->get('advanced_filters'),
                'search'             => $this->request->get('search', ''),
                'sort_by'            => $this->request->get('sort_by', 'id'),
                'sort_type'          => $this->request->get('sort_type', 'DESC')
            ];
        } else {
            $queryArgs = [
                'filter_type' => 'simple',
                'search'      => $this->request->get('search', ''),
                'sort_by'     => $this->request->get('sort_by', 'id'),
                'sort_type'   => $this->request->get('sort_type', 'DESC'),
                'tags'        => $this->request->get('tags', []),
                'statuses'    => $this->request->get('statuses', []),
                'lists'       => $this->request->get('lists', [])
            ];
        }

        $queryArgs['with'] = $with;

        if ($limit = $request->get('limit')) {
            $queryArgs['limit'] = intval($limit);
        }

        if ($offset = $request->get('offset')) {
            $queryArgs['offset'] = intval($offset);
        }

        $commerceColumns = $this->request->get('commerce_columns', []);

        if ($commerceColumns) {
            $queryArgs['has_commerce'] = true;
        }

        $subscribers = (new ContactsQuery($queryArgs))->get();

        $maps = $this->contactColumnMaps();
        $header = Arr::only($maps, $columns);
        $header = array_intersect($maps, $header);

        $insertHeaders = $header;
        $customHeaders = [];
        if ($customFields) {
            $allCustomFields = fluentcrm_get_custom_contact_fields();
            foreach ($allCustomFields as $field) {
                if (in_array($field['slug'], $customFields)) {
                    $insertHeaders[$field['slug']] = $field['label'];
                    $customHeaders[] = $field['slug'];
                }
            }
        }

        if ($commerceColumns) {
            foreach ($commerceColumns as $column) {
                $insertHeaders['_commerce_' . $column] = ucwords(implode(' ', explode('_', $column)));
            }
        }


        $writer = $this->getCsvWriter();
        $writer->insertOne(array_values($insertHeaders));

        $rows = [];
        foreach ($subscribers as $subscriber) {
            $row = [];
            foreach ($header as $headerKey => $column) {
                if ($headerKey == 'lists' || $headerKey == 'tags') {
                    $strings = [];
                    foreach ($subscriber->{$headerKey} as $list) {
                        $strings[] = $list->title;
                    }
                    $row[] = implode(', ', $strings);
                } else {
                    $row[] = $subscriber->{$headerKey};
                }
            }
            if ($customHeaders) {
                $customValues = $subscriber->custom_fields();
                foreach ($customHeaders as $valueKey) {
                    $value = Arr::get($customValues, $valueKey, '');
                    if (is_array($value)) {
                        $value = implode(', ', $value);
                    }
                    $row[] = $value;
                }
            }

            if ($commerceColumns) {
                foreach ($commerceColumns as $column) {
                    if($subscriber->commerce_by_provider) {
                        $row[] = $subscriber->commerce_by_provider->{$column};
                    } else {
                        $row[] = '';
                    }
                }
            }

            $rows[] = $row;
        }

        $writer->insertAll($rows);
        $writer->output('contact-' . date('Y-m-d_H-i-s') . '.csv');
        die();
    }

    public function exportNotes()
    {
        $this->verifyRequest();
        $this->request = FluentCrm('request');

        $contactId = $this->request->get('subscriber_id');

        $notes = SubscriberNote::where('subscriber_id', $contactId)
            ->orderBy('id', 'DESC')
            ->with('added_by')
            ->get();

        $writer = $this->getCsvWriter();
        $writer->insertOne([
            'Id',
            'Title',
            'Description',
            'Status',
            'Type',
            'Added By',
            'Created At'
        ]);

        $rows = [];
        foreach ($notes as $note) {
            $rows[] = [
                $note->id,
                $note->title,
                $note->description,
                $note->status,
                $note->type,
                ($note->added_by) ? $note->added_by->display_name : '',
                $note->created_at
            ];
        }

        $writer->insertAll($rows);
        $writer->output($contactId . '-contact-notes-' . date('Y-m-d_H-i') . '.csv');
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


        if (empty($funnel['type']) || $funnel['type'] != 'funnels') {
            wp_send_json([
                'message' => __('The provided JSON file is not valid', 'fluentcampaign-pro')
            ], 423);
        }

        $funnelTrigger = $funnel['trigger_name'];
        $triggers = apply_filters('fluentcrm_funnel_triggers', []);

        $funnel['title'] .= ' (Imported @ ' . current_time('mysql') . ')';

        if (!isset($triggers[$funnelTrigger])) {
            wp_send_json([
                'message'  => __('The trigger defined in the JSON file is not available on your site.', 'fluentcampaign-pro'),
                'requires' => [
                    'Trigger Name Required: ' . $funnelTrigger
                ]
            ], 423);
        }

        $sequences = $funnel['sequences'];
        $formattedSequences = [];

        $blocks = apply_filters('fluentcrm_funnel_blocks', [], (object)$funnel);
        foreach ($sequences as $sequence) {
            $actionName = $sequence['action_name'];

            if($sequence['type'] == 'conditional') {
                $sequence = (object) $sequence;
                $sequence = (array) FunnelHelper::migrateConditionSequence($sequence, true);
                $actionName = $sequence['action_name'];
            }

            if (!isset($blocks[$actionName])) {
                wp_send_json([
                    'message'  => __('The Block Action defined in the JSON file is not available on your site.', 'fluentcampaign-pro'),
                    'requires' => [
                        'Missing Action: ' . $actionName
                    ],
                    'sequence' => $sequence
                ], 423);
            }

            $formattedSequences[] = $sequence;
        }

        unset($funnel['sequences']);

        $data = [
            'funnel'           => $funnel,
            'blocks'           => $blocks,
            'block_fields'     => apply_filters('fluentcrm_funnel_block_fields', [], (object)$funnel),
            'funnel_sequences' => $formattedSequences
        ];
        wp_send_json($data, 200);
    }

    private function contactColumnMaps()
    {
        return [
            'id'             => __('ID', 'fluentcampaign-pro'),
            'user_id'        => __('User ID', 'fluentcampaign-pro'),
            'prefix'         => __('Title', 'fluentcampaign-pro'),
            'first_name'     => __('First Name', 'fluentcampaign-pro'),
            'last_name'      => __('Last Name', 'fluentcampaign-pro'),
            'email'          => __('Email', 'fluentcampaign-pro'),
            'timezone'       => __('Timezone', 'fluentcampaign-pro'),
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
            'updated_at' => __('Updated At', 'fluentcampaign-pro'),
            'lists' => __('Lists', 'fluentcampaign-pro'),
            'tags' => __('Tags', 'fluentcampaign-pro')
        ];
    }

    private function verifyRequest()
    {
        $permission = 'fcrm_manage_contacts';
        if (PermissionManager::currentUserCan($permission)) {
            return true;
        }

        die('You do not have permission');
    }

    private function getCsvWriter()
    {
        if (!class_exists('\League\Csv\Writer')) {
            include FLUENTCRM_PLUGIN_PATH . 'app/Services/Libs/csv/autoload.php';
        }

        return \League\Csv\Writer::createFromFileObject(new \SplTempFileObject());
    }
}
