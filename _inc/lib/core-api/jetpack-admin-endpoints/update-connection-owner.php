<?php

class Jetpack_Admin_REST_API_V2_Endpoint_Update_Connection_Owner {
	public function __construct() {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	public function register_routes() {
		register_rest_route( 'jetpack/v6', '/connection/owner', array(
			array(
				'methods'  => WP_REST_Server::EDITABLE,
				'callback' => array( $this, 'update_connection_owner' ),
				'permission_callback' => 'Jetpack_Core_Json_Api_Endpoints::set_connection_owner_permission_callback',
			),
		) );
	}

	public function update_connection_owner( $request ) {
		return Jetpack_Core_Json_Api_Endpoints::set_connection_owner( $request );
	}
}

wpcom_rest_api_v2_load_plugin( 'Jetpack_Admin_REST_API_V2_Endpoint_Update_Connection_Owner' );
