<?php

namespace FluentCampaign\App\Services\Funnel\Conditions;

use FluentCampaign\App\Services\Funnel\BaseCondition;
use FluentCrm\App\Services\Funnel\FunnelProcessor;
use FluentCrm\Includes\Helpers\Arr;
use FluentCrm\Includes\Helpers\ConditionAssesor;

class CheckUserPropCondition extends BaseCondition
{
    public function __construct()
    {
        $this->conditionName = 'fcrm_check_user_prop';
        $this->priority = 21;
        parent::__construct();
    }

    public function getBlock()
    {
        return [
            'title'            => __('Check Contact\'s Properties', 'fluentcampaign-pro'),
            'description'      => __('Check If the contact match specific data properties', 'fluentcampaign-pro'),
            'icon'             => fluentCrmMix('images/funnel_icons/has_contact_property.svg'),
            'settings'         => [
                'condition_groups' => [
                    [
                        'conditions' => [
                            [
                                'data_key'   => '',
                                'operator'   => '=',
                                'data_value' => ''
                            ]
                        ],
                        'match_type' => 'match_all'
                    ]
                ],
                'group_match'      => 'match_any'
            ],
            'reload_on_insert' => true
        ];
    }

    public function getBlockFields()
    {
        return [
            'title'     => __('Check Contact\'s Properties', 'fluentcampaign-pro'),
            'sub_title' => __('Check If the contact match specific data properties', 'fluentcampaign-pro'),
            'fields'    => [
                'condition_groups' => [
                    'type'                 => 'condition_groups',
                    'label'                => __('Specify Matching Conditions', 'fluentcampaign-pro'),
                    'inline_help'          => __('Specify which contact properties need to matched. Based on the conditions it will run yes blocks or no blocks', 'fluentcampaign-pro'),
                    'labels'               => [
                        'match_type_all_label' => __('True if all conditions match', 'fluentcampaign-pro'),
                        'match_type_any_label' => __('True if any of the conditions match', 'fluentcampaign-pro'),
                        'data_key_label'       => __('Contact Data', 'fluentcampaign-pro'),
                        'condition_label'      => __('Condition', 'fluentcampaign-pro'),
                        'data_value_label'     => __('Match Value', 'fluentcampaign-pro')
                    ],
                    'condition_properties' => $this->getConditionProperties()
                ]
            ]
        ];
    }

    public function handle($subscriber, $sequence, $funnelSubscriberId, $funnelMetric)
    {
        $conditions = Arr::get($sequence->settings, 'condition_groups');
        $subscriberData = $subscriber->toArray();
        $subscriberData['custom'] = $subscriber->custom_fields();
        $matched = ConditionAssesor::matchAllGroups($conditions, $subscriberData);

        (new FunnelProcessor())->initChildSequences($sequence, $matched, $subscriber, $funnelSubscriberId, $funnelMetric);
    }

    private function getConditionProperties()
    {
        $types = \fluentcrm_contact_types();
        $formattedContactTypes = [];

        foreach ($types as $type => $label) {
            $formattedContactTypes[] = [
                'id'    => $type,
                'slug'  => $type,
                'title' => $label
            ];
        }

        $primaryFields = [
            'first_name'   => [
                'label' => __('First Name', 'fluentcampaign-pro'),
                'type'  => 'text'
            ],
            'last_name'    => [
                'label' => __('Last Name', 'fluentcampaign-pro'),
                'type'  => 'text'
            ],
            'email'        => [
                'label' => __('Email', 'fluentcampaign-pro'),
                'type'  => 'text'
            ],
            'city'         => [
                'label' => __('City', 'fluentcampaign-pro'),
                'type'  => 'text'
            ],
            'state'        => [
                'label' => __('State', 'fluentcampaign-pro'),
                'type'  => 'text'
            ],
            'country'      => [
                'label'      => __('Country', 'fluentcampaign-pro'),
                'type'       => 'option_selector',
                'option_key' => 'countries',
                'multiple'   => false
            ],
            'contact_type' => [
                'label'   => __('Contact Type', 'fluentcampaign-pro'),
                'type'    => 'select',
                'options' => $formattedContactTypes
            ]
        ];

        $customFields = fluentcrm_get_option('contact_custom_fields', []);

        $validTypes = ['text', 'textarea', 'number', 'select-one', 'select-multi', 'radio', 'checkbox'];
        $formattedFields = [];
        foreach ($customFields as $customField) {
            $customType = $customField['type'];

            if (!in_array($customType, $validTypes)) {
                continue;
            }

            $fieldType = $customType;

            $options = [];

            if (in_array($customType, ['select-one', 'select-multi', 'radio', 'checkbox'])) {
                $fieldType = 'select';
                $options = [];
                foreach ($customField['options'] as $option) {
                    $options[] = [
                        'id'    => $option,
                        'slug'  => $option,
                        'title' => $option
                    ];
                }
            }

            $formattedFields['custom.' . $customField['slug']] = [
                'label' => $customField['label'],
                'type'  => $fieldType
            ];

            if ($fieldType == 'select') {
                $formattedFields['custom.' . $customField['slug']]['options'] = $options;
            }
        }


        $result = [
            'primary_fields' => [
                'label' => 'Primary Fields',
                'options' => $primaryFields
            ]
        ];

        if($formattedFields) {
            $result['custom_fields'] = [
                'label' => 'Custom Fields',
                'options' => $formattedFields
            ];
        }

        return $result;

    }
}
