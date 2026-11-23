<?php
/**
 * Import / export screen. Variables: $result (array|false).
 *
 * @package AnchorAutopilot
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$sample = function ( $type ) {
	return wp_nonce_url( admin_url( 'admin-post.php?action=aa_sample&type=' . $type ), 'aa_sample' );
};
$export = function ( $type ) {
	return wp_nonce_url( admin_url( 'admin-post.php?action=aa_export&type=' . $type ), 'aa_export' );
};
?>

<?php if ( $result ) : ?>
	<div class="aa-card aa-result">
		<h2><?php echo 'page' === $result['type'] ? esc_html__( 'Page-specific import finished', 'anchor-autopilot' ) : esc_html__( 'Keyword import finished', 'anchor-autopilot' ); ?></h2>
		<p class="aa-counts">
			<span class="aa-badge aa-badge-on"><?php echo (int) $result['inserted']; ?> <?php esc_html_e( 'added', 'anchor-autopilot' ); ?></span>
			<span class="aa-badge aa-badge-kw"><?php echo (int) $result['updated']; ?> <?php esc_html_e( 'updated', 'anchor-autopilot' ); ?></span>
			<span class="aa-badge aa-badge-off"><?php echo (int) $result['skipped']; ?> <?php esc_html_e( 'skipped', 'anchor-autopilot' ); ?></span>
			<span class="aa-badge aa-badge-err"><?php echo (int) $result['errors']; ?> <?php esc_html_e( 'rejected', 'anchor-autopilot' ); ?></span>
			<?php if ( $result['warnings'] ) : ?>
				<span class="aa-badge aa-badge-warn"><?php echo (int) $result['warnings']; ?> <?php esc_html_e( 'warnings', 'anchor-autopilot' ); ?></span>
			<?php endif; ?>
		</p>
		<?php if ( $result['problems'] ) : ?>
			<table class="widefat striped aa-table">
				<thead><tr><th><?php esc_html_e( 'Row', 'anchor-autopilot' ); ?></th><th><?php esc_html_e( 'Anchor', 'anchor-autopilot' ); ?></th><th><?php esc_html_e( 'What happened', 'anchor-autopilot' ); ?></th></tr></thead>
				<tbody>
				<?php foreach ( $result['problems'] as $p ) : ?>
					<tr>
						<td><?php echo $p[0] ? (int) $p[0] : '–'; ?></td>
						<td><?php echo esc_html( $p[1] ); ?></td>
						<td><span class="aa-dot aa-dot-<?php echo esc_attr( $p[3] ); ?>"></span> <?php echo esc_html( $p[2] ); ?></td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
			<?php if ( count( $result['problems'] ) >= 300 ) : ?>
				<p class="description"><?php esc_html_e( 'Showing the first 300 messages.', 'anchor-autopilot' ); ?></p>
			<?php endif; ?>
		<?php endif; ?>
	</div>
<?php endif; ?>

<div class="aa-two">
	<?php foreach ( array( 'keyword', 'page' ) as $type ) : ?>
		<div class="aa-card">
			<h2><?php echo 'keyword' === $type ? esc_html__( 'Import keyword rules', 'anchor-autopilot' ) : esc_html__( 'Import page-specific rules', 'anchor-autopilot' ); ?></h2>
			<?php if ( 'keyword' === $type ) : ?>
				<p><?php esc_html_e( 'Link a phrase to a URL on every page where it appears.', 'anchor-autopilot' ); ?></p>
				<p>Columns: <code>anchor</code>, <code>url</code> <span class="description"><?php esc_html_e( '(optional: case_sensitive, max, nofollow, new_tab, status)', 'anchor-autopilot' ); ?></span></p>
			<?php else : ?>
				<p><?php esc_html_e( 'Link a phrase only on one specific page.', 'anchor-autopilot' ); ?></p>
				<p>Columns: <code>from</code>, <code>to</code>, <code>anchor</code> <span class="description"><?php esc_html_e( '(optional: case_sensitive, max, nofollow, new_tab, status)', 'anchor-autopilot' ); ?></span></p>
			<?php endif; ?>
			<form method="post" enctype="multipart/form-data" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="aa-import-form">
				<input type="hidden" name="action" value="aa_import">
				<input type="hidden" name="type" value="<?php echo esc_attr( $type ); ?>">
				<?php wp_nonce_field( 'aa_import' ); ?>
				<p><input type="file" name="csv" accept=".csv,.txt,.tsv"></p>
				<p><label><?php esc_html_e( 'Or paste rows', 'anchor-autopilot' ); ?><br>
					<textarea name="paste" rows="4" class="large-text code" placeholder="<?php echo 'keyword' === $type ? esc_attr( "SEO services,https://example.com/services/seo/\nweb design Dubai,https://example.com/web-design/" ) : esc_attr( "https://example.com/blog/post/,https://example.com/services/seo/,SEO services" ); ?>"></textarea></label></p>
				<p>
					<label><?php esc_html_e( 'If the anchor already exists:', 'anchor-autopilot' ); ?>
						<select name="dup">
							<option value="update"><?php esc_html_e( 'Update it', 'anchor-autopilot' ); ?></option>
							<option value="skip"><?php esc_html_e( 'Keep the old one (skip)', 'anchor-autopilot' ); ?></option>
						</select>
					</label>
				</p>
				<p><label class="aa-danger"><input type="checkbox" name="replace" value="1" class="aa-replace"> <?php esc_html_e( 'Delete all existing rules of this kind first', 'anchor-autopilot' ); ?></label></p>
				<p>
					<button type="submit" class="button button-primary"><?php esc_html_e( 'Import', 'anchor-autopilot' ); ?></button>
					<a href="<?php echo esc_url( $sample( $type ) ); ?>"><?php esc_html_e( 'Download sample CSV', 'anchor-autopilot' ); ?></a>
				</p>
			</form>
		</div>
	<?php endforeach; ?>
</div>

<div class="aa-card">
	<h2><?php esc_html_e( 'Export', 'anchor-autopilot' ); ?></h2>
	<p>
		<a class="button" href="<?php echo esc_url( $export( 'keyword' ) ); ?>"><?php esc_html_e( 'Export keyword rules (CSV)', 'anchor-autopilot' ); ?></a>
		<a class="button" href="<?php echo esc_url( $export( 'page' ) ); ?>"><?php esc_html_e( 'Export page-specific rules (CSV)', 'anchor-autopilot' ); ?></a>
	</p>
	<p class="description"><?php esc_html_e( 'Tips: headers are detected automatically; commas, semicolons and tabs all work; use UTF-8 (Arabic and accented text are fine). Relative URLs such as /services/seo/ are accepted. Destination URLs must be on this site.', 'anchor-autopilot' ); ?></p>
</div>
