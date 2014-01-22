<?php
/**
 * WP-SeedBank uninstaller.
 *
 * @package plugin
 */
// Don't execute any uninstall code unless WordPress core requests it.
if (!defined('WP_UNINSTALL_PLUGIN')) { exit(); }

// Delete options.
delete_option('wp_seedbank_version'); // Old 0.2.3 setting, not used.
delete_option('seedbank_options');    // As of version 0.3.

// TODO: What do we want to do with custom taxonomies and posts?
