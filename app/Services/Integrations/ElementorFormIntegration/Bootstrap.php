<?php

namespace FluentCampaign\App\Services\Integrations\ElementorFormIntegration;

class Bootstrap
{
    public function init()
    {
        add_action('init', function () {
            if(!class_exists('\ElementorPro\Plugin') || apply_filters('fluentcrm_disable_elementor_form', false)) {
                return;
            }

            $formModule = \ElementorPro\Plugin::instance()->modules_manager->get_modules( 'forms' );
            $formWidget = new FormWidget();
            // Register the action with form widget
            $formModule->add_form_action( $formWidget->get_name(), $formWidget );
        });
    }
}