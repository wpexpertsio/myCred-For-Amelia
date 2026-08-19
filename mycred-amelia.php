<?php
/**
 * Plugin Name: myCred Amelia
 * Plugin URI: https://mycred.me
 * Description: myCred-Amelia connects myCred points management with the Amelia appointment-booking WordPress plugin.
 * Version: 2.0.0
 * Author: myCred
 * Author URI: http://mycred.me
 * Author Email: support@mycred.me
 * Requires at least: WP 4.8
 * Tested up to: WP 7.0
 */

defined( 'ABSPATH' ) || exit;

define( 'MYCRED_AMELIA_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'MYCRED_AMELIA_PLUGIN_PATH', plugin_dir_path( __FILE__ ) );
define( 'MYCRED_AMELIA_CHARGE_REF', 'amelia_booking_payment' );
define( 'MYCRED_AMELIA_REFUND_REF', 'amelia_booking_refund' );

// Payment through myCred Points.
add_filter( 'amelia_before_payment', 'mycred_amelia_before_payment_func', 10, 2 );

// Refund points when Amelia bookings are canceled, rejected, or removed.
add_action( 'amelia_after_booking_canceled', 'mycred_amelia_refund_after_booking_canceled', 10, 1 );
add_action( 'amelia_after_booking_rejected', 'mycred_amelia_refund_after_booking_rejected', 10, 1 );
add_action( 'amelia_after_booking_rejected_link', 'mycred_amelia_refund_after_booking_rejected', 10, 1 );
add_action( 'amelia_after_appointment_status_updated', 'mycred_amelia_refund_after_appointment_status_updated', 10, 2 );
add_action( 'amelia_after_event_status_updated', 'mycred_amelia_refund_after_event_status_updated', 10, 3 );
add_action( 'amelia_after_event_booking_deleted', 'mycred_amelia_refund_after_booking_deleted', 10, 1 );
add_action( 'amelia_after_package_booking_deleted', 'mycred_amelia_refund_after_package_booking_deleted', 10, 2 );
add_action( 'amelia_after_appointment_deleted', 'mycred_amelia_refund_after_booking_deleted', 10, 1 );
add_action( 'amelia_after_event_deleted', 'mycred_amelia_refund_after_booking_deleted', 10, 1 );
add_action( 'amelia_after_events_deleted', 'mycred_amelia_refund_after_booking_deleted', 10, 1 );
add_filter( 'gettext', 'mycred_amelia_override_wpamelia_text', 10, 3 );

function mycred_amelia_override_wpamelia_text( $translation, $text, $domain ) {
	if ( 'wpamelia' !== $domain ) {
		return $translation;
	}

	switch ( $text ) {
		case 'The payment will be done on-site.':
			return 'Payment was completed with points.';
		case 'On-site':
			return 'Paid with points';
	}

	return $translation;
}

function mycred_amelia_default_point_type() {
	return defined( 'MYCRED_DEFAULT_TYPE_KEY' ) ? MYCRED_DEFAULT_TYPE_KEY : 'mycred_default';
}

function mycred_amelia_default_insufficient_message( $buy_points_url = '/buypoints' ) {
	return sprintf(
		'<a href="%s"><h4>You dont have enough Points. Click here to Buy.</h4></a>',
		esc_url( $buy_points_url )
	);
}

function mycred_amelia_default_settings() {
	return array(
		'enabled'                  => 1,
		'point_type'               => mycred_amelia_default_point_type(),
		'conversion_rate'          => 1,
		'rounding'                 => 'ceil',
		'buy_points_url'           => '/buypoints',
		'insufficient_balance_msg' => mycred_amelia_default_insufficient_message(),
		'refund_on_canceled'       => 1,
		'refund_on_rejected'       => 1,
		'refund_on_deleted'        => 1,
	);
}

function mycred_amelia_get_settings() {
	$defaults = mycred_amelia_default_settings();
	$stored   = get_option( 'mycred_amelia_settings', array() );

	if ( isset( $stored['mycred_amelia'] ) && is_array( $stored['mycred_amelia'] ) ) {
		$stored = $stored['mycred_amelia'];
	}

	if ( ! is_array( $stored ) ) {
		$stored = array();
	}

	$settings = array_merge( $defaults, $stored );

	$settings['enabled']            = ! empty( $settings['enabled'] ) ? 1 : 0;
	$settings['conversion_rate']    = (float) $settings['conversion_rate'];
	$settings['conversion_rate']    = $settings['conversion_rate'] > 0 ? $settings['conversion_rate'] : $defaults['conversion_rate'];
	$settings['rounding']           = in_array( $settings['rounding'], array( 'ceil', 'round', 'floor' ), true ) ? $settings['rounding'] : $defaults['rounding'];
	$settings['buy_points_url']     = ! empty( $settings['buy_points_url'] ) ? $settings['buy_points_url'] : $defaults['buy_points_url'];
	$settings['point_type']         = mycred_amelia_validate_point_type( $settings['point_type'] );
	$settings['refund_on_canceled'] = ! empty( $settings['refund_on_canceled'] ) ? 1 : 0;
	$settings['refund_on_rejected'] = ! empty( $settings['refund_on_rejected'] ) ? 1 : 0;
	$settings['refund_on_deleted']  = ! empty( $settings['refund_on_deleted'] ) ? 1 : 0;

	if ( empty( $settings['insufficient_balance_msg'] ) ) {
		$settings['insufficient_balance_msg'] = mycred_amelia_default_insufficient_message( $settings['buy_points_url'] );
	}

	return $settings;
}

function mycred_amelia_sanitize_settings( $input ) {
	$defaults = mycred_amelia_default_settings();
	$input    = is_array( $input ) ? $input : array();

	$settings = array(
		'enabled'                  => ! empty( $input['enabled'] ) ? 1 : 0,
		'point_type'               => mycred_amelia_validate_point_type( isset( $input['point_type'] ) ? sanitize_key( $input['point_type'] ) : $defaults['point_type'] ),
		'conversion_rate'          => isset( $input['conversion_rate'] ) ? (float) $input['conversion_rate'] : $defaults['conversion_rate'],
		'rounding'                 => isset( $input['rounding'] ) ? sanitize_key( $input['rounding'] ) : $defaults['rounding'],
		'buy_points_url'           => isset( $input['buy_points_url'] ) ? esc_url_raw( trim( $input['buy_points_url'] ) ) : $defaults['buy_points_url'],
		'insufficient_balance_msg' => isset( $input['insufficient_balance_msg'] ) ? wp_kses_post( wp_unslash( $input['insufficient_balance_msg'] ) ) : $defaults['insufficient_balance_msg'],
		'refund_on_canceled'       => ! empty( $input['refund_on_canceled'] ) ? 1 : 0,
		'refund_on_rejected'       => ! empty( $input['refund_on_rejected'] ) ? 1 : 0,
		'refund_on_deleted'        => ! empty( $input['refund_on_deleted'] ) ? 1 : 0,
	);

	if ( $settings['conversion_rate'] <= 0 ) {
		$settings['conversion_rate'] = $defaults['conversion_rate'];
	}

	if ( ! in_array( $settings['rounding'], array( 'ceil', 'round', 'floor' ), true ) ) {
		$settings['rounding'] = $defaults['rounding'];
	}

	if ( empty( $settings['buy_points_url'] ) ) {
		$settings['buy_points_url'] = $defaults['buy_points_url'];
	}

	if ( empty( $settings['insufficient_balance_msg'] ) ) {
		$settings['insufficient_balance_msg'] = mycred_amelia_default_insufficient_message( $settings['buy_points_url'] );
	}

	return $settings;
}

function mycred_amelia_validate_point_type( $point_type ) {
	$point_type = sanitize_key( $point_type );

	if ( function_exists( 'mycred_point_type_exists' ) && ! mycred_point_type_exists( $point_type ) ) {
		return mycred_amelia_default_point_type();
	}

	return $point_type ? $point_type : mycred_amelia_default_point_type();
}

function mycred_amelia_get_mycred( $settings = array() ) {
	if ( ! function_exists( 'mycred' ) ) {
		return false;
	}

	$settings   = $settings ? $settings : mycred_amelia_get_settings();
	$point_type = mycred_amelia_validate_point_type( $settings['point_type'] );

	return mycred( $point_type );
}

function mycred_amelia_calculate_points( $amount, $settings = array(), $mycred = null ) {
	$settings = $settings ? $settings : mycred_amelia_get_settings();
	$raw      = max( 0, (float) $amount ) * (float) $settings['conversion_rate'];

	switch ( $settings['rounding'] ) {
		case 'floor':
			$points = floor( $raw );
			break;

		case 'round':
			$points = round( $raw );
			break;

		case 'ceil':
		default:
			$points = ceil( $raw );
			break;
	}

	if ( $mycred && method_exists( $mycred, 'number' ) ) {
		return $mycred->number( $points );
	}

	return $points;
}

function mycred_amelia_get_payment_ref_id( $payment_data ) {
	if ( ! empty( $payment_data['customerBookingId'] ) ) {
		return absint( $payment_data['customerBookingId'] );
	}

	if ( ! empty( $payment_data['packageCustomerId'] ) ) {
		return absint( $payment_data['packageCustomerId'] );
	}

	return 0;
}

function mycred_amelia_log_entry_exists( $reference, $ref_id, $user_id, $point_type ) {
	if ( ! function_exists( 'mycred' ) ) {
		return false;
	}

	global $wpdb;

	$mycred = mycred( $point_type );
	$count  = $wpdb->get_var(
		$wpdb->prepare(
			"SELECT COUNT(*) FROM {$mycred->log_table} WHERE ref = %s AND ref_id = %d AND user_id = %d AND ctype = %s",
			$reference,
			absint( $ref_id ),
			absint( $user_id ),
			$point_type
		)
	);

	return (int) $count > 0;
}

function mycred_amelia_should_refund_status( $status ) {
	$settings = mycred_amelia_get_settings();
	$status   = sanitize_key( $status );

	if ( 'canceled' === $status ) {
		return ! empty( $settings['refund_on_canceled'] );
	}

	if ( 'rejected' === $status ) {
		return ! empty( $settings['refund_on_rejected'] );
	}

	if ( 'deleted' === $status ) {
		return ! empty( $settings['refund_on_deleted'] );
	}

	return false;
}

// Updated payment filter for logged-in users, conversion rate, and duplicate-charge protection.
function mycred_amelia_before_payment_func( $paymentData, $amount ) {
	if ( ! is_user_logged_in() || ! function_exists( 'mycred' ) ) {
		return $paymentData;
	}

	$settings = mycred_amelia_get_settings();

	if ( empty( $settings['enabled'] ) || empty( $paymentData['gateway'] ) || 'onSite' !== $paymentData['gateway'] ) {
		return $paymentData;
	}

	$mycred = mycred_amelia_get_mycred( $settings );

	if ( ! $mycred ) {
		return $paymentData;
	}

	$user_id       = get_current_user_id();
	$point_type    = mycred_amelia_validate_point_type( $settings['point_type'] );
	$required      = mycred_amelia_calculate_points( $amount, $settings, $mycred );
	$payment_ref_id = mycred_amelia_get_payment_ref_id( $paymentData );

	if ( ! $payment_ref_id || $required <= 0 ) {
		return $paymentData;
	}

	// If Amelia retries the same payment creation, keep it paid but do not deduct again.
	if ( mycred_amelia_log_entry_exists( MYCRED_AMELIA_CHARGE_REF, $payment_ref_id, $user_id, $point_type ) ) {
		$paymentData['amount'] = $amount;
		$paymentData['status'] = 'paid';

		return $paymentData;
	}

	$user_balance = $mycred->get_users_balance( $user_id, $point_type );

	if ( (float) $user_balance >= (float) $required ) {
		$deducted = mycred_add(
			MYCRED_AMELIA_CHARGE_REF,
			$user_id,
			-1 * $required,
			'Payment for Amelia booking.',
			$payment_ref_id,
			array(
				'customerBookingId' => ! empty( $paymentData['customerBookingId'] ) ? absint( $paymentData['customerBookingId'] ) : null,
				'packageCustomerId' => ! empty( $paymentData['packageCustomerId'] ) ? absint( $paymentData['packageCustomerId'] ) : null,
				'currency_amount'   => (float) $amount,
				'points'            => (float) $required,
				'conversion_rate'   => (float) $settings['conversion_rate'],
				'rounding'          => $settings['rounding'],
				'gateway'           => $paymentData['gateway'],
			),
			$point_type
		);

		if ( false !== $deducted ) {
			$paymentData['amount'] = $amount;
			$paymentData['status'] = 'paid';
		}
	}

	return $paymentData;
}

function mycred_amelia_get_charge_totals( $ref_id ) {
	if ( ! function_exists( 'mycred' ) ) {
		return array();
	}

	global $wpdb;

	$mycred = mycred();

	return $wpdb->get_results(
		$wpdb->prepare(
			"SELECT user_id, ctype, SUM(ABS(creds)) AS points FROM {$mycred->log_table} WHERE ref = %s AND ref_id = %d AND creds < 0 GROUP BY user_id, ctype",
			MYCRED_AMELIA_CHARGE_REF,
			absint( $ref_id )
		)
	);
}

function mycred_amelia_refund_ref_id( $ref_id, $status, $source = '' ) {
	$ref_id = absint( $ref_id );

	if ( ! $ref_id || ! mycred_amelia_should_refund_status( $status ) ) {
		return false;
	}

	$charge_totals = mycred_amelia_get_charge_totals( $ref_id );

	if ( empty( $charge_totals ) ) {
		return false;
	}

	$refunded = false;

	foreach ( $charge_totals as $charge ) {
		$user_id    = absint( $charge->user_id );
		$point_type = mycred_amelia_validate_point_type( $charge->ctype );
		$points     = (float) $charge->points;

		if ( ! $user_id || $points <= 0 || mycred_amelia_log_entry_exists( MYCRED_AMELIA_REFUND_REF, $ref_id, $user_id, $point_type ) ) {
			continue;
		}

		$mycred = mycred( $point_type );
		$points = $mycred->number( $points );

		mycred_add(
			MYCRED_AMELIA_REFUND_REF,
			$user_id,
			$points,
			'Refund for Amelia booking.',
			$ref_id,
			array(
				'status'      => sanitize_key( $status ),
				'source'      => sanitize_key( $source ),
				'refunded_at' => current_time( 'mysql' ),
			),
			$point_type
		);

		$refunded = true;
	}

	return $refunded;
}

function mycred_amelia_is_booking_like_array( $item ) {
	return is_array( $item ) && ! empty( $item['id'] ) && (
		isset( $item['customerId'] ) ||
		isset( $item['persons'] ) ||
		isset( $item['appointmentId'] ) ||
		isset( $item['packageCustomerServiceId'] )
	);
}

function mycred_amelia_is_package_customer_like_array( $item ) {
	return is_array( $item ) && ! empty( $item['id'] ) && isset( $item['packageId'], $item['customerId'], $item['bookingsCount'] );
}

function mycred_amelia_extract_payment_ref_ids( $payload ) {
	$ids = array();

	if ( ! is_array( $payload ) ) {
		return $ids;
	}

	if ( ! empty( $payload['customerBookingId'] ) ) {
		$ids[] = absint( $payload['customerBookingId'] );
	}

	if ( ! empty( $payload['packageCustomerId'] ) ) {
		$ids[] = absint( $payload['packageCustomerId'] );
	}

	if ( mycred_amelia_is_booking_like_array( $payload ) ) {
		$ids[] = absint( $payload['id'] );
	}

	if ( mycred_amelia_is_package_customer_like_array( $payload ) ) {
		$ids[] = absint( $payload['id'] );
	}

	foreach ( array( 'booking', 'bookings', 'payment', 'payments', 'packageCustomer', 'packageCustomers' ) as $key ) {
		if ( empty( $payload[ $key ] ) || ! is_array( $payload[ $key ] ) ) {
			continue;
		}

		foreach ( $payload[ $key ] as $child ) {
			if ( is_array( $child ) ) {
				$ids = array_merge( $ids, mycred_amelia_extract_payment_ref_ids( $child ) );
			}
		}

		if ( isset( $payload[ $key ]['id'] ) || isset( $payload[ $key ]['customerBookingId'] ) || isset( $payload[ $key ]['packageCustomerId'] ) ) {
			$ids = array_merge( $ids, mycred_amelia_extract_payment_ref_ids( $payload[ $key ] ) );
		}
	}

	return array_values( array_unique( array_filter( array_map( 'absint', $ids ) ) ) );
}

function mycred_amelia_refund_from_payload( $payload, $status, $source = '' ) {
	$ref_ids = mycred_amelia_extract_payment_ref_ids( is_array( $payload ) ? $payload : array() );

	foreach ( $ref_ids as $ref_id ) {
		mycred_amelia_refund_ref_id( $ref_id, $status, $source );
	}
}

function mycred_amelia_get_appointment_booking_ids( $appointment_id ) {
	global $wpdb;

	$table = $wpdb->prefix . 'amelia_customer_bookings';

	return array_map(
		'absint',
		(array) $wpdb->get_col(
			$wpdb->prepare(
				"SELECT id FROM {$table} WHERE appointmentId = %d",
				absint( $appointment_id )
			)
		)
	);
}

function mycred_amelia_get_event_booking_ids( $event_id ) {
	global $wpdb;

	$bookings_table = $wpdb->prefix . 'amelia_customer_bookings';
	$pivot_table    = $wpdb->prefix . 'amelia_customer_bookings_to_events_periods';
	$periods_table  = $wpdb->prefix . 'amelia_events_periods';

	return array_map(
		'absint',
		(array) $wpdb->get_col(
			$wpdb->prepare(
				"SELECT DISTINCT cb.id FROM {$bookings_table} cb INNER JOIN {$pivot_table} cbep ON cbep.customerBookingId = cb.id INNER JOIN {$periods_table} ep ON ep.id = cbep.eventPeriodId WHERE ep.eventId = %d",
				absint( $event_id )
			)
		)
	);
}

function mycred_amelia_refund_after_booking_canceled( $booking_data ) {
	mycred_amelia_refund_from_payload( $booking_data, 'canceled', 'booking_status' );
}

function mycred_amelia_refund_after_booking_rejected( $booking_data ) {
	mycred_amelia_refund_from_payload( $booking_data, 'rejected', 'booking_status' );
}

function mycred_amelia_refund_after_booking_deleted( $booking_data ) {
	mycred_amelia_refund_from_payload( $booking_data, 'deleted', 'booking_deleted' );
}

function mycred_amelia_refund_after_package_booking_deleted( $appointment_data, $removed_booking ) {
	mycred_amelia_refund_from_payload( $removed_booking, 'deleted', 'package_booking_deleted' );
}

function mycred_amelia_refund_after_appointment_status_updated( $appointment_data, $requested_status ) {
	if ( ! mycred_amelia_should_refund_status( $requested_status ) ) {
		return;
	}

	$ref_ids = mycred_amelia_extract_payment_ref_ids( is_array( $appointment_data ) ? $appointment_data : array() );

	if ( empty( $ref_ids ) && ! empty( $appointment_data['id'] ) ) {
		$ref_ids = mycred_amelia_get_appointment_booking_ids( $appointment_data['id'] );
	}

	foreach ( $ref_ids as $ref_id ) {
		mycred_amelia_refund_ref_id( $ref_id, $requested_status, 'appointment_status' );
	}
}

function mycred_amelia_refund_after_event_status_updated( $event_data, $requested_status, $apply_globally = false ) {
	if ( ! mycred_amelia_should_refund_status( $requested_status ) ) {
		return;
	}

	$ref_ids = mycred_amelia_extract_payment_ref_ids( is_array( $event_data ) ? $event_data : array() );

	if ( empty( $ref_ids ) && ! empty( $event_data['id'] ) ) {
		$ref_ids = mycred_amelia_get_event_booking_ids( $event_data['id'] );
	}

	foreach ( $ref_ids as $ref_id ) {
		mycred_amelia_refund_ref_id( $ref_id, $requested_status, $apply_globally ? 'event_status_global' : 'event_status' );
	}
}

// Dependency check on plugin activation.
function mycred_amelia_plugins_active() {
	if ( ! function_exists( 'is_plugin_active' ) ) {
		include_once ABSPATH . 'wp-admin/includes/plugin.php';
	}

	$mycred_active = is_plugin_active( 'mycred/mycred.php' );
	$amelia_active = is_plugin_active( 'ameliabooking-2/ameliabooking.php' ) ||
		is_plugin_active( 'ameliabooking-2/ameliabooking-2.php' ) ||
		is_plugin_active( 'ameliabooking/ameliabooking.php' );

	return (bool) ( $mycred_active && $amelia_active );
}

function mycred_amelia_deactivate_due_to_missing_dependencies() {
	if ( ! function_exists( 'deactivate_plugins' ) ) {
		include_once ABSPATH . 'wp-admin/includes/plugin.php';
	}

	if ( is_plugin_active( plugin_basename( __FILE__ ) ) ) {
		deactivate_plugins( plugin_basename( __FILE__ ) );
		set_transient( 'mycred_amelia_missing_deps', true, 5 );
	}
}

function mycred_amelia_activation_check() {
	if ( ! mycred_amelia_plugins_active() ) {
		mycred_amelia_deactivate_due_to_missing_dependencies();
		wp_die(
			'<p><strong>myCred Amelia</strong> requires both <strong>myCred</strong> and <strong>Amelia Booking</strong> plugins to be installed and active.</p>',
			'<strong>Dependency check failed</strong>',
			array( 'back_link' => true )
		);
	}
}
register_activation_hook( __FILE__, 'mycred_amelia_activation_check' );

function mycred_amelia_check_dependencies_on_load() {
	if ( ! mycred_amelia_plugins_active() ) {
		mycred_amelia_deactivate_due_to_missing_dependencies();
	}
}
add_action( 'admin_init', 'mycred_amelia_check_dependencies_on_load', 1 );

// Admin notice for missing dependencies.
function mycred_amelia_missing_deps_notice() {
	if ( get_transient( 'mycred_amelia_missing_deps' ) ) {
		echo '<div class="error"><p><strong>myCred Amelia</strong> requires both <strong>myCred</strong> and <strong>Amelia Booking</strong> plugins to be installed and active.</p></div>';
		delete_transient( 'mycred_amelia_missing_deps' );
	}
}
add_action( 'admin_notices', 'mycred_amelia_missing_deps_notice' );

// Add script for Amelia shortcodes.
add_filter( 'the_content', 'mycred_amelia_check_booking_shortcode' );

function mycred_amelia_check_booking_shortcode( $content ) {
	if (
		! is_admin() &&
		! wp_doing_ajax() &&
		! defined( 'REST_REQUEST' ) &&
		(
			has_shortcode( $content, 'ameliabooking' ) ||
			has_shortcode( $content, 'ameliacustomerpanel' ) ||
			has_shortcode( $content, 'ameliaevents' ) ||
			has_shortcode( $content, 'ameliastepbooking' ) ||
			has_shortcode( $content, 'ameliaeventslistbooking' ) ||
			has_shortcode( $content, 'ameliaeventscalendarbooking' ) ||
			has_shortcode( $content, 'ameliaemployeepanel' ) ||
			has_shortcode( $content, 'ameliacatalogbooking' ) ||
			has_shortcode( $content, 'ameliacatalog' )
		)
	) {
		ob_start();
		mycred_amelia_booking_script();
		mycred_amelia_event_script();
		$content .= ob_get_clean();
	}

	return $content;
}

// Legacy stubs kept for backward compatibility. Logic lives in assets/js/mycred-amelia-frontend.js.
function mycred_amelia_booking_script() {}
function mycred_amelia_event_script() {}
function mycred_amelia_booking_list_script() {}

function mycred_amelia_enqueue() {
	wp_enqueue_script( 'jquery' );
	wp_localize_script( 'jquery', 'my_ajax_object', array( 'ajax_url' => admin_url( 'admin-ajax.php' ) ) );

	$frontend_path = plugin_dir_path( __FILE__ ) . 'assets/js/mycred-amelia-frontend.js';
	$frontend_url  = plugin_dir_url( __FILE__ ) . 'assets/js/mycred-amelia-frontend.js';

	wp_register_script( 'mycred-amelia-frontend', $frontend_url, array( 'jquery' ), filemtime( $frontend_path ), true );
	wp_enqueue_script( 'mycred-amelia-frontend' );

	if ( ! function_exists( 'mycred' ) ) {
		return;
	}

	$settings   = mycred_amelia_get_settings();
	$mycred     = mycred_amelia_get_mycred( $settings );
	$user_id    = get_current_user_id();
	$point_type = mycred_amelia_validate_point_type( $settings['point_type'] );

	if ( ! $mycred ) {
		return;
	}

	$insufficient_msg = str_replace(
		'%buy_points_url%',
		esc_url( $settings['buy_points_url'] ),
		$settings['insufficient_balance_msg']
	);

	wp_localize_script(
		'mycred-amelia-frontend',
		'myCredData',
		array(
			'enabled'          => ! empty( $settings['enabled'] ) ? 1 : 0,
			'prefix'           => $mycred->core['before'],
			'suffix'           => $mycred->core['after'],
			'balance'          => $user_id ? $mycred->get_users_balance( $user_id, $point_type ) : 0,
			'userId'           => $user_id,
			'insufficientMsg'  => wp_kses_post( $insufficient_msg ),
			'pointType'        => strtolower( $mycred->plural() ),
			'conversionRate'   => (float) $settings['conversion_rate'],
			'rounding'         => $settings['rounding'],
			'buyPointsUrl'     => esc_url( $settings['buy_points_url'] ),
		)
	);
}
add_action( 'wp_enqueue_scripts', 'mycred_amelia_enqueue' );
add_action( 'mycred_init', 'mycred_amelia_load_files' );
add_filter( 'mycred_setup_hooks', 'mycred_amelia_register_hook', 10, 2 );
add_action( 'mycred_load_hooks', 'mycred_amelia_load_hook' );
add_filter( 'mycred_all_references', 'mycred_amelia_references' );

function mycred_amelia_debug_log( $message, $data = null ) {
	if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
		if ( $data !== null ) {
			$message .= ' | ' . wp_json_encode( $data );
		}
		error_log( '[mycred-amelia] ' . $message );
	}
}

