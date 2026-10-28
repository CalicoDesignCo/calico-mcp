<?php
/**
 * Plugin Name: Calico MCP
 * Description: Direct WordPress tools for one operator: custom content, templates, revisions, taxonomy, Elementor and Yoast.
 * Version: 0.1.0-beta.37
 * Requires at least: 6.9
 * Requires PHP: 7.4
 * Author: Calico Design
 * Update URI: https://github.com/CalicoDesignCo/calico-mcp
 * License: MIT
 * Text Domain: calico-mcp
 */
if (!defined('ABSPATH')) { exit; }
define('CALICOMCP_VERSION', '0.1.0-beta.37');
define('CALICOMCP_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('CALICOMCP_PLUGIN_FILE', __FILE__);
define('CALICOMCP_REST_NAMESPACE', 'calicomcp/v1');
if (!defined('CALICOMCP_PUBLIC_BASE')) { define('CALICOMCP_PUBLIC_BASE', ''); }
require __DIR__ . '/autoload.php';
\CalicoMCP\Personal\Bootstrap::init();


