<?php
/**
 * The Jetpack Connection manager class file.
 *
 * @package jetpack-connection
 */

namespace Automattic\Jetpack\Connection;

use Automattic\Jetpack\Constants;
use Automattic\Jetpack\Tracking;

/**
 * The Jetpack Connection Manager class that is used as a single gateway between WordPress.com
 * and Jetpack.
 */
class Manager implements Manager_Interface {

	const SECRETS_MISSING        = 'secrets_missing';
	const SECRETS_EXPIRED        = 'secrets_expired';
	const SECRETS_OPTION_NAME    = 'jetpack_secrets';
	const MAGIC_NORMAL_TOKEN_KEY = ';normal;';
	const JETPACK_MASTER_USER    = true;

	static $capability_translations = array(
		'administrator' => 'manage_options',
		'editor'        => 'edit_others_posts',
		'author'        => 'publish_posts',
		'contributor'   => 'edit_posts',
		'subscriber'    => 'read',
	);

	public static function apply_activation_source_to_args( &$args ) {
		list( $activation_source_name, $activation_source_keyword ) = get_option( 'jetpack_activation_source' );

		if ( $activation_source_name ) {
			$args['_as'] = urlencode( $activation_source_name );
		}

		if ( $activation_source_keyword ) {
			$args['_ak'] = urlencode( $activation_source_keyword );
		}
	}

	/**
	 * The procedure that should be run to generate secrets.
	 *
	 * @var Callable
	 */
	protected $secret_callable;

	/**
	 * Initializes all needed hooks and request handlers. Handles API calls, upload
	 * requests, authentication requests. Also XMLRPC options requests.
	 * Fallback XMLRPC is also a bridge, but probably can be a class that inherits
	 * this one. Among other things it should strip existing methods.
	 *
	 * @param Array $methods an array of API method names for the Connection to accept and
	 *                       pass on to existing callables. It's possible to specify whether
	 *                       each method should be available for unauthenticated calls or not.
	 * @see Jetpack::__construct
	 */
	public function initialize( $methods ) {
		$methods;
	}

	public static function admin_url( $args = null ) {
		$args = wp_parse_args( $args, array( 'page' => 'jetpack' ) );
		$url  = add_query_arg( $args, admin_url( 'admin.php' ) );
		return $url;
	}

	/**
	 * Returns true if the current site is connected to WordPress.com.
	 *
	 * @return Boolean is the site connected?
	 */
	public function is_active() {
		return (bool) $this->get_access_token( self::JETPACK_MASTER_USER );
	}

	/**
	 * Returns true if the user with the specified identifier is connected to
	 * WordPress.com.
	 *
	 * @param Integer|Boolean $user_id the user identifier.
	 * @return Boolean is the user connected?
	 */
	public function is_user_connected( $user_id = false ) {
		$user_id = false === $user_id ? get_current_user_id() : absint( $user_id );
		if ( ! $user_id ) {
			return false;
		}

		return (bool) $this->get_access_token( $user_id );
	}

	/**
	 * Get the wpcom user data of the current|specified connected user.
	 *
	 * @param Integer $user_id the user identifier.
	 * @return Object the user object.
	 */
	public function get_connected_user_data( $user_id = null ) {
		if ( ! $user_id ) {
			$user_id = get_current_user_id();
		}

		$transient_key    = "jetpack_connected_user_data_$user_id";
		$cached_user_data = get_transient( $transient_key );

		if ( $cached_user_data ) {
			return $cached_user_data;
		}

		\Jetpack::load_xml_rpc_client();
		$xml = new \Jetpack_IXR_Client(
			array(
				'user_id' => $user_id,
			)
		);
		$xml->query( 'wpcom.getUser' );
		if ( ! $xml->isError() ) {
			$user_data = $xml->getResponse();
			set_transient( $transient_key, $xml->getResponse(), DAY_IN_SECONDS );
			return $user_data;
		}

		return false;
	}

