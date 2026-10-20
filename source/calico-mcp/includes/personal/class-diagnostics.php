<?php
namespace CalicoMCP\Personal;
use CalicoMCP\Common\Services\Discovery\Discovery;
use CalicoMCP\Common\Utils\Utils;
if (!defined('ABSPATH')) { exit; }

/** Owner-triggered self-tests; never an external reachability guarantee. */
class Diagnostics {
    private static function owner() { return current_user_can('manage_options') && Policy::is_owner(get_current_user_id()); }
    private static function rest_url($name) { return preg_replace('#/mcp$#','/'.$name,Utils::resource_url()); }
    public static function urls() {
        return array('health'=>self::rest_url('health'),'resource'=>Discovery::protected_resource_metadata_url(),'oauth'=>Utils::issuer_url().'/.well-known/openid-configuration','challenge'=>Utils::resource_url(),'header'=>Utils::resource_url());
    }
    private static function same_origin($url) {
        $a=wp_parse_url(Utils::issuer_url()); $b=wp_parse_url($url);
        return $a && $b && empty($b['user']) && empty($b['pass']) && in_array($b['scheme']??'',array('https','http'),true) && strtolower($a['host']??'')===strtolower($b['host']??'') && ($a['scheme']??'')===($b['scheme']??'') && (int)($a['port']??0)===(int)($b['port']??0);
    }
    private static function request($url,$marker='') {
        if(!self::same_origin($url)) { return new \WP_Error('diagnostic_origin','Diagnostic URL is outside this website origin.'); }
        // A deliberately invalid bearer value uses the real MCP path and grants no access.
        return wp_safe_remote_request($url,array('method'=>$marker?'POST':'GET','timeout'=>4,'redirection'=>0,'sslverify'=>true,'limit_response_size'=>16384,'cookies'=>array(),'headers'=>array_merge(array('Accept'=>'application/json','Cache-Control'=>'no-cache'),$marker?array('Authorization'=>'Bearer calico-probe-'.$marker,'X-CalicoMCP-Diagnostic-Probe'=>'1'):array())));
    }
    public static function assess($kind,$response) {
        $row=array('check'=>$kind,'status'=>'needs_attention','http_status'=>0,'message'=>'');
        if(is_wp_error($response)) { $row['message']='The website could not complete this self-request. Check DNS, TLS, outbound HTTP restrictions and loopback access with the host.'; return $row; }
        $status=(int)wp_remote_retrieve_response_code($response); $row['http_status']=$status;
        $body=json_decode(wp_remote_retrieve_body($response),true);
        if($status>=300&&$status<400) { $row['message']='This endpoint redirected. Check the canonical HTTPS URL and exclude these connection paths from login or language redirects.'; return $row; }
        if(in_array($status,array(403,429),true)) { $row['message']='This request was refused or rate limited. Check the hosting firewall, security plugin or CDN rule for this exact path. This response alone does not identify which layer blocked it.'; return $row; }
        if(!is_array($body)) { $row['message']='Expected JSON was replaced with an empty or non-JSON response. Check caching, login redirects and browser challenges on this path.'; return $row; }
        $expected=Discovery::authorization_server_metadata();
        $ok=false;
        if($kind==='health') { $ok=$status===200&&($body['service']??'')==='calicomcp'&&($body['mcp_endpoint']??'')===Utils::resource_url()&&($body['version']??'')===CALICOMCP_VERSION; }
        elseif($kind==='resource') { $ok=$status===200&&($body['resource']??'')===Utils::resource_url()&&($body['authorization_servers']??array())===array(Utils::issuer_url()); }
        elseif($kind==='oauth') { $ok=$status===200;foreach(array('issuer','authorization_endpoint','token_endpoint','registration_endpoint')as $key){$ok=$ok&&isset($body[$key])&&$body[$key]===$expected[$key];}$ok=$ok&&in_array('S256',(array)($body['code_challenge_methods_supported']??array()),true); }
        elseif($kind==='challenge') { $challenge=(string)wp_remote_retrieve_header($response,'www-authenticate');$ok=$status===401&&($body['jsonrpc']??'')==='2.0'&&($body['error']['code']??0)===-32001&&strpos($challenge,'resource_metadata="'.Discovery::protected_resource_metadata_url().'"')!==false; }
        elseif($kind==='header') { $seen=(string)wp_remote_retrieve_header($response,'x-calicomcp-auth-header-seen');
            $ok=$status===401&&($body['error']['code']??0)===-32001&&$seen==='1';
            if($status===401&&in_array($seen,array('0','fallback'),true)) { $row['message']='The standard Authorization header did not reach the MCP handler on this self-request. Ask the host to preserve Authorization on this path.'; return $row; }
        }
        $row['status']=$ok?'passed':'needs_attention';
        $row['message']=$ok?($kind==='challenge'?'Expected HTTP 401 and OAuth discovery challenge received.':($kind==='header'?'The test Authorization header reached WordPress.':'Expected connection response received.')):'The response did not match this installation. Check stale caches, endpoint rewrites and conflicting MCP plugins.';
        return $row;
    }
    public static function run() {
        if(!self::owner()) { return new \WP_Error('forbidden','Save access for your administrator account before running connection checks.'); }
        $user=get_current_user_id(); $rate='calicomcp_diagnostic_rate_'.$user;
        if(get_transient($rate)) { return new \WP_Error('diagnostic_rate','Wait one minute before running the connection check again.'); }
        set_transient($rate,true,60);
        $marker=bin2hex(random_bytes(32));
        $report=array('checked_at'=>time(),'checks'=>array(),'external_verified'=>false);
        if(Bootstrap::conflict()||!Policy::enabled()) { $report['checks'][]=array('check'=>'configuration','status'=>'needs_attention','http_status'=>0,'message'=>'Enable the direct connection for your account and use only one MCP server before testing.'); }
        else {
            foreach(self::urls()as $kind=>$url) {
                $report['checks'][]=array_merge(self::assess($kind,self::request($url,$kind==='header'?$marker:'')),array('url'=>$url));
            }
        }
        set_transient('calicomcp_diagnostic_report_'.$user,$report,10*MINUTE_IN_SECONDS);
        return $report;
    }
    public static function render() {
        if(!self::owner()) { return; }
        $labels=array('configuration'=>'Connection settings','health'=>'Plugin endpoint','resource'=>'Resource discovery','oauth'=>'OAuth discovery','challenge'=>'MCP sign-in challenge','header'=>'Authorization header');
        echo '<div class="cmcp-card"><h2>Connection check</h2><p>Check the website connection before adding it to your AI app. These requests come from this website server; they do not prove that a host accepts traffic from Codex or another external client.</p>';
        echo '<form method="post" action="'.esc_url(admin_url('admin-post.php')).'"><input type="hidden" name="action" value="calicomcp_diagnose">';wp_nonce_field('calicomcp_diagnose');submit_button('Run connection check','secondary','submit',false);echo '</form>';
        $report=get_transient('calicomcp_diagnostic_report_'.get_current_user_id());
        if(is_array($report)) {
            echo '<p>Self-test run: '.esc_html(wp_date('M j, Y g:i a',(int)$report['checked_at'])).'. Results expire after 10 minutes.</p><table><thead><tr><th>Check</th><th>Result</th></tr></thead><tbody>';
            foreach($report['checks'] as $row) { echo '<tr><td>'.esc_html($labels[$row['check']]??$row['check']).'</td><td><strong>'.esc_html($row['status']==='passed'?'Passed':'Needs attention').'</strong>'.($row['http_status']?' · HTTP '.(int)$row['http_status']:'').'<br>'.esc_html($row['message']).'</td></tr>'; }
            echo '</tbody></table><p><strong>External client access: not tested here.</strong> Complete OAuth and a read-only tool call from Codex to verify that connection.</p>';
        }
        echo '<details><summary>Hosting support details</summary><p>The destination uses your website domain and its normal DNS/CDN routing. Incoming source IPs belong to the connecting client; they cannot be changed by this plugin. There is no miniOrange gateway in this connection.</p><p>Ask the host to inspect the specific failing path and rule. Preserve Authorization headers and JSON responses, and check for caching or browser challenges on these paths. Keep OAuth authentication and the rest of the firewall enabled.</p><ul>';
        foreach(array_unique(array_values(self::urls()))as $url) { echo '<li><code>'.esc_html($url).'</code></li>'; }
        echo '<li><code>'.esc_html(self::rest_url('register')).'</code></li><li><code>'.esc_html(self::rest_url('token')).'</code></li><li><code>'.esc_html(Utils::issuer_url().'/.well-known/oauth-authorization-server').'</code></li><li><code>'.esc_html(Utils::maybe_force_https(admin_url('admin-post.php?action=calicomcp_authorize'))).'</code></li></ul><p>These details contain endpoint URLs only. Do not include OAuth credentials when contacting support.</p></details></div>';
    }
}
