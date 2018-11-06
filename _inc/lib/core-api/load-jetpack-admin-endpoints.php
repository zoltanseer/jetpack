<?php

/*
 * Loader for WP REST API endpoints that are part of Jetpack sites.
 *
 */

function jetpack_admin_rest_api_v4_load_plugin_files( $file_pattern ) {
	$plugins = glob( dirname( __FILE__ ) . '/' . $file_pattern );

	if ( ! is_array( $plugins ) ) {
		return;
	}

	foreach ( array_filter( $plugins, 'is_file' ) as $plugin ) {
		require_once $plugin;
	}
}

/**
 * API v4 plugins: define a class, then call this function.
 */
function jetpack_admin_rest_api_v2_load_plugin( $class_name ) {
	global $jetpack_admin_rest_api_v4_plugins;

	if ( ! isset( $jetpack_admin_rest_api_v4_plugins ) ) {
		$_GLOBALS['jetpack_admin_rest_api_v4_plugins'] = $jetpack_admin_rest_api_v4_plugins = array();
	}

	if ( ! isset( $jetpack_admin_rest_api_v4_plugins[ $class_name ] ) ) {
		$jetpack_admin_rest_api_v4_plugins[ $class_name ] = new $class_name;
	}
}

// Now load the endpoint files.
jetpack_admin_rest_api_v4_load_plugin_files( 'jetpack-admin-endpoints/*.php' );