	/**
	 * Is the user the connection owner.
	 *
	 * @param Integer $user_id the user identifier.
	 * @return Boolean is the user the connection owner?
	 */
	public function is_connection_owner( $user_id ) {
		return $user_id;
	}

	/**
	 * Unlinks the current user from the linked WordPress.com user
	 *
	 * @param Integer $user_id the user identifier.
	 */
	public static function disconnect_user( $user_id ) {
		return $user_id;
	}

	/**
	 * Initializes a transport server, whatever it may be, saves into the object property.
	 * Should be changed to be protected.
	 */
	public function initialize_server() {

	}

	/**
	 * Checks if the current request is properly authenticated, bails if not.
	 * Should be changed to be protected.
	 */
	public function require_authentication() {

	}

	/**
	 * Verifies the correctness of the request signature.
	 * Should be changed to be protected.
	 */
	public function verify_signature() {

	}

	/**
	 * Attempts Jetpack registration which sets up the site for connection. Should
	 * remain public because the call to action comes from the current site, not from
	 * WordPress.com.
	 *
	 * @return Integer zero on success, or a bitmask on failure.
	 */
	public function register() {
		return 0;
	}

	/**
	 * Returns the callable that would be used to generate secrets.
	 *
	 * @return Callable a function that returns a secure string to be used as a secret.
	 */
	protected function get_secret_callable() {
		if ( ! isset( $this->secret_callable ) ) {
			/**
			 * Allows modification of the callable that is used to generate connection secrets.
			 *
			 * @param Callable a function or method that returns a secret string.
			 */
			$this->secret_callable = apply_filters( 'jetpack_connection_secret_generator', 'wp_generate_password' );
		}

		return $this->secret_callable;
	}

	/**
	 * Generates two secret tokens and the end of life timestamp for them.
	 *
	 * @param String  $action  The action name.
	 * @param Integer $user_id The user identifier.
	 * @param Integer $exp     Expiration time in seconds.
	 */
	public function generate_secrets( $action, $user_id, $exp ) {
		$callable = $this->get_secret_callable();

		$secrets = \Jetpack_Options::get_raw_option(
			self::SECRETS_OPTION_NAME,
			array()
		);

		$secret_name = 'jetpack_' . $action . '_' . $user_id;

		if (
			isset( $secrets[ $secret_name ] ) &&
			$secrets[ $secret_name ]['exp'] > time()
		) {
			return $secrets[ $secret_name ];
		}

		$secret_value = array(
			'secret_1' => call_user_func( $callable ),
			'secret_2' => call_user_func( $callable ),
			'exp'      => time() + $exp,
		);

		$secrets[ $secret_name ] = $secret_value;

		\Jetpack_Options::update_raw_option( self::SECRETS_OPTION_NAME, $secrets );
		return $secrets[ $secret_name ];
	}

	/**
	 * Returns two secret tokens and the end of life timestamp for them.
	 *
	 * @param String  $action  The action name.
	 * @param Integer $user_id The user identifier.
	 * @return string|array an array of secrets or an error string.
	 */
	public function get_secrets( $action, $user_id ) {
		$secret_name = 'jetpack_' . $action . '_' . $user_id;
		$secrets     = \Jetpack_Options::get_raw_option(
			self::SECRETS_OPTION_NAME,
			array()
		);

		if ( ! isset( $secrets[ $secret_name ] ) ) {
			return self::SECRETS_MISSING;
		}

		if ( $secrets[ $secret_name ]['exp'] < time() ) {
			$this->delete_secrets( $action, $user_id );
			return self::SECRETS_EXPIRED;
		}

		return $secrets[ $secret_name ];
	}

