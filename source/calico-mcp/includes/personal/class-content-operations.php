<?php
namespace CalicoMCP\Personal;
use CalicoMCP\Abilities\Ability_Pack;
use CalicoMCP\Abilities\Packs\Core\Support\Post_Duplicator;
if (!defined('ABSPATH')) { exit; }
class Content_Operations extends Ability_Pack {
    public function category() { return array('slug'=>'calicomcp-content-operations','label'=>'Content structure and revisions','description'=>'Draft duplication, templates and guarded revision restoration.'); }
    public function abilities() {
        $id=array('id'=>Toolbox::id());
        $specs=array(
            array('content-duplicate','Copy an existing post/page/selected CPT, including its design metadata and terms, into a new draft. Read the source immediately before copying.',$id+array('title'=>Toolbox::text()),array('id'),'duplicate',false),
            array('content-template-get','Read the assigned template and registered choices.',$id,array('id'),'template_get',true),
            array('content-template-set','Assign a registered template to an existing draft, preserving other metadata.',$id+array('template'=>Toolbox::text()),array('id','template'),'template_set',false),
            array('content-revision-compare','Compare a saved revision with its exact parent.',$id+array('revision_id'=>Toolbox::id()),array('id','revision_id'),'compare',true),
            array('content-revision-restore','Restore a native WordPress revision on an existing non-Elementor draft, including revision-enabled metadata. Elementor documents/revisions are refused because their restore hooks can change layout outside the core comparison. Use Calico Undo or Elementor History for those.',$id+array('revision_id'=>Toolbox::id()),array('id','revision_id'),'restore',false)
        );
        $out=array();foreach($specs as $s)$out[]=Toolbox::tool($s[0],$s[1],$s[2],$s[3],array(__CLASS__,$s[4]),$s[5]);return $out;
    }
    private static function post($id,$draft=false) {
        $p=get_post($id);
        return Toolbox::content_type($p)&&current_user_can('edit_post',$id)&&!in_array($p->post_status,array('trash','auto-draft'),true)&&(!$draft||$p->post_status==='draft')?$p:new \WP_Error('content_denied','Use a supported readable item; structural edits and revision restore require a draft.');
    }
    public static function duplicate($a) {
        $post=self::post($a['id']);if(is_wp_error($post))return $post;
        $result=Post_Duplicator::duplicate(array('source_id'=>$post->ID,'status'=>'draft','title'=>$a['title']??''),$post->post_type);
        if(is_array($result)) { $result['id']=(int)$result['new_id'];$result['revision']=Guard::revision(Guard::snapshot($result['id'])); }return $result;
    }
    public static function template_get($a) {
        $post=self::post($a['id']);if(is_wp_error($post))return $post;
        return array('id'=>$post->ID,'template'=>get_page_template_slug($post->ID)?:'default','choices'=>(object)(array_merge(array('default'=>'Default'),wp_get_theme()->get_page_templates($post,$post->post_type))),'revision'=>Guard::revision(Guard::snapshot($post->ID)));
    }
    public static function template_set($a) {
        $post=self::post($a['id'],true);if(is_wp_error($post))return $post;
        $choices=array_merge(array('default'=>'Default'),wp_get_theme()->get_page_templates($post,$post->post_type));
        if(!isset($choices[$a['template']])||!Events::can_edit_meta($post->ID,'_wp_page_template'))return new \WP_Error('template_denied','Choose a registered template with metadata permission.');
        update_post_meta($post->ID,'_wp_page_template',$a['template']);
        return get_post_meta($post->ID,'_wp_page_template',true)===$a['template']?self::template_get($a):new \WP_Error('save_failed','Template readback failed.');
    }
    private static function revision($a) {
        $parent=self::post($a['id']);if(is_wp_error($parent))return $parent;
        $revision=wp_get_post_revision($a['revision_id']);
        return $revision&&(int)$revision->post_parent===(int)$parent->ID?$revision:new \WP_Error('revision_denied','Revision does not belong to this item.');
    }
    public static function compare($a) {
        $r=self::revision($a);if(is_wp_error($r))return $r;$p=get_post($a['id']);$changes=array();
        foreach(array('post_title','post_content','post_excerpt') as $field)$changes[$field]=array('current'=>$p->$field,'revision'=>$r->$field,'different'=>$p->$field!==$r->$field);
        $elementor=self::elementor_revision($p->ID,$r->ID);
        return array('id'=>$p->ID,'revision_id'=>$r->ID,'fields'=>$changes,'revision_meta_keys'=>function_exists('wp_post_revision_meta_keys')?wp_post_revision_meta_keys($p->post_type):array(),'restore_supported'=>!$elementor&&$p->post_status==='draft','restore_limitation'=>$elementor?'Core fields shown here do not describe Elementor layout/settings restored by its hooks. Native restore is refused; use Calico Undo or Elementor History.':($p->post_status==='draft'?'':'Native restore requires a draft.'),'revision'=>Guard::revision(Guard::snapshot($p->ID)));
    }
    private static function elementor_revision($parent,$revision) {
        // Elementor copies metadata through wp_restore_post_revision rather than
        // WordPress revision-enabled meta. Check both ends, including old layouts
        // on a now-plain page, and do not depend on Elementor being active.
        foreach(array($parent,$revision)as $id)foreach(array('_elementor_data','_elementor_edit_mode','_elementor_page_settings')as $key)if(metadata_exists('post',$id,$key))return true;
        return false;
    }
    public static function restore($a) {
        $p=self::post($a['id'],true);if(is_wp_error($p))return $p;$r=self::revision($a);if(is_wp_error($r))return $r;
        if(self::elementor_revision($p->ID,$r->ID))return new \WP_Error('elementor_revision_unsupported','Native revision restore is refused for Elementor documents or saved Elementor revisions: its hooks can change layout outside the core comparison. Use Calico Undo or Elementor History.');
        foreach(function_exists('wp_post_revision_meta_keys')?wp_post_revision_meta_keys($p->post_type):array() as $key)if(!Events::can_edit_meta($p->ID,$key))return new \WP_Error('revision_meta_denied','Revision metadata authorization refused.');
        $result=wp_restore_post_revision($r->ID);if(!$result||is_wp_error($result))return is_wp_error($result)?$result:new \WP_Error('restore_failed','WordPress did not restore the revision.');
        $fresh=get_post($p->ID);foreach(array('post_title','post_content','post_excerpt') as $field)if($fresh->$field!==$r->$field)return new \WP_Error('restore_failed','Restored content does not match the revision.');
        if(get_post_meta($p->ID,'_elementor_data',true))Elementor::invalidate($p->ID);
        return array('id'=>$p->ID,'restored_revision_id'=>$r->ID,'status'=>$fresh->post_status);
    }
}
