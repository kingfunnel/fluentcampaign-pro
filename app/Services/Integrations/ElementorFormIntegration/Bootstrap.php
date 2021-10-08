<?php

namespace FluentCampaign\App\Services\Integrations\ElementorFormIntegration;

class Bootstrap
{
    public function init()
    {
        if(!class_exists('\ElementorPro\Plugin')) {
            return;
        }
        $formModule = \ElementorPro\Plugin::instance()->modules_manager->get_modules( 'forms' );
        $formWidget = new FormWidget();
        // Register the action with form widget
        $formModule->add_form_action( $formWidget->get_name(), $formWidget );
    }
}