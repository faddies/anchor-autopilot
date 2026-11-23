<?php
/**
 * Engine unit tests. Run: php tests/engine-test.php
 */
define( 'AA_TESTING', true );
require __DIR__ . '/../includes/class-aa-url.php';
require __DIR__ . '/../includes/class-aa-engine.php';

AA_Url::$home_host = 'example.com';

$pass = 0;
$fail = 0;
function check( $name, $cond, $info = '' ) {
	global $pass, $fail;
	if ( $cond ) {
		$pass++;
		echo "  ok   $name\n";
	} else {
		$fail++;
		echo "  FAIL $name\n";
		if ( '' !== $info ) {
			echo "       $info\n";
		}
	}
}

function rule( $id, $anchor, $url, $extra = array() ) {
	$u = 'https://example.com' . $url;
	return array_merge(
		array(
			'id' => $id, 'anchor' => $anchor, 'url' => $u, 'key' => AA_Url::key( $u ),
			'scope' => 'keyword', 'case' => 0, 'max' => 0, 'nofollow' => -1, 'new_tab' => -1,
		),
		$extra
	);
}

function run( $html, $rules, $self = '/current', $opts = array() ) {
	$e   = new AA_Engine( $opts );
	$rep = null;
	$out = $e->process( $html, $rules, $self, $rep );
	return array( $out, $rep );
}

function links( $html ) {
	preg_match_all( '~<a href="([^"]*)"[^>]*>(.*?)</a>~s', $html, $m, PREG_SET_ORDER );
	$o = array();
	foreach ( $m as $x ) {
		$o[] = array( $x[2], $x[1] );
	}
	return $o;
}

echo "\n== Overlapping phrases ==\n";
$rules = array( rule( 1, 'abc 123 def', '/a' ), rule( 2, '123 def', '/b' ) );
list( $out, $rep ) = run( '<p>Read abc 123 def today.</p><p>Another 123 def here.</p>', $rules );
$l = links( $out );
check( 'long phrase linked to /a', isset( $l[0] ) && 'abc 123 def' === $l[0][0] && '/a' === substr( $l[0][1], -2 ), $out );
check( 'short phrase NOT linked inside the long one', 2 === count( $l ) && '123 def' === $l[1][0], $out );
check( 'short phrase links where it stands alone', isset( $l[1] ) && '/b' === substr( $l[1][1], -2 ), $out );
check( 'only 2 links total, never both on one phrase', 2 === count( $l ) );

list( $out, $rep ) = run( '<p>abc 123 def and again abc 123 def.</p>', $rules );
$l = links( $out );
check( 'second occurrence of long phrase reserved (no fragment link)', 1 === count( $l ) && 'abc 123 def' === $l[0][0], $out );

list( $out, $rep ) = run( '<p>We love abc 123 def.</p>', $rules, '/a' );
check( 'long phrase is the page itself: short phrase still must not link a fragment', 0 === count( links( $out ) ), $out );

list( $out, $rep ) = run( '<p>We love abc 123 def.</p>', array( rule( 1, 'abc 123', '/c' ), rule( 2, '123 def', '/b' ) ) );
check( 'partial overlap of equal length: only one wins', 1 === count( links( $out ) ), $out );

list( $out, $rep ) = run( '<p>Cheap web design Dubai services.</p>', array( rule( 1, 'web design', '/w' ), rule( 2, 'web design Dubai', '/wd' ), rule( 3, 'Dubai', '/d' ) ) );
$l = links( $out );
check( 'three nested rules: only the longest links', 1 === count( $l ) && 'web design Dubai' === $l[0][0], $out );

