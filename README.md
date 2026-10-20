# Calico MCP

Private development and release repository for a single-operator WordPress MCP connector. Skills provide task instructions; this plugin supplies authenticated WordPress and plugin operations.

## Current package

Version: **0.1.0-beta.22**, a prerelease for staging evaluation. Download the **calico-mcp.zip** release asset. GitHub's automatic source ZIP includes the source wrapper and is not the installable plugin.

SHA256: `E6F8084BFE929A2FBB4DAEA306463D7BEDB13DE3FC3ED5D1230B01334EB5B9CE`. The attached asset is the unchanged locally tested package. Its bundled readme.txt is historical and still contains beta 5 descriptions and a stale Stable tag; the PHP header/runtime version is beta 22. This repository README and release notes describe the current capabilities. Correct the bundled documentation in the next version without replacing this frozen ZIP.

## Operations

- Posts/pages, media uploads and metadata, selected CPTs and declared supported scalar fields.
- Categories/tags and selected custom taxonomies; duplication into drafts, registered templates and guarded native revision restoration.
- Existing repeater scalar-cell edits; separate permission for append/remove/reorder of supported rows.
- Native Elementor layout/content/style operations and supported rendered preview; compatibility depends on actual widgets and document types.
- Yoast metadata and advanced per-item controls.
- Gravity Forms sensitive reads, CSV export and limited entry read-status/Trash management. No form editing or notification sending.

Already-published selected CPT and full Elementor post/page edits require the additional published-content permission. Published editing and structural repeater permissions default off. New content and duplicates remain drafts. No publish/schedule/unpublish tools are exposed. No general site/user/plugin administration, arbitrary metadata access or theme-file editing is provided.

## Requirements and installation

WordPress 6.9 or newer; PHP 7.4 or newer. Relevant optional plugins must be installed for their operations. Start on a recoverable staging clone. Deactivate miniOrange MCP on that clone before activation because the servers share OAuth discovery paths. Install the release ZIP through WordPress Plugins, activate it, open Calico MCP and enable access for the intended administrator. Start read-only; explicitly select supported custom types, fields and taxonomies. Enter the supplied connection URL into the MCP client and complete WordPress login/consent.

Scope changes revoke existing connections and require reauthorization. Enable only needed write groups. Edits to published items affect public content immediately. Use fresh expected_revision values; successful guarded edits provide recovery records. Administrator Undo refuses newer work. Recovery is retained for 30 days; activity defaults to 90 days. Removal does not automatically purge stored settings or records.

## Updates and rollback

This version has **no GitHub updater**. Updates are manual ZIP replacements through WordPress. The Update URI header is currently false to prevent an unrelated WordPress.org package replacing this plugin. A future private updater needs authenticated, repository-scoped read access configured server-side, never embedded credentials. Automatic installation should stay off initially. The first updater-enabled build requires one manual upload.

Back up the site before an update, retain the prior ZIP and verify settings, connection and behavior on the clone. Reinstalling an older ZIP rolls back code only; restore content with Undo or a site backup. No production deployment is authorized by this repository.

## Verification

381 exact-package local checks passed: 45 core, 103 retained-feature, 26 legacy Elementor, 123 JetEngine/Yoast and 84 published-content/repeater checks. All 84 PHP files parse on PHP 7.4.33 and 8.3.33; all 88 package files match source. Disposable runtime tests used WordPress 7.1.3/PHP 8.3.33 with actual JetEngine 3.8.9.1, Elementor 4.3.4 and Yoast 28.7 as applicable. Nineteen Gravity Forms checks use simulated provider contracts. OAuth consent uses a code fixture; transport, PKCE, native authorization, saves and recovery use real code. This is not a production-clone visual test or a beta 22 remote deployment.

See verification-summary.json for counts. Full internal tests/evidence remain in the canonical local project; they are not all included in this initial repository snapshot.

## License

MIT/Expat-derived code. Preserve LICENSE and source/calico-mcp/UPSTREAM-NOTICES.md. Calico MCP is independent derivative software and uses no miniOrange hosted gateway, license key or account.
