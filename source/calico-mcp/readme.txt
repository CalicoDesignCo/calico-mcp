=== Calico MCP ===
Contributors: Calico Design
Requires at least: 6.9
Requires PHP: 7.4
Stable tag: 0.1.0-beta.29
License: MIT

Personal-use beta. Direct OAuth-protected MCP tools for WordPress, selected custom content, Elementor and Yoast SEO.

== Setup ==
Use on staging first. Deactivate miniOrange MCP before activating Calico MCP because the two servers share OAuth discovery paths. No miniOrange settings are imported or deleted.

Open Calico MCP, enable access for your current administrator account, and select any editing permissions you need. Reading is the default. Paste the Connection URL into your remote MCP client and complete WordPress login and consent.

New content and duplicates stay in draft. Selected custom content fields, taxonomies, duplication/templates/revisions, full legacy Elementor layouts and advanced Yoast controls have separate editing permissions. Already-published custom content and Elementor edits need additional explicit permission and affect the live item immediately. Gravity Forms integration is not included. Existing forms, submissions and historical recovery records are retained; old entry recovery actions are unavailable.

Registered WordPress and JetEngine declarations provide custom-field schemas. Optional existing Bridge selections can be imported; undeclared stored fields remain read-only. Static radio/select choices expose their allowed values. Native JetEngine Advanced Date supports a single standalone date and optional end through its active provider. Structured date patches preserve omitted parts; recurring/multiple-date configurations remain read-only. No integration with Jet Engine Dynamic Calendar Post Repeater is included.

Publishing, scheduling, permanent deletion, broad site administration, arbitrary REST execution, user management, theme-file edits and atomic Elementor layout editing are not exposed. Public GitHub release checks support numbered beta releases and version-pinned calico-mcp.zip assets with SHA256 verification. No token is required. Updates use WordPress Plugins or Dashboard Updates; automatic installation remains under your control. The first updater-enabled build requires one manual ZIP installation.

Existing-item edits require expected_revision from a fresh read. Successful edits return recovery_id. Recovery is available in the Calico MCP admin screen and refuses to overwrite newer work.

Native WordPress revision restoration supports non-Elementor drafts only. Elementor documents and revisions with Elementor metadata are refused before mutation because Elementor's restore hooks can change layout beyond the core title/content/excerpt comparison. Use Calico Undo or Elementor History for Elementor recovery. Revision comparison reports this limitation explicitly.

Changing access settings and deactivating the plugin revoke connection credentials. Per-client revoke controls remove authorization codes, access tokens and refresh tokens. Recovery records are retained for 30 days; activity logs use a 90-day default. Plugin removal does not automatically purge stored settings or records.

Yoast tools require an active Yoast plugin. This is independent derivative software; see UPSTREAM-NOTICES.md and LICENSE for attribution.

== Changelog ==
= 0.1.0-beta.25 =
Exclude the verified numeric Essential Addons view counter from authored revision comparisons and recovery deltas. Preserve analytics during Undo. Unexpected or duplicate counter values and all other metadata remain guarded. Older recovery hashes remain valid only when their complete legacy state still matches.

= 0.1.0-beta.24 =
Local candidate: static radio choices and native JetEngine single-date configuration/readback/recovery. Preserve Elementor media options and refresh image alt metadata. New recovery records identify changed keys on conflict without disclosing values; the revision guard remains strict. The Hydrate full-preview Undo conflict still requires host diagnosis. No Select all controls or recurrence-plugin integration in this build.

Select all and Clear all controls are available for optional editing permissions, all custom content, and each content-type block. They change only unsaved form choices until Save access is selected. Global custom-content selection does not enable editing or AI access. Permission changes still revoke existing connections.
