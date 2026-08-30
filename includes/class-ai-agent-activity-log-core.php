<?php
/**
 * What runs, and when.
 *
 * @package AI_Agent_Activity_Log
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class AI_Agent_Activity_Log_Core {

	/** @var AI_Agent_Activity_Log_Admin|null */
	private static $admin = null;

	/**
	 * Wire everything up, on 'plugins_loaded'.
	 *
	 * The listeners are registered for every request, including a person
	 * clicking around wp-admin. That is deliberate and it is not a leak: the
	 * channel cannot be known this early, because core defines REST_REQUEST
	 * on 'parse_request', so nothing may be filtered out yet. The rows are
	 * held in memory and the channel gate runs once at shutdown, where the
	 * answer is finally knowable - see AI_Agent_Activity_Log_Hooks::flush().
	 *
	 * @return void
	 */
	public static function boot() {
		AI_Agent_Activity_Log_Store::install_table();
		AI_Agent_Activity_Log_Hooks::init();

		add_action( 'rest_api_init', array( 'AI_Agent_Activity_Log_Rest', 'init' ) );

		if ( is_admin() ) {
			self::$admin = new AI_Agent_Activity_Log_Admin();
			add_action( 'admin_menu', array( self::$admin, 'register_menu' ) );
		}
	}
}
