<?php
namespace CalicoMCP\Personal;
if (!defined('ABSPATH')) { exit; }
/** Recovery for non-post entities; never interpret a term or form entry ID as a post. */
class Entity_Recovery {
    public static function handles($tool) { return in_array($tool,array('calicomcp/organization-update','calicomcp/term-update','calicomcp/gf-mark-entry-read','calicomcp/gf-mark-entry-unread','calicomcp/gf-trash-entry','calicomcp/gf-restore-entry-from-trash'),true); }
    // Recognize old entry records so their numeric IDs can never become post IDs.
    public static function provider($tool) { return $tool==='calicomcp/organization-update'?Organization::class:($tool==='calicomcp/term-update'?Taxonomies::class:null); }
    public static function run($ability,$args) {
        $tool=$ability->get_name(); $provider=self::provider($tool);
        if(!$provider)return new \WP_Error('integration_removed','Gravity Forms integration was removed. Existing recovery records are retained; no entry was changed.');
        $before=$provider::snapshot($args);
        if(is_wp_error($before))return $before;
        $lock=Guard::lock('entity_'.($before['entity']??'unknown').'_'.$before['id']);
        if(!$lock)return new \WP_Error('item_busy','Another edit is in progress.');
        try {
            $before=$provider::snapshot($args); if(is_wp_error($before))return $before;
            if(!isset($args['expected_revision'])||!hash_equals(Toolbox::revision($before),(string)$args['expected_revision']))return new \WP_Error('stale_revision','Read this exact entity again before editing.');
            $backup=Recovery::begin($before['id'],$before,$tool); if(!$backup)return new \WP_Error('backup_failed','No edit was attempted.');
            unset($args['expected_revision']); $result=$ability->execute($args); $after=$provider::snapshot($args);
            global $wpdb;
            $state=is_wp_error($after)?'failed':($before===$after?'empty':'ready');
            $finished=$wpdb->update(Recovery::table(),array('state'=>$state,'after_hash'=>is_wp_error($after)?'':Toolbox::revision($after)),array('id'=>$backup));
            if($finished===false)return new \WP_Error('recovery_finalize_failed','The edit may have run. Recovery data was retained.',array('recovery_id'=>$backup));
            if(is_array($result)) { $result['recovery_id']=$backup; if(!is_wp_error($after))$result['revision']=Toolbox::revision($after); }
            elseif(is_wp_error($result))$result->add_data(array('recovery_id'=>$backup));
            return $result;
        } finally { Guard::unlock($lock); }
    }
    public static function restore($row) {
        $before=json_decode($row['before_data'],true); $provider=self::provider($row['tool']);
        if(!$provider)return new \WP_Error('integration_removed','Gravity Forms integration was removed. Existing recovery records are retained; no entry was changed.');
        $args=$before['args']; $lock=Guard::lock('entity_'.$before['entity'].'_'.$before['id']);
        if(!$lock)return new \WP_Error('item_busy','Another edit is in progress.');
        try {
            global $wpdb;
            $current=$provider::snapshot($args);
            if(is_wp_error($current))return $current;
            if(!hash_equals($row['after_hash'],Toolbox::revision($current)))return new \WP_Error('stale_recovery','This entity changed after that edit.');
            $claimed=$wpdb->query($wpdb->prepare("UPDATE %i SET state='restoring' WHERE id=%d AND state='ready'",Recovery::table(),$row['id']));
            if($claimed!==1)return new \WP_Error('recovery_busy','Recovery is no longer available.');
            $result=$provider::restore_entity($before); $read=$provider::snapshot($args);
            $ok=!is_wp_error($result)&&!is_wp_error($read)&&$read===$before;
            $wpdb->update(Recovery::table(),array('state'=>$ok?'restored':'failed'),array('id'=>$row['id']));
            return $ok?true:new \WP_Error('restore_failed','Exact recovery readback failed. Recovery data was retained.');
        } finally { Guard::unlock($lock); }
    }
}
