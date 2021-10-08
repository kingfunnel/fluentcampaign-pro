<?php

namespace FluentCampaign\App\Services\Integrations\WooCommerce;

use FluentCrm\App\Models\Subscriber;
use FluentCrm\Includes\Helpers\Arr;

class WooProductAdmin
{
    private $postMetaName = 'fcrm-settings-woo';

    public function init()
    {

        $usingWpFusion = apply_filters('fluentcrm_using_wpfusion', defined('WP_FUSION_VERSION'));

        if ($usingWpFusion || apply_filters('fluentcrm_disable_woo_admin_integration', false)) {
            return false;
        }

        /*
         * Admin Product Edit Page Actions
         */
        add_action('woocommerce_product_write_panel_tabs', array($this, 'addPanelTitle'));
        add_action('woocommerce_product_data_panels', array($this, 'addPanelInputs'));
        add_action('save_post_product', array($this, 'saveMetaData'));

        /*
         * order success actions
         */
        add_action('woocommerce_order_status_processing', array($this, 'applyOrderTags'), 10, 2);
        add_action('woocommerce_order_status_completed', array($this, 'applyOrderTags'), 10, 2);
        add_action('woocommerce_order_status_refunded', array($this, 'applyRefundTags'), 10, 1);

    }

    public function addPanelTitle()
    {
        if (!is_admin()) {
            return;
        }
        ?>
        <li class="custom_tab fluent_crm-settings-tab hide_if_grouped">
            <a href="#fluent_crm_tab">
                <svg width="14px" height="100%" viewBox="0 0 300 235" version="1.1" xmlns="http://www.w3.org/2000/svg"
                     xmlns:xlink="http://www.w3.org/1999/xlink" xml:space="preserve"
                     style="fill-rule:evenodd;clip-rule:evenodd;stroke-linejoin:round;stroke-miterlimit:2;"><g>
                        <path
                            d="M300,0c0,0 -211.047,56.55 -279.113,74.788c-12.32,3.301 -20.887,14.466 -20.887,27.221l0,38.719c0,0 169.388,-45.387 253.602,-67.952c27.368,-7.333 46.398,-32.134 46.398,-60.467c0,-7.221 0,-12.309 0,-12.309Z"/>
                        <path
                            d="M184.856,124.521c0,-0 -115.6,30.975 -163.969,43.935c-12.32,3.302 -20.887,14.466 -20.887,27.221l0,38.719c0,0 83.701,-22.427 138.458,-37.099c27.368,-7.334 46.398,-32.134 46.398,-60.467c0,-7.221 0,-12.309 0,-12.309Z"/>
                    </g></svg>
                <span><?php _e('FluentCRM', 'fluentcampaign-pro'); ?></span>
            </a>
        </li>
        <style>
            .fluent_crm-settings-tab a:before {
                content: none;
                display: none;
            }
        </style>
        <?php
    }

