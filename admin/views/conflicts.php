<?php
/**
 * Conflicts screen. Variables: $report.
 *
 * @package AnchorAutopilot
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$link = function ( $r ) {
	return '<a href="' . esc_url( admin_url( 'admin.php?page=aa-rules&action=edit&id=' . (int) $r->id ) ) . '"><strong>' . esc_html( $r->anchor ) . '</strong></a>';
};
$dest = function ( $r ) {
	return '<code>' . esc_html( preg_replace( '~^https?://(www\.)?~i', '', $r->target_url ) ) . '</code>';
};
$scope = function ( $r ) {
	return 'page' === $r->scope ? '<span class="aa-badge aa-badge-page">' . esc_html__( 'One page', 'anchor-autopilot' ) . '</span>' : '<span class="aa-badge aa-badge-kw">' . esc_html__( 'Whole site', 'anchor-autopilot' ) . '</span>';
};
$issues = count( $report['duplicates'] );
?>

<div class="aa-card">
	<h2><?php esc_html_e( 'Health check', 'anchor-autopilot' ); ?></h2>
	<p>
		<?php
		/* translators: %d: number of active rules */
		echo esc_html( sprintf( __( '%d active rules checked.', 'anchor-autopilot' ), (int) $report['total'] ) );
		?>
		<?php if ( $issues ) : ?>
			<span class="aa-badge aa-badge-err"><?php echo (int) $issues; ?> <?php esc_html_e( 'need attention', 'anchor-autopilot' ); ?></span>
		<?php else : ?>
			<span class="aa-badge aa-badge-on"><?php esc_html_e( 'No blocking conflicts', 'anchor-autopilot' ); ?></span>
		<?php endif; ?>
	</p>
</div>

