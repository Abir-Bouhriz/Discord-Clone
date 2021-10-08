<?php

namespace WPForms\Admin\Tools\Views;

use WPForms\Helpers\File;
use WPForms\Admin\Tools\Importers;
use WPForms\Admin\Tools\Tools;

/**
 * Class Import.
 *
 * @since 1.6.6
 */
class Import extends View {

	/**
	 * View slug.
	 *
	 * @since 1.6.6
	 *
	 * @var string
	 */
	protected $slug = 'import';

	/**
	 * Registered importers.
	 *
	 * @since 1.6.6
	 *
	 * @var array
	 */
	public $importers = [];

	/**
	 * Checking user capability to view.
	 *
	 * @since 1.6.6
	 *
	 * @return bool
	 */
	public function check_capability() {

		return wpforms_current_user_can( 'create_forms' );
	}

	/**
	 * Init view.
	 *
	 * @since 1.6.6
	 */
	public function init() {

		add_action( 'wpforms_tools_init', [ $this, 'import_process' ] );

		$this->importers = ( new Importers() )->get_importers();
	}

	/**
	 * Get view label.
	 *
	 * @since 1.6.6
	 *
	 * @return string
	 */
	public function get_label() {

		return esc_html__( 'Import', 'wpforms-lite' );
	}

	/**
	 * Import process.
	 *
	 * @since 1.6.6
	 */
	public function import_process() {

		if (
			empty( $_POST['action'] ) || //phpcs:ignore WordPress.Security.NonceVerification
			$_POST['action'] !== 'import_form' || //phpcs:ignore WordPress.Security.NonceVerification
			empty( $_FILES['file']['tmp_name'] ) ||
			! isset( $_POST['submit-import'] ) || //phpcs:ignore WordPress.Security.NonceVerification
			! $this->verify_nonce()
		) {
			return;
		}

		$this->process();
	}

	/**
	 * Import view content.
	 *
	 * @since 1.6.6
	 */
	public function display() {

		$this->success_import_message();

		$this->wpforms_block();

		$this->other_forms_block();
	}

	/**
	 * Success import message.
	 *
	 * @since 1.6.6
	 */
	private function success_import_message() {

		if ( isset( $_GET['wpforms_notice'] ) && $_GET['wpforms_notice'] === 'forms-imported' ) { //phpcs:ignore WordPress.Security.NonceVerification.Recommended
			?>
			<div class="updated notice is-dismissible">
				<p>
					<?php esc_html_e( 'Import was successfully finished.', 'wpforms-lite' ); ?>
					<?php
					if ( wpforms_current_user_can( 'view_forms' ) ) {
						printf(
							wp_kses( /* translators: %s - Forms list page URL. */
								__( 'You can go and <a href="%s">check your forms</a>.', 'wpforms-lite' ),
								[ 'a' => [ 'href' => [] ] ]
							),
							esc_url( admin_url( 'admin.php?page=wpforms-overview' ) )
						);
					}
					?>
				</p>
			</div>
			<?php
		}
	}

	/**
	 * WPForms section.
	 *
	 * @since 1.6.6
	 */
	private function wpforms_block() {
		?>

		<div class="wpforms-setting-row tools">
			<h4><?php esc_html_e( 'WPForms Import', 'wpforms-lite' ); ?></h4>
			<p><?php esc_html_e( 'Select a WPForms export file.', 'wpforms-lite' ); ?></p>

			<form method="post" enctype="multipart/form-data" action="<?php echo esc_attr( $this->get_link() ); ?>">
				<div class="wpforms-file-upload">
					<input type="file" name="file" id="wpforms-tools-form-import" class="inputfile"
						data-multiple-caption="{count} <?php esc_attr_e( 'files selected', 'wpforms-lite' ); ?>"
						accept=".json" />
					<label for="wpforms-tools-form-import">
						<span class="fld"><span class="placeholder"><?php esc_html_e( 'No file chosen', 'wpforms-lite' ); ?></span></span>
						<strong class="wpforms-btn wpforms-btn-md wpforms-btn-light-grey">
							<i class="fa fa-upload"></i><?php esc_html_e( 'Choose a file&hellip;', 'wpforms-lite' ); ?>
						</strong>
					</label>
				</div>
				<br>
				<input type="hidden" name="action" value="import_form">
				<button name="submit-import" class="wpforms-btn wpforms-btn-md wpforms-btn-orange">
					<?php esc_html_e( 'Import', 'wpforms-lite' ); ?>
				</button>
				<?php $this->nonce_field(); ?>
			</form>
		</div>
		<?php
	}

