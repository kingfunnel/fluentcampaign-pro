<?php

namespace FluentCampaign\App\Services\DynamicSegments;

use FluentCrm\App\Models\Meta;
use FluentCrm\App\Models\Subscriber;
use FluentCrm\Includes\Helpers\Arr;

class CustomSegment extends BaseSegment
{

    public $slug = 'custom_segment';

    public $priority = 100;

    public function register()
    {
        add_filter('fluentcrm_dynamic_segments', function ($segments) {
            if ($customSegments = $this->getSegments()) {
                $segments = array_merge($segments, $customSegments);
            }
            return $segments;
        }, $this->priority);

        add_filter('fluentcrm_dynamic_segment_' . $this->slug, array($this, 'getSegmentDetails'), 10, 3);
    }

    public function getSegments()
    {
        $segments = Meta::where('object_type', $this->slug)
            ->orderBy('id', 'ASC')
            ->get();
        $formattedSegments = [];
        foreach ($segments as $segment) {
            $settings = $segment->value;
            $settings = wp_parse_args($settings, $this->getInfo());
            $settings['id'] = $segment->id;
            $formattedSegments[] = $settings;
        }
        return $formattedSegments;
    }

    public function getInfo()
    {
        return [
            'slug'        => $this->slug,
            'is_system'   => false,
            'subtitle'    => __('Custom Segments with custom filters on Subscriber data', 'fluentcampaign-pro'),
            'description' => __('This is a custom segment and contacts are filter based your provided filters on real time data.', 'fluentcampaign-pro')
        ];
    }

    public function getCount()
    {
        return $this->getModel()->count();
    }

    public function getSegmentDetails($segment, $id, $config)
    {
        $item = Meta::where('id', $id)->where('object_type', 'custom_segment')->first();

        if (!$item) {
            return [];
        }

        $segment = $item->value;
        $segment = wp_parse_args($segment, $this->getInfo());
        $segment['id'] = $item->id;
        $segment['contact_count'] = $this->getContactCount($segment);

        if (Arr::get($config, 'model')) {
            $segment['model'] = $this->getModel($segment);
        }

        if (Arr::get($config, 'subscribers')) {
            $segment['subscribers'] = $this->getSubscribers($config, $segment);
        }
        return $segment;
    }

    private function getContactCount($segment)
    {
        return $this->getModel($segment)->count();
    }

    public function getModel($segment = [])
    {
        $settings = Arr::get($segment, 'settings');
        $subscribersModel = Subscriber::orderBy('id', 'DESC');

        /*
         * Main Column Conditions
         */
        $conditions = Arr::get($settings, 'conditions', []);
        $isMatchAll = Arr::get($settings, 'condition_match') == 'match_all';
        $validConditions = $this->getValidColumnConditions($conditions);

        if ($validConditions) {
            if ($isMatchAll || count($validConditions) == 1) {

                foreach ($validConditions as $condition) {
                    $this->addCondition($subscribersModel, $condition);
                }

            } else {
                $subscribersModel->where(function ($query) use ($validConditions) {
                    $firstCondition = array_shift($validConditions);
                    $this->addCondition($query, $firstCondition);

                    foreach ($validConditions as $condition) {
                        $this->addCondition($query, $condition);
                    }
                });
            }
        }


        /*
         * Email Activity Conditions
         */
        $emailSettings = Arr::get($settings, 'email_activities', []);
        $matches = $this->getEmailActivityMatches($emailSettings);
        if ($matches) {
            $matchAll = Arr::get($emailSettings, 'last_email_activity_match') == 'match_all';
            if ($matchAll || count($matches) == 1) {
                foreach ($matches as $match) {
                    $subscribersModel->whereHas('urlMetrics', function ($query) use ($match) {
                        $query->where(function ($q) use ($match) {
                            $q->where('type', $match['type']);
                            $q->where(
                                'updated_at',
                                $match['date_operator'],
                                $match['updated_at']
                            );
                        });
                    });
                }
            } else {
                $subscribersModel->whereHas('urlMetrics', function ($query) use ($matches) {
                    $query->where(function ($qq) use ($matches) {
                        $qq->where(function ($q) use ($matches) {
                            $first = $matches[0];
                            $q->where('type', $first['type']);
                            $q->where(
                                'updated_at',
                                $first['date_operator'],
                                $first['updated_at']
                            );
                        });
                        $qq->orWhere(function ($q) use ($matches) {
                            $second = $matches[1];
                            $q->where('type', $second['type']);
                            $q->where(
                                'updated_at',
                                $second['date_operator'],
                                $second['updated_at']
                            );
                        });
                    });
                });
            }
        }

        return $subscribersModel;
    }

