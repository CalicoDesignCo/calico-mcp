<?php
namespace CalicoMCP\Personal;
use CalicoMCP\Common\Repositories\Store;
use CalicoMCP\Common\Repositories\Audit_Store;
use CalicoMCP\Common\Utils\Utils;
if (!defined('ABSPATH')) { exit; }
class Admin {
    public static function assets($hook) {
        if($hook==='toplevel_page_calico-mcp')wp_enqueue_script('calicomcp-select-all',plugin_dir_url(CALICOMCP_PLUGIN_FILE).'views/admin/select-all.js',array(),CALICOMCP_VERSION,true);
    }
    public static function selection_buttons($label) {
        echo '<p class="cmcp-selection-actions"><button type="button" class="button" data-cmcp-select="all" aria-label="'.esc_attr('Select all '.$label).'">Select all</button> <button type="button" class="button" data-cmcp-select="none" aria-label="'.esc_attr('Clear all '.$label).'">Clear all</button> <span role="status" aria-live="polite" data-cmcp-selection-status></span></p>';
    }
    public static function menu() { add_menu_page('Calico MCP','Calico MCP','manage_options','calico-mcp',array(__CLASS__,'render'),'dashicons-admin-links',81); }
    private static function permission($nonce) {
        if(!current_user_can('manage_options')) { wp_die('Administrator access required.','',array('response'=>403)); }
        $s=Policy::settings();
        if($s['owner'] && !Policy::is_owner(get_current_user_id())) { wp_die('Only the configured account can manage Calico MCP.','',array('response'=>403)); }
        check_admin_referer($nonce);
    }
    private static function back($notice) { wp_safe_redirect(admin_url('admin.php?page=calico-mcp&notice='.rawurlencode($notice))); exit; }
    public static function save() {
        self::permission('calicomcp_save');
        if(Bootstrap::conflict()) { wp_die('Deactivate miniOrange MCP before enabling this prototype.'); }
        $new=array('owner'=>get_current_user_id(),'enabled'=>isset($_POST['enabled']),'content'=>isset($_POST['content']),'media'=>isset($_POST['media']),'seo'=>isset($_POST['seo']),'events'=>isset($_POST['events']),'elementor'=>isset($_POST['elementor']),'event_types'=>Events::available()?Events::clean_scope(wp_unslash($_POST['event_types']??array())):Events::scope());
        foreach(array('structure','terms','seo_advanced','elementor_full','published','repeater_rows') as $key)$new[$key]=isset($_POST[$key]);
        // Preserve inert legacy flags without changing saved access on update.
        $old=Policy::settings();foreach(array('forms_read','forms_edit')as $key)if(array_key_exists($key,$old))$new[$key]=$old[$key];
        if(isset($_POST['import_bridge'])) {
            $import=Events::clean_scope(Content_Discovery::bridge_preview());
            foreach($import as $type=>$selection) {
                $existing=$new['event_types'][$type]??array('fields'=>array(),'taxonomies'=>array());
                $new['event_types'][$type]=array('fields'=>array_values(array_unique(array_merge($existing['fields'],$selection['fields']))),'taxonomies'=>$existing['taxonomies']);
            }
        }
        $result=Policy::configure($new);
        if(is_wp_error($result)) { wp_die(esc_html($result->get_error_message())); }
        self::back('saved');
    }
    public static function revoke() {
        $client=isset($_POST['client_id'])?sanitize_text_field(wp_unslash($_POST['client_id'])):'';
        self::permission('calicomcp_revoke_'.$client);
        if(!Store::get_client($client) || !Policy::revoke($client)) { wp_die('Revocation could not be confirmed.'); }
        self::back('revoked');
    }
    public static function undo() {
        $id=isset($_POST['recovery_id'])?absint($_POST['recovery_id']):0;
        self::permission('calicomcp_undo_'.$id);
        $result=Recovery::restore($id);
        if(is_wp_error($result)) { wp_die(esc_html($result->get_error_message())); }
        \CalicoMCP\Common\Services\Logging\Audit_Logger::set_context(array('user_id'=>get_current_user_id(),'client_id'=>'wordpress-admin','client_name'=>'WordPress admin'));
        \CalicoMCP\Common\Services\Logging\Audit_Logger::set_meta(array('recovery_id'=>$id));
        \CalicoMCP\Common\Services\Logging\Audit_Logger::record_tool_call('calicomcp/recovery-restore','success',0,null,null,null);
        self::back('restored');
    }
    public static function diagnose() {
        self::permission('calicomcp_diagnose');
        $result=Diagnostics::run();
        if(is_wp_error($result)) { wp_die(esc_html($result->get_error_message()),'',array('response'=>400)); }
        self::back('diagnosed');
    }
    private static function form($action,$nonce) { echo '<form method="post" action="'.esc_url(admin_url('admin-post.php')).'"><input type="hidden" name="action" value="'.esc_attr($action).'">'; wp_nonce_field($nonce); }
    public static function render() {
        if(!current_user_can('manage_options')) { return; }
        $s=Policy::settings(); $owner=$s['owner']?get_userdata((int)$s['owner']):false;
        $can_manage=!$s['owner']||Policy::is_owner(get_current_user_id());
        echo '<div class="wrap cmcp"><style>.cmcp{max-width:1100px}.cmcp .cmcp-head{display:flex;justify-content:space-between;align-items:center;margin:22px 0}.cmcp h1{font-size:30px;font-weight:650}.cmcp .cmcp-grid{display:grid;grid-template-columns:1.25fr 1fr;gap:20px}.cmcp .cmcp-card{background:#fff;border:1px solid #dcdcde;border-radius:10px;padding:22px;margin-bottom:20px}.cmcp h2{margin-top:0}.cmcp label{display:block;margin:15px 0;line-height:1.6}.cmcp details{margin:14px 0}.cmcp summary{cursor:pointer;font-weight:600;line-height:1.6}.cmcp code{overflow-wrap:anywhere}.cmcp .cmcp-head{gap:16px;flex-wrap:wrap}.cmcp .cmcp-muted{color:#646970}.cmcp .cmcp-pill{background:#e9f3ee;color:#165a35;padding:6px 12px;border-radius:20px}.cmcp .cmcp-pill.off{background:#f0f0f1;color:#50575e}.cmcp input[type=url]{width:100%;font-family:monospace}.cmcp table{width:100%;border-collapse:collapse}.cmcp th,.cmcp td{text-align:left;padding:11px 8px;border-bottom:1px solid #eee;vertical-align:top}.cmcp .cmcp-inline{margin:0}.cmcp .cmcp-inline .submit{margin:0;padding:0}@media(max-width:850px){.cmcp .cmcp-grid{grid-template-columns:1fr}.cmcp .cmcp-card{overflow-x:auto}}</style>';
        $access_enabled=Policy::enabled()&&!Bootstrap::conflict();
        $edit_labels=array('content'=>'Content','media'=>'Media','seo'=>'Yoast SEO','events'=>'Custom content','elementor'=>'Elementor widget drafts','structure'=>'Duplication/templates/revisions','terms'=>'Categories and tags','seo_advanced'=>'Advanced Yoast','elementor_full'=>'Full Elementor','published'=>'Published item permission','repeater_rows'=>'Repeater row operations');
        $editing=array(); foreach($edit_labels as $key=>$label) { if(!empty($s[$key])) { $editing[]=$label; } }
        echo '<div class="cmcp-head"><div><h1>Calico MCP</h1><p class="cmcp-muted">Your direct connection to WordPress · '.esc_html(CALICOMCP_VERSION).'</p></div><span class="cmcp-pill '.($access_enabled?'':'off').'">'.esc_html($access_enabled?($editing?'Editing allowed':'Read-only access'):'Access disabled').'</span></div>';
        $notice=isset($_GET['notice'])?sanitize_key($_GET['notice']):'';
        $notices=array('saved'=>'Access settings saved. Reconnect your AI client if permissions changed.','revoked'=>'Connection revoked. Its authorization codes and access and refresh tokens were removed.','restored'=>'The edit was undone and the restored fields were verified.');
        if(isset($notices[$notice])) { echo '<div class="notice notice-success"><p>'.esc_html($notices[$notice]).'</p></div>'; }
        if(!$can_manage) { echo '<div class="notice notice-warning"><p>Calico MCP is configured for another administrator account. Sign in with that account to manage it.</p></div></div>'; return; }
        echo '<div class="cmcp-grid"><div class="cmcp-card"><h2>Your access</h2><p>'.($owner?'WordPress account: <strong>'.esc_html($owner->display_name).'</strong>.':'Save these settings to use your current account.').'</p><p>'.esc_html($access_enabled?($editing?'Editing allowed: '.implode(', ',$editing).'.':'Read-only: your AI app can read selected content. All editing is off.'):'AI access is disabled. Saved editing choices apply when you enable access.').'</p>';
        self::form('calicomcp_save','calicomcp_save');
        echo '<label><input type="checkbox" name="enabled" value="1" '.checked(!empty($s['enabled']),true,false).'> Enable AI access to this website</label><details data-cmcp-selection="editing"'.($editing?' open':'').'><summary>Optional editing permissions</summary>';
        self::selection_buttons('optional editing permissions');
        echo '<p class="cmcp-muted">Select all includes published-item editing. Review choices before Save access; these buttons do not save or change the live connection.</p>';
        foreach(array('content'=>'Allow content edits','media'=>'Allow media uploads and edits','seo'=>'Allow Yoast SEO edits','events'=>'Allow custom content creation and edits','elementor'=>'Allow Elementor draft text and image edits','structure'=>'Allow draft duplication, template assignment and revision restoration','terms'=>'Allow category and tag management','seo_advanced'=>'Allow advanced Yoast SEO controls','elementor_full'=>'Allow full Elementor layout and style editing','published'=>'Also allow edits to already-published custom content and Elementor posts/pages','repeater_rows'=>'Allow appending, removing and reordering selected repeater rows') as $key=>$label) {
            echo '<label><input type="checkbox" name="'.esc_attr($key).'" value="1" '.checked(!empty($s[$key]),true,false).'> '.esc_html($label).'</label>';
        }
        echo '<p class="cmcp-muted">New content and duplicates stay as drafts. Published-item permission works only alongside custom content or full Elementor editing; those edits affect live content immediately and preserve its status. Selected custom content SEO also needs custom editing and the applicable Yoast permission. Repeater row operations also need custom editing. Template/revision restoration and legacy draft tools stay draft-only. Core content, media, terms and SEO changes may affect the live site.</p></details><p class="cmcp-muted">Changing access settings revokes existing connections. Reconnect your AI app afterward.</p><details><summary>Select custom content types and fields</summary>';
        Events::render_controls(); echo '</details>';
        submit_button('Save access'); echo '</form></div><div class="cmcp-card"><h2>Connect your AI client</h2><p>Add a remote MCP server in your AI app and use this connection URL:</p><input type="url" readonly aria-label="Connection URL" value="'.esc_attr(Utils::resource_url()).'"><ol><li>Enable the connection and save access.</li><li>Add the URL to your AI client.</li><li>Sign in with your configured WordPress account and review the permissions.</li></ol><p class="cmcp-muted">The connection goes directly to this website. No Calico or miniOrange gateway is required.</p><p>'.(class_exists('WPSEO_Meta')||function_exists('YoastSEO')?'Yoast SEO detected.':'Yoast tools appear when Yoast SEO is active.').'</p><p class="cmcp-muted">This beta includes custom content discovery, draft duplication and templates, revision restoration, categories/tags, Elementor draft layouts and Yoast SEO. Publishing, permanent deletion and site administration are not included.</p></div></div>';
        global $wpdb;
        $clients=$wpdb->get_results('SELECT client_id,client_name,created FROM '.Store::table('clients').' ORDER BY created DESC LIMIT 100',ARRAY_A);
        echo '<div class="cmcp-card"><h2>Registered apps</h2><p class="cmcp-muted">Registration alone does not confirm sign-in or a working connection. Verify access with a read in your AI app. Revoke removes the app registration and its credentials immediately.</p>';
        if(!$clients) { echo '<p>No apps registered yet. Use the connection URL above to add this website to your AI app.</p>'; }
        else { echo '<table><thead><tr><th>Client</th><th>Registered</th><th></th></tr></thead><tbody>'; foreach($clients as $client) {
            echo '<tr><td><strong>'.esc_html($client['client_name']?:'Unnamed client').'</strong><details><summary>App ID</summary><code>'.esc_html($client['client_id']).'</code></details></td><td>'.esc_html(wp_date('M j, Y g:i a',(int)$client['created'])).'</td><td>';
            self::form('calicomcp_revoke','calicomcp_revoke_'.$client['client_id']); echo '<input type="hidden" name="client_id" value="'.esc_attr($client['client_id']).'">'; submit_button('Revoke','secondary','submit',false);echo '</form></td></tr>';
        } echo '</tbody></table>'; } echo '</div>';
        echo '<div class="cmcp-card"><h2>Undo recent edits</h2><p class="cmcp-muted">Recovery data is kept for 30 days. Undo restores only fields changed by that edit and refuses if the item has changed again since.</p>';
        $backups=Recovery::recent();
        if(!$backups) { echo '<p>No edits to undo yet.</p>'; }
        else { echo '<table><thead><tr><th>Item</th><th>Edit</th><th></th></tr></thead><tbody>'; foreach($backups as $row) {
            $label=Entity_Recovery::handles($row['tool'])?($row['tool']==='calicomcp/term-update'?'Taxonomy term ':'Removed integration record ').$row['post_id']:get_the_title((int)$row['post_id']);
            echo '<tr><td>'.esc_html($label).'</td><td>'.esc_html(ucwords(str_replace(array('calicomcp/','-'),array('',' '),$row['tool']))).'<details><summary>Tool details</summary><code>'.esc_html($row['tool']).'</code></details>'.esc_html(wp_date('M j g:i a',(int)$row['created'])).'</td><td>';
            if(Entity_Recovery::handles($row['tool'])&&!Entity_Recovery::provider($row['tool']))echo '<span class="cmcp-muted">Integration removed; record retained.</span>';
            else { self::form('calicomcp_undo','calicomcp_undo_'.$row['id']);echo '<input type="hidden" name="recovery_id" value="'.esc_attr($row['id']).'">';submit_button('Undo edit','secondary','submit',false); echo '</form>'; }
            echo '</td></tr>';
        }echo '</tbody></table>'; } echo '</div>';
        echo '<div class="cmcp-card"><details><summary>Recent activity</summary>';
        $audit=Audit_Store::query(array('per_page'=>15));
        if(!$audit['logs']) { echo '<p>No tool calls recorded yet.</p>'; }
        else { echo '<table><thead><tr><th>When</th><th>Tool</th><th>Result</th></tr></thead><tbody>';foreach($audit['logs'] as $row) {echo '<tr><td>'.esc_html(wp_date('M j g:i a',(int)$row['created'])).'</td><td>'.esc_html($row['tool_name']).'</td><td>'.esc_html($row['status']).'</td></tr>';}echo '</tbody></table>'; } echo '</details></div>';
        echo '<details><summary>Connection troubleshooting</summary>'; Diagnostics::render(); echo '</details></div>';
    }
}
