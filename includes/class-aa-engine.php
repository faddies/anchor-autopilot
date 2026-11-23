<?php
/**
 * Linking engine. Pure PHP, no WordPress calls, so it can be unit-tested on its own.
 *
 * It never re-serialises the page. The HTML is split into tags and text pieces; only text
 * pieces that receive a link are rebuilt, everything else stays byte-identical.
 *
 * Rule format (array): id, anchor, url, key (normalised target), scope ('keyword'|'page'),
 * case (0/1), max (0 = default), nofollow (-1/0/1), new_tab (-1/0/1).
 * Optional precomputed: akey, w (anchor words), len.
 *
 * @package AnchorAutopilot
 */

if ( ! defined( 'ABSPATH' ) && ! defined( 'AA_TESTING' ) ) {
	exit;
}

class AA_Engine {

	const TOKEN_RX = '~(<!--.*?-->|<!\[CDATA\[.*?\]\]>|<script\b[^>]*>.*?</script\s*>|<style\b[^>]*>.*?</style\s*>|</?[a-zA-Z][a-zA-Z0-9:_-]*(?:[^>"\']|"[^"]*"|\'[^\']*\')*>|<[?!][^>]*>)~is';

	const ENTITY_RX = '~(&(?:#[0-9]{1,7}|#[xX][0-9a-fA-F]{1,6}|[a-zA-Z][a-zA-Z0-9]{1,31});)~';

	private $o;
	private $skip_tags;
	private $allow_tags;
	private $block_classes;
	private $block_prefixes;
	private $block_ids;
	private $block_roles;
	private $accordion_classes;
	private $extra_classes = array();
	private $extra_ids = array();
	private $rx_cache = array();

	public static function defaults() {
		return array(
			'max_links_page'  => 5,   // 0 = unlimited.
			'max_per_keyword' => 1,   // Default occurrences linked per keyword per page.
			'max_per_url'     => 1,   // Links to the same URL per page (existing manual links count). 0 = unlimited.
			'scope_mode'      => 'strict', // strict: p, li, td, dd, blockquote. relaxed: any visible text.
			'skip_accordions' => true,
			'new_tab'         => false,
			'nofollow'        => false,
			'link_class'      => '',
			'extra_block'     => array(), // Lines like ".my-class" or "#my-id".
		);
	}

	public static function void_tags() {
		return array_flip( array( 'area', 'base', 'br', 'col', 'embed', 'hr', 'img', 'input', 'link', 'meta', 'param', 'source', 'track', 'wbr' ) );
	}

	public static function default_skip_tags() {
		return array(
			'a', 'head', 'title', 'meta', 'link', 'base', 'script', 'style', 'noscript', 'template', 'svg', 'math', 'canvas',
			'iframe', 'frame', 'frameset', 'noframes', 'object', 'embed', 'video', 'audio', 'source', 'track', 'map', 'area', 'picture', 'img',
			'nav', 'header', 'footer', 'aside', 'menu', 'dialog', 'form', 'button', 'select', 'option', 'optgroup', 'textarea',
			'input', 'label', 'legend', 'fieldset', 'datalist', 'output', 'progress', 'meter',
			'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'hgroup',
			'code', 'pre', 'kbd', 'samp', 'var', 'tt',
			'figcaption', 'caption', 'th', 'time', 'cite', 'address',
		);
	}

	public static function default_allow_tags() {
		return array( 'p', 'li', 'td', 'dd', 'blockquote' );
	}