    public function addPanelInputs()
    {
        if (!is_admin()) {
            return '';
        }
        global $post;

        $settings = wp_parse_args(get_post_meta($post->ID, $this->postMetaName, true), [
            'purchase_apply_tags'  => array(),
            'purchase_remove_tags' => array(),
            'refund_apply_tags'    => array(),
            'refund_remove_tags'   => array()
        ]);

        $tags = FluentCrmApi('tags')->all();

        // Add an nonce field so we can check for it later.
        wp_nonce_field('fcrm_meta_box_woo', 'fcrm_meta_box_woo_nonce');
        ?>
        <div id="fluent_crm_tab" class="panel woocommerce_options_panel fcrm-meta">
            <h3><?php _e('FluentCRM Integration', 'fluentcampaign-pro'); ?></h3>
            <div class="fc_field_group">
                <h4>Successful Purchase Actions</h4>
                <p>Please specify which tags will be added/removed to the contact when purchase</p>
                <div class="fc_field_items">
                    <div class="fc_field">
                        <p><b>Add Tags</b></p>
                        <select placeholder="Select Tags" style="width:100%;" class="fc_multi_slect"
                                name="<?php echo $this->postMetaName; ?>[purchase_apply_tags][]" multiple="multiple"
                                id="fcrm_purchase_tags">
                            <?php foreach ($tags as $tag): ?>
                                <option
                                    value="<?php echo $tag->id; ?>" <?php if (in_array($tag->id, $settings['purchase_apply_tags'])) {
                                    echo 'selected';
                                } ?> ><?php echo $tag->title; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="fc_field">
                        <p><b>Remove Tags</b></p>
                        <select placeholder="Select Tags" style="width:100%;" class="fc_multi_slect"
                                name="<?php echo $this->postMetaName; ?>[purchase_remove_tags][]" multiple="multiple"
                                id="fcrm_purchase_remove_tags">
                            <?php foreach ($tags as $tag): ?>
                                <option
                                    value="<?php echo $tag->id; ?>" <?php if (in_array($tag->id, $settings['purchase_remove_tags'])) {
                                    echo 'selected';
                                } ?> ><?php echo $tag->title; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </div>
            <div class="fc_field_group">
                <h4>Refund Actions</h4>
                <p>Please specify which tags will be added/removed to the contact when refunded</p>
                <div class="fc_field_items">
                    <div class="fc_field">
                        <p><b>Add Tags</b></p>
                        <select placeholder="Select Tags" style="width:100%;" class="fc_multi_slect"
                                name="<?php echo $this->postMetaName; ?>[refund_apply_tags][]" multiple="multiple">
                            <?php foreach ($tags as $tag): ?>
                                <option
                                    value="<?php echo $tag->id; ?>" <?php if (in_array($tag->id, $settings['refund_apply_tags'])) {
                                    echo 'selected';
                                } ?> ><?php echo $tag->title; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="fc_field">
                        <p><b>Remove Tags</b></p>
                        <select placeholder="Select Tags" style="width:100%;" class="fc_multi_slect"
                                name="<?php echo $this->postMetaName; ?>[refund_remove_tags][]" multiple="multiple">
                            <?php foreach ($tags as $tag): ?>
                                <option
                                    value="<?php echo $tag->id; ?>" <?php if (in_array($tag->id, $settings['refund_remove_tags'])) {
                                    echo 'selected';
                                } ?> ><?php echo $tag->title; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </div>
        </div>
        <style>
            .fcrm-meta {
                padding: 0 20px;
            }

            .fcrm-meta h4 {
                margin-bottom: 10px;
            }

            .fc_field_group {
                margin-bottom: 40px;
                padding: 10px 15px 15px;
                background: #fafafa;
            }

            .fcrm-meta p {
                margin: 0;
                padding: 0;
            }

            .fc_field_items {
                display: flex;
                width: 100%;
                margin-bottom: 10px;
                overflow: hidden;
                padding: 0;
                flex-direction: row;
                justify-content: flex-start;
                align-items: center;
            }

            .fc_field_items .fc_field {
                width: 50%;
                padding-right: 20px;
            }
        </style>
        <?php

        add_action('admin_footer', function () {
            ?>
            <script>
                jQuery(document).ready(function () {
                    jQuery('.fc_multi_slect').select2();
                });
            </script>
            <?php
        }, 999);
    }

    public function saveMetaData($post_id)
    {
        if (
            !isset($_POST['fcrm_meta_box_woo_nonce']) ||
            !wp_verify_nonce($_POST['fcrm_meta_box_woo_nonce'], 'fcrm_meta_box_woo') ||
            (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE)
        ) {
            return;
        }

        if ($_POST['post_type'] != 'product') {
            return;
        }

        $data = Arr::get($_POST, $this->postMetaName, []);
        update_post_meta($post_id, $this->postMetaName, $data);
    }

    public function applyOrderTags($orderId, $order)
    {
        if (get_post_meta($orderId, '_fcrm_order_success_complete', true)) {
            return true;
        }

        if (!$order) {
            return false;
        }

        $actions = $this->getActionTags($order, 'purchase');

        if (!$actions['apply_tags'] && !$actions['remove_tags']) {
            return false;
        }

        $subscriberData = Helper::prepareSubscriberData($order);
        if (!is_email($subscriberData['email'])) {
            return false;
        }

        $subscriberData['tags'] = $actions['apply_tags'];

        $subscriberClass = new Subscriber();
        $contact = $subscriberClass->updateOrCreate($subscriberData);

        if ($actions['remove_tags']) {
            $contact->detachTags($actions['remove_tags']);
        }

        update_post_meta($orderId, '_fcrm_order_success_complete', true);

        return true;
    }

    public function applyRefundTags($orderId)
    {
        $order = wc_get_order($orderId);

        if (!$order) {
            return false;
        }

        $actions = $this->getActionTags($order, 'refund');

        if (!$actions['apply_tags'] && !$actions['remove_tags']) {
            return false;
        }

        $subscriberData = Helper::prepareSubscriberData($order);

        if (!is_email($subscriberData['email'])) {
            return false;
        }

        $subscriberData['tags'] = $actions['apply_tags'];

        $subscriberClass = new Subscriber();
        $contact = $subscriberClass->updateOrCreate($subscriberData);

        if ($actions['remove_tags']) {
            $contact->detachTags($actions['remove_tags']);
        }

        return true;
    }

    private function getActionTags($order, $type = 'purchase')
    {
        $applyTags = [];
        $removeTags = [];

        foreach ($order->get_items() as $item) {
            $productId = $item->get_product_id();
            $settings = get_post_meta($productId, $this->postMetaName, true);
            if (!$settings || !is_array($settings)) {
                continue;
            }

            if ($adds = Arr::get($settings, $type . '_apply_tags', [])) {
                $applyTags = array_merge($applyTags, $adds);
            }
            if ($removes = Arr::get($settings, $type . '_remove_tags', [])) {
                $removeTags = array_merge($removeTags, $removes);
            }
        }

        return [
            'apply_tags'  => array_unique($applyTags),
            'remove_tags' => array_unique($removeTags),
        ];
    }
}
