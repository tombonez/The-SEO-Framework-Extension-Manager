<?php
/**
 * @package TSF_Extension_Manager\Classes
 */
namespace TSF_Extension_Manager;

defined( 'ABSPATH' ) or die;

/**
 * The SEO Framework - Extension Manager plugin
 * Copyright (C) 2016-2019 Sybre Waaijer, CyberWire (https://cyberwire.nl/)
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License version 3 as published
 * by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 */

/**
 * Class TSF_Extension_Manager\API
 *
 * Holds plugin API functions.
 *
 * @since 1.0.0
 * @access private
 */
class API extends Core {
	use Enclose_Stray_Private, Construct_Child_Interface;

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 */
	private function construct() { }

	/**
	 * Determines whether the plugin's set to be auto-activated.
	 *
	 * @since 2.0.0
	 *
	 * @return bool
	 */
	final public function is_auto_activated() {
		return (bool) TSF_EXTENSION_MANAGER_API_INFORMATION;
	}

	/**
	 * Fetches status API request and returns response data.
	 *
	 * @since 1.0.0
	 *
	 * @param array $args : {
	 *    'licence_key'      => string The license key.
	 *    'activation_email' => string The activation email.
	 * }
	 * @return bool|array {
	 *    Always: False on failure.
	 *    Deactivation: True on successful deactivation.
	 *    Activation/Status: Reponse data.
	 * }
	 */
	final protected function handle_request( $type = 'status', $args = [] ) {

		if ( empty( $args['licence_key'] ) ) {
			$this->set_error_notice( [ 101 => '' ] );
			return false;
		}

		if ( empty( $args['activation_email'] ) ) {
			$this->set_error_notice( [ 102 => '' ] );
			return false;
		}

		$this->activation_key   = trim( $args['licence_key'] );
		$this->activation_email = \sanitize_email( $args['activation_email'] );

		switch ( $type ) :
			case 'status':
			case 'activation':
				break;

			case 'deactivation':
				if ( false === $this->is_plugin_activated() ) {
					$this->kill_options();
					$this->set_error_notice( [ 103 => '' ] );
					return false;
				}

				if ( false === $this->is_connected_user() ) {
					return $this->do_free_deactivation();
				}
				//* Premium/Essential deactivation propagates through API, so nothing happens here.
				break;

			default:
				$this->set_error_notice( [ 104 => '' ] );
				return false;
				break;
		endswitch;

		$request = [
			'request'     => $type,
			'licence_key' => $this->activation_key,
			'email'       => $this->activation_email,
		];

		$response = $this->get_api_response( $request );
		$response = $this->handle_response( $type, $response, WP_DEBUG );

		return $response;
	}

	/**
	 * Returns domain host of plugin holder.
	 * Some web hosts have security policies that block the : (colon) and // (slashes) in http://,
	 * so only the host portion of the URL can be sent. For example the host portion might be
	 * www.example.com or example.com. http://www.example.com includes the scheme http,
	 * and the host www.example.com.
	 * Sending only the host also eliminates issues when a client site changes from http to https,
	 * but their activation still uses the original scheme.
	 *
	 * @since 1.0.0
	 * @since 2.0.0 Now uses the site URL instead of the home URL.
	 *
	 * @return string Domain Host.
	 */
	final protected function get_activation_site_domain() {
		return str_ireplace( [ 'http://', 'https://' ], '', \esc_url( \get_home_url(), [ 'http', 'https' ] ) );
	}

	/**
	 * Returns website's instance key from option. Generates one if non-existent.
	 *
	 * @since 1.0.0
	 *
	 * @param bool $save_option Whether to save the instance in an option. Useful
	 *             for when you're going to save it later.
	 * @return string Instance key.
	 */
	final protected function get_activation_instance( $save_option = true ) {

		static $instance = null;

		if ( isset( $instance ) )
			return $instance;

		$instance = $this->get_option( '_instance' );

		if ( empty( $instance ) ) {
			$instance = trim( \wp_generate_password( 32, false ) );

			if ( $save_option )
				$this->update_option( '_instance', $instance );
		}

		return $instance;
	}

	/**
	 * Returns activation domain URL.
	 *
	 * @since 1.0.0
	 *
	 * @param string $path The URL Path.
	 * @return string
	 */
	final protected function get_activation_url( $path = '' ) {
		return TSF_EXTENSION_MANAGER_PREMIUM_URI . ltrim( $path, ' \\/' );
	}

