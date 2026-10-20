=== Calico MCP ===
Contributors: Calico Design
Requires at least: 6.9
Requires PHP: 7.4
Stable tag: 0.1.0-beta.5
License: MIT

Personal-use beta. Direct OAuth-protected MCP tools for WordPress posts, pages, media and Yoast SEO.

== Setup ==
Use on staging first. Deactivate miniOrange MCP before activating Calico MCP because the two servers share OAuth discovery paths. No miniOrange settings are imported or deleted.

Open Calico MCP, enable access for your current administrator account, and select any editing permissions you need. Reading is the default. Paste the Connection URL into your remote MCP client and complete WordPress login and consent.

New posts/pages stay in draft. Publishing, scheduling, permanent deletion, site administration, arbitrary REST access, user management, theme-file edits, Elementor body edits and JetEngine events are not exposed.

Existing-item edits require expected_revision from a fresh read. Successful edits return recovery_id. Recovery is available in the Calico MCP admin screen and refuses to overwrite newer work.

Changing access settings and deactivating the plugin revoke connection credentials. Per-client revoke controls remove authorization codes, access tokens and refresh tokens. Recovery records are retained for 30 days; activity logs use a 90-day default. Plugin removal does not automatically purge stored settings or records.

Yoast tools require an active Yoast plugin. This is independent derivative software; see UPSTREAM-NOTICES.md and LICENSE for attribution.
