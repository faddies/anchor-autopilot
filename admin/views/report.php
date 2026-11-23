<?php
/**
 * Link Report screen. Variable: $v (from AA_Report::view_data()).
 *
 * @package AnchorAutopilot
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$base   = admin_url( 'admin.php?page=aa-report' );
$scan   = $v['scan'];
$short  = function ( $u ) {
	return preg_replace( '~^https?://(www\.)?~i', '', (string) $u );
};
$page_link = function ( $post_id, $title = '' ) {
	$url = get_permalink( (int) $post_id );
	$t   = '' !== (string) $title ? $title : get_the_title( (int) $post_id );
	if ( '' === (string) $t ) {
		/* translators: %d: post ID */
		$t = sprintf( __( '(post %d)', 'anchor-autopilot' ), (int) $post_id );
	}
	return $url ? '<a href="' . esc_url( $url ) . '" target="_blank" rel="noopener">' . esc_html( $t ) . '</a>' : esc_html( $t );
};
$detail_url = function ( $args ) use ( $base ) {
	return esc_url( add_query_arg( $args, $base ) );
};
$scanned = ( 'done' === $scan['state'] );
$pager   = function ( $total, $per, $paged, $args ) use ( $base ) {
	$pages = (int) ceil( $total / $per );
	if ( $pages < 2 ) {
		return;
	}
	echo '<div class="tablenav"><div class="tablenav-pages">';
	echo '<span class="displaying-num">' . esc_html( sprintf( /* translators: %d: items */ _n( '%d item', '%d items', $total, 'anchor-autopilot' ), $total ) ) . '</span> ';
	echo wp_kses_post( paginate_links( array( 'base' => add_query_arg( array_merge( $args, array( 'paged' => '%#%' ) ), $base ), 'format' => '', 'current' => $paged, 'total' => $pages, 'type' => 'plain' ) ) );
	echo '</div></div>';
};
?>

<div class="aa-card aa-scanbox" id="aa-scanbox">
	<h2><?php esc_html_e( 'Where are my links?', 'anchor-autopilot' ); ?></h2>
	<p class="description">
		<?php esc_html_e( 'Links are added when a page is displayed, so nothing is stored on the pages themselves. A scan runs every published page through the same engine as your site and records what was linked, what was skipped and why. Your content is not changed.', 'anchor-autopilot' ); ?>
	</p>
	<p class="aa-actions">
		<button type="button" class="button button-primary" id="aa-scan-btn"><?php echo $scanned ? esc_html__( 'Scan again', 'anchor-autopilot' ) : esc_html__( 'Scan site now', 'anchor-autopilot' ); ?></button>
		<span id="aa-scan-status" class="description">
			<?php
			if ( $scanned ) {
				/* translators: 1: pages scanned, 2: date/time */
				echo esc_html( sprintf( __( 'Last scan: %1$d pages, %2$s.', 'anchor-autopilot' ), (int) $scan['done'], wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), (int) $scan['finished'] ) ) );
			} elseif ( 'running' === $scan['state'] ) {
				esc_html_e( 'A scan did not finish. Run it again.', 'anchor-autopilot' );
			} else {
				esc_html_e( 'Not scanned yet.', 'anchor-autopilot' );
			}
			?>
		</span>
		<?php if ( $scanned ) : ?>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline">
				<input type="hidden" name="action" value="aa_export_report">
				<?php wp_nonce_field( 'aa_export_report' ); ?>
				<button type="submit" class="button"><?php esc_html_e( 'Download report (CSV)', 'anchor-autopilot' ); ?></button>
			</form>
		<?php endif; ?>
	</p>
	<div class="aa-progress" id="aa-progress" hidden><div class="aa-progress-bar" id="aa-progress-bar"></div></div>
	<?php if ( $scanned && ! empty( $scan['stale'] ) ) : ?>
		<div class="notice notice-warning inline"><p><?php esc_html_e( 'Rules or settings changed since the last scan. Scan again to refresh these numbers.', 'anchor-autopilot' ); ?></p></div>
	<?php endif; ?>
	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="aa-check">
		<input type="hidden" name="action" value="aa_check_page">
		<?php wp_nonce_field( 'aa_check_page' ); ?>
		<label for="aa-page-ref"><strong><?php esc_html_e( 'Check one page:', 'anchor-autopilot' ); ?></strong></label>
		<input type="text" id="aa-page-ref" name="page_ref" class="regular-text" placeholder="https://example.com/my-page/">
		<button type="submit" class="button"><?php esc_html_e( 'Check this page', 'anchor-autopilot' ); ?></button>
		<span class="description"><?php esc_html_e( 'Shows every rule that linked or was skipped on that page, and why. Takes a second.', 'anchor-autopilot' ); ?></span>
	</form>
