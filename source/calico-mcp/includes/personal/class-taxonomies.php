<?php
namespace CalicoMCP\Personal;
use CalicoMCP\Abilities\Ability_Pack;
if (!defined('ABSPATH')) { exit; }
class Taxonomies extends Ability_Pack {
    public function category() { return array('slug'=>'calicomcp-taxonomies','label'=>'Categories and tags','description'=>'Approved content taxonomies and terms.'); }
    public function abilities() {
        $taxonomy=array('taxonomy'=>Toolbox::text('category, post_tag, or a selected custom taxonomy.'));
        $term=$taxonomy+array('term_id'=>Toolbox::id('Term ID, not a post ID.'));
        $edit=array('name'=>Toolbox::text(),'slug'=>Toolbox::text(),'description'=>Toolbox::text(),'parent'=>array('type'=>'integer','minimum'=>0));
        $specs=array(
            array('taxonomy-list','List available taxonomies for posts/pages and selected custom content.',array(),array(),'listing',true),
            array('term-list','Find existing terms.',$taxonomy+array('search'=>Toolbox::text(),'page'=>Toolbox::id('Page number.')),array('taxonomy'),'terms',true),
            array('term-get','Read a term and its revision.',$term,array('taxonomy','term_id'),'get',true),
            array('term-create','Create a category, tag or approved custom term; no deletion.',$taxonomy+$edit,array('taxonomy','name'),'create',false),
            array('term-update','Edit only supplied term properties. Requires a fresh term revision.',$term+$edit,array('taxonomy','term_id'),'update',false),
            array('content-set-terms','Add or remove existing terms without disturbing other assignments.',array('id'=>Toolbox::id(),'taxonomy'=>Toolbox::text(),'term_ids'=>array('type'=>'array','maxItems'=>100,'items'=>Toolbox::id(),'description'=>'Existing term IDs in the named taxonomy.'),'operation'=>array('type'=>'string','enum'=>array('add','remove'),'description'=>'Add these terms or remove only these terms.')),array('id','taxonomy','term_ids','operation'),'assign',false)
        );
        $out=array();foreach($specs as $s)$out[]=Toolbox::tool($s[0],$s[1],$s[2],$s[3],array(__CLASS__,$s[4]),$s[5],'calicomcp-taxonomies');return $out;
    }
    public static function allowed($taxonomy) {
        $t=get_taxonomy($taxonomy);if(!$t||!$t->show_ui)return false;
        if(in_array($taxonomy,array('category','post_tag'),true))return true;
        foreach(Events::scope() as $type=>$s) { if(Events::is_selected_type($type)&&in_array($taxonomy,$s['taxonomies']??array(),true)&&is_object_in_taxonomy($type,$taxonomy))return true; }
        return false;
    }
    private static function require_taxonomy($taxonomy,$write=false) {
        $t=self::allowed($taxonomy)?get_taxonomy($taxonomy):false;
        return $t&&(!$write||current_user_can($t->cap->manage_terms))?$t:new \WP_Error('taxonomy_denied','Taxonomy is not selected or permission is missing.');
    }
    public static function listing() {
        $out=array();foreach(get_taxonomies(array('show_ui'=>true),'objects') as $slug=>$t)if(self::allowed($slug))$out[]=array('taxonomy'=>$slug,'label'=>$t->label,'hierarchical'=>$t->hierarchical);return array('taxonomies'=>$out);
    }
    public static function terms($a) {
        $t=self::require_taxonomy($a['taxonomy']);if(is_wp_error($t))return $t;
        $items=get_terms(array('taxonomy'=>$a['taxonomy'],'hide_empty'=>false,'search'=>$a['search']??'','number'=>50,'offset'=>50*max(0,(int)($a['page']??1)-1)));
        if(is_wp_error($items))return $items;return array('terms'=>array_map(function($x){return array('id'=>$x->term_id,'name'=>$x->name,'slug'=>$x->slug,'parent'=>$x->parent);},$items));
    }
    public static function snapshot($a) {
        $t=self::require_taxonomy($a['taxonomy'],true);if(is_wp_error($t))return $t;
        $term=get_term($a['term_id'],$a['taxonomy']);if(!$term||is_wp_error($term))return new \WP_Error('term_missing','Term not found.');
        if(!current_user_can('edit_term',$term->term_id))return new \WP_Error('term_denied','This term cannot be edited by this account.');
        return array('entity'=>'term','id'=>(int)$term->term_id,'args'=>array('term_id'=>(int)$term->term_id,'taxonomy'=>$term->taxonomy),'values'=>array('name'=>$term->name,'slug'=>$term->slug,'description'=>$term->description,'parent'=>(int)$term->parent));
    }
    public static function get($a) { $s=self::snapshot($a);return is_wp_error($s)?$s:array_merge($s,array('revision'=>Toolbox::revision($s))); }
    private static function values($a) {
        $out=array();foreach(array('name','slug','description','parent') as $key)if(array_key_exists($key,$a))$out[$key]=$key==='parent'?(int)$a[$key]:($key==='description'?wp_kses_post($a[$key]):sanitize_text_field($a[$key]));
        if(isset($out['slug']))$out['slug']=sanitize_title($out['slug']);
        if(isset($a['term_id'],$out['parent'])&&(int)$a['term_id']===$out['parent'])return new \WP_Error('invalid_parent','A term cannot be its own parent.');
        if(isset($out['parent'])&&$out['parent']>0&&(!is_taxonomy_hierarchical($a['taxonomy'])||!term_exists($out['parent'],$a['taxonomy'])))return new \WP_Error('invalid_parent','Use an existing parent in this taxonomy.');
        return $out;
    }
    public static function create($a) {
        $t=self::require_taxonomy($a['taxonomy'],true);if(is_wp_error($t))return $t;$v=self::values($a);if(is_wp_error($v))return $v;
        $name=$v['name'];unset($v['name']);$r=wp_insert_term($name,$a['taxonomy'],$v);return is_wp_error($r)?$r:self::get(array('term_id'=>$r['term_id'],'taxonomy'=>$a['taxonomy']));
    }
    public static function update($a) {
        $s=self::snapshot($a);if(is_wp_error($s))return $s;$v=self::values($a);if(is_wp_error($v))return $v;
        $r=wp_update_term($a['term_id'],$a['taxonomy'],$v);if(is_wp_error($r))return $r;$read=self::get($a);if(is_wp_error($read))return $read;
        foreach($v as $key=>$value)if($read['values'][$key]!==$value)return new \WP_Error('term_save_failed','Term readback did not match: '.$key);
        return $read;
    }
    public static function restore_entity($s) { return wp_update_term($s['id'],$s['args']['taxonomy'],$s['values']); }
    public static function assign($a) {
        $post=get_post($a['id']);$t=self::require_taxonomy($a['taxonomy']);if(is_wp_error($t))return $t;
        if(!Toolbox::content_type($post)||!current_user_can('edit_post',$post->ID)||!current_user_can($t->cap->assign_terms)||!is_object_in_taxonomy($post->post_type,$a['taxonomy']))return new \WP_Error('assignment_denied','Content or taxonomy assignment is not allowed.');
        if(!in_array($post->post_type,array('post','page'),true)&&(!Policy::allowed('calicomcp/cpt-update')||!Policy::editable_status($post,true)))return new \WP_Error('content_status_denied','Selected custom content needs editing and the applicable status permission.');
        foreach($a['term_ids'] as $id)if(!term_exists($id,$a['taxonomy']))return new \WP_Error('term_missing','Use existing terms.');
        $before=wp_get_object_terms($post->ID,$a['taxonomy'],array('fields'=>'ids'));if(is_wp_error($before))return $before;
        $ids=$a['operation']==='add'?array_values(array_unique(array_merge($before,$a['term_ids']))):array_values(array_diff($before,$a['term_ids']));
        $r=wp_set_object_terms($post->ID,array_map('intval',$ids),$a['taxonomy'],false);if(is_wp_error($r))return $r;
        $read=wp_get_object_terms($post->ID,$a['taxonomy'],array('fields'=>'ids'));sort($ids);if(is_wp_error($read))return $read;sort($read);
        return $read===$ids?array('id'=>$post->ID,'taxonomy'=>$a['taxonomy'],'term_ids'=>$read):new \WP_Error('save_failed','Term assignments did not read back exactly.');
    }
}
