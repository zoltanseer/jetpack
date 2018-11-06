<?php

class Jetpack_Admin_REST_API_V2_Endpoint_Remote_User_Authorization {
	public function __construct() {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	public function register_routes() {
		register_rest_route( 'jetpack/v6', '/remoteuser/authorization', array(
			array(
				'methods'  => WP_REST_Server::EDITABLE,
				'callback' => array( $this, 'authorize_remote_user' ),
			),
		) );
	}

	public function authorize_remote_user( $request ) {
		return Jetpack_Core_Json_Api_Endpoints::remote_authorize( $request );
	}

}

wpcom_rest_api_v2_load_plugin( 'Jetpack_Admin_REST_API_V2_Endpoint_Remote_User_Authorization' );
