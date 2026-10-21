<?php
namespace CalicoMCP\Personal;
use CalicoMCP\Common\Migration\Migration;
use CalicoMCP\Common\Services\Discovery\Discovery;
use CalicoMCP\Common\Services\Http\No_Cache;
use CalicoMCP\Common\Services\OAuth\OAuth_Server;
use CalicoMCP\Common\Apis\MCP\MCP_Routes;
use CalicoMCP\Common\Apis\OAuth\OAuth_Routes;
use CalicoMCP\Common\Repositories\Audit_Store;
use CalicoMCP\Common\Repositories\Debug_Store;
if (!defined('ABSPATH')) { exit; }
class Bootstrap {
    public static function conflict() {
        $slug='miniorange-secure-mcp-server/miniorange-secure-mcp-server.php';
        return in_array($slug,(array)get_option('active_plugins',array()),true) || isset(((array)get_site_option('active_sitewide_plugins',array()))[$slug]);
    }
    public static function init() {
        Updater::init();
        register_activation_hook(CALICOMCP_PLUGIN_FILE,array(__CLASS__,'activate'));
        register_deactivation_hook(CALICOMCP_PLUGIN_FILE,array(__CLASS__,'deactivate'));
        add_action('admin_menu',array(Admin::class,'menu'));
        add_action('admin_post_calicomcp_save',array(Admin::class,'save'));
        add_action('admin_post_calicomcp_revoke',array(Admin::class,'revoke'));
        add_action('admin_post_calicomcp_undo',array(Admin::class,'undo'));
        add_action('admin_post_calicomcp_diagnose',array(Admin::class,'diagnose'));
        if(self::conflict()) { add_action('admin_notices',function(){echo '<div class="notice notice-warning"><p>Calico MCP is paused while miniOrange MCP is active. Use one MCP server at a time because both own OAuth discovery URLs.</p></div>';}); return; }
        add_action('plugins_loaded',array(Migration::class,'maybe_upgrade'),1);
        Abilities::init();
        add_filter('wpseo_exclude_from_sitemap_by_post_ids',array(\CalicoMCP\Abilities\Packs\Yoast\YSOA_Sitemap_Abilities::class,'filter_excluded_posts'));
        add_action('rest_api_init',function(){ MCP_Routes::register(); OAuth_Routes::register(); });
        add_filter('rest_pre_dispatch',array(__CLASS__,'request_boundary'),10,3);
        add_filter('rest_post_dispatch',array(No_Cache::class,'filter_rest_response'),99,3);
        add_action('admin_post_calicomcp_authorize',array(OAuth_Server::class,'authorize'));
        add_action('admin_post_nopriv_calicomcp_authorize',array(OAuth_Server::class,'authorize'));
        add_action('init',array(Discovery::class,'register_rewrite'));
        add_filter('query_vars',array(Discovery::class,'add_query_var'));
        add_action('parse_request',array(Discovery::class,'maybe_serve_wellknown'));
        add_action('calicomcp_retention',array(__CLASS__,'purge'));
    }
    public static function activate() {
        if(self::conflict()) { wp_die('Deactivate miniOrange MCP before activating Calico MCP. The two servers share OAuth discovery paths.'); }
        if(!function_exists('wp_register_ability')) { wp_die('Calico MCP requires WordPress 6.9 or newer with the Abilities API.'); }
        Migration::install(); Recovery::install();
        if(!wp_next_scheduled('calicomcp_retention')) { wp_schedule_event(time(),'daily','calicomcp_retention'); }
        Discovery::register_rewrite(); flush_rewrite_rules(false);
    }
    public static function deactivate() { Policy::revoke_all(); wp_clear_scheduled_hook('calicomcp_retention'); }
    public static function purge() { Audit_Store::purge(); Debug_Store::purge(); Recovery::purge(); }
    public static function request_boundary($result,$server,$request) {
        if(strpos($request->get_route(),'/'.CALICOMCP_REST_NAMESPACE.'/')!==0) { return $result; }
        if(strlen($request->get_body())>16*1024*1024) { return new \WP_Error('request_too_large','Request exceeds 16 MB.',array('status'=>413)); }
        $origin=$request->get_header('origin');
        $home=wp_parse_url(home_url()); $given=$origin?wp_parse_url($origin):false;
        if($origin && (!$given || strtolower($given['host']??'')!==strtolower($home['host']??'') || strtolower($given['scheme']??'')!==strtolower($home['scheme']??'') || (int)($given['port']??0)!==(int)($home['port']??0))) { return new \WP_Error('invalid_origin','Origin is not allowed.',array('status'=>403)); }
        return $result;
    }
}
