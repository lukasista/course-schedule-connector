<?php
/**
 * Plugin Name:       Course & Schedule Connector for iSport
 * Plugin URI:        https://github.com/lukasista/wpdissi
 * Description:       Display courses and class schedules from an iSport System installation, as a shortcode, a block, or Divi 5 modules.
 * Version:           0.1.0
 * Requires at least: 6.5
 * Requires PHP:      8.1
 * Author:            Lukas Pivonka
 * Author URI:        https://pivonka.co.uk
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       course-schedule-connector
 * Domain Path:       /languages
 * Update URI:        https://github.com/lukasista/wpdissi
 *
 * @package CourseScheduleConnector
 */

declare( strict_types=1 );

defined( 'ABSPATH' ) || exit;

const CSCS_VERSION     = '0.1.0';
const CSCS_MIN_PHP     = '8.1';
const CSCS_TEXT_DOMAIN = 'course-schedule-connector';

define( 'CSCS_FILE', __FILE__ );
define( 'CSCS_DIR', plugin_dir_path( __FILE__ ) );
define( 'CSCS_URL', plugin_dir_url( __FILE__ ) );

require_once CSCS_DIR . 'includes/Autoloader.php';

\CSCS\Autoloader::register( CSCS_DIR . 'includes/' );

add_action( 'plugins_loaded', array( \CSCS\Plugin::class, 'boot' ) );