	/**
	 * Deletes secret tokens in case they, for example, have expired.
	 *
	 * @param String  $action  The action name.
	 * @param Integer $user_id The user identifier.
	 */
	public function delete_secrets( $action, $user_id ) {
		$secret_name = 'jetpack_' . $action . '_' . $user_id;
		$secrets     = \Jetpack_Options::get_raw_option(
			self::SECRETS_OPTION_NAME,
			array()
		);
		if ( isset( $secrets[ $secret_name ] ) ) {
			unset( $secrets[ $secret_name ] );
			\Jetpack_Options::update_raw_option( self::SECRETS_OPTION_NAME, $secrets );
		}
	}

	/**
	 * Responds to a WordPress.com call to register the current site.
	 * Should be changed to protected.
	 *
	 * @param array $registration_data Array of [ secret_1, user_id ].
	 */
	public function handle_registration( array $registration_data ) {
		list( $registration_secret_1, $registration_user_id ) = $registration_data;
		if ( empty( $registration_user_id ) ) {
			return new \WP_Error( 'registration_state_invalid', __( 'Invalid Registration State', 'jetpack' ), 400 );
		}

		return $this->verify_secrets( 'register', $registration_secret_1, (int) $registration_user_id );
	}

	/**
	 * Verify a Previously Generated Secret.
	 *
	 * @param string $action   The type of secret to verify.
	 * @param string $secret_1 The secret string to compare to what is stored.
	 * @param int    $user_id  The user ID of the owner of the secret.
	 */
	protected function verify_secrets( $action, $secret_1, $user_id ) {
		$allowed_actions = array( 'register', 'authorize', 'publicize' );
		if ( ! in_array( $action, $allowed_actions, true ) ) {
			return new \WP_Error( 'unknown_verification_action', 'Unknown Verification Action', 400 );
		}

		$user = get_user_by( 'id', $user_id );

		/**
		 * We've begun verifying the previously generated secret.
		 *
		 * @since 7.5.0
		 *
		 * @param string   $action The type of secret to verify.
		 * @param \WP_User $user The user object.
		 */
		do_action( 'jetpack_verify_secrets_begin', $action, $user );

		$return_error = function( \WP_Error $error ) use ( $action, $user ) {
			/**
			 * Verifying of the previously generated secret has failed.
			 *
			 * @since 7.5.0
			 *
			 * @param string    $action  The type of secret to verify.
			 * @param \WP_User  $user The user object.
			 * @param \WP_Error $error The error object.
			 */
			do_action( 'jetpack_verify_secrets_fail', $action, $user, $error );

			return $error;
		};

		$stored_secrets = $this->get_secrets( $action, $user_id );
		$this->delete_secrets( $action, $user_id );

		if ( empty( $secret_1 ) ) {
			return $return_error(
				new \WP_Error(
					'verify_secret_1_missing',
					/* translators: "%s" is the name of a paramter. It can be either "secret_1" or "state". */
					sprintf( __( 'The required "%s" parameter is missing.', 'jetpack' ), 'secret_1' ),
					400
				)
			);
		} elseif ( ! is_string( $secret_1 ) ) {
			return $return_error(
				new \WP_Error(
					'verify_secret_1_malformed',
					/* translators: "%s" is the name of a paramter. It can be either "secret_1" or "state". */
					sprintf( __( 'The required "%s" parameter is malformed.', 'jetpack' ), 'secret_1' ),
					400
				)
			);
		} elseif ( empty( $user_id ) ) {
			// $user_id is passed around during registration as "state".
			return $return_error(
				new \WP_Error(
					'state_missing',
					/* translators: "%s" is the name of a paramter. It can be either "secret_1" or "state". */
					sprintf( __( 'The required "%s" parameter is missing.', 'jetpack' ), 'state' ),
					400
				)
			);
		} elseif ( ! ctype_digit( (string) $user_id ) ) {
			return $return_error(
				new \WP_Error(
					'verify_secret_1_malformed',
					/* translators: "%s" is the name of a paramter. It can be either "secret_1" or "state". */
					sprintf( __( 'The required "%s" parameter is malformed.', 'jetpack' ), 'state' ),
					400
				)
			);
		}

		if ( ! $stored_secrets ) {
			return $return_error(
				new \WP_Error(
					'verify_secrets_missing',
					__( 'Verification secrets not found', 'jetpack' ),
					400
				)
			);
		} elseif ( is_wp_error( $stored_secrets ) ) {
			$stored_secrets->add_data( 400 );
			return $return_error( $stored_secrets );
		} elseif ( empty( $stored_secrets['secret_1'] ) || empty( $stored_secrets['secret_2'] ) || empty( $stored_secrets['exp'] ) ) {
			return $return_error(
				new \WP_Error(
					'verify_secrets_incomplete',
					__( 'Verification secrets are incomplete', 'jetpack' ),
					400
				)
			);
		} elseif ( ! hash_equals( $secret_1, $stored_secrets['secret_1'] ) ) {
			return $return_error(
				new \WP_Error(
					'verify_secrets_mismatch',
					__( 'Secret mismatch', 'jetpack' ),
					400
				)
			);
		}

		/**
		 * We've succeeded at verifying the previously generated secret.
		 *
		 * @since 7.5.0
		 *
		 * @param string   $action The type of secret to verify.
		 * @param \WP_User $user The user object.
		 */
		do_action( 'jetpack_verify_secrets_success', $action, $user );

		return $stored_secrets['secret_2'];
	}

