=== Anchor Autopilot – Auto Internal Links ===
Contributors: fahad
Tags: internal links, seo, auto link, anchor text, internal linking
Requires at least: 5.8
Tested up to: 7.2
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Automatically link your chosen phrases to your chosen URLs, inside body content only, without overlapping or broken links.

== Description ==

Add an anchor text and a URL (one by one or by CSV) and the plugin links that phrase wherever it appears in your post and page content. Or upload a CSV with From, To and Anchor to add a link on one specific page only.

**Built to avoid the usual auto-linking mess**

* Overlapping phrases are handled: with "abc 123 def" and "123 def" both set up, the longer phrase gets the link and the shorter one is not linked inside it.
* One anchor never points to two different URLs on the same page.
* Only real body text is linked. Menus, headers, footers, sidebars, headings, buttons, forms and code are skipped.
* Hidden content, image alt text, title attributes and HTML comments are never touched.
* No self-links, and links you added by hand are respected.
* Limits per page, per keyword and per URL.

**Safe by design**

Links are added at display time. Your saved content is never changed, so turning the plugin off removes every automatic link.

**Tools**

* Test screen: see exactly which links would be added, and why others were skipped, before anything goes live.
* Conflicts screen: spots overlapping phrases and duplicates.
* Import / export CSV, with sample files.

== Installation ==

1. Upload the `anchor-autopilot` folder to `/wp-content/plugins/` or install the ZIP from Plugins > Add New > Upload.
2. Activate the plugin.
3. Open Anchor Autopilot in the admin menu and add your first rule.
4. If you use a caching plugin, clear its cache after changing rules.

== Frequently Asked Questions ==

= Does it change my saved posts? =
No. Links are added when the page is displayed.

= Does it link inside headings or menus? =
No. Strict mode links only paragraph, list, table-cell and quote text.

= How do I stop links on one post? =
Tick "Do not add automatic links on this page" in the Anchor Autopilot box on the post edit screen, or add the class `aa-nolink` to any block.

= What CSV columns do I need? =
Keyword rules: anchor, url. Page-specific rules: from, to, anchor.

== Changelog ==

= 1.0.0 =
First release.
