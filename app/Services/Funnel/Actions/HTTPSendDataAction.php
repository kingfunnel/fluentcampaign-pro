<?php

namespace FluentCampaign\App\Services\Funnel\Actions;

use FluentCrm\App\Models\FunnelMetric;
use FluentCrm\App\Services\Funnel\BaseAction;
use FluentCrm\App\Services\Funnel\FunnelHelper;
use FluentCrm\Includes\Helpers\Arr;
use FluentCrm\Includes\Parser\Parser;

class HTTPSendDataAction extends BaseAction
{
    public function __construct()
    {
        $this->actionName = 'http_send_data';
        $this->priority = 99;
        parent::__construct();

        add_action('fluent_run_http_send_data_request_process', array($this, 'runRequestProcess'));
    }

    public function getBlock()
    {
        return [
            'category'    => 'CRM',
            'title'       => __('Outgoing Webhook', 'fluentcampaign-pro'),
            'description' => __('Send Data to external server via GET or POST Method', 'fluentcampaign-pro'),
            'icon'        => fluentCrmMix('images/funnel_icons/webhooks.svg'),
            'settings'    => [
                'sending_method'    => 'POST',
                'run_on_background' => 'yes',
                'remote_url'        => '',
                'body_data_type'    => 'subscriber_data',
                'request_format'    => 'json',
                'body_data_values'  => [
                    [
                        'data_key'   => '',
                        'data_value' => ''
                    ]
                ],
                'header_type'       => 'no_headers',
                'header_data'       => [
                    [
                        'data_key'   => '',
                        'data_value' => ''
                    ]
                ]
            ]
        ];
    }

    public function getBlockFields()
    {
        return [
            'title'     => __('Send Data to External Server', 'fluentcampaign-pro'),
            'sub_title' => __('Send Data to external server via GET or POST Method', 'fluentcampaign-pro'),
            'fields'    => [
                'sending_method'    => [
                    'type'    => 'radio',
                    'label'   => 'Data Send Method',
                    'options' => [
                        [
                            'id'    => 'POST',
                            'title' => 'POST Method'
                        ],
                        [
                            'id'    => 'GET',
                            'title' => 'GET Method'
                        ]
                    ]
                ],
                'remote_url'        => [
                    'type'        => 'input-text',
                    'data-type'   => 'url',
                    'placeholder' => 'Remote URL',
                    'label'       => 'Remote URL',
                    'help'        => 'Please provide valid URL in where you want to send the data'
                ],
                'request_format'    => [
                    'type'    => 'radio',
                    'label'   => 'Request Format',
                    'options' => [
                        [
                            'id'    => 'json',
                            'title' => 'Send as JSON format'
                        ],
                        [
                            'id'    => 'form',
                            'title' => 'Send as Form Method'
                        ]
                    ]
                ],
                'body_data_type'    => [
                    'type'    => 'radio',
                    'label'   => 'Request Body',
                    'options' => [
                        [
                            'id'    => 'subscriber_data',
                            'title' => 'Full Subscriber Data (Raw)'
                        ],
                        [
                            'id'    => 'custom_data',
                            'title' => 'Custom Data'
                        ]
                    ]
                ],
                'body_data_values'  => [
                    'label'                  => __('Request Body Data', 'fluentcampaign-pro'),
                    'type'                   => 'text-value-multi-properties',
                    'data_key_label'         => 'Data Key',
                    'data_value_label'       => 'Data Value',
                    'data_value_placeholder' => 'key',
                    'data_key_placeholder'   => 'value',
                    'help'                   => 'Please map the data for custom sending data type',
                    'value_input_type'       => 'text-popper',
                    'dependency'             => [
                        'depends_on' => 'body_data_type',
                        'operator'   => '=',
                        'value'      => 'custom_data'
                    ]
                ],
                'header_type'       => [
                    'type'    => 'radio',
                    'label'   => 'Request Header',
                    'options' => [
                        [
                            'id'    => 'no_headers',
                            'title' => 'No Headers'
                        ],
                        [
                            'id'    => 'with_headers',
                            'title' => 'With Headers'
                        ]
                    ]
                ],
                'header_data'       => [
                    'label'                  => __('Request Headers Data', 'fluentcampaign-pro'),
                    'type'                   => 'text-value-multi-properties',
                    'data_key_label'         => 'Header Key',
                    'data_value_label'       => 'Header Value',
                    'data_value_placeholder' => 'key',
                    'data_key_placeholder'   => 'value',
                    'help'                   => 'Please map the data for request headers',
                    'value_input_type'       => 'input-text',
                    'dependency'             => [
                        'depends_on' => 'header_type',
                        'operator'   => '=',
                        'value'      => 'with_headers'
                    ]
                ],
                'run_on_background' => [
                    'type'        => 'yes_no_check',
                    'label'       => '',
                    'check_label' => __('Send Data as Background Process. (You may enable this if you have lots of tasks)', 'fluentcampaign-pro')
                ],
            ]
        ];
    }

