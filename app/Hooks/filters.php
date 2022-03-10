<?php

/**
 * @var $app \FluentCrm\Includes\Core\Application
 */
// Let's push dashboard stats

$app->addFilter('fluentcrm_is_require_verify', function ($status) {
    $licenseManager = new \FluentCampaign\App\Services\PluginManager\LicenseManager();
    return $licenseManager->isRequireVerify() && $licenseManager->licenseVar('status') == 'valid';
});


add_filter('fluentcrm_commerce_provider', function ($defaultProvider) {
    return \FluentCampaign\App\Services\Commerce\Commerce::getCommerceProvider($defaultProvider);
}, 10, 1);

add_filter('fluentcrm_currency_sign', function ($currencySign) {
    return \FluentCampaign\App\Services\Commerce\Commerce::getDefaultCurrencySign($currencySign);
}, 10, 2);


if (defined('FL_BUILDER_VERSION')) {
    add_filter('fl_builder_subscribe_form_services', function ($services) {
        if (is_array($services)) {
            return array_merge([
                'fluentcrm' => [
                    'type'      => 'autoresponder',
                    'name'      => 'FluentCRM',
                    'class'     => '\FluentCampaign\App\Hooks\Handlers\FLBuilderServiceFluentCrm',
                    'namespace' => true,
                ]
            ], $services);
        }

        return $services;
    });
}
