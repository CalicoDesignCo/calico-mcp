<?php
namespace CalicoMCP\Personal;
use CalicoMCP\Common\Repositories\NHI_Store;
use CalicoMCP\Common\Repositories\Store;
if (!defined('ABSPATH')) { exit; }

class Policy {
    const OPTION = 'calicomcp_personal_settings';
    public static function settings() {
        return wp_parse_args((array)get_option(self::OPTION, array()), array('other_statuses'=>false,'template_existing'=>false,'trash'=>false,'publish'=>false,'schedule'=>false,'status'=>false,'shared_templates'=>false,'organization'=>false,'owner'=>0,'enabled'=>false,'content'=>false,'media'=>false,'seo'=>false,'events'=>false,'elementor'=>false,'structure'=>false,'terms'=>false,'seo_advanced'=>false,'elementor_full'=>false,'published'=>false,'repeater_rows'=>false,'event_types'=>array()));
    }
    public static function enabled() { $s=self::settings(); $site=wp_parse_url(home_url()); $secure=($site['scheme']??'')==='https' || in_array($site['host']??'',array('127.0.0.1','localhost','::1'),true); return $secure && !empty($s['enabled']) && self::is_owner((int)$s['owner']); }
    public static function is_owner($id) { $s=self::settings(); return $id>0 && (int)$s['owner']===$id && user_can($id,'manage_options'); }
    public static function recover_owner($expected_owner) {
        $s=self::settings();
        if(!current_user_can('manage_options') || !(int)$s['owner'] || (int)$s['owner']!==(int)$expected_owner || get_userdata((int)$s['owner'])) {
            return new \WP_Error('owner_recovery_forbidden','Ownership can only be recovered by an administrator after the configured account has been deleted.');
        }
        if(!self::revoke_all())return new \WP_Error('revoke_failed','Existing connections could not be revoked. Ownership was not changed.');
        $s['owner']=get_current_user_id();
        $s['enabled']=false;
        update_option(self::OPTION,$s,false);
        if((int)self::settings()['owner']!==get_current_user_id() || !self::sync())return new \WP_Error('policy_failed','Recovery could not be fully verified. AI access remains disabled.');
        return true;
    }
    public static function groups() {
        return array(
            'lifecycle_read'=>array('content-status-get','content-trash-get'),
            'trash'=>array('content-trash','content-restore-from-trash'),
            'publish'=>array('content-publish'),
            'schedule'=>array('content-schedule'),
            'status'=>array('content-set-status'),
            'organization_read'=>array('organization-get'),
            'organization'=>array('organization-update'),
            'shared_read'=>array('shared-template-list'),
            'structure_read'=>array('content-template-get','content-revision-compare','taxonomy-list','term-list','term-get','cpt-discover','cpt-field-explain','bridge-import-preview','cpt-schema','cpt-list','cpt-get','cpt-terms'),
            'structure'=>array('content-duplicate','content-template-set','content-revision-restore'),
            'terms'=>array('term-create','term-update','content-set-terms'),
            'seo_advanced'=>array('update-breadcrumb-title','update-canonical','update-robots-meta','update-robots-advanced','update-cornerstone','update-schema-types','update-sitemap-inclusion'),
            'elementor_full_read'=>array('elementor-page-read','elementor-render-preview','elementor-list-widget-types','elementor-widget-schema'),
            'elementor_full'=>array('elementor-page-write','elementor-element-duplicate','elementor-element-move','elementor-element-delete','elementor-element-set-content','elementor-element-set-style'),
            'read'=>array('post-find','post-get','post-list-all','post-list-published','post-list-drafts','page-find','page-get','page-list-all','page-list-published','page-list-drafts','media-find','media-get','media-list-all','media-list-images','media-count-by-type','revision-get','revision-list-for-post','revision-count-for-post','get-meta-fields','get-focus-keyword','content-find-in-posts','event-schema','event-list','event-get','event-terms'),
            'content'=>array('post-create-draft','post-update','post-set-featured-image','page-create-draft','page-update','page-set-featured-image','content-replace-in-post'),
            'media'=>array('media-upload-from-url','media-upload-file','media-update-title','media-update-alt-text','media-update-caption','media-update-description'),
            'seo'=>array('update-seo-title','update-meta-description','update-focus-keyword','update-og-tags','update-twitter-card'),
            'elementor_read'=>array('elementor-get'),
            'elementor'=>array('elementor-update-draft'),
            'events'=>array('event-create-draft','event-update-draft','cpt-create-draft','cpt-update-draft','cpt-update'),
            'repeater_rows'=>array('cpt-repeater-rows')
        );
    }
    public static function group($name) {
        if (strpos($name,'calicomcp/')!==0) { return null; }
        $slug=substr($name,10);
        foreach(self::groups() as $group=>$names) { if(in_array($slug,$names,true)) { return $group; } }
        return null;
    }
    public static function supports($name) { return self::group($name)!==null; }
    public static function listed($name,$available) {
        // Only suppress aliases when their replacement is already authorized by
        // the current policy AND the existing application's role grants.
        $aliases=array('event-schema'=>'cpt-schema','event-list'=>'cpt-list','event-get'=>'cpt-get','event-terms'=>'cpt-terms','event-create-draft'=>'cpt-create-draft','event-update-draft'=>'cpt-update','cpt-update-draft'=>'cpt-update','elementor-get'=>'elementor-page-read','elementor-update-draft'=>'elementor-element-set-content');
        $target=$aliases[substr($name,10)]??null;
        return !$target||!in_array('calicomcp/'.$target,$available,true);
    }
    public static function allowed($name) {
        if(!self::enabled()) { return false; }
        $group=self::group($name); $settings=self::settings();
        if(in_array($group,array('organization','organization_read'),true)&&(!defined('WPSEO_VERSION')||!class_exists('WPSEO_Options')))return false;
        if($group==='shared_read'&&!Elementor::available())return false;
        if($group==='repeater_rows'&&empty($settings['events']))return false;
        if(in_array($group,array('elementor_read','elementor','elementor_full_read','elementor_full'),true) && !Elementor::available()) { return false; }
        return in_array($group,array('lifecycle_read','read','structure_read','elementor_full_read','elementor_read','organization_read','shared_read'),true) || ($group!==null && !empty($settings[$group]));
    }
    public static function writer($name) { $group=self::group($name); return $group!==null && !in_array($group,array('lifecycle_read','read','structure_read','elementor_read','elementor_full_read','organization_read','shared_read'),true); }
    public static function editable_status($post,$allow_published=false) {
        if(!$post)return false;
        if($post->post_status==='draft')return true;
        if(!$allow_published)return false;
        $s=self::settings();
        if($post->post_status==='publish')return !empty($s['published']);
        if(in_array($post->post_status,array('pending','private','future'),true)&&!empty($s['other_statuses']))return $post->post_status!=='future'||strtotime($post->post_date_gmt.' UTC')>=time()+120;
        return false;
    }
    public static function configure(array $new) {
        $old=self::settings();
        if(!current_user_can('manage_options') || ($old['owner'] && !self::is_owner(get_current_user_id())) || (int)($new['owner']??0)!==get_current_user_id()) { return new \WP_Error('forbidden','Only the configured administrator can change access.'); }
        if($old!==$new && !self::revoke_all()) { return new \WP_Error('revoke_failed','Existing connections could not be revoked. Settings were not changed.'); }
        update_option(self::OPTION,$new,false);
        if(!self::sync()) { $new['enabled']=false; update_option(self::OPTION,$new,false); return new \WP_Error('policy_failed','Access policy could not be saved. Connection is disabled.'); }
        return true;
    }
    public static function sync() {
        $settings=self::settings(); $names=array();
        foreach(self::groups() as $group=>$slugs) {
            if(in_array($group,array('lifecycle_read','read','structure_read','elementor_read','elementor_full_read','organization_read','shared_read'),true) || !empty($settings[$group])) {
                foreach($slugs as $slug) { $names[]='calicomcp/'.$slug; }
            }
        }
        $all=NHI_Store::list_all();
        $data=array('name'=>'Personal access','role_ability_map'=>array('administrator'=>$names),'is_enabled'=>self::enabled()?1:0);
        if(!$all) { $created=NHI_Store::create($data); if(!$created) { return false; } NHI_Store::update($created['id'],$data); }
        else { foreach($all as $row) { $id=NHI_Store::find_id_by_uuid($row['uuid']); if(!$id || !NHI_Store::update($id,$data)) { return false; } } }
        return true;
    }
    public static function revoke($client_id) {
        Store::delete_client($client_id);
        global $wpdb;
        foreach(array('clients','tokens','codes') as $key) {
            $left=$wpdb->get_var($wpdb->prepare('SELECT COUNT(*) FROM %i WHERE client_id=%s',Store::table($key),$client_id));
            if($left===null || (int)$left!==0) { return false; }
        }
        return true;
    }
    public static function revoke_all() {
        global $wpdb;
        // DCR clients are durable application identities. Revoke user sessions,
        // including orphan credentials, without breaking the client's next login.
        foreach(array('tokens','codes')as $key){
            if($wpdb->query('DELETE FROM '.Store::table($key))===false)return false;
            $left=$wpdb->get_var('SELECT COUNT(*) FROM '.Store::table($key));
            if($left===null||(int)$left!==0)return false;
        }
        delete_transient(Store::SUMMARY_TRANSIENT);
        return true;
    }
    public static function registration_allowed() {
        global $wpdb;
        $count=(int)$wpdb->get_var('SELECT COUNT(*) FROM '.Store::table('clients'));
        if($count>=100) { return false; }
        $ip=isset($_SERVER['REMOTE_ADDR'])?(string)$_SERVER['REMOTE_ADDR']:'unknown';
        $key='calicomcp_dcr_'.hash('sha256',$ip); $n=(int)get_transient($key);
        if($n>=10) { return false; }
        set_transient($key,$n+1,HOUR_IN_SECONDS);
        return true;
    }
}