	/**
	 * WPForms section.
	 *
	 * @since 1.6.6
	 */
	private function other_forms_block() {
		?>

		<div class="wpforms-setting-row tools" id="wpforms-importers">
			<h4><?php esc_html_e( 'Import from Other Form Plugins', 'wpforms-lite' ); ?></h4>
			<p><?php esc_html_e( 'Not happy with other WordPress contact form plugins?', 'wpforms-lite' ); ?></p>
			<p><?php esc_html_e( 'WPForms makes it easy for you to switch by allowing you import your third-party forms with a single click.', 'wpforms-lite' ); ?></p>

			<div class="wpforms-importers-wrap">
				<?php if ( empty( $this->importers ) ) { ?>
					<p><?php esc_html_e( 'No form importers are currently enabled.', 'wpforms-lite' ); ?> </p>
				<?php } else { ?>
					<form action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>">
						<span class="choicesjs-select-wrap">
							<select class="choicesjs-select" name="provider" required>
								<option value=""><?php esc_html_e( 'Select previous contact form plugin...', 'wpforms-lite' ); ?></option>
								<?php
								foreach ( $this->importers as $importer ) {
									$status = '';

									if ( empty( $importer['installed'] ) ) {
										$status = esc_html__( 'Not Installed', 'wpforms-lite' );
									} elseif ( empty( $importer['active'] ) ) {
										$status = esc_html__( 'Not Active', 'wpforms-lite' );
									}
									printf(
										'<option value="%s" %s>%s %s</option>',
										esc_attr( $importer['slug'] ),
										! empty( $status ) ? 'disabled' : '',
										esc_html( $importer['name'] ),
										! empty( $status ) ? '(' . esc_html( $status ) . ')' : '' //phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
									);
								}
								?>
							</select>
						</span>
						<br />
						<input type="hidden" name="page" value="<?php echo esc_attr( Tools::SLUG ); ?>">
						<input type="hidden" name="view" value="importer">
						<button class="wpforms-btn wpforms-btn-md wpforms-btn-orange">
							<?php esc_html_e( 'Import', 'wpforms-lite' ); ?>
						</button>
					</form>
				<?php } ?>
			</div>
		</div>
		<?php
	}

	/**
	 * Import processing.
	 *
	 * @since 1.6.6
	 */
	private function process() {

		// Add filter of the link rel attr to avoid JSON damage.
		add_filter( 'wp_targeted_link_rel', '__return_empty_string', 50, 1 );

		$ext = '';

		if ( isset( $_FILES['file']['name'] ) ) {
			$ext = strtolower( pathinfo( sanitize_text_field( wp_unslash( $_FILES['file']['name'] ) ), PATHINFO_EXTENSION ) );
		}

		if ( $ext !== 'json' ) {
			wp_die(
				esc_html__( 'Please upload a valid .json form export file.', 'wpforms-lite' ),
				esc_html__( 'Error', 'wpforms-lite' ),
				[
					'response' => 400,
				]
			);
		}

		$tmp_name = isset( $_FILES['file']['tmp_name'] ) ? sanitize_text_field( $_FILES['file']['tmp_name'] ) : ''; //phpcs:ignore WordPress.Security.ValidatedSanitizedInput.MissingUnslash -- wp_unslash() breaks upload on Windows.
		$forms    = json_decode( File::remove_utf8_bom( file_get_contents( $tmp_name ) ), true );

		if ( empty( $forms ) || ! is_array( $forms ) ) {
			wp_die(
				esc_html__( 'Form data cannot be imported.', 'wpforms-lite' ),
				esc_html__( 'Error', 'wpforms-lite' ),
				[
					'response' => 400,
				]
			);
		}

		foreach ( $forms as $form ) {
			$title  = ! empty( $form['settings']['form_title'] ) ? $form['settings']['form_title'] : '';
			$desc   = ! empty( $form['settings']['form_desc'] ) ? $form['settings']['form_desc'] : '';
			$new_id = wp_insert_post(
				[
					'post_title'   => $title,
					'post_status'  => 'publish',
					'post_type'    => 'wpforms',
					'post_excerpt' => $desc,
				]
			);

			if ( $new_id ) {
				$form['id'] = $new_id;

				wp_update_post(
					[
						'ID'           => $new_id,
						'post_content' => wpforms_encode( $form ),
					]
				);
			}
		}

		wp_safe_redirect( add_query_arg( [ 'wpforms_notice' => 'forms-imported' ] ) );
		exit;
	}

}
