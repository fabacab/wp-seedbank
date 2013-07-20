<?php

/*
This file goes in wp-content/install.php.  It should be there before the WP installer is run.

The Jetpack plugin should alreday be in wp-content/plugins/jetpack/ beffore the WP installer is run.
*/

defined( 'JETPACK__INSTALL_PLUGIN_PATH' ) or define( 'JETPACK__INSTALL_PLUGIN_PATH', 'jetpack/jetpack.php' );

function install_jetpack() {
	global $pagenow;

	if ( !( 'install.php' == $pagenow && isset( $_REQUEST['step'] ) && 2 == $_REQUEST['step'] ) ) {
		return;
	}

	$active_plugins = (array) get_option( 'active_plugins', array() );

	// Shouldn't happen, but avoid duplicate entries just in case.
	if ( !empty( $active_plugins ) && false !== array_search( JETPACK__INSTALL_PLUGIN_PATH, $active_plugins ) ) {
		return;
	}

	$active_plugins[] = JETPACK__INSTALL_PLUGIN_PATH;
	update_option( 'active_plugins', $active_plugins );
	update_option( 'jetpack_activated',   3 );
	update_option( 'jetpack_do_activate', 1 );
}

add_action( 'shutdown', 'install_jetpack' );
