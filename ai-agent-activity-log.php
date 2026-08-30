<?php
/**
 * Plugin Name:       AI Agent Activity Log
 * Plugin URI:        https://github.com/Digitizers/ai-agent-activity-log
 * Description:       Records what automations changed on this site - anything that arrived over the REST API, WP-Cron, WP-CLI or XML-RPC. A change made by a person in wp-admin is not recorded at all, so the log is what the agents did and nothing else.
 * Version:           1.0.0
 * Requires at least: 5.5
 * Requires PHP:      7.2
 * Author:            Digitizer
 * Author URI:        https://www.digitizer.studio
 * License:           GPLv2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       ai-agent-activity-log
 *
 * @package AI_Agent_Activity_Log
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'AI_AGENT_ACTIVITY_LOG_VERSION', '1.0.0' );
define( 'AI_AGENT_ACTIVITY_LOG_PATH', plugin_dir_path( __FILE__ ) );

require_once AI_AGENT_ACTIVITY_LOG_PATH . 'includes/class-ai-agent-activity-log-channel.php';
require_once AI_AGENT_ACTIVITY_LOG_PATH . 'includes/class-ai-agent-activity-log-store.php';
require_once AI_AGENT_ACTIVITY_LOG_PATH . 'includes/class-ai-agent-activity-log-buffer.php';
require_once AI_AGENT_ACTIVITY_LOG_PATH . 'includes/class-ai-agent-activity-log-hooks.php';
require_once AI_AGENT_ACTIVITY_LOG_PATH . 'includes/class-ai-agent-activity-log-rest.php';
require_once AI_AGENT_ACTIVITY_LOG_PATH . 'includes/class-ai-agent-activity-log-core.php';

if ( is_admin() ) {
	require_once AI_AGENT_ACTIVITY_LOG_PATH . 'includes/class-ai-agent-activity-log-admin.php';
}

add_action( 'plugins_loaded', array( 'AI_Agent_Activity_Log_Core', 'boot' ) );
