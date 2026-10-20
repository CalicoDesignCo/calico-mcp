<?php
namespace CalicoMCP\Personal;
if (!defined('ABSPATH')) { exit; }
class Recovery {
    public static function table() { global $wpdb; return $wpdb->prefix.'calicomcp_recovery'; }
    public static function install() {
        global $wpdb; require_once ABSPATH.'wp-admin/includes/upgrade.php';
        $table=self::table(); $charset=$wpdb->get_charset_collate();
        dbDelta("CREATE TABLE {$table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            user_id bigint(20) unsigned NOT NULL,
            post_id bigint(20) unsigned NOT NULL,
            tool varchar(191) NOT NULL,
            created bigint(20) unsigned NOT NULL,
            state varchar(20) NOT NULL DEFAULT 'pending',
            before_data longtext NOT NULL,
            after_hash varchar(64) NOT NULL DEFAULT '',
            PRIMARY KEY  (id),
            KEY created (created)
        ) {$charset};");
    }
    public static function begin($id,$before,$tool) {
        global $wpdb;
        $ok=$wpdb->insert(self::table(),array('user_id'=>get_current_user_id(),'post_id'=>$id,'tool'=>$tool,'created'=>time(),'state'=>'pending','before_data'=>wp_json_encode($before)));
        return $ok?(int)$wpdb->insert_id:0;
    }
    public static function finish($id,$after) {
        global $wpdb;
        $row=$wpdb->get_row($wpdb->prepare('SELECT * FROM %i WHERE id=%d',self::table(),$id),ARRAY_A);
        if(!$row || !$after) { return false; }
        $before=json_decode($row['before_data'],true); $delta=array('fields'=>array(),'meta'=>array(),'terms'=>array());
        $event=in_array($row['tool'],array('calicomcp/event-update-draft','calicomcp/cpt-update-draft','calicomcp/cpt-update','calicomcp/cpt-repeater-rows'),true);
        $full_elementor=Policy::group($row['tool'])==='elementor_full';
        $full_revision=$row['tool']==='calicomcp/content-revision-restore';
        $selection=$event?(Events::scope()[$before['fields']['post_type']]??array()):array();
        foreach(array('post_title','post_content','post_excerpt','post_name','post_status') as $field) { if($before['fields'][$field]!==$after['fields'][$field]) { $delta['fields'][$field]=$before['fields'][$field]; } }
        foreach(array_unique(array_merge(array_keys($before['meta']),array_keys($after['meta']))) as $key) {
            if(in_array($key,array('_elementor_css','_elementor_element_cache','_elementor_page_assets'),true)||preg_match('/^_elementor_migrations_state_[a-f0-9]{4}$/D',$key))continue;
            if(!$full_elementor&&!$full_revision&&!($row['tool']==='calicomcp/elementor-update-draft' && $key==='_elementor_data') && !in_array($key,array('_thumbnail_id','_wp_attachment_image_alt','_wp_page_template','_calicomcp_sitemap_excluded'),true) && strpos($key,'_yoast_wpseo_')!==0 && !in_array($key,$selection['fields']??array(),true)) { continue; }
            if(($before['meta'][$key]??null)!==($after['meta'][$key]??null)) { $delta['meta'][$key]=$before['meta'][$key]??null; }
        }
        foreach($selection['taxonomies']??array() as $tax) { if(($before['terms'][$tax]??array())!==($after['terms'][$tax]??array())) { $delta['terms'][$tax]=$before['terms'][$tax]??array(); } }
        if($row['tool']==='calicomcp/content-set-terms')foreach($before['terms'] as $tax=>$ids)if($ids!==($after['terms'][$tax]??array()))$delta['terms'][$tax]=$ids;
        $changed=!empty($delta['fields'])||!empty($delta['meta'])||!empty($delta['terms']);
        return $wpdb->update(self::table(),array('state'=>$changed?'ready':'empty','before_data'=>wp_json_encode($delta),'after_hash'=>Guard::revision($after)),array('id'=>$id))!==false;
    }
    public static function recent() { global $wpdb; return $wpdb->get_results($wpdb->prepare("SELECT id,post_id,tool,created,state FROM %i WHERE user_id=%d AND state='ready' ORDER BY id DESC LIMIT 20",self::table(),get_current_user_id()),ARRAY_A); }
    public static function restore($id) {
        if(!Policy::is_owner(get_current_user_id())) { return new \WP_Error('forbidden','Only the configured account can undo edits.'); }
        global $wpdb;
        $entity=$wpdb->get_row($wpdb->prepare("SELECT * FROM %i WHERE id=%d AND user_id=%d AND state='ready'",self::table(),$id,get_current_user_id()),ARRAY_A);
        if($entity&&Entity_Recovery::handles($entity['tool']))return Entity_Recovery::restore($entity);
        $post_id=(int)$wpdb->get_var($wpdb->prepare("SELECT post_id FROM %i WHERE id=%d AND user_id=%d AND state='ready'",self::table(),$id,get_current_user_id()));
        if(!$post_id) { return new \WP_Error('not_found','Recovery entry is not available.'); }
        $lock=Guard::lock($post_id); if(!$lock) { return new \WP_Error('item_busy','Another Calico edit is in progress.'); }
        try { return self::restore_locked($id); } finally { Guard::unlock($lock); }
    }
    private static function restore_locked($id) {
        global $wpdb;
        if(!Policy::is_owner(get_current_user_id())) { return new \WP_Error('forbidden','Only the configured account can undo edits.'); }
        $row=$wpdb->get_row($wpdb->prepare("SELECT * FROM %i WHERE id=%d AND user_id=%d AND state='ready'",self::table(),$id,get_current_user_id()),ARRAY_A);
        if(!$row || !current_user_can('edit_post',(int)$row['post_id'])) { return new \WP_Error('not_found','Recovery entry is not available.'); }
        $post_id=(int)$row['post_id']; $current=Guard::snapshot($post_id);
        if(!$current || !hash_equals($row['after_hash'],Guard::revision($current))) { return new \WP_Error('stale_recovery','This item has changed since that edit. Undo was refused to preserve newer work.'); }
        // Claim the recovery entry before applying it, so repeated submissions cannot run twice.
        $claimed=$wpdb->query($wpdb->prepare("UPDATE %i SET state='restoring' WHERE id=%d AND state='ready'",self::table(),$id));
        if($claimed!==1) { return new \WP_Error('recovery_busy','This recovery is already in progress.'); }
        $delta=json_decode($row['before_data'],true);
        if(!empty($delta['fields'])) {
            $result=wp_update_post(wp_slash(array_merge(array('ID'=>$post_id),$delta['fields'])),true);
            if(is_wp_error($result)) { $wpdb->update(self::table(),array('state'=>'ready'),array('id'=>$id)); return $result; }
        }
        foreach($delta['meta'] as $key=>$values) {
            delete_post_meta($post_id,$key);
            // Snapshots contain raw database meta strings. Decode exactly one level
            // before WordPress serializes again, including repeater arrays.
            if($values!==null) { foreach($values as $value) { add_post_meta($post_id,$key,wp_slash(maybe_unserialize($value))); } }
        }
        foreach($delta['terms']??array() as $taxonomy=>$ids) { $r=wp_set_object_terms($post_id,array_map('intval',$ids),$taxonomy,false); if(is_wp_error($r)) { $wpdb->update(self::table(),array('state'=>'failed'),array('id'=>$id)); return $r; } }
        clean_post_cache($post_id);
        if($row['tool']==='calicomcp/elementor-update-draft'||Policy::group($row['tool'])==='elementor_full'||($row['tool']==='calicomcp/content-revision-restore'&&get_post_meta($post_id,'_elementor_data',true))) { Elementor::invalidate($post_id); }
        $restored=Guard::snapshot($post_id);
        foreach($delta['fields'] as $key=>$value) { if($restored['fields'][$key]!==$value) { $wpdb->update(self::table(),array('state'=>'failed'),array('id'=>$id)); return new \WP_Error('restore_failed','Content readback did not match. Recovery data was retained.'); } }
        foreach($delta['meta'] as $key=>$value) { if(($restored['meta'][$key]??null)!==$value) { $wpdb->update(self::table(),array('state'=>'failed'),array('id'=>$id)); return new \WP_Error('restore_failed','Metadata readback did not match. Recovery data was retained.'); } }
        foreach($delta['terms']??array() as $taxonomy=>$ids) { sort($ids); if(($restored['terms'][$taxonomy]??array())!==$ids) { $wpdb->update(self::table(),array('state'=>'failed'),array('id'=>$id)); return new \WP_Error('restore_failed','Taxonomy readback did not match. Recovery data was retained.'); } }
        $wpdb->update(self::table(),array('state'=>'restored'),array('id'=>$id));
        return true;
    }
    public static function purge() {
        global $wpdb;
        $wpdb->query($wpdb->prepare('DELETE FROM %i WHERE created < %d',self::table(),time()-30*DAY_IN_SECONDS));
    }
}
