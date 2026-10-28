# Calico MCP

Direct WordPress MCP tools for one configured administrator. Skills supply workflow instructions; the connector supplies authenticated site operations.

Version: **0.1.0-beta.37**, for staging evaluation. Install the **calico-mcp.zip** release asset, not GitHub's automatic source archive.

Beta37 fixes WP-Optimize adding an HTML cache comment to OAuth discovery JSON. It uses request-local cache/comment filters for Calico responses, preserving saved cache settings and normal site processing. No WP-Optimize files, OAuth grants or registrations are changed.

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

The exact beta37 ZIP passed 70 local integration checks: 25 focused WP-Optimize compatibility checks and 45 core OAuth/content/media/Yoast checks. All 89 PHP files parse under PHP7.4.33 and PHP8.3. Local runtime: WordPress7.1.3/PHP8.3, official WP-Optimize4.7.0 with caching enabled, and real Yoast28.7 in the core suite. The compatibility suite reproduced beta36 invalid JSON, then verified clean beta37 discovery, no-store headers, normal page cache processing, isolated bypass and preserved settings/registration. Subdirectory URLs use an explicit local fixture override.

A separately approved hosted upgrade verified clean discovery with WP-Optimize4.7.0 page caching enabled, the unauthenticated MCP challenge, normal homepage processing and preservation of all 92 access choices plus the original app registration. The original cache/plugin state was restored. An authenticated read through the existing AI connection was unavailable in that chat and remains unverified; registration preservation alone is not proof of a usable token or fresh OAuth login.

Beta36 previously passed 430 local checks and 27 focused hosted checks; those are retained prior-version evidence, not beta37 reruns. The native GitHub update flow was previously verified from beta28 to beta29. Beta37 was installed manually for its hosted retest; a live Dashboard upgrade into beta37 has not been performed. The fix cannot change a response served by an external cache before WordPress loads. See verification-summary.json and CHANGELOG.md.

## License

Independent MIT/Expat-derived software. Preserve LICENSE and source/calico-mcp/UPSTREAM-NOTICES.md. No miniOrange gateway, account or license is required.
