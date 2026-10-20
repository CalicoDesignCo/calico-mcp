<?php
namespace CalicoMCP\Personal;
if (!defined('ABSPATH')) { exit; }
class Forms {
    public static function available() { return class_exists('GFAPI'); }
    public static function can($capability) {
        if(!self::available())return false;
        // Use the provider's permission decision, including its native full-access capability.
        // Never grant access merely because the WordPress user manages site options.
        if(is_callable(array('GFCommon','current_user_can_any')))return (bool)\GFCommon::current_user_can_any($capability);
        return current_user_can($capability);
    }
    public static function snapshot($a) {
        if(!self::can('gravityforms_view_entries')||!self::can('gravityforms_edit_entries'))return new \WP_Error('forms_denied','Gravity Forms entry permissions are required.');
        $entry=\GFAPI::get_entry((int)$a['id']);if(is_wp_error($entry))return $entry;
        return array('entity'=>'gravity-entry','id'=>(int)$entry['id'],'args'=>array('id'=>(int)$entry['id']),'values'=>$entry);
    }
    public static function restore_entity($before) {
        $check=self::snapshot($before['args']);if(is_wp_error($check))return $check;
        // Only the properties exposed by our four management tools can change.
        foreach(array('is_read','status') as $key) {
            if(($check['values'][$key]??null)===($before['values'][$key]??null))continue;
            $r=\GFAPI::update_entry_property($before['id'],$key,$before['values'][$key]);if(is_wp_error($r)||$r===false)return new \WP_Error('entry_restore_failed','Gravity Forms entry restoration failed.');
        }
        return true;
    }
    public static function entry_revision($result,$args) {
        // The entry getter's output is provider-specific; attach an explicit entity token.
        if(is_array($result)&&isset($args['id'])&&self::can('gravityforms_edit_entries')) {
            $snapshot=self::snapshot($args);if(!is_wp_error($snapshot))$result['revision']=Toolbox::revision($snapshot);
        }
        return $result;
    }
}
