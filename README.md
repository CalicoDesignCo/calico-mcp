# Calico MCP

Direct WordPress MCP tools for one configured administrator. Skills supply workflow instructions; this connector supplies authenticated site operations.

Version: **0.1.0-beta.28**. This is a beta release for staging evaluation. Download the **calico-mcp.zip** release asset, not GitHub's automatic source archive.

Adds the public GitHub update channel. A first manual installation is required on versions without an updater.

## Included

- Posts/pages, media and metadata; draft creation and precise edits.
- Registered CPT/JetEngine field discovery, selected custom content and taxonomies; optional Bridge selection import.
- Standalone native JetEngine Advanced Date, declared static radio/select choices and supported generic repeater operations.
- Duplication into drafts, registered templates, categories/tags and supported native WordPress revision management.
- Legacy Elementor content/style/layout operations, complete page-setting preservation and guarded Undo.
- Yoast metadata and advanced per-item controls.

Gravity Forms integration is removed. No publishing, scheduling, user administration, theme-file editing, arbitrary REST execution or permanent deletion tools are exposed. No Jet Engine Dynamic Calendar Post Repeater integration is included.

## Install and connect

Requires WordPress 6.9+ and PHP 7.4+. Start on staging with a site backup. Deactivate miniOrange MCP before activation because both own OAuth discovery paths. Install calico-mcp.zip through Plugins -> Add Plugin -> Upload Plugin. Enable Calico MCP for the intended administrator and begin read-only. Select supported CPT fields/taxonomies, copy the displayed MCP Connection URL into your client and complete WordPress login/consent.

Permission changes revoke existing connections. Published-item edits require an extra permission and affect public content immediately; new items remain drafts. Existing edits require a fresh revision token. Calico Undo is available for 30 days and refuses newer work. Native WordPress revision restoration is refused for Elementor documents/revisions because its hooks can restore layout beyond the core comparison; use Calico Undo or Elementor History manually.

## Updates

The public release channel needs no GitHub token. Numbered beta releases are intentionally included. WordPress Plugins or Dashboard -> Updates -> Check again discovers newer releases. Downloads use an exact version-pinned calico-mcp.zip asset and verify the GitHub SHA256 digest before installation. Automatic installation is not enabled by this plugin. Hosts must allow outbound HTTPS to GitHub. Failed checks leave the installed plugin usable.

Install beta 28 or newer manually once to bootstrap updates from older versions. Back up before updates; preserve the previous ZIP for code rollback. Settings/data survive code updates. Restore content separately with Calico Undo, Elementor History or a site backup.

## Verification

The exact package passed 171 local checks: 45 core OAuth/content/media/Yoast/recovery plus 126 retained capability/removal/updater checks. Tests use disposable WordPress 7.1.3/PHP 8.3, actual Elementor 4.3.4 and Yoast 28.7. Updater transport tests substitute GitHub responses; a separate native upgrader trial exercises beta 28 to beta 29 with exact ZIPs. These local tests do not claim live delivery or exhaustive third-party widget compatibility. See verification-summary.json.

## License

MIT/Expat-derived independent software. Preserve LICENSE and source/calico-mcp/UPSTREAM-NOTICES.md. No miniOrange gateway, account or license is required.