	/**
	 * Responds to a WordPress.com call to authorize the current user.
	 * Should be changed to protected.
	 */
	public function handle_authorization() {

	}

	/**
	 * Builds a URL to the Jetpack connection auth page.
	 * This needs rethinking.
	 *
	 * @param bool        $raw If true, URL will not be escaped.
	 * @param bool|string $redirect If true, will redirect back to Jetpack wp-admin landing page after connection.
	 *                              If string, will be a custom redirect.
	 * @param bool|string $from If not false, adds 'from=$from' param to the connect URL.
	 * @param bool        $register If true, will generate a register URL regardless of the existing token, since 4.9.0.
	 *
	 * @return string Connect URL
	 */
	public function build_connect_url( $raw = false, $redirect = false, $from = false, $register = false ) {
		$site_id    = \Jetpack_Options::get_option( 'id' );
		$blog_token = \Jetpack_Data::get_access_token();

		if ( $register || ! $blog_token || ! $site_id ) {
			$url = $this->nonce_url_no_esc( $this->admin_url( 'action=register' ), 'jetpack-register' );

			if ( ! empty( $redirect ) ) {
				$url = add_query_arg(
					'redirect',
					urlencode( wp_validate_redirect( esc_url_raw( $redirect ) ) ),
					$url
				);
			}

			if ( is_network_admin() ) {
				$url = add_query_arg( 'is_multisite', network_admin_url( 'admin.php?page=jetpack-settings' ), $url );
			}
		} else {

			// Let's check the existing blog token to see if we need to re-register. We only check once per minute
			// because otherwise this logic can get us in to a loop.
			$last_connect_url_check = intval( \Jetpack_Options::get_raw_option( 'jetpack_last_connect_url_check' ) );
			if ( ! $last_connect_url_check || ( time() - $last_connect_url_check ) > MINUTE_IN_SECONDS ) {
				\Jetpack_Options::update_raw_option( 'jetpack_last_connect_url_check', time() );

				$response = Client::wpcom_json_api_request_as_blog(
					sprintf( '/sites/%d', $site_id ) . '?force=wpcom',
					'1.1'
				);

				if ( 200 !== wp_remote_retrieve_response_code( $response ) ) {

					// Generating a register URL instead to refresh the existing token
					return $this->build_connect_url( $raw, $redirect, $from, true );
				}
			}

			if ( defined( 'JETPACK__GLOTPRESS_LOCALES_PATH' ) && include_once JETPACK__GLOTPRESS_LOCALES_PATH ) {
				$gp_locale = \GP_Locales::by_field( 'wp_locale', get_locale() );
			}

			$role        = self::translate_current_user_to_role();
			$signed_role = self::sign_role( $role );

			$user = wp_get_current_user();

			$jetpack_admin_page = esc_url_raw( admin_url( 'admin.php?page=jetpack' ) );
			$redirect           = $redirect
				? wp_validate_redirect( esc_url_raw( $redirect ), $jetpack_admin_page )
				: $jetpack_admin_page;

			if ( isset( $_REQUEST['is_multisite'] ) ) {
				$redirect = Jetpack_Network::init()->get_url( 'network_admin_page' );
			}

			$secrets = $this->generate_secrets( 'authorize', false, 2 * HOUR_IN_SECONDS );

			/**
			 * Filter the type of authorization.
			 * 'calypso' completes authorization on wordpress.com/jetpack/connect
			 * while 'jetpack' ( or any other value ) completes the authorization at jetpack.wordpress.com.
			 *
			 * @since 4.3.3
			 *
			 * @param string $auth_type Defaults to 'calypso', can also be 'jetpack'.
			 */
			$auth_type = apply_filters( 'jetpack_auth_type', 'calypso' );

			$tracks          = new Tracking();
			$client          = new Client();
			$tracks_identity = $tracks->tracks_get_identity( get_current_user_id() );

			$args = urlencode_deep(
				array(
					'response_type' => 'code',
					'client_id'     => \Jetpack_Options::get_option( 'id' ),
					'redirect_uri'  => add_query_arg(
						array(
							'action'   => 'authorize',
							'_wpnonce' => wp_create_nonce( "jetpack-authorize_{$role}_{$redirect}" ),
							'redirect' => urlencode( $redirect ),
						),
						esc_url( admin_url( 'admin.php?page=jetpack' ) )
					),
					'state'         => $user->ID,
					'scope'         => $signed_role,
					'user_email'    => $user->user_email,
					'user_login'    => $user->user_login,
					'is_active'     => $this->is_active(),
					'jp_version'    => JETPACK__VERSION,
					'auth_type'     => $auth_type,
					'secret'        => $secrets['secret_1'],
					'locale'        => ( isset( $gp_locale ) && isset( $gp_locale->slug ) ) ? $gp_locale->slug : '',
					'blogname'      => get_option( 'blogname' ),
					'site_url'      => site_url(),
					'home_url'      => home_url(),
					'site_icon'     => get_site_icon_url(),
					'site_lang'     => get_locale(),
					'_ui'           => $tracks_identity['_ui'],
					'_ut'           => $tracks_identity['_ut'],
					'site_created'  => $this->get_assumed_site_creation_date(),
				)
			);

			$this->apply_activation_source_to_args( $args );

			$url = add_query_arg( $args, $client::api_url( 'authorize' ) );
		}

		if ( $from ) {
			$url = add_query_arg( 'from', $from, $url );
		}

		// Ensure that class to get the affiliate code is loaded
		if ( ! class_exists( 'Jetpack_Affiliate' ) ) {
			require_once JETPACK__PLUGIN_DIR . 'class.jetpack-affiliate.php';
		}
		// Get affiliate code and add it to the URL
		$url = \Jetpack_Affiliate::init()->add_code_as_query_arg( $url );

		$calypso_env = $this->get_calypso_env();

		if ( ! empty( $calypso_env ) ) {
			$url = add_query_arg( 'calypso_env', $calypso_env, $url );
		}

		return $raw ? esc_url_raw( $url ) : esc_url( $url );
	}