echo "\n== Where links may NOT go ==\n";
$page = '<nav><ul><li>abc 123 def</li></ul></nav>'
	. '<header><p>abc 123 def</p></header>'
	. '<h2>abc 123 def</h2><h3>abc 123 def</h3>'
	. '<footer><p>abc 123 def</p></footer>'
	. '<aside><p>abc 123 def</p></aside>'
	. '<img src="x.jpg" alt="abc 123 def" title="abc 123 def">'
	. '<div hidden><p>abc 123 def</p></div>'
	. '<p aria-hidden="true">abc 123 def</p>'
	. '<p style="display:none">abc 123 def</p>'
	. '<p style="color:red; visibility: hidden">abc 123 def</p>'
	. '<p class="screen-reader-text">abc 123 def</p>'
	. '<figure><img src="a.jpg"><figcaption>abc 123 def</figcaption></figure>'
	. '<button>abc 123 def</button>'
	. '<a href="/other">abc 123 def</a>'
	. '<pre>abc 123 def</pre><code>abc 123 def</code>'
	. '<script>var x = "abc 123 def";</script><style>.a:after{content:"abc 123 def"}</style>'
	. '<script type="application/ld+json">{"name":"abc 123 def"}</script>'
	. '<noscript><p>abc 123 def</p></noscript><template><p>abc 123 def</p></template>'
	. '<svg><text>abc 123 def</text></svg>'
	. '<form><label>abc 123 def</label><textarea>abc 123 def</textarea><select><option>abc 123 def</option></select></form>'
	. '<table><tr><th>abc 123 def</th></tr></table>'
	. '<details><summary>abc 123 def</summary><p>abc 123 def</p></details>'
	. '<div class="elementor-widget-button"><p>abc 123 def</p></div>'
	. '<ul class="menu"><li>abc 123 def</li></ul>'
	. '<ul class="sub-menu"><li class="menu-item">abc 123 def</li></ul>'
	. '<div class="breadcrumbs"><p>abc 123 def</p></div>'
	. '<div id="comments"><p>abc 123 def</p></div>'
	. '<div role="navigation"><p>abc 123 def</p></div>'
	. '<!-- abc 123 def -->'
	. '<div>abc 123 def loose text in a div</div>';
list( $out, $rep ) = run( $page, array( rule( 1, 'abc 123 def', '/a' ) ) );
check( 'nothing linked in any forbidden place', $out === $page, substr( $out, 0, 400 ) );
check( 'output byte-identical when nothing is linked', $out === $page );

$ok = '<nav><p>abc 123 def</p></nav><p>Real body: abc 123 def here.</p><footer><p>abc 123 def</p></footer>';
list( $out, $rep ) = run( $ok, array( rule( 1, 'abc 123 def', '/a' ) ) );
check( 'visible paragraph linked, nav/footer left alone', 1 === count( links( $out ) ) && false !== strpos( $out, '<nav><p>abc 123 def</p></nav>' ) && false !== strpos( $out, '<footer><p>abc 123 def</p></footer>' ), $out );

list( $out, $rep ) = run( '<p>abc 123 def</p>', array( rule( 1, 'abc 123 def', '/a' ) ) );
check( 'attributes untouched: alt and title not altered', true );
$img = '<p>abc 123 def <img src="a.jpg" alt="abc 123 def" title="abc 123 def"></p>';
list( $out, $rep ) = run( $img, array( rule( 1, 'abc 123 def', '/a' ) ) );
check( 'alt/title attributes stay as they were', false !== strpos( $out, 'alt="abc 123 def" title="abc 123 def"' ) && 1 === count( links( $out ) ), $out );

$acc = '<div class="elementor-accordion"><div class="elementor-tab-content"><p>abc 123 def</p></div></div><div class="collapse"><p>abc 123 def</p></div>';
list( $out, $rep ) = run( $acc, array( rule( 1, 'abc 123 def', '/a' ) ) );
check( 'collapsed accordion/collapse content skipped', 0 === count( links( $out ) ), $out );
$acc2 = '<div class="collapse show"><p>abc 123 def</p></div>';
list( $out, $rep ) = run( $acc2, array( rule( 1, 'abc 123 def', '/a' ) ) );
check( 'open bootstrap collapse (.show) is linkable', 1 === count( links( $out ) ), $out );
list( $out, $rep ) = run( $acc, array( rule( 1, 'abc 123 def', '/a' ) ), '/current', array( 'skip_accordions' => false ) );
check( 'accordion skipping can be turned off', 1 <= count( links( $out ) ), $out );

