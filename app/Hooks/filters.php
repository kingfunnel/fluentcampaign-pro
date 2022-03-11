<?php

/**
 * @var $app \FluentCrm\Includes\Core\Application
 */
// Let's push dashboard stats

$app->addFilter('fluentcrm_is_require_verify', function ($status) {
    $licenseManager = new \FluentCampaign\App\Services\PluginManager\LicenseManager();
    return $licenseManager->isRequireVerify() && $licenseManager->licenseVar('status') == 'valid';
});
