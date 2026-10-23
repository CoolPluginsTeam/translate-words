<?php
/** Standalone regression checks: php tests/scan-elementor.php. No WordPress data is changed. */
namespace Linguator\Includes\Other {
	class Linguator_Model {
		public $post;
		public $options;
		public function get_languages_list() { return array( (object) array( 'slug' => 'en', 'name' => 'English' ), (object) array( 'slug' => 'hi', 'name' => 'Hindi' ), (object) array( 'slug' => 'de', 'name' => 'German', 'active' => false ) ); }
		public function get_default_language() { return $this->get_languages_list()[0]; }
	}
}
namespace {
	define( 'ABSPATH', __DIR__ );
	$active = true;
	class WP_Post {
		public $ID;
		public $post_type = 'elementor_library';
		public $post_status = 'publish';
		public function __construct( $id, $status = 'publish' ) { $this->ID = $id; $this->post_status = $status; }
	}
	$documents = array( 10 => new WP_Post( 10 ), 20 => new WP_Post( 20, 'draft' ), 30 => new WP_Post( 30 ), 40 => new WP_Post( 40 ), 50 => new WP_Post( 50 ), 60 => new WP_Post( 60 ), 70 => new WP_Post( 70 ) );
	$meta = array();
	foreach ( $documents as $id => $document ) $meta[$id] = array( '_elementor_template_type' => 'header', '_elementor_data' => '[{"id":"a","elType":"container","elements":[]}]' );
	$meta[10]['_elementor_data'] = '[{"elements":[{"elType":"widget","widgetType":"lmat_widget"}]}]';
	$meta[20]['_elementor_template_type'] = 'footer';
	$meta[60]['_elementor_template_type'] = 'kit';
	$meta[70]['_elementor_data'] = '{invalid';
	function __( $text, $domain = '' ) { return $text; }
	function _n( $single, $plural, $number, $domain = '' ) { return 1 === $number ? $single : $plural; }
	function get_post_type_object( $type ) { return (object) array( 'labels' => (object) array( 'name' => 'Templates' ) ); }
	function did_action( $action ) { return $GLOBALS['active'] ? 1 : 0; }
	function post_type_exists( $type ) { return 'elementor_library' === $type; }
	function get_posts( $args ) {
		$GLOBALS['last_query'] = $args;
		$documents = $GLOBALS['documents'];
		if ( ! empty( $args['post__in'] ) ) $documents = array_intersect_key( $documents, array_flip( $args['post__in'] ) );
		return isset( $args['fields'] ) ? ( isset( $args['meta_query'] ) ? array( 10 ) : array_keys( $documents ) ) : array_values( $documents );
	}
	function get_option( $name, $default = false ) { return $GLOBALS['options'][$name] ?? $default; }
	function update_option( $name, $value, $autoload = false ) { $GLOBALS['options'][$name] = $value; }
	function clean_post_cache( $id ) {}
	function get_post( $id ) { return $GLOBALS['documents'][$id] ?? null; }
	function get_post_meta( $id, $key, $single = true ) { return $GLOBALS['meta'][$id][$key] ?? ''; }
	function get_edit_post_link( $id, $context = '' ) { return 'http://example.test/wp-admin/post.php?post=' . $id . '&action=edit'; }
	function get_the_title( $id ) { return 'Template ' . $id; }
	function admin_url( $path ) { return 'http://example.test/wp-admin/' . $path; }
	function sanitize_key( $key ) { return $key; }
	function wp_nonce_url( $url, $action ) { return $url . '&nonce=test'; }
	function add_query_arg( ...$args ) { return is_array( $args[0] ) ? $args[1] . '?' . http_build_query( $args[0] ) : $args[2] . '&' . $args[0] . '=' . $args[1]; }
	require __DIR__ . '/../modules/scan/result.php';
	require __DIR__ . '/../modules/scan/abstract-check.php';
	require __DIR__ . '/../modules/scan/checks/elementor.php';
	require __DIR__ . '/../modules/scan/checks/switcher.php';
	require __DIR__ . '/../modules/scan/checks/content.php';
	require __DIR__ . '/../modules/scan/scanner.php';
	$model = new \Linguator\Includes\Other\Linguator_Model();
	$model->post = new class {
		public function get_language( $id ) { return 30 === $id ? false : (object) array( 'slug' => in_array( $id, array( 20, 40 ), true ) ? 'hi' : 'en' ); }
		public function get_translations( $id ) { return $GLOBALS['translation_override'][$id] ?? ( in_array( $id, array( 10, 20 ), true ) ? array( 'en' => 10, 'hi' => 20 ) : array() ); }
	};
	$model->options = new class {
		public $enabled = true;
		public function get( $key ) { return $this->enabled ? array( 'elementor' ) : array(); }
	};
	function check( $condition, $message ) { if ( ! $condition ) throw new \RuntimeException( $message ); }
	$scan = new \Linguator\Modules\Scan\Checks\Elementor( $model );
	$results = array();
	foreach ( $scan->run() as $result ) $results[$result->id] = $result->to_array();
	foreach ( array( 'missing', 'unpublished', 'language', 'layout', 'type', 'orphan' ) as $kind ) check( 'warning' === $results['elementor_' . $kind]['status'], 'Missing finding: ' . $kind );
	check( 2 === count( $results['elementor_missing']['details'] ), 'Disabled languages and kit documents must not produce missing translations.' );
	check( 20 === (int) explode( ':', $results['elementor_unpublished']['details'][0]['ignore_key'] )[2], 'Draft finding should point to the translation.' );
	check( false !== strpos( $results['elementor_missing']['details'][0]['action_url'], 'from_post=50' ), 'Translation action must use Linguator source ID.' );
	$scan->only( 'elementor_layout' );
	check( 1 === count( $scan->run() ), 'Single issue rescans should return only the requested check.' );
	check( \Linguator\Modules\Scan\Checks\Elementor::contains_switcher( json_decode( $meta[10]['_elementor_data'], true ) ), 'Nested switcher widget was missed.' );
	check( ! \Linguator\Modules\Scan\Checks\Elementor::contains_switcher( array( array( 'widgetType' => 'heading', 'settings' => array( 'title' => 'lmat_widget' ) ) ) ), 'Widget name in text must not count as a switcher.' );
	$switcher = new \Linguator\Modules\Scan\Checks\Switcher( $model );
	$detect = new \ReflectionMethod( $switcher, 'has_elementor_switcher' );
	$detect->setAccessible( true );
	check( $detect->invoke( $switcher ), 'Enabled Elementor switcher was not detected.' );
	$model->options->enabled = false;
	check( ! $detect->invoke( $switcher ), 'Disabled Elementor widget integration must not pass detection.' );
	$saved = array( 'time' => 100, 'partial_scans' => array( 'elementor' => 200 ), 'coverage' => array( array( 'slug' => 'hi', 'percent' => 10 ) ), 'results' => array_values( $results ) );
	$GLOBALS['options'][\Linguator\Modules\Scan\Scanner::REPORT_OPTION] = $saved;
	$GLOBALS['translation_override'][50] = array( 'en' => 50, 'hi' => 20 );
	$runner = new \Linguator\Modules\Scan\Scanner( $model );
	$updated = $runner->recheck_rows( 'elementor', array( 'elementor:missing:50:hi' ) );
	check( array( 50 ) === $GLOBALS['last_query']['post__in'], 'Row rescan queried unrelated templates.' );
	check( $updated['time'] === $saved['time'] && $updated['partial_scans'] === $saved['partial_scans'] && $updated['coverage'] === $saved['coverage'], 'Row rescans must preserve full/section scan times and coverage.' );
	$updated_by_id = array_column( $updated['results'], null, 'id' );
	check( 1 === count( $updated_by_id['elementor_missing']['details'] ), 'Resolved row was not removed on recheck.' );
	check( $results['elementor_missing']['details'][1] === $updated_by_id['elementor_missing']['details'][0], 'Unrelated missing-translation row changed.' );
	check( $results['elementor_layout'] === $updated_by_id['elementor_layout'], 'Unrelated issue check changed.' );
	check( null === $runner->recheck_rows( 'elementor', array( 'elementor:missing:999:hi' ) ), 'Unknown rows must be rejected.' );
	$GLOBALS['translation_override'][70] = array( 'en' => 70, 'hi' => 20 );
	$all_resolved = $runner->recheck_rows( 'elementor', array( 'elementor:missing:70:hi' ) );
	check( 'pass' === array_column( $all_resolved['results'], null, 'id' )['elementor_missing']['status'], 'Resolving the last stored row should pass the check.' );
	$content = new \Linguator\Modules\Scan\Checks\Content( $model );
	$content->scope_rows( array( 'content:50:hi' ) );
	$coverage = new \ReflectionProperty( $content, 'coverage' );
	$coverage->setAccessible( true );
	$coverage->setValue( $content, array( 'hi' => array( 'total' => 0, 'translated' => 0 ) ) );
	$missing = new \ReflectionMethod( $content, 'missing_translations_result' );
	$missing->setAccessible( true );
	$content_result = $missing->invoke( $content, 'elementor_library', (object) array( 'term_id' => 1 ), array( (object) array( 'slug' => 'hi', 'name' => 'Hindi' ) ) );
	check( array( 50 ) === $GLOBALS['last_query']['post__in'], 'Content row rescan queried unrelated posts.' );
	check( 'pass' === $content_result->status, 'Resolved content translation was not recognized.' );
	$active = false;
	check( array() === $scan->run(), 'Inactive Elementor must produce no template results.' );
	echo "Passed Elementor findings, widget detection, scoped queries, row-only report updates, resolution, unknown-row rejection and preservation of unrelated checks, coverage and scan times.\n";
}