	public static function default_block_classes() {
		return array(
			// Hidden / assistive.
			'hidden', 'hide', 'sr-only', 'screen-reader-text', 'screen-reader-only', 'visually-hidden', 'visuallyhidden',
			'assistive-text', 'd-none', 'invisible', 'is-hidden', 'u-hidden', 'aa-nolink', 'nolink', 'no-link',
			// Navigation and page furniture.
			'nav', 'navbar', 'navigation', 'menu', 'main-navigation', 'site-navigation', 'breadcrumb', 'breadcrumbs', 'crumbs',
			'site-header', 'site-footer', 'entry-header', 'entry-footer', 'entry-meta', 'post-meta', 'byline', 'posted-on',
			'cat-links', 'tags-links', 'post-navigation', 'pagination',
			'author-box', 'author-bio', 'author-info', 'sharedaddy', 'share-buttons', 'social-share', 'social-links', 'social-icons',
			'related-posts', 'related-post', 'yarpp-related', 'jp-relatedposts', 'post-related',
			'comments', 'comment-list', 'comment-respond', 'comments-area', 'comment-form',
			'toc', 'toc_container', 'table-of-contents', 'ez-toc-container', 'lwptoc', 'rank-math-toc-block',
			// Buttons, forms, popups.
			'btn', 'button', 'wp-element-button', 'cookie-notice', 'cookie-banner', 'modal', 'popup', 'tooltip', 'dropdown',
			'wpcf7', 'wpcf7-form', 'gform_wrapper', 'nf-form-cont', 'wpforms-container',
			// Gutenberg blocks that are not body copy.
			'wp-block-button', 'wp-block-buttons', 'wp-block-site-title', 'wp-block-site-tagline', 'wp-block-post-title',
			'wp-block-post-terms', 'wp-block-post-date', 'wp-block-post-author', 'wp-block-post-author-name', 'wp-block-post-excerpt',
			'wp-block-latest-posts', 'wp-block-latest-comments', 'wp-block-search', 'wp-block-tag-cloud', 'wp-block-categories',
			'wp-block-archives', 'wp-block-calendar', 'wp-block-rss', 'wp-block-page-list', 'wp-block-loginout', 'wp-block-comments',
			// Elementor and other builders (non-body widgets).
			'elementor-button', 'elementor-button-wrapper', 'elementor-heading-title', 'elementor-widget-heading', 'elementor-widget-button',
			'elementor-widget-breadcrumbs', 'elementor-widget-search-form', 'elementor-widget-social-icons', 'elementor-widget-share-buttons',
			'elementor-widget-post-navigation', 'elementor-widget-author-box', 'elementor-widget-posts', 'elementor-widget-portfolio',
			'elementor-widget-loop-grid', 'elementor-posts-container', 'elementor-widget-icon-list', 'elementor-icon-list-items',
			'elementor-widget-form', 'elementor-form', 'elementor-widget-counter', 'elementor-widget-countdown', 'elementor-widget-progress',
			'elementor-widget-price-list', 'elementor-widget-price-table', 'elementor-widget-flip-box', 'elementor-widget-call-to-action',
			'elementor-cta', 'elementor-widget-nav-menu',
			'et_pb_menu', 'et_pb_button', 'et_pb_button_module_wrapper', 'et_pb_blog_grid', 'et_pb_post_nav', 'et_pb_search',
			'et_pb_social_media_follow', 'et-menu', 'fusion-menu', 'fusion-button', 'fusion-title', 'wpb_button', 'vc_btn3', 'vc_btn3-container',
		);
	}

	public static function default_block_prefixes() {
		return array(
			'elementor-hidden-', 'hidden-', 'elementor-nav-', 'menu-item', 'nav-', 'navbar-', 'breadcrumb-',
			'wp-block-navigation', 'wp-block-social-link', 'wp-block-query', 'wp-block-table-of-contents', 'wp-block-button',
			'et_pb_menu_', 'fusion-menu-', 'ez-toc-',
		);
	}

	public static function default_block_ids() {
		return array(
			'header', 'footer', 'nav', 'menu', 'sidebar', 'masthead', 'colophon', 'site-navigation', 'site-header', 'site-footer',
			'main-navigation', 'breadcrumbs', 'comments', 'respond', 'toc', 'toc_container', 'ez-toc-container', 'related-posts',
			'cookie-notice', 'wpadminbar',
		);
	}

	public static function default_block_roles() {
		return array( 'navigation', 'banner', 'contentinfo', 'complementary', 'search', 'menu', 'menubar', 'menuitem', 'dialog', 'alertdialog', 'tooltip', 'tablist', 'tab', 'toolbar', 'button', 'alert' );
	}