    public function handle($subscriber, $sequence, $funnelSubscriberId, $funnelMetric)
    {
        $settings = $sequence->settings;
        $remoteUrl = $settings['remote_url'];
        if (!$remoteUrl || filter_var($remoteUrl, FILTER_VALIDATE_URL) === FALSE) {
            $funnelMetric->notes = 'Funnel Skipped because provided url is not valid';
            $funnelMetric->save();
            FunnelHelper::changeFunnelSubSequenceStatus($funnelSubscriberId, $sequence->id, 'skipped');
            return false;
        }

        $body = [];
        if ($settings['body_data_type'] == 'subscriber_data') {
            $body = $subscriber->toArray();
            $body['custom_field'] = $subscriber->custom_fields();
        } else {
            // We have to loop the data
            foreach ($settings['body_data_values'] as $item) {
                if (empty($item['data_key']) || empty($item['data_value'])) {
                    continue;
                }
                $body[$item['data_key']] = Parser::parse($item['data_value'], $subscriber);
            }
        }

        if (!$body) {
            $funnelMetric->notes = 'No valid body data found';
            $funnelMetric->save();
            FunnelHelper::changeFunnelSubSequenceStatus($funnelSubscriberId, $sequence->id, 'skipped');
            return false;
        }

        $headers = [];
        if ($settings['header_type'] == 'with_headers') {
            foreach ($settings['header_data'] as $item) {
                if (empty($item['data_key']) || empty($item['data_value'])) {
                    continue;
                }
                $headers[$item['data_key']] = Parser::parse($item['data_value'], $subscriber);
            }
        }

        $sendingMethod = $settings['sending_method'];

        if ($settings['request_format'] == 'json' && $sendingMethod == 'POST') {
            $body = json_encode($body);
            $headers['Content-Type'] = 'application/json';
        }

        if ($sendingMethod == 'GET') {
            $remoteUrl = add_query_arg($body, $remoteUrl);
        }

        $data = [
            'payload' => [
                'body'      => ($sendingMethod == 'POST') ? $body : null,
                'method'    => $sendingMethod,
                'headers'   => $headers,
                'sslverify' => apply_filters('ff_webhook_ssl_verify', false),
            ],
            'remote_url' => $remoteUrl,
            'funnel_sub_id'=> $funnelSubscriberId,
            'sequence_id' => $sequence->id,
            'metric_id' => $funnelMetric->id
        ];

        if($settings['run_on_background'] == 'yes') {
            FunnelHelper::changeFunnelSubSequenceStatus($funnelSubscriberId, $sequence->id, 'processing');
            fluentcrm_queue_on_background('fluent_run_http_send_data_request_process', $data);
            return true;
        }

        return $this->runRequestProcess($data);
    }

    public function runRequestProcess($data)
    {
        $response = wp_remote_request($data['remote_url'], $data['payload']);
        if (is_wp_error($response)) {
            $code = Arr::get($response, 'response.code');
            $message = $response->get_error_message() . ', with response code: ' . $code . ' - ' . (int)$response->get_error_code();
            FunnelMetric::where('id', $data['metric_id'])
                ->update('note', $message);
            FunnelHelper::changeFunnelSubSequenceStatus($data['funnel_sub_id'], $data['sequence_id'], 'skipped');
            return false;
        }

        $responseBody = wp_remote_retrieve_body($response);
        if(is_array($responseBody)) {
            $responseBody = json_encode($responseBody);
        }
        if(is_string($responseBody)) {
            FunnelMetric::where('id', $data['metric_id'])
                ->update('note', $responseBody);
        }

        FunnelHelper::changeFunnelSubSequenceStatus($data['funnel_sub_id'], $data['sequence_id']);
        return true;
    }
}