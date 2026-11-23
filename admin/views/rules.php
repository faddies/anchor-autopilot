<?php
/**
 * Rules screen. Variables: $stats, $edit (row|null), $form (array|null), $table.
 *
 * @package AnchorAutopilot
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$f = array(
	'rule_id' => 0, 'scope' => 'keyword', 'anchor' => '', 'url' => '', 'from' => '', 'case_sensitive' => '0',
	'max' => 0, 'nofollow' => '', 'new_tab' => '', 'status' => 'active',
);
if ( $edit ) {
	$tri = function ( $v ) {
		$v = (int) $v;
		return -1 === $v ? '' : (string) $v;
	};
	$f = array(
		'rule_id' => (int) $edit->id, 'scope' => $edit->scope, 'anchor' => $edit->anchor, 'url' => $edit->target_url,
		'from' => (string) $edit->from_url, 'case_sensitive' => (string) (int) $edit->case_sensitive,
		'max' => (int) $edit->max_per_page, 'nofollow' => $tri( $edit->nofollow ), 'new_tab' => $tri( $edit->new_tab ), 'status' => $edit->status,
	);
} elseif ( $form ) {
	$f = array_merge( $f, $form );
}
$is_page = ( 'page' === $f['scope'] );
$sel = function ( $cur, $val ) {
	return selected( (string) $cur, (string) $val, false );
};
?>

<div class="aa-tiles">
	<div class="aa-tile"><span class="aa-tile-n"><?php echo (int) $stats['total']; ?></span><span class="aa-tile-l"><?php esc_html_e( 'Rules', 'anchor-autopilot' ); ?></span></div>
	<div class="aa-tile"><span class="aa-tile-n"><?php echo (int) $stats['keyword']; ?></span><span class="aa-tile-l"><?php esc_html_e( 'Whole-site keywords', 'anchor-autopilot' ); ?></span></div>
	<div class="aa-tile"><span class="aa-tile-n"><?php echo (int) $stats['page']; ?></span><span class="aa-tile-l"><?php esc_html_e( 'Page-specific', 'anchor-autopilot' ); ?></span></div>
	<div class="aa-tile"><span class="aa-tile-n"><?php echo (int) $stats['active']; ?></span><span class="aa-tile-l"><?php esc_html_e( 'Active', 'anchor-autopilot' ); ?></span></div>
</div>

<div class="aa-card" id="aa-form-card">
	<h2><?php echo $edit ? esc_html__( 'Edit rule', 'anchor-autopilot' ) : esc_html__( 'Add a rule', 'anchor-autopilot' ); ?></h2>
	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="aa-form">
		<input type="hidden" name="action" value="aa_save_rule">
		<input type="hidden" name="rule_id" value="<?php echo (int) $f['rule_id']; ?>">
		<?php wp_nonce_field( 'aa_save_rule' ); ?>

		<div class="aa-scope">
			<label><input type="radio" name="scope" value="keyword" <?php checked( ! $is_page ); ?>> <strong><?php esc_html_e( 'Whole site', 'anchor-autopilot' ); ?></strong> <span class="description"><?php esc_html_e( 'Link this phrase on any page where it appears.', 'anchor-autopilot' ); ?></span></label>
			<label><input type="radio" name="scope" value="page" <?php checked( $is_page ); ?>> <strong><?php esc_html_e( 'One page only', 'anchor-autopilot' ); ?></strong> <span class="description"><?php esc_html_e( 'Link this phrase only on the page you choose.', 'anchor-autopilot' ); ?></span></label>
		</div>

		<div class="aa-grid">
			<p class="aa-from" <?php echo $is_page ? '' : 'style="display:none"'; ?>>
				<label for="aa-from"><strong><?php esc_html_e( 'From page (where the link goes)', 'anchor-autopilot' ); ?></strong></label>
				<input type="text" id="aa-from" name="from" class="large-text" value="<?php echo esc_attr( $f['from'] ); ?>" placeholder="https://example.com/blog/my-post/">
			</p>
			<p>
				<label for="aa-anchor"><strong><?php esc_html_e( 'Anchor text', 'anchor-autopilot' ); ?></strong></label>
				<input type="text" id="aa-anchor" name="anchor" class="large-text" value="<?php echo esc_attr( $f['anchor'] ); ?>" placeholder="<?php esc_attr_e( 'e.g. web design Dubai', 'anchor-autopilot' ); ?>" required>
			</p>
			<p>
				<label for="aa-url"><strong><?php esc_html_e( 'Link to (destination URL)', 'anchor-autopilot' ); ?></strong></label>
				<input type="text" id="aa-url" name="url" class="large-text" value="<?php echo esc_attr( $f['url'] ); ?>" placeholder="https://example.com/services/web-design/" required>
			</p>
		</div>

		<details class="aa-more" <?php echo ( $edit || $form ) ? 'open' : ''; ?>>
			<summary><?php esc_html_e( 'More options', 'anchor-autopilot' ); ?></summary>
			<div class="aa-opts">
				<label><input type="checkbox" name="case_sensitive" value="1" <?php checked( '1', (string) $f['case_sensitive'] ); ?>> <?php esc_html_e( 'Case-sensitive', 'anchor-autopilot' ); ?></label>
				<label><?php esc_html_e( 'Max links per page', 'anchor-autopilot' ); ?>
					<select name="max">
						<option value="0" <?php echo $sel( $f['max'], 0 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>><?php esc_html_e( 'Default (Settings)', 'anchor-autopilot' ); ?></option>
						<?php for ( $i = 1; $i <= 5; $i++ ) : ?>
							<option value="<?php echo (int) $i; ?>" <?php echo $sel( $f['max'], $i ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>><?php echo (int) $i; ?></option>
						<?php endfor; ?>
					</select>
				</label>
				<label><?php esc_html_e( 'Nofollow', 'anchor-autopilot' ); ?>
					<select name="nofollow">
						<option value="" <?php echo $sel( $f['nofollow'], '' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>><?php esc_html_e( 'Default', 'anchor-autopilot' ); ?></option>
						<option value="1" <?php echo $sel( $f['nofollow'], '1' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>><?php esc_html_e( 'Yes', 'anchor-autopilot' ); ?></option>
						<option value="0" <?php echo $sel( $f['nofollow'], '0' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>><?php esc_html_e( 'No', 'anchor-autopilot' ); ?></option>
					</select>
				</label>
				<label><?php esc_html_e( 'Open in new tab', 'anchor-autopilot' ); ?>
					<select name="new_tab">
						<option value="" <?php echo $sel( $f['new_tab'], '' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>><?php esc_html_e( 'Default', 'anchor-autopilot' ); ?></option>
						<option value="1" <?php echo $sel( $f['new_tab'], '1' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>><?php esc_html_e( 'Yes', 'anchor-autopilot' ); ?></option>
						<option value="0" <?php echo $sel( $f['new_tab'], '0' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>><?php esc_html_e( 'No', 'anchor-autopilot' ); ?></option>
					</select>
				</label>
				<label><?php esc_html_e( 'Status', 'anchor-autopilot' ); ?>
					<select name="status">
						<option value="active" <?php echo $sel( $f['status'], 'active' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>><?php esc_html_e( 'Active', 'anchor-autopilot' ); ?></option>
						<option value="paused" <?php echo $sel( $f['status'], 'paused' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>><?php esc_html_e( 'Paused', 'anchor-autopilot' ); ?></option>
					</select>
				</label>
			</div>
		</details>

		<p class="aa-actions">
			<button type="submit" class="button button-primary"><?php echo $edit ? esc_html__( 'Save rule', 'anchor-autopilot' ) : esc_html__( 'Add rule', 'anchor-autopilot' ); ?></button>
			<?php if ( $edit ) : ?>
				<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=aa-rules' ) ); ?>"><?php esc_html_e( 'Cancel', 'anchor-autopilot' ); ?></a>
			<?php endif; ?>
			<span class="description"><?php esc_html_e( 'Have a list? Use Import / Export for CSV upload.', 'anchor-autopilot' ); ?></span>
		</p>
	</form>
</div>

<form method="get" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>" class="aa-search">
	<input type="hidden" name="page" value="aa-rules">
	<?php if ( ! empty( $_GET['view'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
		<input type="hidden" name="view" value="<?php echo esc_attr( sanitize_key( $_GET['view'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>">
	<?php endif; ?>
	<?php $table->views(); ?>
	<?php $table->search_box( __( 'Search rules', 'anchor-autopilot' ), 'aa-rule' ); ?>
</form>

<form method="post" action="<?php echo esc_url( admin_url( 'admin.php?page=aa-rules' ) ); ?>">
	<input type="hidden" name="aa_bulk" value="1">
	<?php wp_nonce_field( 'aa_bulk', 'aa_bulk_nonce' ); ?>
	<?php $table->display(); ?>
</form>

<p class="description aa-foot"><?php esc_html_e( 'If a caching plugin is active, clear its cache after changing rules so visitors see the new links.', 'anchor-autopilot' ); ?></p>
