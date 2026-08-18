<?php

defined( 'ABSPATH' ) || exit;

if ( class_exists( 'myCRED_Module' ) && ! class_exists( 'myCred_Amelia_Settings' ) ) {

	class myCred_Amelia_Settings extends myCRED_Module {

		public function __construct() {
			parent::__construct( 'mycred_amelia' );
			add_action( 'mycred_after_core_prefs', array( $this, 'mycred_amelia_display_settings' ), 10, 1 );
			add_filter( 'mycred_save_core_prefs', array( $this, 'mycred_amelia_save_settings' ), 10, 3 );
		}

		public function mycred_amelia_display_settings( $object ) {
			$settings    = function_exists( 'mycred_amelia_get_settings' ) ? mycred_amelia_get_settings() : array();
			$point_types = function_exists( 'mycred_get_types' ) ? mycred_get_types() : array( $settings['point_type'] => $settings['point_type'] );
			?>
			<div class="mycred-ui-accordion">
				<div class="mycred-ui-accordion-header">
					<h4 class="mycred-ui-accordion-header-title">
						<span class="dashicons dashicons-admin-plugins static mycred-ui-accordion-header-icon"></span>
						<label><?php esc_html_e( 'myCred Amelia Settings', 'mycred-amelia' ); ?></label>
					</h4>
					<div class="mycred-ui-accordion-header-actions hide-if-no-js">
						<button type="button" aria-expanded="true">
							<span class="mycred-ui-toggle-indicator" aria-hidden="true"></span>
						</button>
					</div>
				</div>
				<div class="body mycred-ui-accordion-body" style="display:none;">
					<div class="row">
						<div class="col-lg-12 col-md-12 col-sm-12 col-xs-12">
							<div class="form-group">
								<label>
									<input type="checkbox" name="<?php echo esc_attr( $this->field_name( array( 'mycred_amelia' => 'enabled' ) ) ); ?>" value="1" <?php checked( ! empty( $settings['enabled'] ) ); ?> />
									<?php esc_html_e( 'Enable Amelia payment with myCred points', 'mycred-amelia' ); ?>
								</label>
							</div>
						</div>

						<div class="col-lg-6 col-md-6 col-sm-12 col-xs-12">
							<div class="form-group">
								<label for="<?php echo esc_attr( $this->field_id( array( 'mycred_amelia' => 'point_type' ) ) ); ?>"><?php esc_html_e( 'Point type', 'mycred-amelia' ); ?></label>
								<select name="<?php echo esc_attr( $this->field_name( array( 'mycred_amelia' => 'point_type' ) ) ); ?>" id="<?php echo esc_attr( $this->field_id( array( 'mycred_amelia' => 'point_type' ) ) ); ?>" class="form-control">
									<?php foreach ( $point_types as $type => $label ) : ?>
										<option value="<?php echo esc_attr( $type ); ?>" <?php selected( $settings['point_type'], $type ); ?>><?php echo esc_html( $label ); ?></option>
									<?php endforeach; ?>
								</select>
							</div>
						</div>

						<div class="col-lg-6 col-md-6 col-sm-12 col-xs-12">
							<div class="form-group">
								<label for="<?php echo esc_attr( $this->field_id( array( 'mycred_amelia' => 'conversion_rate' ) ) ); ?>"><?php esc_html_e( 'Currency conversion rate', 'mycred-amelia' ); ?></label>
								<input type="number" step="0.0001" min="0.0001" name="<?php echo esc_attr( $this->field_name( array( 'mycred_amelia' => 'conversion_rate' ) ) ); ?>" id="<?php echo esc_attr( $this->field_id( array( 'mycred_amelia' => 'conversion_rate' ) ) ); ?>" class="form-control" value="<?php echo esc_attr( $settings['conversion_rate'] ); ?>" />
								<p class="description"><?php esc_html_e( 'Example: 100 means 1 currency unit costs 100 points.', 'mycred-amelia' ); ?></p>
							</div>
						</div>

						<div class="col-lg-6 col-md-6 col-sm-12 col-xs-12">
							<div class="form-group">
								<label for="<?php echo esc_attr( $this->field_id( array( 'mycred_amelia' => 'rounding' ) ) ); ?>"><?php esc_html_e( 'Rounding', 'mycred-amelia' ); ?></label>
								<select name="<?php echo esc_attr( $this->field_name( array( 'mycred_amelia' => 'rounding' ) ) ); ?>" id="<?php echo esc_attr( $this->field_id( array( 'mycred_amelia' => 'rounding' ) ) ); ?>" class="form-control">
									<option value="ceil" <?php selected( $settings['rounding'], 'ceil' ); ?>><?php esc_html_e( 'Round up', 'mycred-amelia' ); ?></option>
									<option value="round" <?php selected( $settings['rounding'], 'round' ); ?>><?php esc_html_e( 'Nearest', 'mycred-amelia' ); ?></option>
									<option value="floor" <?php selected( $settings['rounding'], 'floor' ); ?>><?php esc_html_e( 'Round down', 'mycred-amelia' ); ?></option>
								</select>
							</div>
						</div>

						<div class="col-lg-6 col-md-6 col-sm-12 col-xs-12">
							<div class="form-group">
								<label for="<?php echo esc_attr( $this->field_id( array( 'mycred_amelia' => 'buy_points_url' ) ) ); ?>"><?php esc_html_e( 'Buy points URL', 'mycred-amelia' ); ?></label>
								<input type="text" name="<?php echo esc_attr( $this->field_name( array( 'mycred_amelia' => 'buy_points_url' ) ) ); ?>" id="<?php echo esc_attr( $this->field_id( array( 'mycred_amelia' => 'buy_points_url' ) ) ); ?>" class="form-control" value="<?php echo esc_attr( $settings['buy_points_url'] ); ?>" />
								<p class="description"><?php esc_html_e( 'You can use %buy_points_url% inside the message below.', 'mycred-amelia' ); ?></p>
							</div>
						</div>

						<div class="col-lg-12 col-md-12 col-sm-12 col-xs-12">
							<div class="form-group">
								<label for="<?php echo esc_attr( $this->field_id( array( 'mycred_amelia' => 'insufficient_balance_msg' ) ) ); ?>"><?php esc_html_e( 'Insufficient balance message', 'mycred-amelia' ); ?></label>
								<textarea name="<?php echo esc_attr( $this->field_name( array( 'mycred_amelia' => 'insufficient_balance_msg' ) ) ); ?>" id="<?php echo esc_attr( $this->field_id( array( 'mycred_amelia' => 'insufficient_balance_msg' ) ) ); ?>" class="form-control" rows="3"><?php echo esc_textarea( $settings['insufficient_balance_msg'] ); ?></textarea>
							</div>
						</div>

						<div class="col-lg-12 col-md-12 col-sm-12 col-xs-12">
							<div class="form-group">
								<label><?php esc_html_e( 'Refund points when:', 'mycred-amelia' ); ?></label>
								<p>
									<label>
										<input type="checkbox" name="<?php echo esc_attr( $this->field_name( array( 'mycred_amelia' => 'refund_on_canceled' ) ) ); ?>" value="1" <?php checked( ! empty( $settings['refund_on_canceled'] ) ); ?> />
										<?php esc_html_e( 'Booking is canceled', 'mycred-amelia' ); ?>
									</label>
								</p>
								<p>
									<label>
										<input type="checkbox" name="<?php echo esc_attr( $this->field_name( array( 'mycred_amelia' => 'refund_on_rejected' ) ) ); ?>" value="1" <?php checked( ! empty( $settings['refund_on_rejected'] ) ); ?> />
										<?php esc_html_e( 'Booking is rejected or declined', 'mycred-amelia' ); ?>
									</label>
								</p>
								<p>
									<label>
										<input type="checkbox" name="<?php echo esc_attr( $this->field_name( array( 'mycred_amelia' => 'refund_on_deleted' ) ) ); ?>" value="1" <?php checked( ! empty( $settings['refund_on_deleted'] ) ); ?> />
										<?php esc_html_e( 'Booking is deleted', 'mycred-amelia' ); ?>
									</label>
								</p>
							</div>
						</div>
					</div>
				</div>
			</div>
			<?php
		}

		public function mycred_amelia_save_settings( $new_data, $post, $object ) {
			$posted   = isset( $post['mycred_amelia'] ) && is_array( $post['mycred_amelia'] ) ? $post['mycred_amelia'] : array();
			$settings = function_exists( 'mycred_amelia_sanitize_settings' ) ? mycred_amelia_sanitize_settings( $posted ) : $posted;

			update_option( 'mycred_amelia_settings', array( 'mycred_amelia' => $settings ) );

			return $new_data;
		}
	}

	$myCred_Amelia_Settings = new myCred_Amelia_Settings();
}
