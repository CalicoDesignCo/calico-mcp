<?php
namespace CalicoMCP\Personal;
use CalicoMCP\Abilities\Ability_Pack;
if(!defined('ABSPATH'))exit;
/** Native content Trash only. Never call a delete API or restore publication implicitly. */
class Trash extends Ability_Pack {
    public function category(){return array('slug'=>'calicomcp-trash','label'=>'Content Trash','description'=>'Recoverable content removal and draft restoration.');}
    public function abilities(){
        $out=array(Toolbox::tool('content-trash-get','Read an editable post/page/selected CPT in Trash or an ordinary supported status. Returns a fresh revision and previous status; never returns deleted objects.',array('id'=>Toolbox::id()),array('id'),array(__CLASS__,'get'),true,'calicomcp-trash'));
        foreach(array('content-trash'=>'Move supported content into WordPress Trash. Requires Trash permission and native deletion rights. No permanent deletion. WordPress may automatically empty Trash after its configured retention period. Use content-restore-from-trash for recovery.', 'content-restore-from-trash'=>'Restore a supported trashed item to Draft, preserving its content and using native WordPress comment restoration. Never republish or restore an old schedule automatically. Requires Trash permission. Read content-trash-get first.')as $name=>$desc){
            $a=Toolbox::tool($name,$desc,array('id'=>Toolbox::id()),array('id'),array(__CLASS__,$name==='content-trash'?'trash':'restore'),false,'calicomcp-trash');$d=$a->to_args();$d['annotations']=array('readonly'=>false,'destructive'=>true,'idempotent'=>false,'open_world'=>true);$out[]=new \CalicoMCP\Abilities\Ability('calicomcp/'.$name,$d);
        }return $out;
    }
    private static function post($id){
        $p=get_post($id);
        return Toolbox::content_type($p)&&!in_array($p->post_type,Shared_Templates::types(),true)&&in_array($p->post_status,array('draft','pending','private','future','publish','trash'),true)&&current_user_can('edit_post',$id)?$p:new \WP_Error('trash_denied','Use an editable post, page or explicitly selected CPT. Attachments, revisions, shared templates and auto-drafts are excluded.');
    }
    public static function get($a){$p=self::post($a['id']);if(is_wp_error($p))return $p;return array('id'=>(int)$p->ID,'post_type'=>$p->post_type,'title'=>$p->post_title,'status'=>$p->post_status,'previous_status'=>$p->post_status==='trash'?get_post_meta($p->ID,'_wp_trash_meta_status',true):null,'restore_status'=>'draft','scheduled_timestamp'=>wp_next_scheduled('publish_future_post',array((int)$p->ID))?:null,'revision'=>Guard::revision(Guard::snapshot($p->ID)));}
    public static function trash($a){return self::change($a,false);}
    public static function restore($a){return self::change($a,true);}
    public static function execute($ability,$args){
        $id=absint($args['id']??0);$lock=Guard::lock($id);if(!$lock)return new \WP_Error('item_busy','Another edit is in progress.');
        try{$p=self::post($id);if(is_wp_error($p))return $p;$before=Guard::snapshot($id);if(!is_string($args['expected_revision']??null)||!hash_equals(Guard::revision($before),$args['expected_revision']))return new \WP_Error('stale_revision','Read content-trash-get again before changing this item.');unset($args['expected_revision']);return $ability->execute($args);}finally{Guard::unlock($lock);}
    }
    private static function change($a,$restore){
        if(empty(Policy::settings()['trash']))return new \WP_Error('trash_denied','Trash/Restore permission is off.');
        $p=self::post($a['id']);if(is_wp_error($p))return $p;
        if(!current_user_can('delete_post',$p->ID))return new \WP_Error('trash_denied','WordPress refused native deletion permission.');
        if(($restore&&$p->post_status!=='trash')||(!$restore&&$p->post_status==='trash'))return new \WP_Error('trash_status','Read the current Trash status before choosing an action.');
        if(!$restore&&(!defined('EMPTY_TRASH_DAYS')||EMPTY_TRASH_DAYS<=0))return new \WP_Error('trash_disabled','WordPress Trash retention is disabled. Refusing because native Trash would permanently delete the item.');
        $force_draft=static function($status,$id)use($p){return (int)$id===(int)$p->ID?'draft':$status;};
        if($restore)add_filter('wp_untrash_post_status',$force_draft,PHP_INT_MAX,2);
        try{$r=$restore?wp_untrash_post($p->ID):wp_trash_post($p->ID);}finally{if($restore)remove_filter('wp_untrash_post_status',$force_draft,PHP_INT_MAX);}
        if(!$r||is_wp_error($r))return is_wp_error($r)?$r:new \WP_Error('trash_failed','WordPress refused the operation. Read the saved state before retrying.');
        clean_post_cache($p->ID);$fresh=get_post($p->ID);
        if(!$fresh||$fresh->post_status!==($restore?'draft':'trash'))return new \WP_Error('trash_readback_failed','Saved status differs. Inspect the item before retrying.');
        foreach(array('post_title','post_content','post_excerpt','post_type','post_author','post_parent')as $key)if($p->$key!==$fresh->$key)return new \WP_Error('trash_preservation_failed','A WordPress hook changed another content field: '.$key.'. Inspect the item before retrying.');
        wp_clear_scheduled_hook('publish_future_post',array((int)$p->ID));
        $out=self::get($a);if($out['scheduled_timestamp']!==null)return new \WP_Error('trash_cron_failed','The item changed status but its publication cron was not cleared. Inspect it before retrying.');
        $out['recovery_note']='Trash uses WordPress native recovery, not a Calico Undo checkpoint. Restore returns to Draft; publish or schedule separately. Native Trash retention/automatic cleanup still applies. External notifications cannot be retracted.';return $out;
    }
}
