=== Calico MCP ===
Contributors: Calico Design
Requires at least: 6.9
Requires PHP: 7.4
Stable tag: 0.1.0-beta.36
License: MIT

Personal-use beta. Direct OAuth-protected MCP tools for WordPress, selected custom content, Elementor and Yoast SEO.

== Setup ==
Use on staging first. Deactivate miniOrange MCP before activating Calico MCP because the two servers share OAuth discovery paths. No miniOrange settings are imported or deleted.

Open Calico MCP, enable access for your current administrator account, and select any editing permissions you need. Reading is the default. Paste the Connection URL into your remote MCP client and complete WordPress login and consent.

Creation and duplication produce drafts. Separate default-off permissions allow publishing immediately, scheduling/rescheduling publication, or moving existing posts/pages/selected CPTs to Draft, Pending Review or Private. Scheduling requires an explicit ISO 8601 UTC offset and uses WordPress cron; publication time is separate from event dates. Native publishing capabilities, expected status and a fresh revision are required. Undo restores saved status/dates/cron but cannot retract external notifications or services. Shared-template status changes remain excluded. Native Trash and restore to Draft have a separate permission and never permanently delete through a connector tool. WordPress Trash retention still applies. Selected custom content fields, taxonomies, duplication/templates/revisions, full legacy Elementor layouts and advanced Yoast controls have separate editing permissions. Already-published custom content and Elementor edits need additional explicit permission and affect the live item immediately. Gravity Forms integration is not included. Existing forms, submissions and historical recovery records are retained; old entry recovery actions are unavailable.

Registered WordPress and JetEngine declarations provide custom-field schemas. Optional existing Bridge selections can be imported; undeclared stored fields remain read-only. Static radio/select choices expose their allowed values. Native JetEngine Advanced Date supports a single standalone date and optional end through its active provider. Structured date patches preserve omitted parts; recurring/multiple-date configurations remain read-only. No integration with Jet Engine Dynamic Calendar Post Repeater is included.

Permanent deletion, broad site administration, arbitrary REST execution, user management, theme-file edits and atomic Elementor layout editing are not exposed. Public GitHub release checks support numbered beta releases and version-pinned calico-mcp.zip assets with SHA256 verification. No token is required. Updates use WordPress Plugins or Dashboard Updates; automatic installation remains under your control. The first updater-enabled build requires one manual ZIP installation.

Existing-item edits require expected_revision from a fresh read. Supported edits return recovery_id. Native Trash/Restore uses WordPress recovery instead of a Calico Undo checkpoint. Recovery is available in the Calico MCP admin screen and refuses to overwrite newer work.

Native WordPress revision restoration supports non-Elementor drafts only. Elementor documents and revisions with Elementor metadata are refused before mutation because Elementor's restore hooks can change layout beyond the core title/content/excerpt comparison. Use Calico Undo or Elementor History for Elementor recovery. Revision comparison reports this limitation explicitly.

Changing access settings and deactivating the plugin expire connection credentials while preserving app registrations. Per-client revoke controls remove authorization codes, access tokens and refresh tokens. Recovery records are retained for 30 days; activity logs use a 90-day default. Plugin removal does not automatically purge stored settings or records.

Yoast tools require an active Yoast plugin. This is independent derivative software; see UPSTREAM-NOTICES.md and LICENSE for attribution.

Pending, Private and Scheduled custom content/Elementor edits need the new other-status permission; already-published edits retain their existing separate permission. Scheduled edits preserve dates and cron and refuse items due in less than two minutes. Existing non-draft registered template assignment also requires its own opt-in and the applicable status permission. Core post/page editing retains its existing status scope.

Custom field definitions and cpt-field-explain report why fields are read-only and the next supported action. Discovery prefers generic CPT and full Elementor tools when authorized; existing aliases remain callable with their original checks. Setup groups Content, Media, Elementor and SEO while retaining existing choices. No new permission is enabled automatically.

== Changelog ==
= 0.1.0-beta.36 =
Preserve local and GMT publication dates on existing-item edits even when an unpublished item has an unset GMT date. Scope protection to the exact item; explicit publication tools retain date control. Verify date and cron preservation for all non-lifecycle edits.

= 0.1.0-beta.35 =
Add guarded existing-status edits, native Trash/Restore to Draft, field support explanations and registered-template assignment across enabled statuses. Streamline discovery and permission groups without removing cached aliases or widening saved grants. Preserve schedules and refuse imminent publication. Native Trash follows site retention and refuses operation when retention is disabled.
= 0.1.0-beta.33 =
Add explicit publication, scheduling and status tools with separate permissions defaulting off. Preserve draft creation, include publication dates in revision guards, and recover status/dates/cron safely. Existing pre-beta33 Undo records retain their original comparison format. No permissions are enabled automatically.

= 0.1.0-beta.25 =
Exclude the verified numeric Essential Addons view counter from authored revision comparisons and recovery deltas. Preserve analytics during Undo. Unexpected or duplicate counter values and all other metadata remain guarded. Older recovery hashes remain valid only when their complete legacy state still matches.

= 0.1.0-beta.24 =
Local candidate: static radio choices and native JetEngine single-date configuration/readback/recovery. Preserve Elementor media options and refresh image alt metadata. New recovery records identify changed keys on conflict without disclosing values; the revision guard remains strict. The Hydrate full-preview Undo conflict still requires host diagnosis. No Select all controls or recurrence-plugin integration in this build.

Select all and Clear all controls are available for optional editing permissions, all custom content, and each content-type block. They change only unsaved form choices until Save access is selected. Global custom-content selection does not enable editing or AI access. Permission changes still revoke existing connections.


== Ownership recovery ==
If the configured account has been deleted, a current administrator can explicitly Recover ownership. Saved editing and custom content selections are preserved, old connections are revoked, and AI access stays disabled until reviewed and enabled. An existing account cannot be displaced through recovery.


== Shared templates and organization profiles ==
Beta31 adds shared-template-list and permits existing Elementor library, JetThemeCore and Essential Addons documents through full Elementor tools. Shared-template editing defaults off and needs Full Elementor plus published-item permission for published templates. Template assignment/conditions are not directly edited. Organization-get and organization-update patch only Yoast's supported organization social profiles, with separate default-off permission, revision checks and administrator Undo. Other option settings are preserved. Permission changes, owner recovery and plugin deactivation revoke codes/tokens while retaining durable OAuth client registrations for a fresh login. The individual Revoke app action still removes that registration. Previously deleted client registrations cannot be reconstructed by this update.
