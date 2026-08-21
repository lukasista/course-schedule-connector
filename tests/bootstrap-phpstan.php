<?php
/**
 * Constants PHPStan needs to know about.
 *
 * @package CourseScheduleConnector
 */

declare( strict_types=1 );

define( 'CSCS_VERSION', '0.1.0' );
define( 'CSCS_MIN_PHP', '8.1' );
define( 'CSCS_FILE', __DIR__ . '/../course-schedule-connector.php' );
define( 'CSCS_DIR', __DIR__ . '/../' );
define( 'CSCS_URL', 'https://example.com/wp-content/plugins/course-schedule-connector/' );

// WordPress time constants, which the stub package does not declare.
define( 'MINUTE_IN_SECONDS', 60 );
define( 'HOUR_IN_SECONDS', 3600 );
define( 'DAY_IN_SECONDS', 86400 );
define( 'WEEK_IN_SECONDS', 604800 );
define( 'MONTH_IN_SECONDS', 2592000 );
define( 'YEAR_IN_SECONDS', 31536000 );