    private function addCondition(&$model, $condition)
    {
        if ($condition['field'] == 'tags') {
            if ($condition['operator'] == 'whereIn') {
                $model->filterByTags($condition['value']);
            } else {
                $model->filterByNotInTags($condition['value']);
            }
        } else if ($condition['field'] == 'lists') {
            if ($condition['operator'] == 'whereIn') {
                $model->filterByLists($condition['value']);
            } else {
                $model->filterByNotInLists($condition['value']);
            }
        } else if ($condition['operator'] == 'whereIn' || $condition['operator'] == 'whereNotIn') {
            $model->{$condition['operator']}($condition['field'], $condition['value']);
        } else {
            $model->where(
                $condition['field'],
                $condition['operator'],
                $condition['value']
            );
        }
    }

    private function getValidColumnConditions($conditions)
    {
        $dateColumns = ['last_activity', 'created_at', 'updated_at'];
        $validConditions = [];
        foreach ($conditions as $condition) {
            if (!$condition['field'] || !$condition['operator']) {
                continue;
            }
            if ($condition['operator'] == 'whereIn' || $condition['operator'] == 'whereNotIn') {
                if (!$condition['value'] || !is_array($condition['value'])) {
                    continue;
                }
            }
            if ($condition['operator'] == 'LIKE' || $condition['operator'] == 'NOT LIKE') {
                $condition['value'] = '%' . $condition['value'] . '%';
            }

            if (in_array($condition['field'], $dateColumns)) {
                // value is in days
                $timestamp = time() - $condition['value'] * 86400;
                $condition['value'] = date('Y-m-d H:i:s', $timestamp);
            }

            $validConditions[] = $condition;
        }
        return $validConditions;
    }

    private function getEmailActivityMatches($emailSettings)
    {
        $emailActivityStatus = Arr::get($emailSettings, 'status') == 'yes' &&
            (
                (Arr::get($emailSettings, 'last_email_open.value') && Arr::get($emailSettings, 'last_email_open.operator')) ||
                (Arr::get($emailSettings, 'last_email_link_click.value') && Arr::get($emailSettings, 'last_email_link_click.operator'))
            );
        if (!$emailActivityStatus) {
            return [];
        }

        $openDays = Arr::get($emailSettings, 'last_email_open.value');
        $openOperator = Arr::get($emailSettings, 'last_email_open.operator');
        $clickDays = Arr::get($emailSettings, 'last_email_link_click.value');
        $clickOperator = Arr::get($emailSettings, 'last_email_link_click.operator');

        $matches = [];
        if ($openDays && $openOperator) {
            $matches[] = [
                'type'          => 'open',
                'updated_at'    => date('Y-m-d H:i:s', strtotime(fluentCrmTimestamp()) - ($openDays * 86400)),
                'date_operator' => $openOperator
            ];
        }

        if ($clickDays && $clickOperator) {
            $matches[] = [
                'type'          => 'click',
                'updated_at'    => date('Y-m-d H:i:s', strtotime(fluentCrmTimestamp()) - ($clickDays * 86400)),
                'date_operator' => $clickOperator
            ];
        }
        return $matches;
    }
}