function mycred_amelia_load_files() {
	include_once MYCRED_AMELIA_PLUGIN_PATH . 'inc/settings.php';
}

function mycred_amelia_register_hook( $installed, $point_type ) {
	mycred_amelia_debug_log( 'registering amelia_booking_completion hook', array( 'point_type' => $point_type ) );

	$installed['amelia_booking_completion'] = array(
		'title'       => __( 'Amelia booking completion', 'mycred-amelia' ),
		'description' => __( 'Award %_plural% for completing Amelia bookings.', 'mycred-amelia' ),
		'callback'    => array( 'myCRED_Amelia_Booking_Completion_Hook' ),
		'defaults'    => array(
			'creds' => 10,
			'log'   => '%plural% for completing an Amelia booking.',
			'limit' => '0',
		),
	);

	return $installed;
}

function mycred_amelia_load_hook( $point_type ) {
	mycred_amelia_debug_log( 'loading amelia hook file', array( 'point_type' => $point_type ) );
	include_once MYCRED_AMELIA_PLUGIN_PATH . 'inc/class-mycred-amelia-complete-booking-hook.php';
	if ( ! class_exists( 'myCRED_Amelia_Booking_Completion_Hook' ) ) {
		mycred_amelia_debug_log( 'myCRED_Amelia_Booking_Completion_Hook class not found after include' );
	}
}

function mycred_amelia_references( $references ) {
	$references['amelia_booking_completion'] = __( 'Amelia booking completion', 'mycred-amelia' );
	return $references;
}
