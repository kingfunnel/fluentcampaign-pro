<?php

namespace FluentCampaign\App\Migration;

class SmartLinksMigrator
{
    /**
     * On-Demand Action Links Migrator.
     *
     * @param bool $isForced
     * @return void
     */
    public static function migrate($isForced = true)
    {
        global $wpdb;

        $charsetCollate = $wpdb->get_charset_collate();
        $table = $wpdb->prefix .'fc_smart_links';

        if ($wpdb->get_var("SHOW TABLES LIKE '$table'") != $table || $isForced) {
            $sql = "CREATE TABLE $table (
                `id` BIGINT UNSIGNED NOT NULL PRIMARY KEY AUTO_INCREMENT,
                `title` VARCHAR(192),
                `short` VARCHAR(192),
                `target_url` TEXT NULL,
                `actions` TEXT NULL,
                `notes` TEXT NULL,
                `contact_clicks` INT(11) DEFAULT 0,
                `all_clicks` INT(11) DEFAULT 0,
                `created_by` BIGINT UNSIGNED NULL,
                `created_at` TIMESTAMP NULL,
                `updated_at` TIMESTAMP NULL
            ) $charsetCollate;";
            require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
            dbDelta($sql);
        }
    }
}