	/**
	 * Generates software API URL to connect to the WooCommerce API manager.
	 *
	 * @since 1.0.0
	 * @since 1.3.2 Circumvented improper separator and encoding.
	 * @link http://php.net/arg-separator.output
	 *
	 * @param array $args The API query parameters.
	 * @return string The escaped API URL with parameters.
	 */
	final protected function get_api_url( $args = [] ) {

		$api_url = \add_query_arg( 'wc-api', 'tsfem-software-api', $this->get_activation_url() );

		return \esc_url_raw( $api_url . '&' . http_build_query( $args, '', '&', PHP_QUERY_RFC1738 ), [ 'http', 'https' ] );
	}

	/**
	 * Connects to the main plugin API handler.
	 *
	 * @since 1.0.0
	 * @since 1.2.0 The security parameters are now passed by reference.
	 * @access private
	 * @see $this->get_api_response();
	 *
	 * @param array $args The API query parameters.
	 * @param string $instance The verification instance key. Passed by reference.
	 * @param int $bit The verification instance bit. Passed by reference.
	 * @return string|boolean The escaped API URL with parameters. False on failed instance verification.
	 */
	final public function _get_api_response( array $args, &$_instance, &$bits ) {

		if ( $this->_verify_instance( $_instance, $bits[1] ) )
			return $this->get_api_response( $args, false );

		return false;
	}

	/**
	 * Connects to the main plugin API handler.
	 *
	 * @since 1.0.0
	 * @see $this->handle_request() The request validation wrapper.
	 *
	 * @param array $args     The API query parameters.
	 * @param bool  $internal Whether the API call is for $this object.
	 * @return string Response body. Empty string if no body or incorrect parameter given.
	 */
	final protected function get_api_response( array $args, $internal = true ) {

		$defaults = [
			'request'     => '',
			'email'       => '',
			'licence_key' => '',
			'instance'    => $this->get_activation_instance( false ),
			'platform'    => $this->get_activation_site_domain(),
			'version'     => '2.0',
		];

		$args = \wp_parse_args( $args, $defaults );

		if ( empty( $args['request'] ) ) {
			$internal && $this->set_error_notice( [ 201 => '' ] );
			return false;
		}

		$target_url = $this->get_api_url( $args );

		$http_args = [
			/**
			 * @since 1.0.0
			 * @param int $timeout 7 seconds should be more than sufficient and equals
			 *                     the API server keep_alive_timeout. WP default is 5.
			 */
			'timeout'     => \apply_filters( 'tsf_extension_manager_request_timeout', 7 ),
			/**
			 * @since 1.0.0
			 * @param string $httpversion HTTP 1.1 is used for improved performance.
			 *                            WP default is '1.0'
			 */
			'httpversion' => \apply_filters( 'tsf_extension_manager_http_request_version', '1.1' ),
		];

		$request = \wp_safe_remote_get( $target_url, $http_args );

		if ( 200 !== (int) \wp_remote_retrieve_response_code( $request ) ) {
			$internal && $this->set_error_notice( [ 202 => '' ] );
			return false;
		}

		return \wp_remote_retrieve_body( $request );
	}

