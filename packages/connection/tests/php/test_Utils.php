<?php // phpcs:ignore WordPress.Files.FileName.InvalidClassFileName
/**
 * The UtilsTest class file.
 *
 * @package automattic/jetpack-connection
 */

namespace Automattic\Jetpack\Connection;

use Automattic\Jetpack\Constants;
use PHPUnit\Framework\TestCase;

/**
 * Provides unit tests for the methods in the Utils class.
 */
class UtilsTest extends TestCase {

	/**
	 * This method is called after each test.
	 */
	public function tearDown() {
		Constants::clear_constants();
	}

	/**
	 * Tests the Utils::get_jetpack_api_constant() method.
	 *
	 * @covers Automattic\Jetpack\Connection\Utils::get_jetpack_api_constant
	 * @dataProvider get_jetpack_api_constant_data_provider
	 *
	 * @param string $name The constant name.
	 * @param mixed  $value The constant value. Null if the constant will not be set.
	 * @param mixed  $expected_output The expected output of Utils::get_jetpack_api_constant.
	 */
	public function test_get_jetpack__api_constant( $name, $value, $expected_output ) {
		if ( $value ) {
			Constants::set_constant( $name, $value );
		}
		$this->assertEquals( $expected_output, Utils::get_jetpack_api_constant( $name ) );
	}

	/**
	 * Data provider for test_get_jetpack__api_constant.
	 *
	 * The test data arrays have the format:
	 *    'name'   => The name of the constant.
	 *    'value'  => The value that the constant will be set to. Null if the constant will not be set.
	 *    'output' => The expected output of Utils::get_jetpack_api_constant().
	 */
	public function get_jetpack_api_constant_data_provider() {
		return array(
			'jetpack__api_base_with_constant'       =>
				array(
					'name'   => 'JETPACK__API_BASE',
					'value'  => 'https://example.com/api/base.',
					'output' => 'https://example.com/api/base.',
				),
			'jetpack__api_base_without_constant'    =>
				array(
					'name'   => 'JETPACK__API_BASE',
					'value'  => null,
					'output' => Utils::DEFAULT_JETPACK__API_BASE,
				),
			'jetpack__api_version_with_constant'    =>
				array(
					'name'   => 'JETPACK__API_VERSION',
					'value'  => 3,
					'output' => 3,
				),
			'jetpack__api_version_without_constant' =>
				array(
					'name'   => 'JETPACK__API_VERSION',
					'value'  => null,
					'output' => Utils::DEFAULT_JETPACK__API_VERSION,
				),
			'bad_constant_name'                     =>
				array(
					'name'   => 'JETPACK_TEST',
					'value'  => null,
					'output' => null,
				),
		);
	}
}
