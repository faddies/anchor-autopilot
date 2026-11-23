# Changelog

## 1.1.1
- Fix: links were never added when a theme or page builder (for example an Elementor theme template) prints the page text outside the classic WordPress loop. The plugin now follows the main page query instead.
- Fix: the Link Report scan showed zeros for Elementor pages because it could not read their text. It now reads the live page (with linking held back), and any page it still cannot read is listed with a warning instead of silently counted as zero.
- "Check this page" now also loads the real page and reports whether the plugin actually ran on it and which links it added.
- Large page-builder pages: processing limit raised from 600 KB to 2 MB.

## 1.1.0
- New **Link Report** screen: scan the site once and see, for every rule, which pages it linked on (and how many times), which pages contain the phrase but were skipped (with the reason), and which only contain it in a protected place (heading, menu, hidden, existing link).
- "Check one page": paste a URL and see exactly what happened on that page.
- By rule / By page / Rules not linked anywhere views, and CSV download of the whole report.
- New "Live links" column on the Rules list.
- Clearer reason when a page already has a hand-made link to the same destination.

## 1.0.0 – first release
- Site-wide keyword rules (anchor → URL), added one by one or by CSV upload.
- Page-specific rules (From page → To URL + anchor), added one by one or by CSV, applied only on the From page.
- Overlap protection: longest phrase wins, and a shorter phrase is never linked inside a longer one ("abc 123 def" vs "123 def").
- One destination per anchor per page, per-page / per-keyword / per-URL link caps, no self-links, existing links respected.
- Body content only: menus, headers, footers, sidebars, headings, buttons, forms, code, hidden content, alt/title attributes and HTML comments are never touched.
- Links are added when the page is displayed. Your saved posts are never modified, so deactivating the plugin removes every link.
- Admin: Rules, Import / Export, Conflicts (health check), Test (dry run), Settings; per-post "disable auto links" box.
- Safe uninstall: data is kept unless "delete on uninstall" is ticked.
