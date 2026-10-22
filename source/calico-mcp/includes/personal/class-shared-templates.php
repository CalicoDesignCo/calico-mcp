<?php
namespace CalicoMCP\Personal;
use CalicoMCP\Abilities\Ability;
use CalicoMCP\Abilities\Ability_Pack;
if(!defined('ABSPATH'))exit;
/** Existing Elementor template documents only; no assignment/condition mutation. */
class Shared_Templates extends Ability_Pack {
    public static function types(){return array('elementor_library','jet-theme-core','ea_theme_builder');}
    public static function supported($post){return $post&&in_array($post->post_type,self::types(),true)&&post_type_exists($post->post_type)&&get_post_meta($post->ID,'_elementor_data',true)&&!in_array($post->post_status,array('trash','auto-draft','inherit'),true);}
    public function category(){return array('slug'=>'calicomcp-shared','label'=>'Shared templates','description'=>'Inspect existing shared Elementor documents.');}
    public function abilities(){return array(new Ability('calicomcp/shared-template-list',array('label'=>'List shared Elementor templates','description'=>'Find existing Elementor, Jupiter X/JetThemeCore and Essential Addons template documents. Read-only. A published shared template may affect many pages. Conditions and assignments are not changed.','category'=>'calicomcp-shared','input_schema'=>array('type'=>'object','properties'=>array('offset'=>array('type'=>'integer','minimum'=>0),'per_page'=>array('type'=>'integer','minimum'=>1,'maximum'=>50)),'additionalProperties'=>false),'execute'=>array(__CLASS__,'listing'),'capability'=>'manage_options','annotations'=>array('readonly'=>true,'destructive'=>false,'idempotent'=>true,'open_world'=>false))));}
    public static function listing($a){
        if(!Elementor::available())return new \WP_Error('elementor_unavailable','Elementor is unavailable.');
        $q=new \WP_Query(array('post_type'=>array_values(array_filter(self::types(),'post_type_exists')),'post_status'=>array('publish','draft','pending','private','future'),'posts_per_page'=>min(50,max(1,(int)($a['per_page']??20))),'offset'=>max(0,(int)($a['offset']??0)),'orderby'=>'ID','order'=>'ASC','meta_query'=>array(array('key'=>'_elementor_data','compare'=>'EXISTS'))));
        $out=array();foreach($q->posts as $p){if(!self::supported($p)||!current_user_can('edit_post',$p->ID))continue;$out[]=array('id'=>$p->ID,'title'=>$p->post_title,'post_type'=>$p->post_type,'status'=>$p->post_status,'template_type'=>get_post_meta($p->ID,'_elementor_template_type',true),'revision'=>Guard::revision(Guard::snapshot($p->ID)));}
        return array('templates'=>$out,'total'=>(int)$q->found_posts,'note'=>'Use elementor-page-read with this ID. Shared editing requires shared-template permission and full Elementor editing; published documents also require published editing. This does not discover every theme assignment.');
    }
}
