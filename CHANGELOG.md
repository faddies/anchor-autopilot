# Changelog

## 1.0.0 – first release
- Site-wide keyword rules (anchor → URL), added one by one or by CSV upload.
- Page-specific rules (From page → To URL + anchor), added one by one or by CSV, applied only on the From page.
- Overlap protection: longest phrase wins, and a shorter phrase is never linked inside a longer one ("abc 123 def" vs "123 def").
- One destination per anchor per page, per-page / per-keyword / per-URL link caps, no self-links, existing links respected.
- Body content only: menus, headers, footers, sidebars, headings, buttons, forms, code, hidden content, alt/title attributes and HTML comments are never touched.
- Links are added when the page is displayed. Your saved posts are never modified, so deactivating the plugin removes every link.
- Admin: Rules, Import / Export, Conflicts (health check), Test (dry run), Settings; per-post "disable auto links" box.
- Safe uninstall: data is kept unless "delete on uninstall" is ticked.
