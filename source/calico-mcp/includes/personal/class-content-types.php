<?php
namespace CalicoMCP\Personal;
use CalicoMCP\Abilities\Ability;
use CalicoMCP\Abilities\Ability_Pack;
if (!defined('ABSPATH')) { exit; }
class Content_Types extends Ability_Pack {
    public function category() { return array('slug'=>'calicomcp-custom-content','label'=>'Custom content','description'=>'Site-declared custom types and fields.'); }
    public function abilities() {
        $out=array();
        foreach((new Events())->abilities() as $ability) {
            $name=str_replace('calicomcp/event-','calicomcp/cpt-',$ability->get_name());
            $callbacks=array('schema'=>'schema','list'=>'listing','get'=>'get','terms'=>'terms','create-draft'=>'create','update-draft'=>'update');
            $suffix=substr($name,14); $read=!in_array($suffix,array('create-draft','update-draft'),true);
            if($suffix==='update-draft')$update_schema=$ability->get_input_schema();
            $out[]=new Ability($name,array('label'=>'Custom content: '.$suffix,'description'=>'Use selected site-declared custom content. Read cpt-schema and the exact item before editing. Writes are draft-only.','category'=>'calicomcp-custom-content','input_schema'=>$ability->get_input_schema(),'execute'=>array(Events::class,$callbacks[$suffix]),'capability'=>'manage_options','annotations'=>array('readonly'=>$read,'destructive'=>false,'idempotent'=>$read||$suffix==='update-draft','open_world'=>false)));
        }
        if(isset($update_schema)) {
            $update_schema['properties']['title']['description']='Replacement title; omitted fields are preserved. Status cannot be changed.';
            $out[]=new Ability('calicomcp/cpt-update',array('label'=>'Edit existing custom content','description'=>'Edit a selected draft, or an already-published item only with the separate published-edit permission. Read cpt-schema and cpt-get first. Preserves status, omitted fields and unrelated rows.','category'=>'calicomcp-custom-content','input_schema'=>$update_schema,'execute'=>array(Events::class,'update_existing'),'capability'=>'manage_options','annotations'=>array('readonly'=>false,'destructive'=>false,'idempotent'=>true,'open_world'=>false)));
            $out[]=new Ability('calicomcp/cpt-repeater-rows',array('label'=>'Append, remove or reorder custom repeater rows','description'=>'One selected repeater on an existing draft or permitted published item. Append declared scalar rows, remove exact row keys, or reorder all exact keys without renumbering. Requires custom editing and separate repeater-row permission. Read cpt-schema and cpt-get first; Undo is recorded.','category'=>'calicomcp-custom-content','input_schema'=>array('type'=>'object','required'=>array('post_type','id','field','operation'),'additionalProperties'=>false,'properties'=>array('post_type'=>array('type'=>'string','description'=>'Selected custom post-type slug.'),'id'=>array('type'=>'integer','minimum'=>1,'description'=>'Existing item ID from cpt-get.'),'field'=>array('type'=>'string','maxLength'=>128,'description'=>'Selected repeater with row_operations_supported in cpt-schema.'),'operation'=>array('type'=>'string','enum'=>array('append','remove','reorder'),'description'=>'Append supplied rows; remove up to 25 exact keys; reorder with every existing key exactly once.'),'rows'=>array('type'=>'array','minItems'=>1,'maxItems'=>25,'description'=>'Append only: ordered new rows, each with 1–20 declared scalar child values.','items'=>array('type'=>'object','minProperties'=>1,'maxProperties'=>20,'additionalProperties'=>true)),'row_keys'=>array('type'=>'array','minItems'=>1,'maxItems'=>500,'uniqueItems'=>true,'description'=>'Remove/reorder only: exact string row_key values from a fresh cpt-get. Reorder must include every existing key.','items'=>array('type'=>'string','pattern'=>'^(?:0|[1-9][0-9]{0,5}|item-[0-9]{1,6})$')))),'execute'=>array(Events::class,'rows'),'capability'=>'manage_options','annotations'=>array('readonly'=>false,'destructive'=>true,'idempotent'=>false,'open_world'=>false)));
        }
        foreach(array('cpt-discover'=>'discover','bridge-import-preview'=>'bridge') as $name=>$callback) {
            $out[]=new Ability('calicomcp/'.$name,array('label'=>$name,'description'=>'Inspect available field declarations or existing Bridge approvals. Does not grant access or change settings.','category'=>'calicomcp-custom-content','input_schema'=>array('type'=>'object','properties'=>new \stdClass(),'additionalProperties'=>false),'execute'=>array(__CLASS__,$callback),'capability'=>'manage_options','annotations'=>array('readonly'=>true,'destructive'=>false,'idempotent'=>true,'open_world'=>false)));
        }
        return $out;
    }
    public static function discover() {
        $out=array(); foreach(Content_Discovery::candidates() as $type=>$candidate) {
            $fields=array(); foreach($candidate['fields'] as $key=>$field) { $fields[]=Events::definition($key,$field)+array('source'=>$field['source']??'declared'); }
            $out[]=array('post_type'=>$type,'label'=>$candidate['label'],'fields'=>$fields,'taxonomies'=>Events::taxonomy_definitions($type),'selected'=>isset(Events::scope()[$type]));
        }
        return array('types'=>$out,'note'=>'Discovery does not approve access. Select fields in WordPress. Sampled Bridge fields without declarations are read-only.');
    }
    public static function bridge() { return array('proposed_selection'=>Content_Discovery::bridge_preview(),'applied'=>false); }
}