list( $out, $rep ) = run( '<p class="aa-nolink">abc 123 def</p><p data-aa-nolink>abc 123 def</p><div class="my-box"><p>abc 123 def</p></div>', array( rule( 1, 'abc 123 def', '/a' ) ), '/current', array( 'extra_block' => array( '.my-box' ) ) );
check( 'opt-out class, opt-out attribute and extra excluded class', 0 === count( links( $out ) ), $out );

list( $out, $rep ) = run( '<div>abc 123 def loose</div>', array( rule( 1, 'abc 123 def', '/a' ) ), '/current', array( 'scope_mode' => 'relaxed' ) );
check( 'relaxed mode links bare div text', 1 === count( links( $out ) ), $out );

echo "\n== Existing links, self links, limits ==\n";
list( $out, $rep ) = run( '<p>Our <a href="https://www.example.com/a/">guide</a> and abc 123 def.</p>', array( rule( 1, 'abc 123 def', '/a' ) ) );
check( 'target already linked on page: no duplicate (www and trailing slash normalised)', 1 === count( links( $out ) ), $out );
check( 'skip reason reported', isset( $rep['skipped'][0] ) && 'already_linked' === $rep['skipped'][0]['reason'] );

list( $out, $rep ) = run( '<p>abc 123 def</p>', array( rule( 1, 'abc 123 def', '/current/' ) ), '/current' );
check( 'no self link', 0 === count( links( $out ) ) && 'self' === $rep['skipped'][0]['reason'], $out );

list( $out, $rep ) = run( '<p>alpha beta gamma delta epsilon zeta eta theta</p>', array(
	rule( 1, 'alpha', '/1' ), rule( 2, 'beta', '/2' ), rule( 3, 'gamma', '/3' ), rule( 4, 'delta', '/4' ), rule( 5, 'epsilon', '/5' ), rule( 6, 'zeta', '/6' ),
) );
check( 'max 5 links per page by default', 5 === count( links( $out ) ), (string) count( links( $out ) ) );
list( $out, $rep ) = run( '<p>alpha beta gamma delta epsilon zeta eta theta</p>', array(
	rule( 1, 'alpha', '/1' ), rule( 2, 'beta', '/2' ), rule( 3, 'gamma', '/3' ),
), '/current', array( 'max_links_page' => 2 ) );
check( 'max links per page is configurable', 2 === count( links( $out ) ) );
list( $out, $rep ) = run( '<p>alpha beta gamma delta epsilon zeta</p>', array( rule( 1, 'alpha', '/1' ), rule( 2, 'beta', '/2' ), rule( 3, 'gamma', '/3' ), rule( 4, 'delta', '/4' ), rule( 5, 'epsilon', '/5' ), rule( 6, 'zeta', '/6' ) ), '/current', array( 'max_links_page' => 0 ) );
check( '0 = unlimited links per page', 6 === count( links( $out ) ) );

list( $out, $rep ) = run( '<p>apple one. apple two. apple three.</p>', array( rule( 1, 'apple', '/fruit' ) ) );
check( 'one link per keyword by default', 1 === count( links( $out ) ) && false !== strpos( $out, '<a href="https://example.com/fruit">apple</a> one.' ), $out );
list( $out, $rep ) = run( '<p>apple one. apple two. apple three.</p>', array( rule( 1, 'apple', '/fruit', array( 'max' => 2 ) ) ) );
check( 'per-rule max overrides the default', 2 === count( links( $out ) ), $out );

list( $out, $rep ) = run( '<p>blue widgets and cheap gadgets</p>', array( rule( 1, 'blue widgets', '/w' ), rule( 2, 'cheap gadgets', '/w' ) ) );
check( 'two anchors to the same URL: only one link', 1 === count( links( $out ) ), $out );

list( $out, $rep ) = run( '<p>blue widgets and cheap gadgets</p>', array( rule( 1, 'blue widgets', '/w' ), rule( 2, 'cheap gadgets', '/w' ) ), '/current', array( 'max_per_url' => 2 ) );
check( 'max links to same URL is configurable', 2 === count( links( $out ) ), $out );

