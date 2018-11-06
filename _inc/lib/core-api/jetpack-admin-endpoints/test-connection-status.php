<?php

class Jetpack_Admin_REST_API_V2_Endpoint_Test_Connection_Status {
	public function __construct() {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	public function register_routes() {
		register_rest_route( 'jetpack/v6', '/connection/test', array(
			array(
				'methods'  => WP_REST_Server::READABLE,
				'callback' => array( $this, 'test_connection_status' ),
				'permission_callback' => 'Jetpack_Core_Json_Api_Endpoints::manage_modules_permission_check',

			),
		) );
	}

	public function test_connection_status( $request ) {
		return Jetpack_Core_Json_Api_Endpoints::jetpack_connection_test( $request );
	}

}

wpcom_rest_api_v2_load_plugin( 'Jetpack_Admin_REST_API_V2_Endpoint_Test_Connection_Status' );