	public static function default_accordion_classes() {
		return array(
			'accordion', 'accordion-content', 'accordion-body', 'accordion-collapse', 'accordion-panel', 'accordion-item',
			'elementor-accordion', 'elementor-tab-content', 'elementor-toggle', 'et_pb_toggle', 'et_pb_toggle_content', 'et_pb_accordion',
			'wp-block-accordion', 'wp-block-accordion-item', 'wp-block-accordion-panel', 'collapsible', 'panel-collapse',
			'tab-pane', 'tab-content',
		);
	}

	public function __construct( $opts = array() ) {
		$this->o = array_merge( self::defaults(), is_array( $opts ) ? $opts : array() );

		$skip = isset( $opts['skip_tags'] ) ? $opts['skip_tags'] : self::default_skip_tags();
		if ( $this->o['skip_accordions'] ) {
			$skip = array_merge( $skip, array( 'details', 'summary' ) );
		}
		$this->skip_tags      = array_flip( $skip );
		$this->allow_tags     = array_flip( isset( $opts['allow_tags'] ) ? $opts['allow_tags'] : self::default_allow_tags() );
		$this->block_classes  = array_flip( isset( $opts['block_classes'] ) ? $opts['block_classes'] : self::default_block_classes() );
		$this->block_prefixes = isset( $opts['block_prefixes'] ) ? $opts['block_prefixes'] : self::default_block_prefixes();
		$this->block_ids      = array_flip( isset( $opts['block_ids'] ) ? $opts['block_ids'] : self::default_block_ids() );
		$this->block_roles    = array_flip( isset( $opts['block_roles'] ) ? $opts['block_roles'] : self::default_block_roles() );
		$this->accordion_classes = array_flip( self::default_accordion_classes() );

		foreach ( (array) $this->o['extra_block'] as $line ) {
			$line = trim( (string) $line );
			if ( '' === $line ) {
				continue;
			}
			if ( '.' === $line[0] ) {
				$this->extra_classes[ AA_Url::lower( substr( $line, 1 ) ) ] = true;
			} elseif ( '#' === $line[0] ) {
				$this->extra_ids[ AA_Url::lower( substr( $line, 1 ) ) ] = true;
			}
		}
	}

	/* ------------------------------------------------------------------ */
	/* Public helpers shared with the WordPress layer                      */
	/* ------------------------------------------------------------------ */

	/** Normalised anchor used for duplicate detection. */
	public static function anchor_key( $anchor ) {
		$a = AA_Url::lower( trim( preg_replace( '/\s+/u', ' ', (string) $anchor ) ) );
		$a = preg_replace( '/[\x{2018}\x{2019}\x{02BC}]/u', "'", $a );
		$a = preg_replace( '/[\x{201C}\x{201D}\x{201E}]/u', '"', $a );
		$a = preg_replace( '/[\x{2010}-\x{2015}\x{2212}]/u', '-', $a );
		return $a;
	}

	/** Lowercase letter/number words of an anchor (used for the fast pre-filter). */
	public static function anchor_words( $anchor ) {
		$words = array();
		if ( preg_match_all( '/[\p{L}\p{N}]+/u', AA_Url::lower( (string) $anchor ), $m ) ) {
			$words = array_values( array_unique( $m[0] ) );
		}
		return $words;
	}

	/* ------------------------------------------------------------------ */
	/* Main entry                                                          */
	/* ------------------------------------------------------------------ */