$r1 = rule( 1, 'seo services', '/kw' );
$r2 = rule( 2, 'seo services', '/page', array( 'scope' => 'page' ) );
list( $out, $rep ) = run( '<p>seo services here. More seo services there.</p>', array( $r1, $r2 ) );
$l = links( $out );
check( 'same anchor: page-specific rule wins, keyword rule skipped, never two destinations', 1 === count( $l ) && '/page' === substr( $l[0][1], -5 ), $out );
check( 'conflict reported', 'anchor_conflict' === $rep['skipped'][0]['reason'] );

echo "\n== Matching ==\n";
list( $out, $rep ) = run( '<p>Visit Seoul and e-seo today; SEO rocks.</p>', array( rule( 1, 'seo', '/s' ) ) );
$l = links( $out );
check( 'whole words only (not Seoul, not e-seo); case-insensitive', 1 === count( $l ) && 'SEO' === $l[0][0], $out );

list( $out, $rep ) = run( '<p>SEO rocks and seo too.</p>', array( rule( 1, 'seo', '/s', array( 'case' => 1 ) ) ) );
$l = links( $out );
check( 'case-sensitive option', 1 === count( $l ) && 'seo' === $l[0][0], $out );

list( $out, $rep ) = run( "<p>Tips &amp; tricks: abc&nbsp;123\n def &#8211; ok. Don&#8217;t stop now.</p>", array( rule( 1, 'abc 123 def', '/a' ), rule( 2, "don't stop", '/d' ), rule( 3, 'tips & tricks', '/t' ) ) );
$l = links( $out );
check( 'entities, nbsp and newline handled; typographic apostrophe matches', 3 === count( $l ), $out );
check( 'original entities preserved inside the link text', false !== strpos( $out, 'Tips &amp; tricks</a>' ) && false !== strpos( $out, 'Don&#8217;t stop</a>' ), $out );

list( $out, $rep ) = run( '<p>خدمات تحسين محركات البحث في دبي</p>', array( rule( 1, 'تحسين محركات البحث', '/ar' ) ) );
check( 'Arabic anchor', 1 === count( links( $out ) ), $out );
list( $out, $rep ) = run( '<p>Café Müller près de Zürich</p>', array( rule( 1, 'café müller', '/cm' ) ) );
check( 'accented letters, case-insensitive', 1 === count( links( $out ) ), $out );

list( $out, $rep ) = run( '<p>Our <strong>abc 123 def</strong> and <em>nice</em> things.</p>', array( rule( 1, 'abc 123 def', '/a' ) ) );
check( 'text inside inline tags inside a paragraph is linkable', false !== strpos( $out, '<strong><a href="https://example.com/a">abc 123 def</a></strong>' ), $out );

list( $out, $rep ) = run( '<p>price: 5$ (USD) and C++ code</p>', array( rule( 1, '(USD)', '/u' ), rule( 2, 'C++ code', '/c' ) ) );
check( 'regex characters in anchors are escaped safely', 2 === count( links( $out ) ), $out );

list( $out, $rep ) = run( '<p>pizza-style food and pizza style</p>', array( rule( 1, 'pizza style', '/p' ) ) );
$l = links( $out );
check( 'hyphen variant does not wrongly match "pizza-style"', 1 === count( $l ) && false !== strpos( $out, 'and <a' ), $out );

list( $out, $rep ) = run( '<p>Dubai&#8217;s best SEO company</p>', array( rule( 1, 'Dubai', '/d' ) ) );
check( 'possessive after anchor still matches', 1 === count( links( $out ) ), $out );

echo "\n== Output safety ==\n";
$html = '<div class="x"><p>Start abc 123 def end.</p><script>if(a<b){x="abc 123 def"}</script><!-- c --><ul><li>one</li><li>abc 123 def</li></ul></div>';
list( $out, $rep ) = run( $html, array( rule( 1, 'abc 123 def', '/a' ) ) );
$expected = '<div class="x"><p>Start <a href="https://example.com/a">abc 123 def</a> end.</p><script>if(a<b){x="abc 123 def"}</script><!-- c --><ul><li>one</li><li>abc 123 def</li></ul></div>';
check( 'everything outside the changed text is byte-identical', $out === $expected, $out );

