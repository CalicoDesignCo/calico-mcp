<?php
namespace CalicoMCP\Personal;
if(!defined('ABSPATH'))exit;
class Field_Diagnostics {
    public static function support($f,$format){
        $type=$f['type']??'text';$code='supported_scalar';$reason='Declared scalar storage is supported.';$next='Select this field and enable the corresponding editing permission.';
        if($type==='repeater'){$code='targeted_repeater';$reason='Whole-field replacement is blocked. Supported scalar children can use targeted cells or row operations.';$next='Inspect initial_rows_supported, row_updates_supported and row_operations_supported; nested/complex children remain read-only.';}
        elseif($format==='read_only'){
            $code='unsupported_storage';$reason='No supported write adapter exists for this declared field type.';$next='Keep this field read-only; add a provider-specific adapter only when a real task needs it.';
            if(in_array($type,array('stored_unknown','registered_multiple'),true)){$code=$type==='stored_unknown'?'missing_schema':'multiple_meta_rows';$reason=$type==='stored_unknown'?'A stored field name was found without a declared storage contract.':'This field stores multiple metadata records; a single scalar write would be ambiguous.';$next='Use a declared provider schema/adapter. Sample values do not establish safe write storage.';}
            elseif($type==='registered'){$code='complex_registered_type';$reason='The registered value is an array/object rather than a supported single scalar.';}
            elseif($type==='advanced_date_companion'){$code='managed_companion';$reason='JetEngine manages this companion key with its main Advanced Date field.';$next='Patch the main date field rather than its config/end-date storage directly.';}
            elseif($type==='advanced-date'){$code='date_provider_unavailable';$reason='A compatible native JetEngine Advanced Date provider is unavailable.';$next='Activate a compatible provider; existing recurring/multiple dates still require a separate adapter.';}
            elseif(in_array($type,array('select','radio'),true)){$code='dynamic_or_multiple_choices';$reason='Only declared static single-choice fields are writable. Dynamic, glossary or multiple-choice storage is not supported.';}
            elseif($type==='media'){$code='media_storage_format';$reason='This media field does not use the supported attachment-ID storage format.';}
        }
        return array('code'=>$code,'reason'=>$reason,'next_step'=>$next);
    }
    public static function explain($a){
        $type=$a['post_type'];$key=$a['field'];$c=Content_Discovery::candidates();
        if(!isset($c[$type]['fields'][$key]))return new \WP_Error('field_missing','Use a discovered type/field. System and custom-table types are excluded.');
        $f=$c[$type]['fields'][$key];$d=Events::definition($key,$f);$s=Events::scope();$selected=isset($s[$type])&&in_array($key,$s[$type]['fields']??array(),true);
        $out=array('post_type'=>$type,'field'=>$key,'source'=>$f['source']??'declared','definition'=>$d,'selected'=>$selected,'editing_enabled'=>Policy::allowed('calicomcp/cpt-update'),'effective_scalar_write'=>false,'blockers'=>array());
        if(!$selected)$out['blockers'][]='Field/type is not selected in Calico MCP.';
        if(!$out['editing_enabled'])$out['blockers'][]='Custom content editing permission is off.';
        if(!$d['writable'])$out['blockers'][]=$d['write_support']['reason'];
        if(isset($a['id'])){
            $p=get_post($a['id']);if(!$p||$p->post_type!==$type||!isset($s[$type])||!current_user_can('edit_post',$p->ID))return new \WP_Error('field_item_denied','Use an editable item in the selected type. No stored values are returned.');
            if(!Policy::editable_status($p,true))$out['blockers'][]='This item status is not enabled for editing.';
            if(!Events::can_edit_meta($p->ID,$key))$out['blockers'][]='WordPress metadata authorization refused this field.';
            if(($f['type']??'')==='advanced-date'&&Advanced_Date::provider()&&!Advanced_Date::view($p->ID,$key)['single_edit_supported'])$out['blockers'][]='This item uses recurring, multiple or malformed date storage; it is read-only.';
            $out['effective_scalar_write']=!$out['blockers'];
        }else $out['note']='Item-specific status, metadata authorization, stored date configuration and input validation still need checking. No values were sampled.';
        return $out;
    }
}
