<?php

class Jetpack_Admin_REST_API_V2_Endpoint_List_Plans {
	public function __construct() {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	public function register_routes() {
		register_rest_route( 'jetpack/v6', '/plans', array(
			array(
				'methods'  => WP_REST_Server::READABLE,
				'callback' => array( $this, 'get_data' ),
				'permission_callback' => __CLASS__ . '::permission_callback',
			),
		) );
	}

	public function get_data( $request ) {
		return json_decode( Jetpack_Core_Json_Api_Endpoints::get_plans() );
	}

	public function permission_callback() {
		return Jetpack_Core_Json_Api_Endpoints::connect_url_permission_callback();
	}
}

wpcom_rest_api_v2_load_plugin( 'Jetpack_Admin_REST_API_V2_Endpoint_List_Plans' );
