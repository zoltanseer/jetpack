<?php

class Jetpack_Admin_REST_API_V2_Endpoint_Get_Connection_Url {
	public function __construct() {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	public function register_routes() {
		register_rest_route( 'jetpack/v6', '/connection/url', array(
			array(
				'methods'  => WP_REST_Server::READABLE,
				'callback' => array( $this, 'connection_url' ),
				'permission_callback' => 'Jetpack_Core_Json_Api_Endpoints::connect_url_permission_callback',

			),
		) );
	}

	public function connection_url( $request ) {
		return Jetpack_Core_Json_Api_Endpoints::build_connect_url( $request );
	}


}

wpcom_rest_api_v2_load_plugin( 'Jetpack_Admin_REST_API_V2_Endpoint_Get_Connection_Url' );
