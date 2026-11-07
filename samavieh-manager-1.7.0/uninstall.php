<?php
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// To protect registrations and receipts, uninstalling the plugin does NOT erase data.
// Remove the database table and private files manually only after taking a full backup.
delete_option('smv_setup_complete');
remove_role('smv_manager');
