# Calico MCP

Direct WordPress MCP tools for one configured administrator. Skills supply workflow instructions; the connector supplies authenticated site operations.

Version: **0.1.0-beta.32**, for staging evaluation. Install the **calico-mcp.zip** release asset, not GitHub's automatic source archive.

Beta32 fixes unrelated stored addon settings being omitted during Elementor saves. It retains only existing unchanged settings on the same element identity/type, preserves explicit removals, and refuses unexpected changes to unrelated controls. New or changed values still use Elementor's normal save path.

## Included

- WordPress posts/pages, media and metadata; new content and duplicates remain drafts.
- Registered CPT/JetEngine field discovery, selected custom fields and taxonomies, optional Bridge selection import, standalone native dates and supported repeater operations.
- Duplication, registered page templates, categories/tags and supported WordPress draft revisions.
- Legacy Elementor content/style/layout tools, guarded edits and administrator Undo.
- Existing shared Elementor library, JetThemeCore and Essential Addons documents through full Elementor tools. Shared editing defaults off; published shared documents also need published-item permission. Assignment/display conditions are not exposed as editing tools.
- Yoast per-item metadata and advanced controls, plus separately enabled organization social-profile patches. Organization identity, logos and arbitrary options are not exposed.
- Deleted-account ownership recovery that preserves selections and leaves access disabled. Permission changes revoke codes/tokens while retaining durable client registrations for fresh login. Explicit Revoke removes the selected registration too.

Gravity Forms integration and Jet Engine Dynamic Calendar Post Repeater are excluded. No publishing, scheduling, permanent deletion, user administration, theme-file editing, arbitrary REST execution or atomic Elementor layout editing tools are exposed.

## Install and connect

Requires WordPress6.9+ and PHP7.4+. Start on staging with a backup. Deactivate miniOrange MCP before activation because both use the same OAuth discovery paths. Install calico-mcp.zip through Plugins → Add Plugin → Upload Plugin. Enable access for the intended administrator and begin read-only. Select needed custom fields/taxonomies, copy the displayed Connection URL into the AI client, then complete WordPress login and consent.

Editing is optional. Select all/Clear all changes form choices only until Save access. Published-item editing affects live content immediately and requires its extra permission. Existing-item edits require a fresh expected_revision. Calico Undo retains recovery for30 days and refuses newer work. Native WordPress revision restoration refuses Elementor documents/revisions; use Calico Undo or Elementor History manually.

## Updates

Public GitHub checks require no token. Numbered beta releases are intentionally included. WordPress Plugins or Dashboard → Updates → Check again retrieves newer releases. Updates use an exact version-pinned asset and check its GitHub SHA256 digest. Automatic installation is not forced. Hosts must allow outbound HTTPS to GitHub; failed checks leave installed code usable.

Older installations without the updater need one manual beta28-or-newer installation. Settings/data survive code updates. Permission changes expire existing sessions; reconnect afterward. Explicitly revoking an app forgets its registration; already-deleted client registrations cannot be rebuilt automatically.

## Verification and limits

The exact beta32 package passed217 local integration checks:45 core OAuth/content/media/Yoast/recovery and172 retained-feature checks. All86 PHP files parse under PHP7.4 and PHP8.3. Local runtime: WordPress7.1.3/PHP8.3, real Elementor4.3.4 and Yoast28.7. Addon omission is simulated through an Elementor save hook in the regression fixture.

A separately approved host test with installed Elementor4.3.2/Pro and addons passed14 checks: native header edit, unchanged mask/layout settings, stale-write refusal, unchanged published source, exact layout Undo, and test credential revocation. Existing access choices and app registration were preserved. Earlier scoped footer and Yoast organization edit/Undo trials passed on beta31. These tests do not establish universal addon/widget compatibility or rendered public-schema/cache behavior. Undo may change WordPress timestamps even when the exact layout is restored.

The ordinary WordPress GitHub upgrade flow was previously verified from beta28 to beta29. Beta32 was installed manually for its host retest; a beta32 native GitHub upgrade is a separate check. See verification-summary.json and CHANGELOG.md.

## License

Independent MIT/Expat-derived software. Preserve LICENSE and source/calico-mcp/UPSTREAM-NOTICES.md. No miniOrange gateway, account or license is required.