	/**
	 * Add links to $html.
	 *
	 * @param string $html     Post content HTML.
	 * @param array  $rules    Rules that apply to this page.
	 * @param string $self_key AA_Url::key() of the page being rendered ('' = unknown).
	 * @param array  $report   Out: links added and rules skipped (with reasons).
	 * @return string
	 */
	public function process( $html, array $rules, $self_key = '', &$report = null ) {
		$report = array( 'links' => array(), 'skipped' => array() );
		if ( ! is_string( $html ) || '' === $html || ! $rules || false === strpos( $html, '<' ) ) {
			return $html;
		}

		$parts = preg_split( self::TOKEN_RX, $html, -1, PREG_SPLIT_DELIM_CAPTURE );
		if ( false === $parts || count( $parts ) < 3 ) {
			return $html; // Parse problem or no markup: leave the page alone.
		}

		$url_counts = array();
		$segments   = $this->scan( $parts, $url_counts );
		$pre_counts = $url_counts; // Links to each destination that were already on the page before we added any.
		if ( ! $segments ) {
			return $html;
		}

		// Fast pre-filter: a rule can only match if all its words occur somewhere in the linkable text.
		$page_words = $this->page_words( $segments );
		$cands      = array();
		foreach ( $rules as $r ) {
			if ( ! isset( $r['anchor'] ) || '' === trim( $r['anchor'] ) || empty( $r['url'] ) ) {
				continue;
			}
			$w = isset( $r['w'] ) ? $r['w'] : self::anchor_words( $r['anchor'] );
			if ( ! $w ) {
				continue;
			}
			$ok = true;
			foreach ( $w as $word ) {
				if ( ! isset( $page_words[ $word ] ) ) {
					$ok = false;
					break;
				}
			}
			if ( $ok ) {
				if ( ! isset( $r['akey'] ) ) {
					$r['akey'] = self::anchor_key( $r['anchor'] );
				}
				if ( ! isset( $r['len'] ) ) {
					$r['len'] = strlen( $r['akey'] );
				}
				$cands[] = $r;
			}
		}
		if ( ! $cands ) {
			return $html;
		}

		// Longest anchor first; ties: page-specific before keyword; then oldest rule.
		usort(
			$cands,
			function ( $a, $b ) {
				if ( $a['len'] !== $b['len'] ) {
					return $b['len'] - $a['len'];
				}
				$sa = ( isset( $a['scope'] ) && 'page' === $a['scope'] ) ? 0 : 1;
				$sb = ( isset( $b['scope'] ) && 'page' === $b['scope'] ) ? 0 : 1;
				if ( $sa !== $sb ) {
					return $sa - $sb;
				}
				return (int) $a['id'] - (int) $b['id'];
			}
		);

		$claims       = array(); // segment index => list of array( start, end, label ).
		$links        = array(); // segment index => list of array( start, end, rule ).
		$anchor_owner = array(); // anchor key => target key already used on this page.
		$total        = 0;
		$max_total    = (int) $this->o['max_links_page'];
		$max_kw       = max( 1, (int) $this->o['max_per_keyword'] );
		$max_url      = (int) $this->o['max_per_url'];

		foreach ( $cands as $r ) {
			$akey = $r['akey'];
			$tkey = isset( $r['key'] ) ? $r['key'] : AA_Url::key( $r['url'] );

			if ( isset( $anchor_owner[ $akey ] ) && $anchor_owner[ $akey ] !== $tkey ) {
				$report['skipped'][] = $this->skip( $r, 'anchor_conflict', $anchor_owner[ $akey ] );
				continue;
			}

			$blockers = array();
			$occ      = $this->find( $r, $segments, $claims, $blockers );
			if ( ! $occ ) {
				if ( $blockers ) {
					$report['skipped'][] = $this->skip( $r, 'overlap', $blockers[0] );
				}
				continue;
			}

			$limit   = ( ! empty( $r['max'] ) && (int) $r['max'] > 0 ) ? (int) $r['max'] : $max_kw;
			$url_cap = ( ! empty( $r['max'] ) && (int) $r['max'] > 0 ) ? max( (int) $r['max'], $max_url ) : $max_url;
			if ( 0 === $max_url ) {
				$url_cap = 0;
			}

			$reason = '';
			if ( '' !== $self_key && $tkey === $self_key ) {
				$reason = 'self';
			}

			$linked_here = 0;
			$detail      = '';
			foreach ( $occ as $o ) {
				list( $si, $s, $e ) = $o;
				// Reserve the text even when not linked so shorter phrases cannot link a fragment of it.
				$claims[ $si ][] = array( $s, $e, $r['anchor'] );

				if ( '' !== $reason ) {
					continue;
				}
				if ( $linked_here >= $limit ) {
					continue;
				}
				$have = isset( $url_counts[ $tkey ] ) ? $url_counts[ $tkey ] : 0;
				if ( $url_cap > 0 && $have >= $url_cap ) {
					$reason = 'already_linked';
					$detail = ( isset( $pre_counts[ $tkey ] ) && $pre_counts[ $tkey ] > 0 ) ? 'existing' : 'ours';
					continue;
				}
				if ( $max_total > 0 && $total >= $max_total ) {
					$reason = 'page_cap';
					continue;
				}
				$links[ $si ][] = array( $s, $e, $r );
				$linked_here++;
				$total++;
				$url_counts[ $tkey ] = $have + 1;
				$anchor_owner[ $akey ] = $tkey;
			}
			if ( '' !== $reason && 0 === $linked_here ) {
				$report['skipped'][] = $this->skip( $r, $reason, 'already_linked' === $reason ? $detail : '' );
			}
		}

		if ( ! $links ) {
			return $html;
		}

		foreach ( $links as $si => $list ) {
			usort(
				$list,
				function ( $a, $b ) {
					return $a[0] - $b[0];
				}
			);
			$seg = $segments[ $si ];
			$raw = $parts[ $seg['i'] ];
			$out = '';
			$pos = 0;
			foreach ( $list as $item ) {
				list( $s, $e, $r ) = $item;
				$os = $this->orig_offset( $seg['map'], $s );
				$oe = $this->orig_offset( $seg['map'], $e );
				if ( $os < $pos || $oe < $os ) {
					continue; // Should not happen; never corrupt output.
				}
				$out .= substr( $raw, $pos, $os - $pos ) . $this->open_tag( $r ) . substr( $raw, $os, $oe - $os ) . '</a>';
				$pos  = $oe;
				$report['links'][] = array(
					'id'     => $r['id'],
					'anchor' => substr( $seg['text'], $s, $e - $s ),
					'url'    => $r['url'],
					'scope'  => isset( $r['scope'] ) ? $r['scope'] : 'keyword',
				);
			}
			$parts[ $seg['i'] ] = $out . substr( $raw, $pos );
		}

		return implode( '', $parts );
	}