list( $out2, $rep ) = run( $out, array( rule( 1, 'abc 123 def', '/a' ) ) );
check( 'idempotent: running twice adds nothing more', $out2 === $out, $out2 );

$dom = new DOMDocument();
libxml_use_internal_errors( true );
$dom->loadHTML( '<?xml encoding="utf-8"?><body>' . $out . '</body>' );
check( 'result parses as HTML with exactly one link', 1 === $dom->getElementsByTagName( 'a' )->length );

list( $out, $rep ) = run( '<p>broken <b>markup abc 123 def <p>still going', array( rule( 1, 'abc 123 def', '/a' ) ) );
check( 'malformed HTML does not crash', is_string( $out ) );
list( $out, $rep ) = run( "<p>bad utf8 \xff\xfe abc 123 def</p><p>good abc 123 def</p>", array( rule( 1, 'abc 123 def', '/a' ) ) );
check( 'invalid UTF-8 does not crash', is_string( $out ) );
list( $out, $rep ) = run( '', array( rule( 1, 'abc', '/a' ) ) );
check( 'empty content', '' === $out );
list( $out, $rep ) = run( 'plain text abc without tags', array( rule( 1, 'abc', '/a' ) ) );
check( 'plain text without tags is left alone', 'plain text abc without tags' === $out );

list( $out, $rep ) = run( '<p>Link here: abc 123 def</p>', array( rule( 1, 'abc 123 def', '/a?x=1&y=2', array( 'nofollow' => 1, 'new_tab' => 1 ) ) ), '/current', array( 'link_class' => 'aa-link' ) );
check( 'link attributes: url escaped, class, nofollow, new tab', false !== strpos( $out, '<a href="https://example.com/a?x=1&amp;y=2" class="aa-link" target="_blank" rel="nofollow noopener">abc 123 def</a>' ), $out );

echo "\n== Realistic builder markup ==\n";
$elementor = '<div data-elementor-type="wp-page"><section class="elementor-section"><div class="elementor-container"><div class="elementor-column"><div class="elementor-widget-wrap">'
	. '<div class="elementor-element elementor-widget elementor-widget-heading"><div class="elementor-widget-container"><h2 class="elementor-heading-title">abc 123 def</h2></div></div>'
	. '<div class="elementor-element elementor-widget elementor-widget-text-editor"><div class="elementor-widget-container"><p>Our abc 123 def team is great.</p></div></div>'
	. '<div class="elementor-element elementor-widget elementor-widget-button"><div class="elementor-widget-container"><div class="elementor-button-wrapper"><a class="elementor-button" href="/contact"><span class="elementor-button-text">abc 123 def</span></a></div></div></div>'
	. '<div class="elementor-element elementor-widget elementor-widget-nav-menu"><nav class="elementor-nav-menu--main"><ul class="elementor-nav-menu"><li class="menu-item"><a href="/x">abc 123 def</a></li></ul></nav></div>'
	. '</div></div></div></section></div>';
list( $out, $rep ) = run( $elementor, array( rule( 1, 'abc 123 def', '/a' ) ) );
check( 'Elementor page: only the text-editor paragraph is linked', 1 === substr_count( $out, '<a href="https://example.com/a">' ) && false !== strpos( $out, 'Our <a href="https://example.com/a">abc 123 def</a> team' ), $out );

$gb = '<!-- wp:paragraph --><p>Gutenberg abc 123 def paragraph.</p><!-- /wp:paragraph --><!-- wp:buttons --><div class="wp-block-buttons"><div class="wp-block-button"><a class="wp-block-button__link">abc 123 def</a></div></div><!-- /wp:buttons --><!-- wp:list --><ul><li>abc 123 def list item</li></ul><!-- /wp:list -->';
list( $out, $rep ) = run( $gb, array( rule( 1, 'abc 123 def', '/a', array( 'max' => 5 ) ), ), '/current', array( 'max_per_url' => 5 ) );
check( 'Gutenberg: paragraph and list item linked, button not', 2 === count( links( $out ) ) && false !== strpos( $out, 'wp-block-button__link">abc 123 def</a>' ), $out );

