<?php

class Jetpack_Admin_REST_API_V2_Endpoint_Get_Jitm {
	public function __construct() {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	public function register_routes() {
		register_rest_route( 'jetpack/v6', '/jitm', array(
			array(
				'methods'  => WP_REST_Server::READABLE,
				'callback' => array( $this, 'get_jitm' ),
			),
		) );
	}

	public function get_jitm( $request ) {
		return Jetpack_Core_Json_Api_Endpoints::get_jitm( $request );
	}

}

wpcom_rest_api_v2_load_plugin( 'Jetpack_Admin_REST_API_V2_Endpoint_Get_Jitm' );
