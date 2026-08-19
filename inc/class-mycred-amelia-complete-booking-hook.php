<?php
/**
 * Award points when an Amelia appointment is approved.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Debug helper.
 * Writes to /wp-content/debug.log only when WP_DEBUG is enabled.
 */
if ( ! function_exists( 'mycred_amelia_debug_log' ) ) {
	function mycred_amelia_debug_log( $message, $data = null ) {
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log(
				'[myCRED Amelia] ' . $message . ' | ' . print_r( $data, true )
			);
		}
	}
}

/**
 * Custom myCRED hook implementation.
 */
if ( class_exists( 'myCRED_Hook' ) && ! class_exists( 'myCRED_Amelia_Booking_Completion_Hook' ) ) {

	class myCRED_Amelia_Booking_Completion_Hook extends myCRED_Hook {

		public function __construct( $hook_prefs, $type = MYCRED_DEFAULT_TYPE_KEY ) {
			parent::__construct(
				array(
					'id'       => 'amelia_booking_completion',
					'defaults' => array(
						'creds' => 10,
						'log'   => '%plural% for Amelia appointment approval.',
						'limit' => '0',
					),
				),
				$hook_prefs,
				$type
			);
		}

		/**
		 * Attach Amelia hooks after this myCRED hook is enabled.
		 */
		public function run() {
			mycred_amelia_debug_log( 'myCRED Amelia hook loaded.' );

			// Runs when an appointment status is changed in Amelia.
			add_action(
				'amelia_after_appointment_status_updated',
				array( $this, 'award_points_on_appointment_status_change' ),
				10,
				2
			);

			// Optional: handles appointments created directly as Approved.
			add_action(
				'amelia_after_booking_added',
				array( $this, 'award_points_on_booking_added' ),
				10,
				1
			);
		}

		/**
		 * Award points after an appointment status changes.
		 */
		public function award_points_on_appointment_status_change( $appointment_data, $new_status ) {
			mycred_amelia_debug_log(
				'Appointment status update received.',
				array(
					'new_status'       => $new_status,
					'appointment_data' => $appointment_data,
				)
			);

			if ( ! is_array( $appointment_data ) ) {
				return;
			}

			if ( ! $this->is_reward_status( $new_status ) ) {
				return;
			}

			$this->process_amelia_booking( $appointment_data );
		}

		/**
		 * Award points if a newly-created booking starts as Approved.
		 */
		public function award_points_on_booking_added( $booking_data ) {
			mycred_amelia_debug_log( 'Booking added received.', $booking_data );

			if ( ! is_array( $booking_data ) ) {
				return;
			}

			$status = $this->get_amelia_status( $booking_data );

			if ( ! $this->is_reward_status( $status ) ) {
				return;
			}

			$this->process_amelia_booking( $booking_data );
		}

		/**
		 * Change this if you want points for another Amelia status.
		 *
		 * Recommended: only award when status becomes "approved".
		 */
		protected function is_reward_status( $status ) {
			return 'approved' === strtolower( trim( (string) $status ) );
		}

		/**
		 * Locate status in different Amelia payload shapes.
		 */
		protected function get_amelia_status( $data ) {
			if ( ! is_array( $data ) ) {
				return '';
			}

			if ( ! empty( $data['status'] ) ) {
				return $data['status'];
			}

			if ( ! empty( $data['booking']['status'] ) ) {
				return $data['booking']['status'];
			}

			if ( ! empty( $data['bookings'] ) && is_array( $data['bookings'] ) ) {
				foreach ( $data['bookings'] as $booking ) {
					if ( ! empty( $booking['status'] ) ) {
						return $booking['status'];
					}
				}
			}

			return '';
		}

		/**
		 * Award points to every valid WordPress user found in the payload.
		 */
		protected function process_amelia_booking( $data ) {
			$reference = $this->get_amelia_reference_id( $data );
			$user_ids  = $this->get_amelia_wordpress_user_ids( $data );

			mycred_amelia_debug_log(
				'Processing Amelia booking.',
				array(
					'reference' => $reference,
					'user_ids'  => $user_ids,
				)
			);

			if ( empty( $user_ids ) ) {
				mycred_amelia_debug_log(
					'No linked WordPress user found. Amelia customer ID is not necessarily a WordPress user ID.',
					$data
				);
				return;
			}

			foreach ( $user_ids as $user_id ) {

				/*
				 * Prevent duplicate rewards for the same booking.
				 * myCRED uses reference + reference ID + user ID to track entries.
				 */
				if ( $this->core->has_entry( 'amelia_booking_completion', $reference, $user_id ) ) {
					mycred_amelia_debug_log(
						'Skipped: points were already awarded for this booking.',
						array(
							'user_id'   => $user_id,
							'reference' => $reference,
						)
					);
					continue;
				}

				if ( $this->over_hook_limit( '', 'amelia_booking_completion', $user_id ) ) {
					mycred_amelia_debug_log(
						'Skipped: user reached hook limit.',
						array( 'user_id' => $user_id )
					);
					continue;
				}

				$this->core->add_creds(
					'amelia_booking_completion',
					$user_id,
					$this->prefs['creds'],
					$this->prefs['log'],
					$reference,
					array(
						'ref_type' => 'amelia_booking',
					),
					$this->mycred_type
				);

				mycred_amelia_debug_log(
					'Points awarded.',
					array(
						'user_id'   => $user_id,
						'points'    => $this->prefs['creds'],
						'reference' => $reference,
					)
				);
			}
		}

		/**
		 * Find the Amelia booking / appointment ID for duplicate prevention.
		 */
		protected function get_amelia_reference_id( $data ) {
			if ( ! is_array( $data ) ) {
				return 0;
			}

			$possible_ids = array(
				isset( $data['customerBookingId'] ) ? $data['customerBookingId'] : 0,
				isset( $data['bookingId'] ) ? $data['bookingId'] : 0,
				isset( $data['id'] ) ? $data['id'] : 0,
				isset( $data['booking']['customerBookingId'] ) ? $data['booking']['customerBookingId'] : 0,
				isset( $data['booking']['id'] ) ? $data['booking']['id'] : 0,
			);

			foreach ( $possible_ids as $id ) {
				if ( ! empty( $id ) ) {
					return absint( $id );
				}
			}

			if ( ! empty( $data['bookings'] ) && is_array( $data['bookings'] ) ) {
				foreach ( $data['bookings'] as $booking ) {
					if ( ! empty( $booking['customerBookingId'] ) ) {
						return absint( $booking['customerBookingId'] );
					}

					if ( ! empty( $booking['id'] ) ) {
						return absint( $booking['id'] );
					}
				}
			}

			/*
			 * Avoid all bookings using reference ID 0.
			 * This is only a fallback when Amelia sends no usable ID.
			 */
			return absint( crc32( wp_json_encode( $data ) ) );
		}

		/**
		 * Get all linked WordPress user IDs from Amelia booking data.
		 *
		 * Important:
		 * - externalId is commonly the WordPress user ID when Amelia customers
		 *   are linked to WordPress accounts.
		 * - customerId and customer.id are Amelia database IDs, so they are NOT
		 *   trusted as WordPress IDs here.
		 */
		protected function get_amelia_wordpress_user_ids( $data ) {
			$user_ids = array();
			$bookings = $this->extract_bookings( $data );

			foreach ( $bookings as $booking ) {
				$user_id = $this->get_wordpress_user_id_from_booking( $booking );

				if ( $user_id ) {
					$user_ids[] = $user_id;
				}
			}

			return array_values( array_unique( array_filter( $user_ids ) ) );
		}

		/**
		 * Normalize various Amelia payload formats into booking arrays.
		 */
		protected function extract_bookings( $data ) {
			$bookings = array();

			if ( ! is_array( $data ) ) {
				return $bookings;
			}

			if ( ! empty( $data['booking'] ) && is_array( $data['booking'] ) ) {
				$bookings[] = $data['booking'];
			}

			if ( ! empty( $data['bookings'] ) && is_array( $data['bookings'] ) ) {
				foreach ( $data['bookings'] as $booking ) {
					if ( is_array( $booking ) ) {
						$bookings[] = $booking;
					}
				}
			}

			if ( ! empty( $data['appointments'] ) && is_array( $data['appointments'] ) ) {
				foreach ( $data['appointments'] as $appointment ) {
					if ( ! empty( $appointment['bookings'] ) && is_array( $appointment['bookings'] ) ) {
						foreach ( $appointment['bookings'] as $booking ) {
							if ( is_array( $booking ) ) {
								$bookings[] = $booking;
							}
						}
					}
				}
			}

			// If the received data itself appears to be one booking.
			if ( empty( $bookings ) ) {
				$bookings[] = $data;
			}

			return $bookings;
		}

		/**
		 * Return a real WordPress user ID, or 0 if this customer is a guest.
		 */
		protected function get_wordpress_user_id_from_booking( $booking ) {
			if ( ! is_array( $booking ) ) {
				return 0;
			}

			$candidates = array();

			if ( ! empty( $booking['customer']['externalId'] ) ) {
				$candidates[] = $booking['customer']['externalId'];
			}

			if ( ! empty( $booking['customer']['id'] ) ) {
				$candidates[] = $booking['customer']['id'];
			}

			if ( ! empty( $booking['customer']['customerId'] ) ) {
				$candidates[] = $booking['customer']['customerId'];
			}

			if ( ! empty( $booking['externalId'] ) ) {
				$candidates[] = $booking['externalId'];
			}

			if ( ! empty( $booking['customerId'] ) ) {
				$candidates[] = $booking['customerId'];
			}

			/*
			 * Some Amelia setups may expose a clearly named WP user field.
			 * These are safe to test because we verify them with get_userdata().
			 */
			if ( ! empty( $booking['customer']['wpUserId'] ) ) {
				$candidates[] = $booking['customer']['wpUserId'];
			}

			if ( ! empty( $booking['wpUserId'] ) ) {
				$candidates[] = $booking['wpUserId'];
			}

			if ( ! empty( $booking['customer']['email'] ) ) {
				$candidates[] = $booking['customer']['email'];
			}

			if ( ! empty( $booking['email'] ) ) {
				$candidates[] = $booking['email'];
			}

			foreach ( $candidates as $candidate ) {
				if ( empty( $candidate ) ) {
					continue;
				}

				$user_id = absint( $candidate );

				if ( $user_id && get_userdata( $user_id ) ) {
					return $user_id;
				}

				if ( is_string( $candidate ) && strpos( $candidate, '@' ) !== false ) {
					$user = get_user_by( 'email', sanitize_email( $candidate ) );

					if ( $user ) {
						return $user->ID;
					}
				}
			}

			return 0;
		}

		/**
		 * Save hook settings safely.
		 */
		public function sanitise_preferences( $data ) {
			return array(
				'creds' => isset( $data['creds'] ) ? floatval( $data['creds'] ) : 0,
				'log'   => isset( $data['log'] ) ? sanitize_text_field( $data['log'] ) : '',
				'limit' => ! empty( $data['limit'] ) ? sanitize_text_field( $data['limit'] ) : '0',
			);
		}

		/**
		 * myCRED Hook settings UI.
		 */
		public function preferences() {
			$prefs = $this->prefs;
			?>
			<div class="hook-instance">
				<h3><?php esc_html_e( 'General', 'mycred-amelia' ); ?></h3>

				<div class="row">
					<div class="col-lg-3 col-md-4 col-sm-12 col-xs-12">
						<div class="form-group">
							<label for="<?php echo esc_attr( $this->field_id( 'creds' ) ); ?>">
								<?php echo esc_html( $this->core->plural() ); ?>
							</label>

							<input
								type="text"
								name="<?php echo esc_attr( $this->field_name( 'creds' ) ); ?>"
								id="<?php echo esc_attr( $this->field_id( 'creds' ) ); ?>"
								value="<?php echo esc_attr( $this->core->number( $prefs['creds'] ) ); ?>"
								class="form-control"
							/>
						</div>
					</div>

					<div class="col-lg-6 col-md-8 col-sm-12 col-xs-12">
						<div class="form-group">
							<label for="<?php echo esc_attr( $this->field_id( 'log' ) ); ?>">
								<?php esc_html_e( 'Log template', 'mycred-amelia' ); ?>
							</label>

							<input
								type="text"
								name="<?php echo esc_attr( $this->field_name( 'log' ) ); ?>"
								id="<?php echo esc_attr( $this->field_id( 'log' ) ); ?>"
								value="<?php echo esc_attr( $prefs['log'] ); ?>"
								class="form-control"
							/>

							<span class="description">
								<?php esc_html_e( 'Available template tags: %plural%', 'mycred-amelia' ); ?>
							</span>
						</div>
					</div>

					<div class="col-lg-3 col-md-12 col-sm-12 col-xs-12">
						<div class="form-group">
							<label for="<?php echo esc_attr( $this->field_id( 'limit' ) ); ?>">
								<?php esc_html_e( 'Limit', 'mycred-amelia' ); ?>
							</label>

							<input
								type="text"
								name="<?php echo esc_attr( $this->field_name( 'limit' ) ); ?>"
								id="<?php echo esc_attr( $this->field_id( 'limit' ) ); ?>"
								value="<?php echo esc_attr( $prefs['limit'] ); ?>"
								class="form-control"
							/>

							<span class="description">
								<?php esc_html_e( 'Number of approved bookings per user.', 'mycred-amelia' ); ?>
							</span>
						</div>
					</div>
				</div>
			</div>
			<?php
		}
	}
}