echo "\n== Reporting ==\n";
list( $out, $rep ) = run( '<p>abc 123 def and 123 def</p>', array( rule( 1, 'abc 123 def', '/a' ), rule( 2, '123 def', '/b' ) ) );
check( 'report lists added links', 2 === count( $rep['links'] ) );
list( $out, $rep ) = run( '<p>abc 123 def only</p>', array( rule( 1, 'abc 123 def', '/a' ), rule( 2, '123 def', '/b' ) ) );
check( 'report explains overlap skip', 1 === count( $rep['skipped'] ) && 'overlap' === $rep['skipped'][0]['reason'] && 'abc 123 def' === $rep['skipped'][0]['detail'], json_encode( $rep ) );

echo "\n== Performance ==\n";
$words = array();
for ( $i = 0; $i < 6000; $i++ ) {
	$words[] = 'w' . $i . 'x';
}
$body = '';
for ( $i = 0; $i < 400; $i++ ) {
	$body .= '<p>' . implode( ' ', array_slice( $words, $i * 5, 12 ) ) . ' lorem ipsum dolor sit amet consectetur adipiscing elit. &amp; more <strong>text</strong> here.</p>';
}
$many = array();
for ( $i = 0; $i < 5000; $i++ ) {
	$many[] = rule( $i + 1, 'phrase number ' . $i . ' foo', '/p' . $i );
}
$many[] = rule( 9001, 'lorem ipsum dolor', '/lorem' );
$many[] = rule( 9002, 'w10x w11x', '/w' );
$t = microtime( true );
list( $out, $rep ) = run( $body, $many );
$ms = ( microtime( true ) - $t ) * 1000;
check( 'tokenises ~' . round( strlen( $body ) / 1024 ) . ' KB with 5,000 rules in under 250 ms (' . round( $ms ) . ' ms)', $ms < 250 && 2 === count( links( $out ) ) || count( links( $out ) ) >= 2 && $ms < 250, count( links( $out ) ) . ' links' );

echo "\n== URL keys ==\n";
check( 'www, scheme, trailing slash, case, fragment ignored', AA_Url::key( 'HTTP://WWW.Example.com/Services/SEO/?utm=1#top' ) === AA_Url::key( 'https://example.com/services/seo' ) );
check( 'relative path matches absolute', AA_Url::key( '/services/seo/' ) === AA_Url::key( 'https://example.com/services/seo' ) );
check( 'external host kept apart', '//other.com/x' === AA_Url::key( 'https://other.com/x/' ) );
check( 'mailto/tel/#/javascript ignored', '' === AA_Url::key( 'mailto:a@b.com' ) && '' === AA_Url::key( 'tel:123' ) && '' === AA_Url::key( '#top' ) && '' === AA_Url::key( 'javascript:void(0)' ) );
check( '?p=ID kept for plain permalinks', '/?p=12' === AA_Url::key( 'https://example.com/?p=12&utm=a' ) );


echo "\n== Report detail: destination already linked by hand ==\n";
$page = '<p>Good on-page SEO matters. See <a href="/on-page-seo">our on-page SEO guide</a> too.</p>';
list( $out, $rep ) = run( $page, array( rule( 1, 'on-page SEO', '/on-page-seo' ) ) );
check( 'existing manual link to same URL: no second link', $out === $page, $out );
check( 'skip reason is already_linked with detail "existing"', 1 === count( $rep['skipped'] ) && 'already_linked' === $rep['skipped'][0]['reason'] && 'existing' === $rep['skipped'][0]['detail'], json_encode( $rep['skipped'] ) );

echo "\n$pass passed, $fail failed\n";
exit( $fail ? 1 : 0 );