</div>

<?php if ( ! empty( $v['problem_n'] ) && 0 === $v['rule'] && 0 === $v['post'] ) : ?>
	<div class="notice notice-warning inline">
		<p><strong><?php echo esc_html( sprintf( /* translators: %d: pages */ _n( '%d page could not be scanned.', '%d pages could not be scanned.', (int) $v['problem_n'], 'anchor-autopilot' ), (int) $v['problem_n'] ) ); ?></strong> <?php esc_html_e( 'Their numbers are missing from this report. Use "Check this page" on them.', 'anchor-autopilot' ); ?></p>
		<details><summary><?php esc_html_e( 'Show pages', 'anchor-autopilot' ); ?></summary><ul>
		<?php foreach ( $v['problems'] as $pr ) : ?>
			<li><?php echo $page_link( $pr->post_id ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> &ndash; <?php echo esc_html( $pr->detail ); ?></li>
		<?php endforeach; ?>
		</ul></details>
	</div>
<?php endif; ?>

<?php if ( $v['rule'] > 0 ) : /* ---------------- one rule ---------------- */ ?>
	<?php
	$r = isset( $v['rule_row'] ) ? $v['rule_row'] : null;
	if ( ! $r ) :
		?>
		<div class="aa-card"><p><?php esc_html_e( 'That rule no longer exists.', 'anchor-autopilot' ); ?></p><p><a href="<?php echo esc_url( $base ); ?>">&larr; <?php esc_html_e( 'Back to the report', 'anchor-autopilot' ); ?></a></p></div>
	<?php else : ?>
		<?php
		$groups = array( 'linked' => array(), 'already' => array(), 'skipped' => array(), 'protected' => array() );
		foreach ( $v['rule_log'] as $row ) {
			if ( 'skipped' === $row->status && AA_Report::is_already( $row->reason, $row->detail ) ) {
				$groups['already'][] = $row;
			} elseif ( isset( $groups[ $row->status ] ) ) {
				$groups[ $row->status ][] = $row;
			}
		}
		$total_links = 0;
		foreach ( $groups['linked'] as $row ) {
			$total_links += (int) $row->cnt;
		}
		?>
		<p><a href="<?php echo esc_url( $base ); ?>">&larr; <?php esc_html_e( 'Back to the report', 'anchor-autopilot' ); ?></a></p>
		<div class="aa-card">
			<h2>
				<?php echo esc_html( $r->anchor ); ?> <span class="description">&rarr; <code><?php echo esc_html( $short( $r->target_url ) ); ?></code></span>
				<?php echo 'page' === $r->scope ? '<span class="aa-badge aa-badge-page">' . esc_html__( 'One page', 'anchor-autopilot' ) . '</span>' : '<span class="aa-badge aa-badge-kw">' . esc_html__( 'Whole site', 'anchor-autopilot' ) . '</span>'; ?>
			</h2>
			<p>
				<?php
				/* translators: 1: number of links, 2: number of pages */
				echo esc_html( sprintf( __( 'Linked %1$d times on %2$d pages.', 'anchor-autopilot' ), $total_links, count( $groups['linked'] ) ) );
				if ( $groups['already'] ) {
					/* translators: %d: number of pages */
					echo ' ' . esc_html( sprintf( _n( 'Already linked on %d more page.', 'Already linked on %d more pages.', count( $groups['already'] ), 'anchor-autopilot' ), count( $groups['already'] ) ) );
				}
				?>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=aa-rules&action=edit&id=' . (int) $r->id ) ); ?>"><?php esc_html_e( 'Edit rule', 'anchor-autopilot' ); ?></a>
			</p>
		</div>

		<div class="aa-card">
			<h2><?php esc_html_e( 'Pages where it is linked', 'anchor-autopilot' ); ?> <span class="aa-badge aa-badge-on"><?php echo (int) count( $groups['linked'] ); ?></span></h2>
			<?php if ( $groups['linked'] ) : ?>
				<table class="widefat striped aa-table"><thead><tr><th><?php esc_html_e( 'Page', 'anchor-autopilot' ); ?></th><th><?php esc_html_e( 'Text linked', 'anchor-autopilot' ); ?></th><th><?php esc_html_e( 'Times', 'anchor-autopilot' ); ?></th></tr></thead><tbody>
				<?php foreach ( $groups['linked'] as $row ) : ?>
					<tr><td><?php echo $page_link( $row->post_id, $row->post_title ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> <a class="description" href="<?php echo $detail_url( array( 'post' => (int) $row->post_id ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>"><?php esc_html_e( 'all links on this page', 'anchor-autopilot' ); ?></a></td><td><?php echo esc_html( $row->anchor_shown ); ?></td><td><?php echo (int) $row->cnt; ?></td></tr>
				<?php endforeach; ?>
				</tbody></table>
			<?php else : ?>
				<p><?php echo $scanned ? esc_html__( 'Not linked on any page in the last scan.', 'anchor-autopilot' ) : esc_html__( 'Run a scan to see this.', 'anchor-autopilot' ); ?></p>
			<?php endif; ?>
		</div>

		<?php if ( $groups['already'] ) : ?>
		<div class="aa-card">
			<h2><?php esc_html_e( 'Already linked on these pages', 'anchor-autopilot' ); ?> <span class="aa-badge aa-badge-kw"><?php echo (int) count( $groups['already'] ); ?></span></h2>
			<p class="description"><?php esc_html_e( 'The link is already on the page (added by hand, by your theme or a builder), so nothing was added. This is why these pages are not counted as linked above.', 'anchor-autopilot' ); ?></p>
			<table class="widefat striped aa-table"><thead><tr><th><?php esc_html_e( 'Page', 'anchor-autopilot' ); ?></th><th><?php esc_html_e( 'Existing link', 'anchor-autopilot' ); ?></th></tr></thead><tbody>
			<?php foreach ( $groups['already'] as $row ) : ?>
				<tr><td><?php echo $page_link( $row->post_id, $row->post_title ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></td><td>
				<?php
				if ( 'in_link_same' === $row->reason ) {
					esc_html_e( 'The phrase is already a link to this same destination.', 'anchor-autopilot' );
				} elseif ( 'in_link' === $row->reason ) {
					/* translators: %s: URL */
					echo esc_html( sprintf( __( 'The phrase is already a link, pointing to %s.', 'anchor-autopilot' ), $row->detail ) );
				} else {
					esc_html_e( 'The page already links to this destination elsewhere in its text.', 'anchor-autopilot' );
				}
				?>
				</td></tr>
			<?php endforeach; ?>
			</tbody></table>
		</div>
		<?php endif; ?>

		<?php if ( $groups['skipped'] || $groups['protected'] ) : ?>
		<div class="aa-card">
			<h2><?php esc_html_e( 'Other pages where the phrase is present but was not linked', 'anchor-autopilot' ); ?> <span class="aa-badge"><?php echo (int) ( count( $groups['skipped'] ) + count( $groups['protected'] ) ); ?></span></h2>
			<table class="widefat striped aa-table"><thead><tr><th><?php esc_html_e( 'Page', 'anchor-autopilot' ); ?></th><th><?php esc_html_e( 'Why', 'anchor-autopilot' ); ?></th></tr></thead><tbody>
			<?php foreach ( array_merge( $groups['skipped'], $groups['protected'] ) as $row ) : ?>
				<tr><td><?php echo $page_link( $row->post_id, $row->post_title ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></td><td><?php echo esc_html( AA_Admin::reason_label( $row->reason, $row->detail ) ); ?></td></tr>
			<?php endforeach; ?>
			</tbody></table>
		</div>
		<?php endif; ?>
	<?php endif; ?>

<?php elseif ( $v['post'] > 0 ) : /* ---------------- one page ---------------- */ ?>
	<?php
	$groups = array( 'linked' => array(), 'already' => array(), 'skipped' => array(), 'protected' => array(), 'page_off' => array() );
	foreach ( $v['post_log'] as $row ) {
		if ( 'skipped' === $row->status && AA_Report::is_already( $row->reason, $row->detail ) ) {
			$groups['already'][] = $row;
		} elseif ( isset( $groups[ $row->status ] ) ) {
			$groups[ $row->status ][] = $row;
		}
	}
	?>
	<p><a href="<?php echo esc_url( add_query_arg( 'tab', 'page', $base ) ); ?>">&larr; <?php esc_html_e( 'Back to the report', 'anchor-autopilot' ); ?></a></p>
	<div class="aa-card">
		<h2><?php echo $v['post_obj'] ? $page_link( $v['post_obj']->ID ) : esc_html__( 'Page not found', 'anchor-autopilot' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></h2>
		<?php if ( $groups['page_off'] ) : ?>
			<p><span class="aa-badge aa-badge-err"><?php esc_html_e( 'Not processed', 'anchor-autopilot' ); ?></span> <?php echo esc_html( $groups['page_off'][0]->detail ); ?></p>
		<?php else : ?>
			<p>
				<?php
				/* translators: %d: number of links */
				echo esc_html( sprintf( _n( '%d automatic link on this page.', '%d automatic links on this page.', count( $groups['linked'] ), 'anchor-autopilot' ), count( $groups['linked'] ) ) );
				?>
			</p>
		<?php endif; ?>
	</div>
	<?php if ( ! empty( $v['probe'] ) ) : $pb = $v['probe']; ?>
	<div class="aa-card">
		<h2><?php esc_html_e( 'Live check (the real page as visitors get it)', 'anchor-autopilot' ); ?>
			<?php if ( 'ran' === $pb['state'] ) : ?><span class="aa-badge aa-badge-on"><?php esc_html_e( 'Plugin ran', 'anchor-autopilot' ); ?></span>
			<?php else : ?><span class="aa-badge aa-badge-err"><?php esc_html_e( 'Plugin did not link here', 'anchor-autopilot' ); ?></span><?php endif; ?>
		</h2>
		<?php if ( 'ran' === $pb['state'] ) : ?>
			<p>
			<?php
			/* translators: %d: number of links */
			echo esc_html( sprintf( _n( 'The plugin processed this page and added %d link.', 'The plugin processed this page and added %d links.', count( $pb['links'] ), 'anchor-autopilot' ), count( $pb['links'] ) ) );
			?>
			</p>
			<?php if ( $pb['links'] ) : ?>
				<ul>
				<?php foreach ( $pb['links'] as $l ) : ?>
					<li><strong><?php echo esc_html( $l['anchor'] ); ?></strong> &rarr; <code><?php echo esc_html( $short( $l['url'] ) ); ?></code></li>
				<?php endforeach; ?>
				</ul>
				<p class="description"><?php esc_html_e( 'If these do not show for visitors, clear your caching plugin and the builder cache.', 'anchor-autopilot' ); ?></p>
			<?php endif; ?>
		<?php else : ?>
			<p><?php echo esc_html( $pb['message'] ); ?></p>
		<?php endif; ?>
	</div>
	<?php endif; ?>
	<?php if ( ! $groups['page_off'] ) : ?>
	<div class="aa-card">
		<h2><?php esc_html_e( 'Links added', 'anchor-autopilot' ); ?></h2>
		<?php if ( $groups['linked'] ) : ?>
			<table class="widefat striped aa-table"><thead><tr><th><?php esc_html_e( 'Text linked', 'anchor-autopilot' ); ?></th><th><?php esc_html_e( 'Links to', 'anchor-autopilot' ); ?></th><th><?php esc_html_e( 'Rule', 'anchor-autopilot' ); ?></th><th><?php esc_html_e( 'Times', 'anchor-autopilot' ); ?></th></tr></thead><tbody>
			<?php foreach ( $groups['linked'] as $row ) : ?>
				<tr><td><strong><?php echo esc_html( $row->anchor_shown ); ?></strong></td><td><code><?php echo esc_html( $short( $row->target_url ) ); ?></code></td><td><a href="<?php echo $detail_url( array( 'rule' => (int) $row->rule_id ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>">#<?php echo (int) $row->rule_id; ?></a></td><td><?php echo (int) $row->cnt; ?></td></tr>
			<?php endforeach; ?>
			</tbody></table>
		<?php else : ?>
			<p><?php esc_html_e( 'No automatic links on this page.', 'anchor-autopilot' ); ?></p>
		<?php endif; ?>
	</div>
	<?php if ( $groups['already'] ) : ?>
	<div class="aa-card">
		<h2><?php esc_html_e( 'Already linked (not added again)', 'anchor-autopilot' ); ?> <span class="aa-badge aa-badge-kw"><?php echo (int) count( $groups['already'] ); ?></span></h2>
		<table class="widefat striped aa-table"><thead><tr><th><?php esc_html_e( 'Phrase', 'anchor-autopilot' ); ?></th><th><?php esc_html_e( 'Rule links to', 'anchor-autopilot' ); ?></th><th><?php esc_html_e( 'What is already on the page', 'anchor-autopilot' ); ?></th></tr></thead><tbody>
		<?php foreach ( $groups['already'] as $row ) : ?>
			<tr><td><strong><?php echo esc_html( $row->anchor_shown ); ?></strong></td><td><code><?php echo esc_html( $short( $row->target_url ) ); ?></code></td><td><?php echo esc_html( AA_Admin::reason_label( $row->reason, $row->detail ) ); ?></td></tr>
		<?php endforeach; ?>
		</tbody></table>
	</div>
	<?php endif; ?>
	<?php if ( $groups['skipped'] || $groups['protected'] ) : ?>
	<div class="aa-card">
		<h2><?php esc_html_e( 'Matched but not linked', 'anchor-autopilot' ); ?></h2>
		<table class="widefat striped aa-table"><thead><tr><th><?php esc_html_e( 'Phrase', 'anchor-autopilot' ); ?></th><th><?php esc_html_e( 'Links to', 'anchor-autopilot' ); ?></th><th><?php esc_html_e( 'Why', 'anchor-autopilot' ); ?></th></tr></thead><tbody>
		<?php foreach ( array_merge( $groups['skipped'], $groups['protected'] ) as $row ) : ?>
			<tr><td><strong><?php echo esc_html( $row->anchor_shown ); ?></strong></td><td><code><?php echo esc_html( $short( $row->target_url ) ); ?></code></td><td><?php echo esc_html( AA_Admin::reason_label( $row->reason, $row->detail ) ); ?></td></tr>
		<?php endforeach; ?>
		</tbody></table>
	</div>
	<?php endif; ?>
	<?php endif; ?>

<?php else : /* ---------------- lists ---------------- */ ?>

	<div class="aa-tiles">
		<div class="aa-tile"><span class="aa-tile-n"><?php echo (int) $v['tiles']['links']; ?></span><span class="aa-tile-l"><?php esc_html_e( 'automatic links', 'anchor-autopilot' ); ?></span></div>
		<div class="aa-tile"><span class="aa-tile-n"><?php echo (int) $v['tiles']['pages']; ?></span><span class="aa-tile-l"><?php esc_html_e( 'pages with links', 'anchor-autopilot' ); ?></span></div>
		<div class="aa-tile"><span class="aa-tile-n"><?php echo (int) $v['tiles']['active']; ?></span><span class="aa-tile-l"><?php esc_html_e( 'active rules', 'anchor-autopilot' ); ?></span></div>
		<div class="aa-tile"><span class="aa-tile-n"><?php echo (int) $v['tiles']['zero']; ?></span><span class="aa-tile-l"><?php esc_html_e( 'rules not linked anywhere', 'anchor-autopilot' ); ?></span></div>
	</div>

	<?php if ( ! $scanned ) : ?>
		<div class="notice notice-info inline"><p><?php esc_html_e( 'Run a scan to fill this report.', 'anchor-autopilot' ); ?></p></div>
	<?php endif; ?>

	<nav class="aa-subtabs">
		<a class="<?php echo ( 'rule' === $v['tab'] && '' === $v['filter'] ) ? 'current' : ''; ?>" href="<?php echo esc_url( $base ); ?>"><?php esc_html_e( 'By rule', 'anchor-autopilot' ); ?></a>
		<a class="<?php echo 'zero' === $v['filter'] ? 'current' : ''; ?>" href="<?php echo esc_url( add_query_arg( 'f', 'zero', $base ) ); ?>"><?php esc_html_e( 'Rules not linked anywhere', 'anchor-autopilot' ); ?></a>
		<a class="<?php echo 'page' === $v['tab'] ? 'current' : ''; ?>" href="<?php echo esc_url( add_query_arg( 'tab', 'page', $base ) ); ?>"><?php esc_html_e( 'By page', 'anchor-autopilot' ); ?></a>
	</nav>

	<?php if ( 'page' === $v['tab'] ) : ?>
		<div class="aa-card">
			<?php if ( $v['rows'] ) : ?>
				<table class="widefat striped aa-table"><thead><tr><th><?php esc_html_e( 'Page', 'anchor-autopilot' ); ?></th><th><?php esc_html_e( 'Automatic links', 'anchor-autopilot' ); ?></th><th></th></tr></thead><tbody>
				<?php foreach ( $v['rows'] as $row ) : ?>
					<tr><td><?php echo $page_link( $row->post_id, $row->post_title ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></td><td><?php echo (int) $row->links; ?></td><td><a href="<?php echo $detail_url( array( 'post' => (int) $row->post_id ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>"><?php esc_html_e( 'See details', 'anchor-autopilot' ); ?></a></td></tr>
				<?php endforeach; ?>
				</tbody></table>
				<?php $pager( $v['total'], $v['per'], $v['paged'], array( 'tab' => 'page' ) ); ?>
			<?php else : ?>
				<p><?php esc_html_e( 'No pages with automatic links yet.', 'anchor-autopilot' ); ?></p>
			<?php endif; ?>
		</div>
	<?php else : ?>
		<div class="aa-card">
			<?php if ( 'zero' !== $v['filter'] ) : ?>
				<form method="get" class="aa-search">
					<input type="hidden" name="page" value="aa-report">
					<input type="search" name="s" value="<?php echo esc_attr( $v['search'] ); ?>" placeholder="<?php esc_attr_e( 'Search anchor or URL', 'anchor-autopilot' ); ?>">
					<button class="button"><?php esc_html_e( 'Search', 'anchor-autopilot' ); ?></button>
				</form>
			<?php endif; ?>
			<?php if ( $v['rows'] ) : ?>
				<table class="widefat striped aa-table"><thead><tr>
					<th><?php esc_html_e( 'Rule', 'anchor-autopilot' ); ?></th>
					<th><?php esc_html_e( 'Links to', 'anchor-autopilot' ); ?></th>
					<th><?php esc_html_e( 'Pages linked', 'anchor-autopilot' ); ?></th>
					<th><?php esc_html_e( 'Total links', 'anchor-autopilot' ); ?></th>
					<th><?php esc_html_e( 'Already linked', 'anchor-autopilot' ); ?></th>
					<th><?php esc_html_e( 'Other skips', 'anchor-autopilot' ); ?></th>
					<th></th>
				</tr></thead><tbody>
				<?php foreach ( $v['rows'] as $row ) : ?>
					<?php $c = isset( $v['counts'][ (int) $row->id ] ) ? $v['counts'][ (int) $row->id ] : array( 'pages' => 0, 'links' => 0, 'skipped' => 0, 'prot' => 0, 'already' => 0 ); ?>
					<tr>
						<td><strong><?php echo esc_html( $row->anchor ); ?></strong> <?php echo 'page' === $row->scope ? '<span class="aa-badge aa-badge-page">' . esc_html__( 'One page', 'anchor-autopilot' ) . '</span>' : ''; ?><?php echo 'paused' === $row->status ? ' <span class="aa-badge aa-badge-off">' . esc_html__( 'Paused', 'anchor-autopilot' ) . '</span>' : ''; ?></td>
						<td><code><?php echo esc_html( $short( $row->target_url ) ); ?></code></td>
						<td><?php echo $scanned ? (int) $c['pages'] : '&mdash;'; ?></td>
						<td><?php echo $scanned ? (int) $c['links'] : '&mdash;'; ?></td>
						<td><?php echo $scanned ? ( $c['already'] ? '<span class="aa-badge aa-badge-kw">' . (int) $c['already'] . '</span>' : '0' ) : '&mdash;'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></td>
						<td><?php echo $scanned ? (int) ( $c['skipped'] + $c['prot'] - $c['already'] ) : '&mdash;'; ?></td>
						<td><a href="<?php echo $detail_url( array( 'rule' => (int) $row->id ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>"><?php esc_html_e( 'See pages', 'anchor-autopilot' ); ?></a></td>
					</tr>
				<?php endforeach; ?>
				</tbody></table>
				<?php $pager( $v['total'], $v['per'], $v['paged'], array_filter( array( 'f' => $v['filter'], 's' => $v['search'] ) ) ); ?>
			<?php else : ?>
				<p><?php echo 'zero' === $v['filter'] ? esc_html__( 'Every active rule is linked somewhere.', 'anchor-autopilot' ) : esc_html__( 'No rules yet.', 'anchor-autopilot' ); ?></p>
			<?php endif; ?>
		</div>
	<?php endif; ?>
<?php endif; ?>