	/* ------------------------------------------------------------------ */
	/* Scanning: which text pieces may carry a link                        */
	/* ------------------------------------------------------------------ */

	private function scan( array $parts, &$url_counts ) {
		$n        = count( $parts );
		$void     = self::void_tags();
		$relaxed  = ( 'relaxed' === $this->o['scope_mode'] );
		$stack    = array();
		$blocked  = 0;
		$allowed  = 0;
		$segments = array();

		for ( $i = 0; $i < $n; $i++ ) {
			$piece = $parts[ $i ];

			if ( 0 === $i % 2 ) { // Text between tags.
				if ( '' === $piece || $blocked > 0 || ( ! $relaxed && 0 === $allowed ) ) {
					continue;
				}
				if ( ! preg_match( '/[\p{L}\p{N}]/u', $piece ) ) {
					continue;
				}
				list( $dec, $map ) = $this->decode( $piece );
				$segments[] = array( 'i' => $i, 'text' => $dec, 'map' => $map );
				continue;
			}

			$c2 = isset( $piece[1] ) ? $piece[1] : '';
			if ( '!' === $c2 || '?' === $c2 ) {
				continue; // Comment, CDATA, doctype.
			}
			if ( preg_match( '~^<(?:script|style)\b~i', $piece ) && preg_match( '~</(?:script|style)\s*>$~i', $piece ) ) {
				continue; // Whole script/style element.
			}
			if ( ! preg_match( '~^<(/?)([a-zA-Z][a-zA-Z0-9:_-]*)~', $piece, $m ) ) {
				continue;
			}
			$name = strtolower( $m[2] );

			if ( '/' === $m[1] ) { // Closing tag: close the nearest open element of that name.
				for ( $k = count( $stack ) - 1; $k >= 0; $k-- ) {
					if ( $stack[ $k ][0] === $name ) {
						while ( count( $stack ) > $k ) {
							$e = array_pop( $stack );
							if ( $e[1] ) {
								$blocked--;
							}
							if ( $e[2] ) {
								$allowed--;
							}
						}
						break;
					}
				}
				continue;
			}

			if ( 'a' === $name ) { // Count links already on the page (anywhere in the content).
				$attrs = self::parse_attrs( $piece );
				if ( isset( $attrs['href'] ) ) {
					$href = html_entity_decode( $attrs['href'], ENT_QUOTES | ENT_HTML5, 'UTF-8' );
					$key  = AA_Url::key( $href );
					if ( '' !== $key ) {
						$url_counts[ $key ] = isset( $url_counts[ $key ] ) ? $url_counts[ $key ] + 1 : 1;
					}
				}
			}

			if ( isset( $void[ $name ] ) || '/>' === substr( $piece, -2 ) ) {
				continue;
			}

			$b = $this->is_blocked( $name, $piece );
			$a = isset( $this->allow_tags[ $name ] );
			$stack[] = array( $name, $b, $a );
			if ( $b ) {
				$blocked++;
			}
			if ( $a ) {
				$allowed++;
			}
		}
		return $segments;
	}

