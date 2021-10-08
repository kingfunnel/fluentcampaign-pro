<?php

namespace FluentCampaign\App\Models;

use FluentCrm\App\Models\CampaignEmail;
use FluentCrm\App\Models\CampaignUrlMetric;
use FluentCrm\App\Models\Model;
use FluentCrm\App\Services\Helper;
use FluentCrm\Includes\Helpers\Arr;

class SequenceMail extends Model
{
    protected $table = 'fc_campaigns';

    protected $guarded = ['id'];

    protected static $type = 'sequence_mail';

    public static function boot()
    {
        static::creating(function ($model) {
            $defaultTemplate = $model->design_template ? $model->design_template : Helper::getDefaultEmailTemplate();

            $model->email_body = $model->email_body ?: '';
            $model->status = $model->status ?: 'published';
            $model->type = self::$type;
            $model->design_template = $defaultTemplate;
            $model->slug = $model->slug ?: sanitize_title($model->title, '', 'preview');
            $model->created_by = $model->created_by ?: get_current_user_id();
            $model->settings = $model->settings ? $model->settings : [
                'action_triggers' => [],
                'timings' => [
                    'delay_unit' => 'days',
                    'delay' => '',
                    'is_anytime' => 'yes',
                    'sending_time' => ['', '']
                ],
                'template_config' => Helper::getTemplateConfig($defaultTemplate),
                'mailer_settings'     => [
                    'from_name'      => '',
                    'from_email'     => '',
                    'reply_to_name'  => '',
                    'reply_to_email' => '',
                    'is_custom'      => 'no'
                ]
            ];

            $timeString = Arr::get($model->settings, 'timings.delay', 0).' '.Arr::get($model->settings, 'timings.delay_unit');
            $model->delay = strtotime($timeString, 0);
        });

        static::saving(function ($model) {
            $timeString = Arr::get($model->settings, 'timings.delay', 0).' '.Arr::get($model->settings, 'timings.delay_unit');
            $model->delay = strtotime($timeString, 0);
        });

        static::addGlobalScope('type', function ($builder) {
            $builder->where('type', '=', self::$type);
        });
    }

    public function setSlugAttribute($slug)
    {
        $this->attributes['slug'] = \sanitize_title($slug, '', 'preview');
    }

    public static function getEmpty()
    {
        $defaultTemplate = Helper::getDefaultEmailTemplate();

        return (object) [
            'id' => 0,
            'title' => '',
            'design_template' => 'simple',
            'email_subject' => '',
            'email_pre_header' => '',
            'email_body' => '',
            'settings' => [
                'action_triggers' => [],
                'timings' => [
                    'delay_unit' => 'days',
                    'delay' => '',
                    'is_anytime' => 'yes',
                    'sending_time' => ''
                ],
                'template_config' => Helper::getTemplateConfig($defaultTemplate)
            ]
        ];
    }

    public function setSettingsAttribute($settings)
    {
        $this->attributes['settings'] = \maybe_serialize($settings);
    }

    public function getSettingsAttribute($settings)
    {
        return \maybe_unserialize($settings);
    }

    public function stats()
    {
        $totalEmails = CampaignEmail::where('campaign_id', $this->id)
            ->count();
        $totalSent = CampaignEmail::where('campaign_id', $this->id)
            ->where('status', 'sent')
            ->count();
        $clicks = CampaignUrlMetric::where('campaign_id', $this->id)
                    ->where('type', 'click')
                    ->count();
        $views = CampaignUrlMetric::where('campaign_id', $this->id)
            ->where('type', 'open')
            ->count();

        $unSubscribed = CampaignUrlMetric::where('campaign_id', $this->id)
            ->where('type', 'unsubscribe')
            ->groupBy('subscriber_id')
            ->count();

        return [
            'total' => $totalEmails,
            'sent' => $totalSent,
            'clicks' => $clicks,
            'views' => $views,
            'unsubscribers' => $unSubscribed
        ];

    }

    public function updateMailerSettings($mailerSettings)
    {
        $settings = $this->settings;
        $settings['mailer_settings'] = $mailerSettings;
        $this->settings = $settings;
        $this->save();
        return $this;
    }
}
