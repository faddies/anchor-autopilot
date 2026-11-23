<?php
/**
 * Test screen. Variables: $in (form values), $out (result|null).
 *
 * @package AnchorAutopilot
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>

<div class="aa-card">
	<h2><?php esc_html_e( 'Try it before it goes live', 'anchor-autopilot' ); ?></h2>
	<p class="description"><?php esc_html_e( 'Pick a page or paste HTML. You will see exactly which links would be added, and why other rules were skipped. Nothing is saved.', 'anchor-autopilot' ); ?></p>
	<form method="post" action="<?php echo esc_url( admin_url( 'admin.php?page=aa-test' ) ); ?>" class="aa-test-form">
		<?php wp_nonce_field( 'aa_test' ); ?>
		<input type="hidden" name="aa_test_run" value="1">
		<p>
			<label><input type="radio" name="source" value="post" <?php checked( 'post', $in['source'] ); ?>> <?php esc_html_e( 'A page or post on this site', 'anchor-autopilot' ); ?></label>
			&nbsp;
			<label><input type="radio" name="source" value="html" <?php checked( 'html', $in['source'] ); ?>> <?php esc_html_e( 'Pasted HTML', 'anchor-autopilot' ); ?></label>
		</p>
		<p class="aa-src-post">
			<input type="text" name="post_ref" class="regular-text" value="<?php echo esc_attr( $in['post_ref'] ); ?>" placeholder="<?php esc_attr_e( 'Post ID or full URL', 'anchor-autopilot' ); ?>">
		</p>
		<div class="aa-src-html" style="display:none">
			<p><textarea name="html" rows="8" class="large-text code" placeholder="&lt;p&gt;Paste page content here&lt;/p&gt;"><?php echo esc_textarea( $in['html'] ); ?></textarea></p>
			<p><label><?php esc_html_e( 'Pretend this is the page:', 'anchor-autopilot' ); ?> <input type="text" name="page_url" class="regular-text" value="<?php echo esc_attr( $in['page_url'] ); ?>"></label></p>
		</div>
		<p><button type="submit" class="button button-primary"><?php esc_html_e( 'Run test', 'anchor-autopilot' ); ?></button></p>
	</form>
</div>

<?php if ( $out ) : ?>
	<?php if ( $out['error'] ) : ?>
		<div class="notice notice-error inline"><p><?php echo esc_html( $out['error'] ); ?></p></div>
	<?php else : ?>
		<?php if ( $out['warning'] ) : ?>
			<div class="notice notice-warning inline"><p><?php echo esc_html( $out['warning'] ); ?></p></div>
		<?php endif; ?>
		<div class="aa-card">
			<h2>
				<?php echo $out['title'] ? esc_html( $out['title'] ) : esc_html__( 'Result', 'anchor-autopilot' ); ?>
				<span class="aa-badge aa-badge-on"><?php echo count( $out['report']['links'] ); ?> <?php esc_html_e( 'links added', 'anchor-autopilot' ); ?></span>
				<span class="aa-badge aa-badge-kw"><?php echo (int) $out['rules']; ?> <?php esc_html_e( 'rules apply to this page', 'anchor-autopilot' ); ?></span>
			</h2>
			<div class="aa-preview"><?php echo wp_kses_post( $out['html'] ); ?></div>
			<p class="description"><?php esc_html_e( 'Highlighted text is where a link would be added. Menus, headers, footers, headings, buttons, image alt text and hidden content are never changed.', 'anchor-autopilot' ); ?></p>
		</div>

		<div class="aa-two">
			<div class="aa-card">
				<h2><?php esc_html_e( 'Links added', 'anchor-autopilot' ); ?></h2>
				<?php if ( $out['report']['links'] ) : ?>
					<table class="widefat striped aa-table">
						<thead><tr><th><?php esc_html_e( 'Anchor', 'anchor-autopilot' ); ?></th><th><?php esc_html_e( 'Links to', 'anchor-autopilot' ); ?></th><th><?php esc_html_e( 'Rule', 'anchor-autopilot' ); ?></th></tr></thead>
						<tbody>
						<?php foreach ( $out['report']['links'] as $l ) : ?>
							<tr>
								<td><strong><?php echo esc_html( $l['anchor'] ); ?></strong></td>
								<td><code><?php echo esc_html( preg_replace( '~^https?://(www\.)?~i', '', $l['url'] ) ); ?></code></td>
								<td><a href="<?php echo esc_url( admin_url( 'admin.php?page=aa-rules&action=edit&id=' . (int) $l['id'] ) ); ?>">#<?php echo (int) $l['id']; ?></a> <?php echo 'page' === $l['scope'] ? '<span class="aa-badge aa-badge-page">' . esc_html__( 'One page', 'anchor-autopilot' ) . '</span>' : ''; ?></td>
							</tr>
						<?php endforeach; ?>
						</tbody>
					</table>
				<?php else : ?>
					<p><?php esc_html_e( 'No links would be added to this content.', 'anchor-autopilot' ); ?></p>
				<?php endif; ?>
			</div>
			<div class="aa-card">
				<h2><?php esc_html_e( 'Matched but not linked', 'anchor-autopilot' ); ?></h2>
				<?php if ( $out['report']['skipped'] ) : ?>
					<table class="widefat striped aa-table">
						<thead><tr><th><?php esc_html_e( 'Anchor', 'anchor-autopilot' ); ?></th><th><?php esc_html_e( 'Why', 'anchor-autopilot' ); ?></th></tr></thead>
						<tbody>
						<?php foreach ( $out['report']['skipped'] as $s ) : ?>
							<tr><td><strong><?php echo esc_html( $s['anchor'] ); ?></strong></td><td><?php echo esc_html( AA_Admin::reason_label( $s['reason'], $s['detail'] ) ); ?></td></tr>
						<?php endforeach; ?>
						</tbody>
					</table>
				<?php else : ?>
					<p><?php esc_html_e( 'Nothing was skipped.', 'anchor-autopilot' ); ?></p>
				<?php endif; ?>
			</div>
		</div>
	<?php endif; ?>
<?php endif; ?>