	/**
	 * Handles AME response and sets options.
	 *
	 * @since 1.0.0
	 * @see $this->handle_request() The request validation wrapper.
	 *
	 * @param string $type The request type.
	 * @param string $response The obtained response body.
	 * @param bool   $explain Whether to show additional info in error messages.
	 * @return bool True on successful response, false on failure.
	 */
	final protected function handle_response( $type = 'status', $response = '', $explain = false ) {

		if ( empty( $response ) ) {
			$this->set_error_notice( [ 301 => '' ] );
			return false;
		}

		$results = json_decode( $response, true );

		$_response       = '';
		$additional_info = '';

		//* If the user's already using a free account, don't deactivate.
		$registered_free = $this->is_plugin_activated() && false === $this->is_connected_user();

		if ( 'status' !== $type ) {
			if ( 'activation' === $type ) :
				$_response = $this->handle_premium_activation( $results );
			elseif ( 'deactivation' === $type ) :
				$_response = $this->handle_premium_disconnection( $results );
			endif;
		} else {
			$_response = $results;
		}

		if ( isset( $results['code'] ) ) :
			switch ( $results['code'] ) :
				case '100':
					$additional_info = $explain && ! empty( $results['additional info'] ) ? \esc_attr( $results['additional info'] ) : '';
					$this->set_error_notice( [ 302 => $additional_info ] );
					$registered_free or $this->do_deactivation( true, true );
					break;
				case '101':
					$additional_info = $explain && ! empty( $results['additional info'] ) ? \esc_attr( $results['additional info'] ) : '';
					$this->set_error_notice( [ 303 => $additional_info ] );
					$registered_free or $this->do_deactivation( false, true );
					break;
				case '102':
					$additional_info = $explain && ! empty( $results['additional info'] ) ? \esc_attr( $results['additional info'] ) : '';
					$this->set_error_notice( [ 304 => $additional_info ] );
					$registered_free or $this->do_deactivation( false, true );
					break;
				case '103':
					$additional_info = $explain && ! empty( $results['additional info'] ) ? \esc_attr( $results['additional info'] ) : '';
					$this->set_error_notice( [ 305 => $additional_info ] );
					$registered_free or $this->do_deactivation( false, true );
					break;
				case '104':
					$additional_info = $explain && ! empty( $results['additional info'] ) ? \esc_attr( $results['additional info'] ) : '';
					$this->set_error_notice( [ 306 => $additional_info ] );
					$registered_free or $this->do_deactivation( false, true );
					break;
				case '105':
					$additional_info = $explain && ! empty( $results['additional info'] ) ? \esc_attr( $results['additional info'] ) : '';
					$this->set_error_notice( [ 307 => $additional_info ] );
					$registered_free or $this->do_deactivation( false, true );
					break;
				case '106':
					$additional_info = $explain && ! empty( $results['additional info'] ) ? \esc_attr( $results['additional info'] ) : '';
					$this->set_error_notice( [ 308 => $additional_info ] );
					$registered_free or $this->do_deactivation( false, true );
					break;
				default:
					break;
			endswitch;
		endif;

		return $_response;
	}

	/**
	 * Returns API secret for a final static class.
	 *
	 * The final static class shouldn't be instanced.
	 *
	 * @since 1.5.0
	 * @param string $class The class instance name.
	 * @param string $_instance The verification instance. Passed by reference.
	 * @param array $bits The verification bits. Passed by reference.
	 */
	final public function _init_final_static_extension_api_access( $class, &$_instance, &$bits ) {

		if ( $this->_has_died() )
			return false;

		if ( false === ( $this->_verify_instance( $_instance, $bits[1] ) or $this->_maybe_die() ) )
			return false;

		if ( false === $class )
			return false;

		return $this->generate_api_access_key( $class )[ $class ];
	}

	/**
	 * Gets API response for extension.
	 *
	 * @since 1.5.0
	 *
	 * @param object $object The caller class.
	 * @param string $key    The API access key for $object.
	 * @param array $args : {
	 *   'request' => string The request type.
	 *    ...      => mixed  Additional parameters.
	 * }
	 * @return bool|string False on failure. JSON/HTML response otherwise.
	 */
	final public function _get_extension_api_response( $object, $key, $args ) {

		if ( ! $this->_verify_api_access( $object, $key ) )
			return false;

		$subscription = $this->get_subscription_status();

		$args = array_merge( $args, [
			'email'       => $subscription['email'],
			'licence_key' => $subscription['key'],
		] );

		return $this->get_api_response( $args, false );
	}

	/**
	 * Verifies API access.
	 *
	 * @since 1.5.0
	 *
	 * @param object $object The class object to verify.
	 * @param string $key    The attached object key.
	 * @return bool True if verified, false otherwise.
	 */
	final private function _verify_api_access( $object, $key ) {
		$keys = &$this->generate_api_access_key();
		return $this->coalesce_var( $keys[ get_class( $object ) ], null ) === (string) $key;
	}

	/**
	 * Generates API access keys.
	 *
	 * @since 1.5.0
	 * @since 2.1.0 Enabled entropy to prevent system sleep.
	 *
	 * @param string|bool $class The class name. If false, no key is generated.
	 * @return array $keys, the storage keys. Passed by reference.
	 */
	final private function &generate_api_access_key( $class = false ) {

		static $keys = [];

		if ( false !== $class )
			$keys[ $class ] = mt_rand() . uniqid( '', true );

		return $keys;
	}
}
