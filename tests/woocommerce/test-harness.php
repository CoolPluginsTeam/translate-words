<?php
/**
 * Verifies that the isolated unit harness itself behaves as the tests assume.
 *
 * @package Linguator
 */

use PHPUnit\Framework\TestCase;

class Harness_Test extends TestCase {

	/**
	 * Resets the simulated request before each test.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		lmat_test_reset_state();
	}

	/**
	 * The stub state is readable and writable.
	 *
	 * @return void
	 */
	public function test_state_round_trip() {
		lmat_test_set( 'shop_page_id', 42 );

		$this->assertSame( 42, lmat_test_get( 'shop_page_id' ) );
	}

	/**
	 * Permalinks are resolved from the simulated state and fail closed.
	 *
	 * @return void
	 */
	public function test_permalink_stub() {
		lmat_test_set( 'permalinks', array( 7 => 'https://example.test/de/shop-page/' ) );

		$this->assertSame( 'https://example.test/de/shop-page/', get_permalink( 7 ) );
		$this->assertFalse( get_permalink( 8 ) );
	}
}
