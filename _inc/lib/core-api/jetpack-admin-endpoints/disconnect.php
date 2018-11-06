<?php

class Jetpack_Admin_REST_API_V2_Endpoint_Disconnect {
	public function __construct() {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	public function register_routes() {
		register_rest_route( 'jetpack/v6', '/connection', array(
			array(
				'methods'  => WP_REST_Server::EDITABLE,
				'callback' => array( $this, 'disconnect' ),
				'permission_callback' => 'Jetpack_Core_Json_Api_Endpoints::disconnect_site_permission_callback',
			),
		) );
	}

	public function disconnect( $request ) {
		return Jetpack_Core_Json_Api_Endpoints::disconnect_site( $request );
	}
}

wpcom_rest_api_v2_load_plugin( 'Jetpack_Admin_REST_API_V2_Endpoint_Disconnect' );
