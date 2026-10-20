<?php
namespace CalicoMCP\Personal;
if (!defined('ABSPATH')) { exit; }

/** Site declarations first. Legacy Bridge scans contribute names, never inferred write schemas. */
class Content_Discovery {
    public static function candidates() {
        $out=array();
        foreach(get_post_types(array('_builtin'=>false),'objects') as $type=>$object) {
            if(!$object->show_ui || preg_match('/^(?:elementor|jet-|jet_|jupiter|acf-|wp_|e-|e_)/i',$type) || Events::custom_storage($type)) { continue; }
            $fields=array();
            $registered=get_registered_meta_keys('post',$type)+get_registered_meta_keys('post');
            foreach($registered as $key=>$definition) {
                if(!Events::safe_key($key)) { continue; }
                $kind=$definition['type']??'string';
                $fields[$key]=array('name'=>$key,'title'=>$definition['label']??$key,'type'=>!empty($definition['single'])?'registered':'registered_multiple','registered_type'=>$kind,'registered_schema'=>$definition,'registered_subtype'=>isset(get_registered_meta_keys('post',$type)[$key])?$type:'','source'=>'wordpress');
            }
            if(function_exists('jet_engine') && isset(jet_engine()->meta_boxes) && method_exists(jet_engine()->meta_boxes,'get_fields_for_context')) {
                foreach((array)jet_engine()->meta_boxes->get_fields_for_context('post_type',$type) as $field) {
                    $key=$field['name']??'';
                    if(!Events::safe_key($key) || (isset($field['object_type']) && $field['object_type']!=='field')) { continue; }
                    $fields[$key]=$field+array('source'=>'jetengine');
                }
            }
            // Optional existing scan: no querying arbitrary postmeta during discovery.
            $scan=(array)get_option('calico_jmcb_scan_results',array());
            foreach((array)($scan['types'][$type]['fields']??array()) as $key=>$field) {
                if(!isset($fields[$key]) && Events::safe_key($key) && substr($key,0,1)!=='_') {
                    $fields[$key]=array('name'=>$key,'title'=>$key,'type'=>'stored_unknown','source'=>'bridge-scan','note'=>'No declared schema. Read-only until a provider declares its storage contract.');
                }
            }
            ksort($fields); $out[$type]=array('label'=>$object->label,'fields'=>$fields);
        }
        ksort($out); return $out;
    }
    public static function bridge_preview() {
        $types=(array)get_option('calico_jmcb_allowed_types',array());
        $fields=(array)get_option('calico_jmcb_allowed_fields',array());
        $candidates=self::candidates(); $scope=array();
        foreach($types as $type) {
            if(!is_string($type)||!isset($candidates[$type])) { continue; }
            $scope[$type]=array('enabled'=>true,'fields'=>array_values(array_intersect(array_keys($candidates[$type]['fields']),(array)($fields[$type]??array()))),'taxonomies'=>array());
        }
        return $scope;
    }
}