	private function is_blocked( $name, $tag ) {
		if ( isset( $this->skip_tags[ $name ] ) ) {
			return true;
		}
		// Cheap exit: nothing that could hide or mark the element.
		if ( false === strpos( $tag, '=' ) && ! preg_match( '/\s(?:hidden|data-aa-nolink|data-nolink)(?:\s|>|\/)/i', $tag ) ) {
			return false;
		}
		$at = self::parse_attrs( $tag );

		if ( isset( $at['hidden'] ) ) {
			return true;
		}
		if ( isset( $at['data-aa-nolink'] ) || isset( $at['data-nolink'] ) ) {
			return true;
		}
		if ( isset( $at['aria-hidden'] ) && 'true' === strtolower( trim( $at['aria-hidden'] ) ) ) {
			return true;
		}
		if ( isset( $at['style'] ) && preg_match( '~(?:^|;)\s*(?:display\s*:\s*none|visibility\s*:\s*hidden|opacity\s*:\s*0(?:\.0+)?\s*(?:;|$)|font-size\s*:\s*0(?:px|em|rem|%)?\s*(?:;|$)|text-indent\s*:\s*-\d{3,}|(?:left|top|right)\s*:\s*-\d{3,}px|clip(?:-path)?\s*:\s*(?:rect\(0|inset\(50%)|(?:max-)?height\s*:\s*0(?:px)?\s*(?:;|$)|(?:max-)?width\s*:\s*0(?:px)?\s*(?:;|$)|content-visibility\s*:\s*hidden)~i', $at['style'] ) ) {
			return true;
		}
		if ( isset( $at['role'] ) && isset( $this->block_roles[ strtolower( trim( $at['role'] ) ) ] ) ) {
			return true;
		}
		if ( isset( $at['id'] ) ) {
			$id = AA_Url::lower( trim( $at['id'] ) );
			if ( isset( $this->block_ids[ $id ] ) || isset( $this->extra_ids[ $id ] ) ) {
				return true;
			}
		}
		if ( isset( $at['class'] ) && '' !== trim( $at['class'] ) ) {
			$tokens = preg_split( '/\s+/', AA_Url::lower( trim( $at['class'] ) ), -1, PREG_SPLIT_NO_EMPTY );
			$set    = array_flip( $tokens );
			foreach ( $tokens as $t ) {
				if ( isset( $this->block_classes[ $t ] ) || isset( $this->extra_classes[ $t ] ) ) {
					return true;
				}
				foreach ( $this->block_prefixes as $pre ) {
					if ( 0 === strpos( $t, $pre ) ) {
						return true;
					}
				}
				if ( $this->o['skip_accordions'] && isset( $this->accordion_classes[ $t ] ) ) {
					return true;
				}
			}
			// Bootstrap "collapse" is hidden unless it also has "show" or "in".
			if ( $this->o['skip_accordions'] && isset( $set['collapse'] ) && ! isset( $set['show'] ) && ! isset( $set['in'] ) ) {
				return true;
			}
		}
		return false;
	}

