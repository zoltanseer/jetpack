<?php

class Jetpack_Admin_REST_API_V2_Endpoint_Delete_Jitm {
	public function __construct() {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	public function register_routes() {
		register_rest_route( 'jetpack/v6', '/jitm', array(
			array(
				'methods'  => WP_REST_Server::DELETABLE,
				'callback' => array( $this, 'delete_jitm_message' ),
			),
		) );
	}

	public function delete_jitm_message( $request ) {
		return Jetpack_Core_Json_Api_Endpoints::delete_jitm_message( $request );
	}

}

wpcom_rest_api_v2_load_plugin( 'Jetpack_Admin_REST_API_V2_Endpoint_Delete_Jitm' );
