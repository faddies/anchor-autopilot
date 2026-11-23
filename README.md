# Anchor Autopilot – Auto Internal Links

WordPress plugin that automatically links your chosen phrases to your chosen URLs, inside **body content only**.

## What it does
- **Keyword rules:** anchor + URL, added one by one or by CSV. The phrase is linked wherever it appears in posts/pages.
- **Page-specific rules:** CSV or form with **From, To, Anchor**. The link is added only on the From page.
- **Smart overlap handling:** with "abc 123 def" and "123 def", the longer phrase wins and the shorter one is never linked inside it (and still links where it stands alone).
- One anchor never goes to two URLs on a page; caps per page / keyword / URL; no self-links; existing links respected.
- **Body text only.** Skips menus, header, footer, sidebars, headings, buttons, forms, code, hidden content, alt/title attributes and comments.
- **Non-destructive.** Links are added at render time; saved content is never modified.

## Install
1. Download `anchor-autopilot-1.0.0.zip`.
2. WordPress admin → Plugins → Add New → Upload Plugin → activate.
3. Open **Anchor Autopilot** in the admin menu.
4. Clear any page cache after changing rules.

Requires WordPress 5.8+ and PHP 7.4+.

## CSV formats
Keyword rules (`anchor,url`, optional: `case_sensitive,max,nofollow,new_tab,status`)

    anchor,url
    web design Dubai,/web-design-dubai/

Page-specific rules (`from,to,anchor`)

    from,to,anchor
    /blog/seo-tips/,/services/seo/,SEO services

Sample files are downloadable from the Import / Export screen.

## Admin screens
Rules · Import / Export · Conflicts (health check) · Test (dry run, nothing saved) · Settings.

## Per-post control
Tick "Do not add automatic links on this page" on the post edit screen, or add the CSS class `aa-nolink` to any block.

## Filters for developers
Filters: `aa_capability`, `aa_skip_tags`, `aa_allow_tags`, `aa_block_classes`, `aa_block_class_prefixes`, `aa_block_ids`, `aa_should_process`, `aa_max_content_bytes`.

## License
GPL-2.0-or-later.
