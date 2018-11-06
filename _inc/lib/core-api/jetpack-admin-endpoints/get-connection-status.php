<?php

class Jetpack_Admin_REST_API_V2_Endpoint_Connection_Status {
	public function __construct() {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	public function register_routes() {
		register_rest_route( 'jetpack/v6', '/connection', array(
			array(
				'methods'  => WP_REST_Server::READABLE,
				'callback' => array( $this, 'connection_status' ),
			),
		) );
	}

	public function connection_status( $request ) {
		return Jetpack_Core_Json_Api_Endpoints::jetpack_connection_status( $request );
	}


}

wpcom_rest_api_v2_load_plugin( 'Jetpack_Admin_REST_API_V2_Endpoint_Connection_Status' );