	public static function parse_attrs( $tag ) {
		$out = array();
		$tag = preg_replace( '~^</?[a-zA-Z][a-zA-Z0-9:_-]*~', '', $tag );
		if ( preg_match_all( '~([a-zA-Z_:@][-a-zA-Z0-9_:.@]*)(?:\s*=\s*(?:"([^"]*)"|\'([^\']*)\'|([^\s"\'>]+)))?~', $tag, $m, PREG_SET_ORDER ) ) {
			foreach ( $m as $a ) {
				$k = strtolower( $a[1] );
				if ( isset( $out[ $k ] ) ) {
					continue;
				}
				$v = '';
				if ( isset( $a[2] ) && '' !== $a[2] ) {
					$v = $a[2];
				} elseif ( isset( $a[3] ) && '' !== $a[3] ) {
					$v = $a[3];
				} elseif ( isset( $a[4] ) ) {
					$v = $a[4];
				}
				$out[ $k ] = $v;
			}
		}
		return $out;
	}

	/* ------------------------------------------------------------------ */
	/* Text decoding with an offset map (keeps original entities intact)   */
	/* ------------------------------------------------------------------ */

	private function decode( $raw ) {
		if ( false === strpos( $raw, '&' ) ) {
			return array( $raw, null );
		}
		$pieces = preg_split( self::ENTITY_RX, $raw, -1, PREG_SPLIT_DELIM_CAPTURE );
		$dec    = '';
		$map    = array(); // Each: dec start, orig start, dec len, orig len.
		$opos   = 0;
		foreach ( $pieces as $k => $p ) {
			if ( '' === $p ) {
				continue;
			}
			$d = $p;
			if ( 1 === $k % 2 ) {
				$x = html_entity_decode( $p, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
				if ( $x !== $p ) {
					$d = $x;
				}
			}
			$map[] = array( strlen( $dec ), $opos, strlen( $d ), strlen( $p ) );
			$dec  .= $d;
			$opos += strlen( $p );
		}
		return array( $dec, $map );
	}

	private function orig_offset( $map, $x ) {
		if ( null === $map ) {
			return $x;
		}
		$last = null;
		foreach ( $map as $m ) {
			if ( $x < $m[0] ) {
				break;
			}
			if ( $x <= $m[0] + $m[2] ) {
				if ( $m[2] === $m[3] ) {
					return $m[1] + ( $x - $m[0] ); // Plain text piece.
				}
				return ( $x === $m[0] ) ? $m[1] : $m[1] + $m[3]; // Entity: start or end only.
			}
			$last = $m;
		}
		if ( $last ) {
			return $last[1] + $last[3];
		}
		return $x;
	}

	private function page_words( array $segments ) {
		$set = array();
		foreach ( $segments as $seg ) {
			if ( preg_match_all( '/[\p{L}\p{N}]+/u', AA_Url::lower( $seg['text'] ), $m ) ) {
				foreach ( $m[0] as $w ) {
					$set[ $w ] = true;
				}
			}
		}
		return $set;
	}

	/* ------------------------------------------------------------------ */
	/* Matching                                                            */
	/* ------------------------------------------------------------------ */

	private function compile( array $r ) {
		$id = isset( $r['id'] ) ? $r['id'] : $r['anchor'];
		$ck = $id . '|' . ( ! empty( $r['case'] ) ? 1 : 0 );
		if ( isset( $this->rx_cache[ $ck ] ) ) {
			return $this->rx_cache[ $ck ];
		}
		$tokens = preg_split( '/\s+/u', trim( $r['anchor'] ), -1, PREG_SPLIT_NO_EMPTY );
		$parts  = array();
		foreach ( $tokens as $t ) {
			if ( in_array( $t, array( '-', "\xE2\x80\x93", "\xE2\x80\x94" ), true ) ) {
				$parts[] = '[-\x{2010}-\x{2015}\x{2212}]';
				continue;
			}
			$q = preg_quote( $t, '~' );
			$q = strtr(
				$q,
				array(
					"'"   => '[\'\x{2018}\x{2019}\x{02BC}]',
					"\xE2\x80\x99" => '[\'\x{2018}\x{2019}\x{02BC}]',
					"\xE2\x80\x98" => '[\'\x{2018}\x{2019}\x{02BC}]',
					'"'   => '["\x{201C}\x{201D}\x{201E}]',
					'\\-' => '[\-\x{2010}\x{2011}]',
				)
			);
			$parts[] = $q;
		}
		$body = implode( '[\s\x{00A0}]+', $parts );
		$pre  = '(?<![\p{L}\p{N}_])(?<![\p{L}\p{N}][-\x{2010}\x{2011}])';
		$post = '(?![\p{L}\p{N}_])(?![-\x{2010}\x{2011}][\p{L}\p{N}])';
		$rx   = '~' . $pre . $body . $post . '~u' . ( ! empty( $r['case'] ) ? '' : 'i' );
		$this->rx_cache[ $ck ] = $rx;
		return $rx;
	}

	/**
	 * Find occurrences not overlapping text already claimed by a longer phrase.
	 *
	 * @return array List of array( segment index, start, end ).
	 */
	private function find( array $r, array $segments, array $claims, array &$blockers ) {
		$rx  = $this->compile( $r );
		$occ = array();
		foreach ( $segments as $si => $seg ) {
			$n = @preg_match_all( $rx, $seg['text'], $m, PREG_OFFSET_CAPTURE );
			if ( ! $n ) {
				continue;
			}
			foreach ( $m[0] as $hit ) {
				$s = $hit[1];
				$e = $s + strlen( $hit[0] );
				$b = null;
				if ( ! empty( $claims[ $si ] ) ) {
					foreach ( $claims[ $si ] as $c ) {
						if ( $s < $c[1] && $e > $c[0] ) {
							$b = $c[2];
							break;
						}
					}
				}
				if ( null !== $b ) {
					$blockers[] = $b;
					continue;
				}
				$occ[] = array( $si, $s, $e );
			}
		}
		return $occ;
	}

	private function skip( array $r, $reason, $detail ) {
		return array(
			'id'     => isset( $r['id'] ) ? $r['id'] : 0,
			'anchor' => $r['anchor'],
			'reason' => $reason,
			'detail' => $detail,
		);
	}

	private function open_tag( array $r ) {
		$url = htmlspecialchars( $r['url'], ENT_QUOTES, 'UTF-8' );
		$nf  = ( isset( $r['nofollow'] ) && -1 !== (int) $r['nofollow'] ) ? (bool) $r['nofollow'] : (bool) $this->o['nofollow'];
		$nt  = ( isset( $r['new_tab'] ) && -1 !== (int) $r['new_tab'] ) ? (bool) $r['new_tab'] : (bool) $this->o['new_tab'];
		$tag = '<a href="' . $url . '"';
		$cls = trim( (string) $this->o['link_class'] );
		if ( '' !== $cls ) {
			$tag .= ' class="' . htmlspecialchars( $cls, ENT_QUOTES, 'UTF-8' ) . '"';
		}
		if ( $nt ) {
			$tag .= ' target="_blank"';
		}
		$rel = array();
		if ( $nf ) {
			$rel[] = 'nofollow';
		}
		if ( $nt ) {
			$rel[] = 'noopener';
		}
		if ( $rel ) {
			$tag .= ' rel="' . implode( ' ', $rel ) . '"';
		}
		return $tag . '>';
	}
}
