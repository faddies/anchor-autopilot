<?php
/**
 * Settings screen. Variables: $s.
 *
 * @package AnchorAutopilot
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
$types = get_post_types( array( 'public' => true ), 'objects' );
unset( $types['attachment'] );
?>

<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
	<input type="hidden" name="action" value="aa_save_settings">
	<?php wp_nonce_field( 'aa_save_settings' ); ?>

	<div class="aa-card">
		<h2><?php esc_html_e( 'General', 'anchor-autopilot' ); ?></h2>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><?php esc_html_e( 'Automatic linking', 'anchor-autopilot' ); ?></th>
				<td><label><input type="checkbox" name="aa[enabled]" value="1" <?php checked( 1, (int) $s['enabled'] ); ?>> <?php esc_html_e( 'Turn on', 'anchor-autopilot' ); ?></label>
				<p class="description"><?php esc_html_e( 'Links are added when the page is displayed. Your saved content is never changed, so switching this off removes every link instantly.', 'anchor-autopilot' ); ?></p></td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Add links on', 'anchor-autopilot' ); ?></th>
				<td>
					<?php foreach ( $types as $slug => $obj ) : ?>
						<label class="aa-inline"><input type="checkbox" name="aa[post_types][]" value="<?php echo esc_attr( $slug ); ?>" <?php checked( in_array( $slug, (array) $s['post_types'], true ) ); ?>> <?php echo esc_html( $obj->labels->name ); ?></label>
					<?php endforeach; ?>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="aa-exclude"><?php esc_html_e( 'Never add links on these pages', 'anchor-autopilot' ); ?></label></th>
				<td><textarea id="aa-exclude" name="aa[exclude_urls]" rows="4" class="large-text code" placeholder="/contact/&#10;https://example.com/privacy-policy/"><?php echo esc_textarea( $s['exclude_urls'] ); ?></textarea>
				<p class="description"><?php esc_html_e( 'One URL or path per line. You can also switch a single page off in its editor (Anchor Autopilot box).', 'anchor-autopilot' ); ?></p></td>
			</tr>
		</table>
	</div>

	<div class="aa-card">
		<h2><?php esc_html_e( 'Limits', 'anchor-autopilot' ); ?></h2>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><label for="aa-max-page"><?php esc_html_e( 'Max automatic links per page', 'anchor-autopilot' ); ?></label></th>
				<td><input type="number" min="0" max="200" id="aa-max-page" name="aa[max_links_page]" value="<?php echo (int) $s['max_links_page']; ?>" class="small-text"> <span class="description"><?php esc_html_e( '0 = no limit. Longest phrases are linked first.', 'anchor-autopilot' ); ?></span></td>
			</tr>
			<tr>
				<th scope="row"><label for="aa-max-kw"><?php esc_html_e( 'Links per keyword per page', 'anchor-autopilot' ); ?></label></th>
				<td><input type="number" min="1" max="50" id="aa-max-kw" name="aa[max_per_keyword]" value="<?php echo (int) $s['max_per_keyword']; ?>" class="small-text"> <span class="description"><?php esc_html_e( 'How many times the same phrase is linked on one page. 1 = first occurrence only.', 'anchor-autopilot' ); ?></span></td>
			</tr>
			<tr>
				<th scope="row"><label for="aa-max-url"><?php esc_html_e( 'Links to the same URL per page', 'anchor-autopilot' ); ?></label></th>
				<td><input type="number" min="0" max="50" id="aa-max-url" name="aa[max_per_url]" value="<?php echo (int) $s['max_per_url']; ?>" class="small-text"> <span class="description"><?php esc_html_e( 'Links you added by hand to the same URL count too. 0 = no limit.', 'anchor-autopilot' ); ?></span></td>
			</tr>
		</table>
	</div>

	<div class="aa-card">
		<h2><?php esc_html_e( 'Where links may be placed', 'anchor-autopilot' ); ?></h2>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><?php esc_html_e( 'Text areas', 'anchor-autopilot' ); ?></th>
				<td>
					<label class="aa-block"><input type="radio" name="aa[scope_mode]" value="strict" <?php checked( 'strict', $s['scope_mode'] ); ?>> <strong><?php esc_html_e( 'Body copy only (recommended)', 'anchor-autopilot' ); ?></strong><br><span class="description"><?php esc_html_e( 'Paragraphs, list items, table cells and quotes.', 'anchor-autopilot' ); ?></span></label>
					<label class="aa-block"><input type="radio" name="aa[scope_mode]" value="relaxed" <?php checked( 'relaxed', $s['scope_mode'] ); ?>> <strong><?php esc_html_e( 'Any visible text', 'anchor-autopilot' ); ?></strong><br><span class="description"><?php esc_html_e( 'Also text sitting directly in div or span elements. Use only if your theme or builder puts body text there.', 'anchor-autopilot' ); ?></span></label>
					<p class="description"><?php esc_html_e( 'In both modes these are never changed: menus, headers, footers, sidebars, headings, buttons, forms, image alt text and captions, existing links, code, scripts, and hidden content.', 'anchor-autopilot' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Accordions and tabs', 'anchor-autopilot' ); ?></th>
				<td><label><input type="checkbox" name="aa[skip_accordions]" value="1" <?php checked( 1, (int) $s['skip_accordions'] ); ?>> <?php esc_html_e( 'Skip collapsed accordion, toggle and tab content', 'anchor-autopilot' ); ?></label></td>
			</tr>
			<tr>
				<th scope="row"><label for="aa-extra"><?php esc_html_e( 'Also skip these CSS classes / IDs', 'anchor-autopilot' ); ?></label></th>
				<td><textarea id="aa-extra" name="aa[extra_block]" rows="3" class="large-text code" placeholder=".my-cta-box&#10;#promo-banner"><?php echo esc_textarea( $s['extra_block'] ); ?></textarea>
				<p class="description"><?php esc_html_e( 'One per line, starting with . for a class or # for an ID. To keep a single block free of links, add the class aa-nolink to it.', 'anchor-autopilot' ); ?></p></td>
			</tr>
		</table>
	</div>

	<div class="aa-card">
		<h2><?php esc_html_e( 'Link appearance', 'anchor-autopilot' ); ?></h2>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><?php esc_html_e( 'Defaults', 'anchor-autopilot' ); ?></th>
				<td>
					<label class="aa-inline"><input type="checkbox" name="aa[new_tab]" value="1" <?php checked( 1, (int) $s['new_tab'] ); ?>> <?php esc_html_e( 'Open in a new tab', 'anchor-autopilot' ); ?></label>
					<label class="aa-inline"><input type="checkbox" name="aa[nofollow]" value="1" <?php checked( 1, (int) $s['nofollow'] ); ?>> <?php esc_html_e( 'Add nofollow', 'anchor-autopilot' ); ?></label>
					<p class="description"><?php esc_html_e( 'Each rule can override these.', 'anchor-autopilot' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="aa-class"><?php esc_html_e( 'CSS class on links', 'anchor-autopilot' ); ?></label></th>
				<td><input type="text" id="aa-class" name="aa[link_class]" value="<?php echo esc_attr( $s['link_class'] ); ?>" class="regular-text" placeholder="<?php esc_attr_e( 'optional', 'anchor-autopilot' ); ?>"></td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'External destinations', 'anchor-autopilot' ); ?></th>
				<td><label><input type="checkbox" name="aa[allow_external]" value="1" <?php checked( 1, (int) $s['allow_external'] ); ?>> <?php esc_html_e( 'Allow rules that link to other websites', 'anchor-autopilot' ); ?></label>
				<p class="description"><?php esc_html_e( 'Off by default: this plugin is for internal linking.', 'anchor-autopilot' ); ?></p></td>
			</tr>
		</table>
	</div>

	<div class="aa-card">
		<h2><?php esc_html_e( 'Uninstall', 'anchor-autopilot' ); ?></h2>
		<label><input type="checkbox" name="aa[delete_on_uninstall]" value="1" <?php checked( 1, (int) $s['delete_on_uninstall'] ); ?>> <?php esc_html_e( 'Delete all rules and settings when the plugin is deleted', 'anchor-autopilot' ); ?></label>
	</div>

	<p><button type="submit" class="button button-primary button-large"><?php esc_html_e( 'Save settings', 'anchor-autopilot' ); ?></button></p>
</form>

<div class="aa-card">
	<h2><?php esc_html_e( 'How it decides', 'anchor-autopilot' ); ?></h2>
	<ol class="aa-how">
		<li><?php esc_html_e( 'Only visible body copy is considered. Everything else on the page is left exactly as it is.', 'anchor-autopilot' ); ?></li>
		<li><?php esc_html_e( 'Longest phrase first. If "abc 123 def" and "123 def" are both rules, "abc 123 def" gets the link and "123 def" is never linked inside it. "123 def" still links where it appears alone.', 'anchor-autopilot' ); ?></li>
		<li><?php esc_html_e( 'A phrase is never linked to two different URLs on one page, never to the page itself, and never when the page already links to that URL.', 'anchor-autopilot' ); ?></li>
		<li><?php esc_html_e( 'A page-specific rule beats a whole-site rule for the same anchor.', 'anchor-autopilot' ); ?></li>
		<li><?php esc_html_e( 'Matches are whole words (so "seo" never links inside "Seoul"), ignore case by default, and work with Arabic and accented text.', 'anchor-autopilot' ); ?></li>
	</ol>
</div>
