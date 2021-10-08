<?php
/**
 * Panel Setup (form templates).
 * Form templates list item template.
 *
 * @since 1.6.8
 *
 * @var string $selected_class       Selected item class.
 * @var string $license_class        License class (in the case of higher license needed).
 * @var string $categories           Categories, coma separated.
 * @var string $badge_text           Badge text.
 * @var string $demo_url             Template demo URL.
 * @var string $template_id          Template ID (Slug or ID if available).
 * @var string $education_class      Education class (in the case of higher license needed).
 * @var string $education_attributes Education attributes.
 * @var string $addons_attributes    Required addons attributes.
 * @var array  $template             Template data.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

?>
<div class="wpforms-template<?php echo esc_attr( $selected_class ); ?><?php echo esc_attr( $license_class ); ?>"
	id="wpforms-template-<?php echo sanitize_html_class( $template['slug'] ); ?>">

	<h3 class="wpforms-template-name categories" data-categories="<?php echo esc_attr( $categories ); ?>">
		<?php echo esc_html( $template['name'] ); ?>
	</h3>

	<?php if ( ! empty( $badge_text ) ) { ?>
		<span class="wpforms-template-badge"><?php echo esc_html( $badge_text ); ?></span>
	<?php } ?>

	<?php if ( ! empty( $template['description'] ) ) : ?>
		<p class="wpforms-template-desc"><?php echo esc_html( $template['description'] ); ?></p>
	<?php endif; ?>

	<div class="wpforms-template-buttons">
		<a href="#" class="wpforms-template-select wpforms-btn wpforms-btn-md wpforms-btn-orange<?php echo esc_attr( $education_class ); ?>"
			data-template-name-raw="<?php echo esc_attr( $template['name'] ); ?>"
			data-template="<?php echo esc_attr( $template_id ); ?>"
			data-slug="<?php echo esc_attr( $template['slug'] ); ?>"
			<?php echo $education_attributes; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			<?php echo $addons_attributes; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
			<?php
			$template['slug'] !== 'blank'
				? esc_html_e( 'Use Template', 'wpforms-lite' )
				: esc_html_e( 'Create Blank Form', 'wpforms-lite' );
			?>
		</a>
		<?php if ( $template['url'] !== '' ) : ?>
			<a class="wpforms-template-demo wpforms-btn wpforms-btn-md wpforms-btn-light-grey"
				href="<?php echo esc_url( $demo_url ); ?>"
				target="_blank" rel="noopener noreferrer">
				<?php esc_html_e( 'View Demo', 'wpforms-lite' ); ?>
			</a>
		<?php endif; ?>
	</div>

</div>
