<?php
namespace CalicoMCP\Personal;
if (!defined('ABSPATH')) { exit; }
/** Public, version-pinned GitHub releases through native WordPress updates. */
class Updater {
    const REPO='https://github.com/CalicoDesignCo/calico-mcp';
    const API='https://api.github.com/repos/CalicoDesignCo/calico-mcp/releases?per_page=30';
    const SLUG='calico-mcp';
    const CACHE='calicomcp_public_release_v1';
    private static $plugin;
    public static function init() {
        self::$plugin=plugin_basename(CALICOMCP_PLUGIN_FILE);
        add_filter('update_plugins_github.com',array(__CLASS__,'update'),10,4);
        add_filter('plugins_api',array(__CLASS__,'details'),10,3);
        add_filter('upgrader_pre_download',array(__CLASS__,'download'),10,4);
        add_action('load-update-core.php',array(__CLASS__,'refresh'));
        add_action('load-plugins.php',array(__CLASS__,'refresh'));
    }
    public static function refresh() {
        if(isset($_GET['force-check'])&&current_user_can('update_plugins'))delete_site_transient(self::CACHE);
    }
    public static function parse($r) {
        if(!is_array($r)||!empty($r['draft'])||empty($r['id'])||!preg_match('/^v(\d+\.\d+\.\d+(?:-beta\.[1-9][0-9]*)?)$/D',$r['tag_name']??'',$m))return false;
        // This personal staging channel deliberately supports numbered beta releases.
        if(!empty($r['prerelease'])&&strpos($m[1],'-beta.')===false)return false;
        $tag=$r['tag_name'];$url=self::REPO.'/releases/tag/'.$tag;
        if(($r['html_url']??'')!==$url)return false;
        $package=self::REPO.'/releases/download/'.$tag.'/'.self::SLUG.'.zip';
        foreach((array)($r['assets']??array())as $a) {
            if(!is_array($a)||($a['name']??'')!==self::SLUG.'.zip'||($a['state']??'')!=='uploaded'||($a['browser_download_url']??'')!==$package||!is_numeric($a['size']??null)||(int)$a['size']<1||(int)$a['size']>25*1024*1024||!preg_match('/^sha256:([a-f0-9]{64})$/D',$a['digest']??'',$digest))continue;
            return array('version'=>$m[1],'tag'=>$tag,'url'=>$url,'package'=>$package,'sha256'=>$digest[1]);
        }
        return false;
    }
    public static function release() {
        $cached=get_site_transient(self::CACHE);
        if(is_array($cached)&&array_key_exists('release',$cached))return $cached['release'];
        $response=wp_safe_remote_get(self::API,array('timeout'=>8,'redirection'=>0,'sslverify'=>true,'limit_response_size'=>1048576,'headers'=>array('Accept'=>'application/vnd.github+json','User-Agent'=>'Calico-MCP-Updater')));
        $best=false;
        if(!is_wp_error($response)&&wp_remote_retrieve_response_code($response)===200) {
            $releases=json_decode(wp_remote_retrieve_body($response),true);
            if(is_array($releases)&&array_values($releases)===$releases)foreach($releases as $r){$parsed=self::parse($r);if($parsed&&(!$best||version_compare($parsed['version'],$best['version'],'>')))$best=$parsed;}
        }
        set_site_transient(self::CACHE,array('release'=>$best),$best?21600:900);
        return $best;
    }
    public static function update($update,$data,$file,$locales) {
        if($file!==self::$plugin)return $update;
        $r=self::release();if(!$r||!version_compare($r['version'],$data['Version']??'0','>'))return false;
        return array('id'=>self::REPO,'slug'=>self::SLUG,'version'=>$r['version'],'url'=>$r['url'],'package'=>$r['package'],'requires'=>'6.9','requires_php'=>'7.4');
    }
    public static function details($result,$action,$args) {
        if($action!=='plugin_information'||($args->slug??'')!==self::SLUG)return $result;
        $r=self::release();if(!$r)return $result;
        return (object)array('name'=>'Calico MCP','slug'=>self::SLUG,'version'=>$r['version'],'author'=>'Calico Design','homepage'=>self::REPO,'requires'=>'6.9','requires_php'=>'7.4','download_link'=>$r['package'],'sections'=>array('description'=>'Direct WordPress, custom content, Elementor and Yoast tools. Includes numbered beta releases.','changelog'=>'<p><a href="'.esc_url($r['url']).'">Read versioned release notes on GitHub</a>.</p>'));
    }
    public static function download($reply,$package,$upgrader,$extra) {
        if($reply!==false)return $reply;
        $ours=strpos((string)$package,self::REPO.'/releases/download/')===0;
        if(!$ours)return $reply;
        if(!empty($extra['plugin'])&&$extra['plugin']!==self::$plugin)return new \WP_Error('calicomcp_update_target','Update target does not match Calico MCP.');
        $r=self::release();
        if(!$r||$r['package']!==$package)return new \WP_Error('calicomcp_update_changed','Release metadata changed or is unavailable. Check for updates again.');
        if(!function_exists('download_url'))require_once ABSPATH.'wp-admin/includes/file.php';
        $file=download_url($package,60);
        if(is_wp_error($file))return new \WP_Error('calicomcp_update_download','GitHub package download failed. The installed plugin remains in place.');
        if(!hash_equals($r['sha256'],hash_file('sha256',$file))) { wp_delete_file($file);return new \WP_Error('calicomcp_update_digest','Downloaded package checksum did not match GitHub. No update was installed.'); }
        return $file;
    }
}