<?php if ( $report['duplicates'] ) : ?>
<div class="aa-card">
	<h2><?php esc_html_e( 'Same anchor, different URLs', 'anchor-autopilot' ); ?> <span class="aa-badge aa-badge-err"><?php echo count( $report['duplicates'] ); ?></span></h2>
	<p class="description"><?php esc_html_e( 'One anchor can only point to one place on a page. Pause or delete the extra rules. Until then the oldest rule wins.', 'anchor-autopilot' ); ?></p>
	<table class="widefat striped aa-table">
		<thead><tr><th><?php esc_html_e( 'Anchor', 'anchor-autopilot' ); ?></th><th><?php esc_html_e( 'Points to', 'anchor-autopilot' ); ?></th><th><?php esc_html_e( 'Applies to', 'anchor-autopilot' ); ?></th></tr></thead>
		<tbody>
		<?php foreach ( $report['duplicates'] as $list ) : foreach ( $list as $r ) : ?>
			<tr><td><?php echo $link( $r ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></td><td><?php echo $dest( $r ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></td><td><?php echo $scope( $r ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></td></tr>
		<?php endforeach; endforeach; ?>
		</tbody>
	</table>
</div>
<?php endif; ?>

<div class="aa-card">
	<h2><?php esc_html_e( 'Overlapping phrases', 'anchor-autopilot' ); ?> <span class="aa-badge aa-badge-warn"><?php echo count( $report['overlaps'] ); ?></span></h2>
	<p class="description"><?php esc_html_e( 'Handled automatically: where both phrases appear together, the longer phrase gets the link and the shorter one is left alone. The shorter phrase still links where it appears on its own. Nothing to fix unless you want a different result.', 'anchor-autopilot' ); ?></p>
	<?php if ( $report['overlaps'] ) : ?>
		<table class="widefat striped aa-table">
			<thead><tr><th><?php esc_html_e( 'Longer phrase (wins)', 'anchor-autopilot' ); ?></th><th><?php esc_html_e( 'Shorter phrase inside it', 'anchor-autopilot' ); ?></th></tr></thead>
			<tbody>
			<?php foreach ( $report['overlaps'] as $pair ) : ?>
				<tr>
					<td><?php echo $link( $pair[0] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> → <?php echo $dest( $pair[0] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></td>
					<td><?php echo $link( $pair[1] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> → <?php echo $dest( $pair[1] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
		<?php if ( count( $report['overlaps'] ) >= 500 ) : ?>
			<p class="description"><?php esc_html_e( 'Showing the first 500.', 'anchor-autopilot' ); ?></p>
		<?php endif; ?>
	<?php else : ?>
		<p><?php esc_html_e( 'None found.', 'anchor-autopilot' ); ?></p>
	<?php endif; ?>
</div>

<?php if ( $report['overrides'] ) : ?>
<div class="aa-card">
	<h2><?php esc_html_e( 'Page-specific rules that replace a keyword rule', 'anchor-autopilot' ); ?> <span class="aa-badge aa-badge-warn"><?php echo count( $report['overrides'] ); ?></span></h2>
	<p class="description"><?php esc_html_e( 'On the From page, the page-specific destination is used and the whole-site rule for the same anchor is skipped on that page.', 'anchor-autopilot' ); ?></p>
	<table class="widefat striped aa-table">
		<thead><tr><th><?php esc_html_e( 'Anchor', 'anchor-autopilot' ); ?></th><th><?php esc_html_e( 'Page rule goes to', 'anchor-autopilot' ); ?></th><th><?php esc_html_e( 'Keyword rule goes to', 'anchor-autopilot' ); ?></th></tr></thead>
		<tbody>
		<?php foreach ( array_slice( $report['overrides'], 0, 200 ) as $pair ) : ?>
			<tr><td><?php echo $link( $pair[0] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></td><td><?php echo $dest( $pair[0] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></td><td><?php echo $dest( $pair[1] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></td></tr>
		<?php endforeach; ?>
		</tbody>
	</table>
</div>
<?php endif; ?>

<div class="aa-card">
	<h2><?php esc_html_e( 'Several anchors for one URL', 'anchor-autopilot' ); ?> <span class="aa-badge aa-badge-kw"><?php echo count( $report['multi'] ); ?></span></h2>
	<p class="description"><?php esc_html_e( 'Fine to have. A page still gets only one link to the same URL (see Settings), using the longest matching anchor.', 'anchor-autopilot' ); ?></p>
	<?php if ( $report['multi'] ) : ?>
		<table class="widefat striped aa-table">
			<thead><tr><th><?php esc_html_e( 'Destination', 'anchor-autopilot' ); ?></th><th><?php esc_html_e( 'Anchors', 'anchor-autopilot' ); ?></th></tr></thead>
			<tbody>
			<?php foreach ( array_slice( $report['multi'], 0, 25, true ) as $list ) : ?>
				<tr>
					<td><?php echo $dest( $list[0] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></td>
					<td><?php echo esc_html( implode( ' · ', array_map( function ( $r ) { return $r->anchor; }, array_slice( $list, 0, 12 ) ) ) ); ?><?php echo count( $list ) > 12 ? ' …' : ''; ?></td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
	<?php else : ?>
		<p><?php esc_html_e( 'None.', 'anchor-autopilot' ); ?></p>
	<?php endif; ?>
</div>

<div class="aa-card">
	<h2><?php esc_html_e( 'One-word anchors', 'anchor-autopilot' ); ?> <span class="aa-badge aa-badge-kw"><?php echo count( $report['single'] ); ?></span></h2>
	<p class="description"><?php esc_html_e( 'Single words match very often and can look over-optimised. They are allowed, but two or three words usually read better and are safer.', 'anchor-autopilot' ); ?></p>
	<?php if ( $report['single'] ) : ?>
		<p><?php echo esc_html( implode( ' · ', array_map( function ( $r ) { return $r->anchor; }, array_slice( $report['single'], 0, 60 ) ) ) ); ?><?php echo count( $report['single'] ) > 60 ? ' …' : ''; ?></p>
	<?php endif; ?>
</div>
