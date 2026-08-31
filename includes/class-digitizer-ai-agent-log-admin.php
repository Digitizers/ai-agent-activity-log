<?php
/**
 * The screen.
 *
 * The API is the product; this exists so that a person looking at a site can
 * answer the question without a terminal. Read-only apart from the one
 * button that empties the log.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

class Digitizer_AI_Agent_Log_Admin {

	const PAGE_SLUG = 'digitizer-ai-agent-log';

	public function __construct() {
		add_action( 'admin_post_digitizer_ai_agent_log_clear', array( $this, 'handle_clear' ) );
	}

	/**
	 * A menu of its own rather than a child of Tools.
	 *
	 * The log answers a question people go looking for - what did the agent
	 * change - and a screen filed under Tools is a screen they do not find.
	 *
	 * @return void
	 */
	public function register_menu() {
		add_menu_page(
			__( 'Digitizer AI Agent Log', 'digitizer-ai-agent-log' ),
			__( 'Agent Activity', 'digitizer-ai-agent-log' ),
			'manage_options',
			self::PAGE_SLUG,
			array( $this, 'render_page' ),
			'dashicons-list-view',
			76
		);
	}

	/**
	 * A filter value from the query string, or '' when absent or malformed.
	 *
	 * sanitize_key() would hand an array-valued parameter to string functions
	 * and raise a TypeError on PHP 8, so the scalar check comes first - the
	 * same guard core's own admin screens use for the same reason.
	 *
	 * @param string $key Parameter name.
	 * @return string
	 */
	private function filter_arg( $key ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only filtering of a read-only screen.
		if ( ! isset( $_GET[ $key ] ) || ! is_scalar( $_GET[ $key ] ) ) {
			return '';
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only filtering of a read-only screen.
		return sanitize_key( wp_unslash( $_GET[ $key ] ) );
	}

	/**
	 * A calendar date from the query string, or '' when absent or malformed.
	 *
	 * The same scalar guard as filter_arg() and for the same reason - an
	 * array-valued parameter must not reach a string function - and then a
	 * format check, because the store turns this into a strtotime() call and
	 * "2026-13-45" or "next tuesday" reaching that would silently produce a
	 * range the administrator did not ask for. createFromFormat() is lenient
	 * about overflow (it rolls 2026-13-45 into 2027-02-14), so the parsed
	 * date is formatted back and compared: only a string that survives that
	 * round trip is the date it claims to be.
	 *
	 * @param string $key Parameter name.
	 * @return string A Y-m-d date, or ''.
	 */
	private function date_arg( $key ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only filtering of a read-only screen.
		if ( ! isset( $_GET[ $key ] ) || ! is_scalar( $_GET[ $key ] ) ) {
			return '';
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only filtering of a read-only screen.
		$value = sanitize_text_field( wp_unslash( (string) $_GET[ $key ] ) );
		if ( '' === $value ) {
			return '';
		}
		$date = DateTimeImmutable::createFromFormat( 'Y-m-d', $value, new DateTimeZone( 'UTC' ) );
		if ( ! $date || $date->format( 'Y-m-d' ) !== $value ) {
			return '';
		}
		return $value;
	}

	public function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to do that.', 'digitizer-ai-agent-log' ) );
		}

		$args = array( 'per_page' => 100 );
		foreach ( array( 'channel', 'object_type' ) as $key ) {
			$value = $this->filter_arg( $key );
			if ( '' !== $value ) {
				$args[ $key ] = $value;
			}
		}

		// The date range. The screen shows the newest hundred rows, so without
		// these an administrator looking into an incident cannot narrow to the
		// window it happened in at all. Whole days, and inclusive at both ends:
		// someone who types the same date in both boxes means that day, not an
		// empty range from its midnight to its midnight.
		//
		// The boxes are picked against the locally rendered date - the 'When'
		// column below reads in the site's timezone via wp_date() - but the
		// store bounds logged_at, which is stored in UTC, and its query_args()
		// compares the value handed to it verbatim (strtotime( $value . ' UTC' )).
		// So each selected day's boundaries are built here in the site's
		// timezone and only then converted to UTC, or a row that displays on
		// the selected local day - but whose UTC timestamp falls on the
		// neighbouring UTC day - would be wrongly excluded (or a row from the
		// neighbouring local day wrongly included). wp_timezone() is what to
		// use for this: per wp-includes/functions.php:152-154 it wraps
		// wp_timezone_string(), and that function (line 124-141) returns the
		// site's named zone when one is set, or otherwise formats gmt_offset
		// into a "+HH:MM" string - DateTimeZone accepts both forms, so a site
		// configured with a raw UTC offset instead of a named zone is handled
		// the same way, with no separate branch needed here.
		$after  = $this->date_arg( 'after' );
		$before = $this->date_arg( 'before' );
		$tz     = wp_timezone();
		$utc    = new DateTimeZone( 'UTC' );
		if ( '' !== $after ) {
			$start         = new DateTimeImmutable( $after . ' 00:00:00', $tz );
			$args['after'] = $start->setTimezone( $utc )->format( 'Y-m-d H:i:s' );
		}
		if ( '' !== $before ) {
			// End of the selected local day, not the start of the next one:
			// query_args() compares with '<=', so the whole day - including its
			// last second - stays inside the range.
			$end            = new DateTimeImmutable( $before . ' 23:59:59', $tz );
			$args['before'] = $end->setTimezone( $utc )->format( 'Y-m-d H:i:s' );
		}

		// Values outside the enums contribute nothing to the query, so a
		// hand-edited URL narrows the list or does nothing - never widens it.
		$rows = Digitizer_AI_Agent_Log_Store::query( $args );

		// The 'When' column is rendered with wp_date(), not date_i18n().
		// $stamp below is a true Unix timestamp - strtotime() on a UTC string -
		// and date_i18n() does not take one of those: its signature is
		// date_i18n( $format, $timestamp_with_offset ), and core's own docblock
		// calls that "a sum of Unix timestamp and timezone offset in seconds"
		// (wp-includes/functions.php:173). Given a real timestamp it takes the
		// legacy branch at line 203, gmdate()s the value and re-reads that
		// wall-clock string in the site timezone, which hands back the UTC time
		// wearing a local label: a row stored at 12:00 UTC would read 12:00 in
		// Jerusalem instead of 15:00. wp_date() was written for exactly this -
		// "unlike date_i18n(), this function accepts a true Unix timestamp, not
		// summed with timezone offset" (line 230).
		$format = get_option( 'date_format' ) . ' ' . get_option( 'time_format' );
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Agent Log', 'digitizer-ai-agent-log' ); ?></h1>
			<p><?php esc_html_e( 'Changes that arrived from somewhere other than a browser: the REST API, WP-Cron, WP-CLI or XML-RPC. Work done by a person in the admin is not recorded here.', 'digitizer-ai-agent-log' ); ?></p>

			<form method="get">
				<input type="hidden" name="page" value="<?php echo esc_attr( self::PAGE_SLUG ); ?>" />
				<select name="channel">
					<option value=""><?php esc_html_e( 'Every channel', 'digitizer-ai-agent-log' ); ?></option>
					<?php foreach ( array( 'rest', 'cron', 'cli', 'xmlrpc' ) as $channel ) : ?>
						<option value="<?php echo esc_attr( $channel ); ?>" <?php selected( isset( $args['channel'] ) ? $args['channel'] : '', $channel ); ?>><?php echo esc_html( $channel ); ?></option>
					<?php endforeach; ?>
				</select>
				<select name="object_type">
					<option value=""><?php esc_html_e( 'Everything', 'digitizer-ai-agent-log' ); ?></option>
					<?php foreach ( array( 'post', 'term', 'attachment', 'user', 'plugin', 'theme', 'option' ) as $type ) : ?>
						<option value="<?php echo esc_attr( $type ); ?>" <?php selected( isset( $args['object_type'] ) ? $args['object_type'] : '', $type ); ?>><?php echo esc_html( $type ); ?></option>
					<?php endforeach; ?>
				</select>
				<label for="aial-after" class="screen-reader-text"><?php esc_html_e( 'From date', 'digitizer-ai-agent-log' ); ?></label>
				<input type="date" id="aial-after" name="after" value="<?php echo esc_attr( $after ); ?>" placeholder="<?php esc_attr_e( 'From date', 'digitizer-ai-agent-log' ); ?>" />
				<label for="aial-before" class="screen-reader-text"><?php esc_html_e( 'To date', 'digitizer-ai-agent-log' ); ?></label>
				<input type="date" id="aial-before" name="before" value="<?php echo esc_attr( $before ); ?>" placeholder="<?php esc_attr_e( 'To date', 'digitizer-ai-agent-log' ); ?>" />
				<button type="submit" class="button"><?php esc_html_e( 'Filter', 'digitizer-ai-agent-log' ); ?></button>
			</form>

			<table class="wp-list-table widefat striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'When', 'digitizer-ai-agent-log' ); ?></th>
						<th><?php esc_html_e( 'Channel', 'digitizer-ai-agent-log' ); ?></th>
						<th><?php esc_html_e( 'Application', 'digitizer-ai-agent-log' ); ?></th>
						<th><?php esc_html_e( 'What', 'digitizer-ai-agent-log' ); ?></th>
						<th><?php esc_html_e( 'Fields', 'digitizer-ai-agent-log' ); ?></th>
					</tr>
				</thead>
				<tbody>
				<?php if ( empty( $rows ) ) : ?>
					<tr><td colspan="5"><?php esc_html_e( 'Nothing recorded yet. Either no automation has changed anything here, or the module was switched on after it did.', 'digitizer-ai-agent-log' ); ?></td></tr>
				<?php else : ?>
					<?php foreach ( $rows as $row ) : ?>
						<?php
						$fields = json_decode( isset( $row->fields ) ? (string) $row->fields : '[]', true );
						$stamp  = strtotime( ( isset( $row->logged_at ) ? $row->logged_at : '' ) . ' UTC' );
						?>
						<tr>
							<td><?php echo esc_html( $stamp ? wp_date( $format, $stamp ) : '' ); ?></td>
							<td><?php echo esc_html( isset( $row->channel ) ? $row->channel : '' ); ?></td>
							<td><?php echo esc_html( isset( $row->app ) && '' !== $row->app ? $row->app : '-' ); ?></td>
							<td>
								<?php
								printf(
									/* translators: 1: action, 2: object type, 3: object name or id */
									esc_html__( '%1$s %2$s %3$s', 'digitizer-ai-agent-log' ),
									esc_html( isset( $row->action ) ? $row->action : '' ),
									esc_html( isset( $row->object_subtype ) && '' !== $row->object_subtype ? $row->object_subtype : ( isset( $row->object_type ) ? $row->object_type : '' ) ),
									esc_html( isset( $row->object_name ) && '' !== $row->object_name ? $row->object_name : '#' . ( isset( $row->object_id ) ? (int) $row->object_id : 0 ) )
								);
								?>
							</td>
							<td><?php echo esc_html( is_array( $fields ) ? implode( ', ', array_map( 'strval', $fields ) ) : '' ); ?></td>
						</tr>
					<?php endforeach; ?>
				<?php endif; ?>
				</tbody>
			</table>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<?php wp_nonce_field( 'digitizer_ai_agent_log_clear' ); ?>
				<input type="hidden" name="action" value="digitizer_ai_agent_log_clear" />
				<p><button type="submit" class="button"><?php esc_html_e( 'Clear the log', 'digitizer-ai-agent-log' ); ?></button></p>
			</form>

			<p class="description">
				<?php
				printf(
					/* translators: 1: the REST route, 2: link to the plugin author's site */
					esc_html__( 'The same entries are readable over the REST API at %1$s. Built by %2$s.', 'digitizer-ai-agent-log' ),
					'<code>' . esc_html( Digitizer_AI_Agent_Log_Rest::NAMESPACE_V1 ) . '/activity</code>',
					'<a href="https://digitizer.co.il" target="_blank" rel="noopener noreferrer">Digitizer</a>'
				);
				?>
			</p>
		</div>
		<?php
	}

	public function handle_clear() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to do that.', 'digitizer-ai-agent-log' ) );
		}
		check_admin_referer( 'digitizer_ai_agent_log_clear' );

		Digitizer_AI_Agent_Log_Store::clear();

		wp_safe_redirect( admin_url( 'admin.php?page=' . self::PAGE_SLUG ) );
		exit;
	}
}
