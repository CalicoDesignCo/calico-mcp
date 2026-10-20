<?php
namespace CalicoMCP\Personal;
use CalicoMCP\Abilities\Ability;
use CalicoMCP\Abilities\Ability_Pack;
if (!defined('ABSPATH')) { exit; }

/** Stored legacy Elementor trees; no document-wide sanitization or layout replacement. */
class Elementor extends Ability_Pack {
    public static function available() { return defined('ELEMENTOR_VERSION') && class_exists('Elementor\\Plugin'); }
    public function category() { return array('slug'=>'calicomcp-elementor','label'=>'Elementor','description'=>'Read stored layouts and edit standard widgets in drafts.'); }
    public function abilities() {
        // The lazy WordPress registry can initialize while Elementor itself is loading.
        // Register stable contracts here; Policy checks runtime availability on exposure.
        $id=array('type'=>'integer','minimum'=>1,'description'=>'Existing WordPress post or page ID; image_id is an existing image attachment ID.');
        $common=array('id'=>$id);
        $specs=array(
            array('elementor-get','Read an Elementor layout','Read the stored legacy element tree, complete page settings and editable widget fields. Atomic elements and dynamic fields are read-only.', $common,array('id'),'get',true),
            array('elementor-update-draft','Edit one Elementor draft widget','Change one existing standard widget in a draft post or page. Read elementor-get first. Preserves all omitted settings, other widgets and page settings.',array_merge($common,array('element_id'=>array('type'=>'string','pattern'=>'^[A-Za-z0-9_-]{1,64}$','description'=>'Exact existing widget ID returned by elementor-get.'),'field'=>array('type'=>'string','enum'=>array('title','editor','image'),'description'=>'One editable field listed by elementor-get for this widget.'),'text'=>array('type'=>'string','maxLength'=>20000),'image_id'=>$id)),array('id','element_id','field'),'update',false)
        );
        $out=array(); foreach($specs as $s) { $out[]=new Ability('calicomcp/'.$s[0],array('label'=>$s[1],'description'=>$s[2],'category'=>'calicomcp-elementor','input_schema'=>array('type'=>'object','properties'=>$s[3],'required'=>$s[4],'additionalProperties'=>false),'execute'=>array(__CLASS__,$s[5]),'capability'=>'manage_options','annotations'=>array('readonly'=>$s[6],'destructive'=>false,'idempotent'=>true,'open_world'=>false))); } return $out;
    }
    private static function document($id) {
        if(!Policy::is_owner(get_current_user_id()) || !Policy::allowed('calicomcp/elementor-get') || !self::available() || !current_user_can('edit_post',$id)) { return new \WP_Error('elementor_denied','Elementor access is unavailable for this account.'); }
        $post=get_post($id);
        if(!$post || !in_array($post->post_type,array('post','page'),true) || in_array($post->post_status,array('trash','auto-draft'),true)) { return new \WP_Error('unsupported_document','Only existing posts and pages are supported.'); }
        $raw=get_post_meta($id,'_elementor_data',true);
        if(!is_string($raw) || strlen($raw)>2*1024*1024 || count(get_post_meta($id,'_elementor_data',false))!==1) { return new \WP_Error('unsupported_layout','A single stored Elementor JSON layout under 2 MB is required.'); }
        $tree=json_decode($raw,true);
        if(!is_array($tree) || json_last_error()!==JSON_ERROR_NONE || array_values($tree)!==$tree) { return new \WP_Error('invalid_layout','The stored Elementor tree is not a JSON list.'); }
        $seen=array(); $count=0; $valid=self::validate_tree($tree,$seen,$count,0);
        if(!$valid) { return new \WP_Error('invalid_layout','Layout has malformed elements, duplicate IDs, or exceeds 2000 elements / 30 levels.'); }
        return array('post'=>$post,'tree'=>$tree,'raw'=>$raw);
    }
    private static function validate_tree($tree,&$seen,&$count,$depth) {
        if($depth>30 || !is_array($tree) || array_values($tree)!==$tree) { return false; }
        foreach($tree as $node) {
            if(!is_array($node) || !is_string($node['id']??null) || !preg_match('/^[A-Za-z0-9_-]{1,64}$/D',$node['id']) || isset($seen[$node['id']]) || ++$count>2000 || !is_string($node['elType']??null) || (isset($node['settings']) && !is_array($node['settings']))) { return false; }
            $seen[$node['id']]=true;
            if(isset($node['elements']) && !self::validate_tree($node['elements'],$seen,$count,$depth+1)) { return false; }
        } return true;
    }
    private static function editable($node) {
        $map=array('heading'=>'title','text-editor'=>'editor','image'=>'image');
        $field=$map[$node['widgetType']??'']??null;
        if(($node['elType']??'')!=='widget' || !$field || !empty($node['settings']['__dynamic__'][$field]) || !empty($node['settings']['__globals__'][$field])) { return array(); }
        return array($field);
    }
    private static function summary($tree,&$out) {
        foreach($tree as $node) { $out[]=array('id'=>$node['id'],'type'=>$node['elType'],'widget'=>$node['widgetType']??null,'editable_fields'=>self::editable($node)); self::summary($node['elements']??array(),$out); }
    }
    public static function get($a) {
        $id=absint($a['id']??0); $doc=self::document($id); if(is_wp_error($doc)) { return $doc; }
        $summary=array(); self::summary($doc['tree'],$summary);
        return array('id'=>$id,'title'=>$doc['post']->post_title,'status'=>$doc['post']->post_status,'elementor_version'=>ELEMENTOR_VERSION,'elements'=>$doc['tree'],'page_settings'=>get_post_meta($id,'_elementor_page_settings',true),'widgets'=>$summary,'draft_edits_supported'=>$doc['post']->post_status==='draft','revision'=>Guard::revision(Guard::snapshot($id)));
    }
    private static function patch(&$tree,$a,&$found) {
        foreach($tree as &$node) {
            if($node['id']===$a['element_id']) {
                if(!in_array($a['field'],self::editable($node),true)) { return new \WP_Error('unsupported_field','Only standard heading title, text-editor editor, and image fields without dynamic/global bindings can be changed.'); }
                $field=$a['field']; $settings=$node['settings']??array();
                if($field==='image') {
                    if(isset($a['text']) || empty($a['image_id']) || !wp_attachment_is_image($a['image_id']) || !current_user_can('read_post',$a['image_id'])) { return new \WP_Error('invalid_image','Supply an existing readable image attachment ID only.'); }
                    $old=$settings['image']??array();
                    if(!is_array($old)) { return new \WP_Error('unsupported_image','Stored image settings are not an object.'); }
                    $url=wp_get_attachment_url($a['image_id']); if(!$url) { return new \WP_Error('invalid_image','Image URL is unavailable.'); }
                    $settings['image']=array_merge($old,array('id'=>(int)$a['image_id'],'url'=>$url));
                } else {
                    if(isset($a['image_id']) || !isset($a['text']) || !is_string($a['text']) || strlen($a['text'])>20000) { return new \WP_Error('invalid_text','Supply text only, up to 20000 bytes.'); }
                    $settings[$field]=wp_kses_post($a['text']);
                }
                $node['settings']=$settings; $found=true; return true;
            }
            if(!empty($node['elements'])) { $r=self::patch($node['elements'],$a,$found); if(is_wp_error($r) || $found) { return $r; } }
        } return true;
    }
    public static function invalidate($id) {
        // Only derived caches, never document/page settings or animation assets.
        delete_post_meta($id,'_elementor_element_cache');
        if(class_exists('Elementor\\Core\\Files\\CSS\\Post')) { \Elementor\Core\Files\CSS\Post::create($id)->delete(); }
        clean_post_cache($id);
    }
    private static function encode_patch(&$nodes,$a,$patched) {
        foreach($nodes as $node) {
            if($node->id===$a['element_id']) {
                if(!isset($node->settings) || !is_object($node->settings)) { $node->settings=(object)($node->settings??array()); }
                $field=$a['field'];
                if($field==='image') {
                    if(!isset($node->settings->image) || !is_object($node->settings->image)) { $node->settings->image=(object)array(); }
                    $node->settings->image->id=$patched['id']; $node->settings->image->url=$patched['url'];
                } else { $node->settings->$field=$patched; }
                return true;
            }
            if(!empty($node->elements) && self::encode_patch($node->elements,$a,$patched)) { return true; }
        } return false;
    }
    private static function patched_value($nodes,$a) {
        foreach($nodes as $node) { if($node['id']===$a['element_id']) { return $node['settings'][$a['field']]; } $r=self::patched_value($node['elements']??array(),$a); if($r!==null) { return $r; } } return null;
    }
    public static function update($a) {
        if(!Policy::allowed('calicomcp/elementor-update-draft')) { return new \WP_Error('elementor_denied','Elementor draft editing is disabled.'); }
        $id=absint($a['id']??0); $doc=self::document($id); if(is_wp_error($doc)) { return $doc; }
        if($doc['post']->post_status!=='draft') { return new \WP_Error('elementor_draft_only','Only draft posts and pages can be edited.'); }
        $found=false; $r=self::patch($doc['tree'],$a,$found); if(is_wp_error($r)) { return $r; }
        if(!$found) { return new \WP_Error('element_not_found','Element ID was not found in this document.'); }
        // Retain JSON object/list distinctions in all untouched values, including empty objects.
        $objects=json_decode($doc['raw']); self::encode_patch($objects,$a,self::patched_value($doc['tree'],$a));
        $encoded=wp_json_encode($objects); if($encoded===false) { return new \WP_Error('invalid_layout','Layout encoding failed.'); }
        if(get_post_meta($id,'_elementor_data',true)!==$encoded) {
            if(!update_post_meta($id,'_elementor_data',wp_slash($encoded))) { return new \WP_Error('elementor_save_failed','Layout write failed. Read before retrying.'); }
            self::invalidate($id);
        }
        if(get_post_meta($id,'_elementor_data',true)!==$encoded) { return new \WP_Error('elementor_readback_failed','Saved layout differs from the requested patch. Inspect recovery before retrying.'); }
        return self::get(array('id'=>$id));
    }
}
