<?php
/**
 * Loads the site scan.
 *
 * @package Linguator
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Don't access directly.
}

require_once __DIR__ . '/result.php';
require_once __DIR__ . '/abstract-check.php';
require_once __DIR__ . '/issue-copy.php';
require_once __DIR__ . '/freshness.php';
require_once __DIR__ . '/editor-panel.php';
require_once __DIR__ . '/scanner.php';
require_once __DIR__ . '/checks/languages.php';
require_once __DIR__ . '/checks/url-config.php';
require_once __DIR__ . '/checks/content.php';
require_once __DIR__ . '/checks/menus.php';
require_once __DIR__ . '/checks/strings.php';
require_once __DIR__ . '/checks/switcher.php';
require_once __DIR__ . '/checks/elementor.php';

\Linguator\Modules\Scan\Freshness::init();
\Linguator\Modules\Scan\Editor_Panel::init();