	static function translate_current_user_to_role() {
		foreach ( self::$capability_translations as $role => $cap ) {
			if ( current_user_can( $role ) || current_user_can( $cap ) ) {
				return $role;
			}
		}

		return false;
	}

	/**
	 * Disconnects from the Jetpack servers.
	 * Forgets all connection details and tells the Jetpack servers to do the same.
	 */
	public function disconnect_site() {

	}

	public static function nonce_url_no_esc( $actionurl, $action = -1, $name = '_wpnonce' ) {
		$actionurl = str_replace( '&amp;', '&', $actionurl );
		return add_query_arg( $name, wp_create_nonce( $action ), $actionurl );
	}
	/**
	 * The Base64 Encoding of the SHA1 Hash of the Input.
	 *
	 * @param string $text The string to hash.
	 * @return string
	 */
	public function sha1_base64( $text ) {
		return base64_encode( sha1( $text, true ) ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
	}

	static function sign_role( $role, $user_id = null ) {
		if ( empty( $user_id ) ) {
			$user_id = (int) get_current_user_id();
		}

		if ( ! $user_id ) {
			return false;
		}

		$token = \Jetpack_Data::get_access_token();
		if ( ! $token || is_wp_error( $token ) ) {
			return false;
		}

		return $role . ':' . hash_hmac( 'md5', "{$role}|{$user_id}", $token->secret );
	}

	/**
	 * This function mirrors Jetpack_Data::is_usable_domain() in the WPCOM codebase.
	 *
	 * @param string $domain The domain to check.
	 *
	 * @return bool|WP_Error
	 */
	public function is_usable_domain( $domain ) {

		// If it's empty, just fail out.
		if ( ! $domain ) {
			return new \WP_Error(
				'fail_domain_empty',
				/* translators: %1$s is a domain name. */
				sprintf( __( 'Domain `%1$s` just failed is_usable_domain check as it is empty.', 'jetpack' ), $domain )
			);
		}

		/**
		 * Skips the usuable domain check when connecting a site.
		 *
		 * Allows site administrators with domains that fail gethostname-based checks to pass the request to WP.com
		 *
		 * @since 4.1.0
		 *
		 * @param bool If the check should be skipped. Default false.
		 */
		if ( apply_filters( 'jetpack_skip_usuable_domain_check', false ) ) {
			return true;
		}

		// None of the explicit localhosts.
		$forbidden_domains = array(
			'wordpress.com',
			'localhost',
			'localhost.localdomain',
			'127.0.0.1',
			'local.wordpress.test',         // VVV pattern.
			'local.wordpress-trunk.test',   // VVV pattern.
			'src.wordpress-develop.test',   // VVV pattern.
			'build.wordpress-develop.test', // VVV pattern.
		);
		if ( in_array( $domain, $forbidden_domains, true ) ) {
			return new \WP_Error(
				'fail_domain_forbidden',
				sprintf(
					/* translators: %1$s is a domain name. */
					__(
						'Domain `%1$s` just failed is_usable_domain check as it is in the forbidden array.',
						'jetpack'
					),
					$domain
				)
			);
		}

		// No .test or .local domains.
		if ( preg_match( '#\.(test|local)$#i', $domain ) ) {
			return new \WP_Error(
				'fail_domain_tld',
				sprintf(
					/* translators: %1$s is a domain name. */
					__(
						'Domain `%1$s` just failed is_usable_domain check as it uses an invalid top level domain.',
						'jetpack'
					),
					$domain
				)
			);
		}

		// No WPCOM subdomains.
		if ( preg_match( '#\.WordPress\.com$#i', $domain ) ) {
			return new \WP_Error(
				'fail_subdomain_wpcom',
				sprintf(
					/* translators: %1$s is a domain name. */
					__(
						'Domain `%1$s` just failed is_usable_domain check as it is a subdomain of WordPress.com.',
						'jetpack'
					),
					$domain
				)
			);
		}

		// If PHP was compiled without support for the Filter module (very edge case).
		if ( ! function_exists( 'filter_var' ) ) {
			// Just pass back true for now, and let wpcom sort it out.
			return true;
		}

		return true;
	}

	/**
	 * Gets the requested token.
	 *
	 * Tokens are one of two types:
	 * 1. Blog Tokens: These are the "main" tokens. Each site typically has one Blog Token,
	 *    though some sites can have multiple "Special" Blog Tokens (see below). These tokens
	 *    are not associated with a user account. They represent the site's connection with
	 *    the Jetpack servers.
	 * 2. User Tokens: These are "sub-"tokens. Each connected user account has one User Token.
	 *
	 * All tokens look like "{$token_key}.{$private}". $token_key is a public ID for the
	 * token, and $private is a secret that should never be displayed anywhere or sent
	 * over the network; it's used only for signing things.
	 *
	 * Blog Tokens can be "Normal" or "Special".
	 * * Normal: The result of a normal connection flow. They look like
	 *   "{$random_string_1}.{$random_string_2}"
	 *   That is, $token_key and $private are both random strings.
	 *   Sites only have one Normal Blog Token. Normal Tokens are found in either
	 *   Jetpack_Options::get_option( 'blog_token' ) (usual) or the JETPACK_BLOG_TOKEN
	 *   constant (rare).
	 * * Special: A connection token for sites that have gone through an alternative
	 *   connection flow. They look like:
	 *   ";{$special_id}{$special_version};{$wpcom_blog_id};.{$random_string}"
	 *   That is, $private is a random string and $token_key has a special structure with
	 *   lots of semicolons.
	 *   Most sites have zero Special Blog Tokens. Special tokens are only found in the
	 *   JETPACK_BLOG_TOKEN constant.
	 *
	 * In particular, note that Normal Blog Tokens never start with ";" and that
	 * Special Blog Tokens always do.
	 *
	 * When searching for a matching Blog Tokens, Blog Tokens are examined in the following
	 * order:
	 * 1. Defined Special Blog Tokens (via the JETPACK_BLOG_TOKEN constant)
	 * 2. Stored Normal Tokens (via Jetpack_Options::get_option( 'blog_token' ))
	 * 3. Defined Normal Tokens (via the JETPACK_BLOG_TOKEN constant)
	 *
	 * @param int|false    $user_id   false: Return the Blog Token. int: Return that user's User Token.
	 * @param string|false $token_key If provided, check that the token matches the provided input.
	 * @param bool|true    $suppress_errors If true, return a falsy value when the token isn't found; When false, return a descriptive WP_Error when the token isn't found.
	 *
	 * @return object|false
	 */
	public function get_access_token( $user_id = false, $token_key = false, $suppress_errors = true ) {
		$possible_special_tokens = array();
		$possible_normal_tokens  = array();
		$user_tokens             = \Jetpack_Options::get_option( 'user_tokens' );

		if ( $user_id ) {
			if ( ! $user_tokens ) {
				return $suppress_errors ? false : new \WP_Error( 'no_user_tokens' );
			}
			if ( self::JETPACK_MASTER_USER === $user_id ) {
				$user_id = \Jetpack_Options::get_option( 'master_user' );
				if ( ! $user_id ) {
					return $suppress_errors ? false : new \WP_Error( 'empty_master_user_option' );
				}
			}
			if ( ! isset( $user_tokens[ $user_id ] ) || ! $user_tokens[ $user_id ] ) {
				return $suppress_errors ? false : new \WP_Error( 'no_token_for_user', sprintf( 'No token for user %d', $user_id ) );
			}
			$user_token_chunks = explode( '.', $user_tokens[ $user_id ] );
			if ( empty( $user_token_chunks[1] ) || empty( $user_token_chunks[2] ) ) {
				return $suppress_errors ? false : new \WP_Error( 'token_malformed', sprintf( 'Token for user %d is malformed', $user_id ) );
			}
			if ( $user_token_chunks[2] !== (string) $user_id ) {
				return $suppress_errors ? false : new \WP_Error( 'user_id_mismatch', sprintf( 'Requesting user_id %d does not match token user_id %d', $user_id, $user_token_chunks[2] ) );
			}
			$possible_normal_tokens[] = "{$user_token_chunks[0]}.{$user_token_chunks[1]}";
		} else {
			$stored_blog_token = \Jetpack_Options::get_option( 'blog_token' );
			if ( $stored_blog_token ) {
				$possible_normal_tokens[] = $stored_blog_token;
			}

			$defined_tokens_string = Constants::get_constant( 'JETPACK_BLOG_TOKEN' );

			if ( $defined_tokens_string ) {
				$defined_tokens = explode( ',', $defined_tokens_string );
				foreach ( $defined_tokens as $defined_token ) {
					if ( ';' === $defined_token[0] ) {
						$possible_special_tokens[] = $defined_token;
					} else {
						$possible_normal_tokens[] = $defined_token;
					}
				}
			}
		}

		if ( self::MAGIC_NORMAL_TOKEN_KEY === $token_key ) {
			$possible_tokens = $possible_normal_tokens;
		} else {
			$possible_tokens = array_merge( $possible_special_tokens, $possible_normal_tokens );
		}

		if ( ! $possible_tokens ) {
			return $suppress_errors ? false : new \WP_Error( 'no_possible_tokens' );
		}

		$valid_token = false;

		if ( false === $token_key ) {
			// Use first token.
			$valid_token = $possible_tokens[0];
		} elseif ( self::MAGIC_NORMAL_TOKEN_KEY === $token_key ) {
			// Use first normal token.
			$valid_token = $possible_tokens[0]; // $possible_tokens only contains normal tokens because of earlier check.
		} else {
			// Use the token matching $token_key or false if none.
			// Ensure we check the full key.
			$token_check = rtrim( $token_key, '.' ) . '.';

			foreach ( $possible_tokens as $possible_token ) {
				if ( hash_equals( substr( $possible_token, 0, strlen( $token_check ) ), $token_check ) ) {
					$valid_token = $possible_token;
					break;
				}
			}
		}

		if ( ! $valid_token ) {
			return $suppress_errors ? false : new \WP_Error( 'no_valid_token' );
		}

		return (object) array(
			'secret'           => $valid_token,
			'external_user_id' => (int) $user_id,
		);
	}

	/**
	 * Get our assumed site creation date.
	 * Calculated based on the earlier date of either:
	 * - Earliest admin user registration date.
	 * - Earliest date of post of any post type.
	 *
	 * @since 7.2.0
	 *
	 * @return string Assumed site creation date and time.
	 */
	public static function get_assumed_site_creation_date() {
		$earliest_registered_users  = get_users(
			array(
				'role'    => 'administrator',
				'orderby' => 'user_registered',
				'order'   => 'ASC',
				'fields'  => array( 'user_registered' ),
				'number'  => 1,
			)
		);
		$earliest_registration_date = $earliest_registered_users[0]->user_registered;

		$earliest_posts = get_posts(
			array(
				'posts_per_page' => 1,
				'post_type'      => 'any',
				'post_status'    => 'any',
				'orderby'        => 'date',
				'order'          => 'ASC',
			)
		);

		// If there are no posts at all, we'll count only on user registration date.
		if ( $earliest_posts ) {
			$earliest_post_date = $earliest_posts[0]->post_date;
		} else {
			$earliest_post_date = PHP_INT_MAX;
		}

		return min( $earliest_registration_date, $earliest_post_date );
	}

	/**
	 * Return Calypso environment value; used for developing Jetpack and pairing
	 * it with different Calypso enrionments, such as localhost.
	 *
	 * @since 7.4.0
	 *
	 * @return string Calypso environment
	 */
	public static function get_calypso_env() {
		if ( isset( $_GET['calypso_env'] ) ) {
			return sanitize_key( $_GET['calypso_env'] );
		}

		if ( getenv( 'CALYPSO_ENV' ) ) {
			return sanitize_key( getenv( 'CALYPSO_ENV' ) );
		}

		if ( defined( 'CALYPSO_ENV' ) && CALYPSO_ENV ) {
			return sanitize_key( CALYPSO_ENV );
		}

		return '';
	}
}
