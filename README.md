# Calico MCP

Direct WordPress MCP tools for one configured administrator. Skills supply workflow instructions; the connector supplies authenticated site operations.

Version: **0.1.0-beta.38**, for staging evaluation. Install the **calico-mcp.zip** release asset, not GitHub's automatic source archive.

Beta38 fixes Select all and Clear all skipping newly added optional editing permissions. The controls now include all enabled checkboxes in their section, including publishing, scheduling, status changes, existing-item editing, template assignment and Trash. Selections take effect only after Save access.

## Included

- WordPress posts/pages, media and metadata; creation and duplication remain drafts. Separate default-off permissions allow Publish, Schedule/reschedule and status changes to Draft, Pending Review or Private.
- Guarded CPT, repeater, taxonomy, Yoast and full Elementor edits across explicitly enabled statuses. Published editing remains a separate permission. Scheduled edits preserve dates/cron and refuse items due within two minutes.
- Separately gated native Trash and Restore to Draft, plus registered-template assignment on enabled existing-item statuses. No permanent-delete tool; site Trash retention still applies.
- Custom-field support explanations and simpler permission groups. Redundant aliases are hidden only when an authorized replacement is available; cached aliases retain their original access checks.
- Registered CPT/JetEngine field discovery, selected custom fields and taxonomies, optional Bridge selection import, standalone native dates and supported repeater operations.
- Duplication, registered page templates, categories/tags and supported WordPress draft revisions.
- Legacy Elementor content/style/layout tools, guarded edits and administrator Undo.
- Existing shared Elementor library, JetThemeCore and Essential Addons documents through full Elementor tools. Shared editing defaults off; published shared documents also need published-item permission. Assignment/display conditions are not exposed as editing tools.
- Yoast per-item metadata and advanced controls, plus separately enabled organization social-profile patches. Organization identity, logos and arbitrary options are not exposed.
- Deleted-account ownership recovery that preserves selections and leaves access disabled. Permission changes revoke codes/tokens while retaining durable client registrations for fresh login. Explicit Revoke removes the selected registration too.

Gravity Forms integration and Jet Engine Dynamic Calendar Post Repeater are excluded. No permanent deletion, user administration, theme-file editing, arbitrary REST execution or atomic Elementor layout editing tools are exposed.

## Install and connect

Requires WordPress6.9+ and PHP7.4+. Start on staging with a backup. Deactivate miniOrange MCP before activation because both use the same OAuth discovery paths. Install calico-mcp.zip through Plugins → Add Plugin → Upload Plugin. Enable access for the intended administrator and begin read-only. Select needed custom fields/taxonomies, copy the displayed Connection URL into the AI client, then complete WordPress login and consent.

Editing is optional. Select all/Clear all changes form choices only until Save access. Published-item editing affects live content immediately and requires its extra permission. Existing-item edits require a fresh expected_revision. Calico Undo retains recovery for30 days and refuses newer work. Native WordPress revision restoration refuses Elementor documents/revisions; use Calico Undo or Elementor History manually.

## Updates

Public GitHub checks require no token. Numbered beta releases are intentionally included. WordPress Plugins or Dashboard → Updates → Check again retrieves newer releases. Updates use an exact version-pinned asset and check its GitHub SHA256 digest. Automatic installation is not forced. Hosts must allow outbound HTTPS to GitHub; failed checks leave installed code usable.

Older installations without the updater need one manual beta28-or-newer installation. Settings/data survive code updates. Permission changes expire existing sessions; reconnect afterward. Explicitly revoking an app forgets its registration; already-deleted client registrations cannot be rebuilt automatically.

## Verification and limits

Beta38 changes only the permission-selection JavaScript and release version/readme. Local selection regression covers all 19 current permissions, future additions, Clear all, disabled controls, section isolation, change events and global/per-CPT selectors. The exact installable ZIP passed 45 core integration checks on disposable WordPress7.1.3/PHP8.3 with official Yoast28.7, including activation, OAuth/PKCE, permission enforcement, guarded content/media/SEO operations, Undo, revocation and audit. A browser fixture verified all 19 permissions select/clear and Enable AI access stays unchanged. See verification-summary.json. Hosted installation and a native Dashboard update into beta38 have not been performed.

Beta37 WP-Optimize discovery compatibility was previously verified locally and on staging; this is retained prior-version evidence. The native GitHub update flow was previously verified from beta28 to beta29. Existing beta28-or-newer installations contain the updater.

## License

Independent MIT/Expat-derived software. Preserve LICENSE and source/calico-mcp/UPSTREAM-NOTICES.md. No miniOrange gateway, account or license is required.
