<?php
/**
 * PHPUnit bootstrap for the Linguator isolated unit suite.
 *
 * WordPress and WooCommerce are not loaded. The stubs below are driven by a single
 * global state array so that each test can describe the exact request it simulates.
 *
 * Set the environment variable LMAT_TEST_WITHOUT_WOOCOMMERCE to run the suite with the
 * WooCommerce stubs undefined, which reproduces a site where WooCommerce is not active.
 *
 * @package Linguator
 */

define( 'ABSPATH', dirname( __DIR__ ) . '/' );

$GLOBALS['lmat_test_state'] = array();

/**
 * Resets the simulated request state.
 *
 * @since 2.2.1
 *
 * @return void
 */
function lmat_test_reset_state() {
	$GLOBALS['lmat_test_state'] = array(
		'is_shop'        => false,
		'is_front_page'  => false,
		'shop_page_id'   => -1,
		'translations'   => array(),
		'permalinks'     => array(),
		'unreadable_ids' => array(),
		'linguator'      => true,
		'hooks'          => array(),
	);
}

/**
 * Sets one simulated request value.
 *
 * @since 2.2.1
 *
 * @param string $key   State key.
 * @param mixed  $value State value.
 * @return void
 */
function lmat_test_set( $key, $value ) {
	$GLOBALS['lmat_test_state'][ $key ] = $value;
}

/**
 * Reads one simulated request value.
 *
 * @since 2.2.1
 *
 * @param string $key     State key.
 * @param mixed  $default Value returned when the key is not set.
 * @return mixed
 */
function lmat_test_get( $key, $default = null ) {
	return array_key_exists( $key, $GLOBALS['lmat_test_state'] ) ? $GLOBALS['lmat_test_state'][ $key ] : $default;
}

lmat_test_reset_state();

/**
 * Records a filter registration.
 *
 * @since 2.2.1
 *
 * @param string   $hook_name     Hook name.
 * @param callable $callback      Callback.
 * @param int      $priority      Priority.
 * @param int      $accepted_args Number of accepted arguments.
 * @return true
 */
function add_filter( $hook_name, $callback, $priority = 10, $accepted_args = 1 ) {
	$GLOBALS['lmat_test_state']['hooks'][] = array(
		'hook'          => $hook_name,
		'callback'      => $callback,
		'priority'      => $priority,
		'accepted_args' => $accepted_args,
	);

	return true;
}

/**
 * Records an action registration.
 *
 * @since 2.2.1
 *
 * @param string   $hook_name     Hook name.
 * @param callable $callback      Callback.
 * @param int      $priority      Priority.
 * @param int      $accepted_args Number of accepted arguments.
 * @return true
 */
function add_action( $hook_name, $callback, $priority = 10, $accepted_args = 1 ) {
	return add_filter( $hook_name, $callback, $priority, $accepted_args );
}

/**
 * Simulates WordPress's is_front_page().
 *
 * @since 2.2.1
 *
 * @return bool
 */
function is_front_page() {
	return (bool) lmat_test_get( 'is_front_page', false );
}

/**
 * Simulates WordPress's get_permalink().
 *
 * @since 2.2.1
 *
 * @param int $post_id Post ID.
 * @return string|false
 */
function get_permalink( $post_id = 0 ) {
	$permalinks = (array) lmat_test_get( 'permalinks', array() );

	return array_key_exists( (int) $post_id, $permalinks ) ? $permalinks[ (int) $post_id ] : false;
}

/**
 * Simulates Linguator's translation lookup.
 *
 * @since 2.2.1
 *
 * @param int    $post_id Post ID.
 * @param string $lang    Language slug.
 * @return mixed
 */
function linguator_get_post( $post_id, $lang = '' ) {
	$translations = (array) lmat_test_get( 'translations', array() );
	$key          = (int) $post_id . ':' . $lang;

	return array_key_exists( $key, $translations ) ? $translations[ $key ] : 0;
}

/**
 * Post model stub exposing the capability check used by the integration.
 *
 * @since 2.2.1
 */
class Lmat_Test_Post_Model {
	/**
	 * Tells whether the current user can read the post.
	 *
	 * @since 2.2.1
	 *
	 * @param int    $id      Post ID.
	 * @param string $context Unused, kept for signature parity.
	 * @return bool
	 */
	public function current_user_can_read( $id, $context = 'view' ) {
		return ! in_array( (int) $id, (array) lmat_test_get( 'unreadable_ids', array() ), true );
	}
}

/**
 * Model stub.
 *
 * @since 2.2.1
 */
class Lmat_Test_Model {
	/**
	 * Post model.
	 *
	 * @var Lmat_Test_Post_Model
	 */
	public $post;

	/**
	 * Constructor.
	 *
	 * @since 2.2.1
	 */
	public function __construct() {
		$this->post = new Lmat_Test_Post_Model();
	}
}

/**
 * Linguator instance stub.
 *
 * @since 2.2.1
 */
class Lmat_Test_Linguator {
	/**
	 * Model.
	 *
	 * @var Lmat_Test_Model
	 */
	public $model;

	/**
	 * Constructor.
	 *
	 * @since 2.2.1
	 */
	public function __construct() {
		$this->model = new Lmat_Test_Model();
	}
}

/**
 * Simulates the LMAT() accessor.
 *
 * @since 2.2.1
 *
 * @return Lmat_Test_Linguator|null
 */
function LMAT() { // phpcs:ignore WordPress.NamingConventions.ValidFunctionName.FunctionNameInvalid
	return lmat_test_get( 'linguator', true ) ? new Lmat_Test_Linguator() : null;
}

if ( ! getenv( 'LMAT_TEST_WITHOUT_WOOCOMMERCE' ) ) {
	/**
	 * Simulates WooCommerce's is_shop().
	 *
	 * @since 2.2.1
	 *
	 * @return bool
	 */
	function is_shop() {
		return (bool) lmat_test_get( 'is_shop', false );
	}

	/**
	 * Simulates WooCommerce's wc_get_page_id().
	 *
	 * @since 2.2.1
	 *
	 * @param string $page Page identifier.
	 * @return int
	 */
	function wc_get_page_id( $page ) {
		return 'shop' === $page ? (int) lmat_test_get( 'shop_page_id', -1 ) : -1;
	}
}

require_once dirname( __DIR__ ) . '/integrations/woocommerce/woocommerce.php';
