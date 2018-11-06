<?php

class Jetpack_Admin_REST_API_V2_Endpoint_Verify_Registration {
	public function __construct() {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	public function register_routes() {
		register_rest_route( 'jetpack/v6', '/registration/verification', array(
			array(
				'methods'  => WP_REST_Server::EDITABLE,
				'callback' => array( $this, 'verify_registration' ),
			),
		) );
	}

	public function verify_registration( $request ) {
		return Jetpack_Core_Json_Api_Endpoints::verify_registration( $request );
	}

}

wpcom_rest_api_v2_load_plugin( 'Jetpack_Admin_REST_API_V2_Endpoint_Verify_Registration' );
