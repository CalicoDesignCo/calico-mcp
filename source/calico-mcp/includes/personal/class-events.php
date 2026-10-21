<?php
namespace CalicoMCP\Personal;
use CalicoMCP\Abilities\Ability;
use CalicoMCP\Abilities\Ability_Pack;
if (!defined('ABSPATH')) { exit; }

/** Selected custom post-meta content. Event class/tool aliases remain for compatibility. */
class Events extends Ability_Pack {
    public static function available() {
        return function_exists('get_post_types');
    }
    public function category() { return array('slug'=>'calicomcp-events','label'=>'Custom content (event aliases)','description'=>'Read selected custom content types and edit their drafts.'); }
    public static function scope() { return (array)(Policy::settings()['event_types']??array()); }
    private static function flag($v) { return in_array($v,array(true,1,'1','true','yes'),true); }
    public static function safe_key($key) {
        return is_string($key) && preg_match('/^[A-Za-z0-9_-]{1,128}$/D',$key) && !preg_match('/^_?(?:wp_|elementor|yoast|rank_math|jupiter|calicomcp|edit_lock|edit_last)/i',$key);
    }
    public static function custom_storage($type) {
        $class='\\Jet_Engine\\CPT\\Custom_Tables\\Manager';
        if(class_exists($class)) { foreach((array)$class::instance()->storages as $storage) { if(($storage['object_slug']??'')===$type && ($storage['object_type']??'post')==='post') { return true; } } }
        return false;
    }
    public static function candidates() {
        return Content_Discovery::candidates();
    }
    public static function clean_scope($submitted) {
        $out=array(); $candidates=self::candidates();
        foreach((array)$submitted as $type=>$selection) {
            if(!is_array($selection)||!isset($candidates[$type]) || empty($selection['enabled'])) { continue; }
            $fields=array_values(array_intersect(array_keys($candidates[$type]['fields']),array_filter((array)($selection['fields']??array()),'is_string')));
            $taxonomies=array_values(array_intersect(array_keys(self::taxonomy_definitions($type)),array_filter((array)($selection['taxonomies']??array()),'is_string')));
            $out[$type]=array('fields'=>$fields,'taxonomies'=>$taxonomies);
        }
        ksort($out); return $out;
    }
    public static function render_controls() {
        echo '<h3>Custom content</h3><p class="cmcp-muted">Select content types and business fields. Registered WordPress and JetEngine definitions provide storage formats. Existing Bridge scan fields without declarations remain read-only. Draft editing, supported repeater rows and Undo use the same protections. Saving a different selection revokes existing connections.</p>';
        if(Content_Discovery::bridge_preview()) { echo '<label><input type="checkbox" name="import_bridge" value="1"> Add existing Bridge-approved types and fields to this selection when saving (editing stays separately controlled).</label>'; }
        $candidates=self::candidates(); $scope=self::scope();
        if(!$candidates) { echo '<p>No supported custom content types are available. Custom-table and system types are excluded.</p>'; return; }
        foreach($candidates as $type=>$candidate) {
            $selected=$scope[$type]??array();
            echo '<details style="margin:12px 0"><summary>'.esc_html($candidate['label']).' <code>'.esc_html($type).'</code></summary><fieldset style="padding-left:12px"><label><input type="checkbox" name="event_types['.esc_attr($type).'][enabled]" value="1" '.checked(isset($scope[$type]),true,false).'> Read '.esc_html($candidate['label']).'</label>';
            foreach($candidate['fields'] as $key=>$field) { $definition=self::definition($key,$field); echo '<label><input type="checkbox" name="event_types['.esc_attr($type).'][fields][]" value="'.esc_attr($key).'" '.checked(in_array($key,$selected['fields']??array(),true),true,false).'> '.esc_html($definition['label']).' <code>'.esc_html($key).'</code> <small>('.esc_html($definition['storage_format']).')</small></label>'; }
            foreach(self::taxonomy_definitions($type) as $tax=>$definition) { echo '<label><input type="checkbox" name="event_types['.esc_attr($type).'][taxonomies][]" value="'.esc_attr($tax).'" '.checked(in_array($tax,$selected['taxonomies']??array(),true),true,false).'> Taxonomy: '.esc_html($definition['label']).' <code>'.esc_html($tax).'</code></label>'; }
            echo '</fieldset></details>';
        }
    }
    private static function authorize($slug) { return Policy::is_owner(get_current_user_id())&&Policy::allowed('calicomcp/'.$slug)?true:new \WP_Error('calicomcp_denied','This event tool is not allowed for this account.'); }
    private static function selected($type) {
        $scope=self::scope(); $candidates=self::candidates();
        if(!isset($scope[$type],$candidates[$type])) { return new \WP_Error('event_type_not_selected','Select this custom content type in Calico MCP first. Custom-table storage is not supported.'); }
        $allowed=array_flip((array)($scope[$type]['fields']??array()));
        return array_intersect_key($candidates[$type]['fields'],$allowed);
    }
    public static function is_selected_type($type) { return !is_wp_error(self::selected($type)); }
    public static function taxonomy_definitions($type) {
        $out=array(); foreach(get_object_taxonomies($type,'objects') as $tax=>$object) { if($object->show_ui && !preg_match('/^(?:elementor|jet_|jupiter|wp_)/i',$tax)) { $out[$tax]=array('label'=>$object->label,'hierarchical'=>(bool)$object->hierarchical); } } return $out;
    }
    private static function format($f) {
        $type=$f['type']??'text';
        if($type==='registered') { return in_array($f['registered_type'],array('string','integer','number','boolean'),true)?'registered_'.$f['registered_type']:'read_only'; }
        if($type==='date') { return self::flag($f['is_timestamp']??false)?'date_timestamp_utc':'date_iso'; }
        if($type==='datetime-local') { return self::flag($f['is_timestamp']??false)?'datetime_timestamp':'datetime_local'; }
        if(in_array($type,array('text','textarea','wysiwyg','time','number'),true)) { return $type; }
        if($type==='media' && ($f['value_format']??'id')==='id') { return 'media_id'; }
        if(in_array($type,array('select','radio'),true) && !self::flag($f['is_multiple']??false) && in_array($f['options_source']??'',array('','manual'),true) && !self::flag($f['options_from_glossary']??false) && !empty($f['options'])) { return $type; }
        if($type==='advanced-date' && ($f['source']??'')==='jetengine' && Advanced_Date::provider()) { return 'jetengine_single_date'; }
        return 'read_only';
    }
    public static function definition($key,$f) {
        $format=self::format($f);
        $out=array('name'=>$key,'label'=>(string)($f['title']??$f['label']??$key),'type'=>$f['type']??'text','storage_format'=>$format,'writable'=>$format!=='read_only','input_note'=>in_array($format,array('date_iso','date_timestamp_utc'),true)?'YYYY-MM-DD; timestamp dates use UTC midnight.':($format==='datetime_timestamp'?'ISO 8601 with explicit offset or Unix seconds.':($format==='read_only'?'Direct replacement is not supported. See initial_rows_supported for new drafts and row_updates_supported for existing rows.':'Use the declared scalar type.')));
        if(in_array($format,array('select','radio'),true)){$out['options']=array();foreach($f['options']as $option)if(isset($option['key'])&&is_scalar($option['key']))$out['options'][]=array('value'=>(string)$option['key'],'label'=>(string)($option['value']??$option['key']));}
        if($format==='jetengine_single_date'){$out['input_note']='Object patch: date/end_date YYYY-MM-DD; time/end_time HH:MM. Omitted parts are preserved. Empty end_date and end_time clear the end. Single dates only; overnight ends need the following date. Native JetEngine wall-clock timestamps, not UTC instants.';$out['storage_keys']=Advanced_Date::keys($key);}
        return ($f['type']??'')==='repeater'?array_merge($out,Event_Repeaters::describe($f)):$out;
    }
    public function abilities() {
        if(!self::available() || !self::scope()) { return array(); }
        $type=array('type'=>'string','description'=>'Selected event post-type slug.');
        $id=array('type'=>'integer','minimum'=>1,'description'=>'Event ID returned by event-list.');
        $common=array('post_type'=>$type);
        $edit=array('title'=>array('type'=>'string','maxLength'=>500,'description'=>'Title of the draft event.'),'content'=>array('type'=>'string','maxLength'=>200000),'excerpt'=>array('type'=>'string','maxLength'=>20000),'featured_image_id'=>array('type'=>'integer','minimum'=>0),'fields'=>array('type'=>'object','additionalProperties'=>true,'description'=>'Selected scalar fields, or structured patches for declared jetengine_single_date fields. Read the schema and exact item first. Omitted fields/date parts are preserved; arbitrary complex/repeater replacement and null deletion are refused.'),'terms'=>array('type'=>'object','additionalProperties'=>array('type'=>'array','maxItems'=>100,'items'=>array('type'=>'integer','minimum'=>1)),'description'=>'Selected taxonomy => complete list of existing term IDs. Omitted taxonomies are preserved.'));
        $row_updates=array('repeater_updates'=>array('type'=>'array','minItems'=>1,'maxItems'=>25,'description'=>'Change existing repeater cells only. Read event-schema and event-get first. No row creation, deletion, reordering or full-array replacement.','items'=>array('type'=>'object','required'=>array('field','row_key','values'),'additionalProperties'=>false,'properties'=>array('field'=>array('type'=>'string','maxLength'=>128,'description'=>'Selected repeater field.'),'row_key'=>array('type'=>'string','maxLength'=>16,'description'=>'Exact existing row_key from event-get, such as 0 or item-0.'),'values'=>array('type'=>'object','minProperties'=>1,'maxProperties'=>20,'additionalProperties'=>true,'description'=>'Declared writable scalar child names and their new values. Other cells remain unchanged.')))));
        $initial_rows=array('initial_repeater_rows'=>array('type'=>'array','minItems'=>1,'maxItems'=>5,'description'=>'Initial repeater rows for a NEW draft only. Read event-schema first. At most 25 rows total. Cannot add rows to an existing event.','items'=>array('type'=>'object','required'=>array('field','rows'),'additionalProperties'=>false,'properties'=>array('field'=>array('type'=>'string','maxLength'=>128,'description'=>'Selected repeater with initial_rows_supported in event-schema.'),'rows'=>array('type'=>'array','minItems'=>1,'maxItems'=>25,'description'=>'Ordered initial rows, stored with numeric keys starting at 0.','items'=>array('type'=>'object','minProperties'=>1,'maxProperties'=>20,'additionalProperties'=>true,'description'=>'Declared writable child names and supplied scalar values only.'))))));
        $specs=array(
            array('event-schema','Read event field definitions','Read selected types, fields, storage formats and taxonomies before editing.',array(),array(),'schema',true),
            array('event-list','List events','Search selected JetEngine events across authors; defaults to all readable non-trash statuses.',array_merge($common,array('status'=>array('type'=>'string','enum'=>array('any','draft','publish','pending','private','future')),'search'=>array('type'=>'string','maxLength'=>200),'per_page'=>array('type'=>'integer','minimum'=>1,'maximum'=>50),'page'=>array('type'=>'integer','minimum'=>1))),array('post_type'),'listing',true),
            array('event-get','Read an event','Read selected custom fields, raw storage, display dates, image, terms, and revision.',array_merge($common,array('id'=>$id)),array('post_type','id'),'get',true),
            array('event-terms','List event categories and tags','List existing terms for a selected event taxonomy; does not create terms.',array_merge($common,array('taxonomy'=>array('type'=>'string','description'=>'Selected taxonomy slug returned by event-schema.'),'search'=>array('type'=>'string','maxLength'=>200),'page'=>array('type'=>'integer','minimum'=>1))),array('post_type','taxonomy'),'terms',true),
            array('event-create-draft','Create a draft event','Create a selected JetEngine event as draft only, optionally including initial repeated-date rows. Read event-schema first.',array_merge($common,$edit,$initial_rows),array('post_type','title'),'create',false),
            array('event-update-draft','Edit a draft event','Edit only draft events with a fresh revision, including targeted existing repeater cells. Published, scheduled, pending and private events are refused.',array_merge($common,array('id'=>$id),$edit,$row_updates),array('post_type','id'),'update',false)
        );
        $out=array(); foreach($specs as $s) { $out[]=new Ability('calicomcp/'.$s[0],array('label'=>$s[1],'description'=>$s[2],'category'=>'calicomcp-events','input_schema'=>array('type'=>'object','properties'=>$s[3]?:new \stdClass(),'required'=>$s[4],'additionalProperties'=>false),'execute'=>array(__CLASS__,$s[5]),'capability'=>'manage_options','annotations'=>array('readonly'=>$s[6],'destructive'=>false,'idempotent'=>$s[6]||$s[5]==='update','open_world'=>false))); } return $out;
    }
    public static function schema() {
        $auth=self::authorize('event-schema'); if(is_wp_error($auth)) { return $auth; }
        $types=array(); foreach(self::scope() as $type=>$selection) { $fields=self::selected($type); if(is_wp_error($fields)) { continue; } $definitions=array(); foreach($fields as $key=>$f) { $definitions[]=self::definition($key,$f); } $types[]=array('post_type'=>$type,'label'=>get_post_type_object($type)->label,'fields'=>$definitions,'taxonomies'=>array_intersect_key(self::taxonomy_definitions($type),array_flip($selection['taxonomies']??array()))); }
        return array('types'=>$types,'timezone'=>wp_timezone_string(),'write_status'=>'draft','existing_write_statuses'=>!empty(Policy::settings()['published'])?array('draft','publish'):array('draft'),'storage'=>'WordPress post meta only');
    }
    private static function resolve($type,$id) {
        $fields=self::selected($type); if(is_wp_error($fields)) { return $fields; }
        $post=get_post($id);
        if(!$post || $post->post_type!==$type || in_array($post->post_status,array('trash','auto-draft'),true) || !current_user_can('read_post',$id)) { return new \WP_Error('event_not_found','No readable event of this selected type was found.'); }
        return $post;
    }
    public static function get($args) {
        $auth=self::authorize('event-get'); if(is_wp_error($auth)) { return $auth; }
        $post=self::resolve($args['post_type'],$args['id']); if(is_wp_error($post)) { return $post; }
        $values=array(); $display=array(); $repeaters=array(); $advanced=array();
        foreach(self::selected($post->post_type) as $key=>$f) {
            $value=get_post_meta($post->ID,$key,true); $values[$key]=$value; $display[$key]=self::display($f,$value);
            if(($f['type']??'')==='repeater') { $repeaters[$key]=Event_Repeaters::view($f,$value); }
            if(($f['type']??'')==='advanced-date') { $advanced[$key]=Advanced_Date::view($post->ID,$key); }
        }
        $terms=array(); foreach((array)(self::scope()[$post->post_type]['taxonomies']??array()) as $tax) { if(!is_object_in_taxonomy($post->post_type,$tax)) { continue; } $found=wp_get_object_terms($post->ID,$tax,array('fields'=>'ids')); if(is_wp_error($found)) { return $found; } $terms[$tax]=$found; }
        return array('id'=>$post->ID,'post_type'=>$post->post_type,'title'=>$post->post_title,'content'=>$post->post_content,'excerpt'=>$post->post_excerpt,'status'=>$post->post_status,'slug'=>$post->post_name,'fields'=>(object)$values,'field_display'=>(object)$display,'repeater_rows'=>(object)$repeaters,'advanced_dates'=>(object)$advanced,'terms'=>(object)$terms,'featured_image_id'=>(int)get_post_thumbnail_id($post->ID),'revision'=>Guard::revision(Guard::snapshot($post->ID)));
    }
    public static function listing($args) {
        $auth=self::authorize('event-list'); if(is_wp_error($auth)) { return $auth; }
        $fields=self::selected($args['post_type']); if(is_wp_error($fields)) { return $fields; }
        $object=get_post_type_object($args['post_type']); if(!current_user_can($object->cap->edit_posts)) { return new \WP_Error('forbidden','This account cannot list this event type.'); }
        $status=$args['status']??'any'; if($status==='any') { $status=array('draft','publish','pending','private','future'); }
        $q=new \WP_Query(array('post_type'=>$args['post_type'],'post_status'=>$status,'perm'=>'readable','s'=>$args['search']??'','posts_per_page'=>min(50,max(1,(int)($args['per_page']??20))),'paged'=>max(1,(int)($args['page']??1)),'orderby'=>'ID','order'=>'DESC'));
        $items=array(); foreach($q->posts as $post) { if(current_user_can('read_post',$post->ID)) { $items[]=array('id'=>$post->ID,'title'=>$post->post_title,'status'=>$post->post_status); } }
        return array('items'=>$items,'total'=>(int)$q->found_posts,'pages'=>(int)$q->max_num_pages);
    }
    public static function terms($args) {
        $auth=self::authorize('event-terms'); if(is_wp_error($auth)) { return $auth; }
        $fields=self::selected($args['post_type']); if(is_wp_error($fields)) { return $fields; }
        if(!in_array($args['taxonomy'],self::scope()[$args['post_type']]['taxonomies']??array(),true) || !is_object_in_taxonomy($args['post_type'],$args['taxonomy'])) { return new \WP_Error('taxonomy_not_selected','Select this taxonomy first.'); }
        $terms=get_terms(array('taxonomy'=>$args['taxonomy'],'hide_empty'=>false,'number'=>50,'offset'=>50*max(0,(int)($args['page']??1)-1),'search'=>$args['search']??'')); if(is_wp_error($terms)) { return $terms; }
        return array('terms'=>array_map(function($t){return array('id'=>(int)$t->term_id,'name'=>$t->name,'slug'=>$t->slug,'parent'=>(int)$t->parent);},$terms));
    }
    private static function error($key,$message) { return new \WP_Error('invalid_event_field',$key.': '.$message); }
    private static function ymd($value) {
        if(!is_string($value)||!preg_match('/^\d{4}-\d{2}-\d{2}$/D',$value)) { return false; }
        $date=\DateTimeImmutable::createFromFormat('!Y-m-d',$value,new \DateTimeZone('UTC')); return $date && $date->format('Y-m-d')===$value?$date:false;
    }
    public static function display($field,$value) {
        $format=self::format($field);
        if($format==='registered_boolean') { return (bool)$value; }
        if($format==='registered_integer' && $value!=='') { return (int)$value; }
        if($format==='registered_number' && $value!=='') { return (float)$value; }
        if(is_numeric($value)&&$format==='date_timestamp_utc') { return gmdate('Y-m-d',(int)$value); }
        if(is_numeric($value)&&$format==='datetime_timestamp') { return wp_date(DATE_ATOM,(int)$value,wp_timezone()); }
        return $value;
    }
    public static function normalize($key,$field,$value) {
        $format=self::format($field);
        if(strpos($format,'registered_')===0) {
            $schema=$field['registered_schema'];
            $kind=$field['registered_type'];
            if(($kind==='string'&&!is_string($value))||($kind==='integer'&&!is_int($value))||($kind==='boolean'&&!is_bool($value))||($kind==='number'&&(!is_int($value)&&!is_float($value)))||((is_int($value)||is_float($value))&&!is_finite((float)$value)))return self::error($key,'Use the exact registered scalar type.');
            $valid=rest_validate_value_from_schema($value,$schema,$key);
            if(is_wp_error($valid)||$value===null) { return self::error($key,'Value does not match the registered WordPress schema.'); }
            $normal=sanitize_meta($key,$value,'post',$field['registered_subtype']??'');
            if($normal!==$value) { return self::error($key,'Registered sanitizer would rewrite the supplied value.'); }
            return is_bool($value)?($value?'1':''):(string)$value;
        }
        if($format==='read_only' || $value===null || !is_scalar($value) || is_bool($value)) { return self::error($key,'This field requires a supported scalar value; complex fields and deletion are not enabled.'); }
        if(in_array($format,array('date_timestamp_utc','date_iso'),true)) { $date=self::ymd($value); if(!$date) { return self::error($key,'Use a valid YYYY-MM-DD date.'); } return $format==='date_iso'?$date->format('Y-m-d'):(string)$date->getTimestamp(); }
        if($format==='datetime_timestamp') {
            if((is_int($value)||is_string($value)) && preg_match('/^\d{10}$/D',(string)$value)) { return (string)$value; }
            if(!is_string($value)||!preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:Z|[+-]\d{2}:\d{2})$/D',$value)) { return self::error($key,'Use YYYY-MM-DDTHH:MM:SS with Z or an explicit offset.'); }
            try { $date=new \DateTimeImmutable($value); $errors=\DateTimeImmutable::getLastErrors(); if(($errors && ($errors['warning_count']||$errors['error_count'])) || $date->format('Y-m-d\TH:i:s')!==substr($value,0,19)) { return self::error($key,'Invalid date or time.'); } return (string)$date->getTimestamp(); } catch(\Exception $e) { return self::error($key,'Invalid date or time.'); }
        }
        if($format==='datetime_local') { if(!is_string($value)||!preg_match('/^\d{4}-\d{2}-\d{2}T(?:[01]\d|2[0-3]):[0-5]\d$/D',$value)||!self::ymd(substr($value,0,10))) { return self::error($key,'Use a valid YYYY-MM-DDTHH:MM local date-time.'); } return $value; }
        if($format==='time') { return is_string($value)&&preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/D',$value)?$value:self::error($key,'Use HH:MM in 24-hour time.'); }
        if($format==='number') { return is_numeric($value)&&is_finite((float)$value)?(string)$value:self::error($key,'Use a finite number.'); }
        if($format==='media_id') { if(!ctype_digit((string)$value)||!wp_attachment_is_image((int)$value)||!current_user_can('read_post',(int)$value)) { return self::error($key,'Use the ID of a readable image attachment.'); } return (string)(int)$value; }
        if(in_array($format,array('select','radio'),true)) { $choices=array(); foreach($field['options'] as $o) { if(isset($o['key'])) { $choices[]=(string)$o['key']; } } return in_array((string)$value,$choices,true)?(string)$value:self::error($key,'Use a declared option key.'); }
        if(strlen((string)$value)>200000) { return self::error($key,'Value is too large.'); }
        return $format==='wysiwyg'?wp_kses_post((string)$value):($format==='textarea'?sanitize_textarea_field((string)$value):sanitize_text_field((string)$value));
    }
    public static function can_edit_meta($post_id,$key) {
        $registered=get_registered_meta_keys('post',get_post_type($post_id))+get_registered_meta_keys('post');
        return (!isset($registered[$key])&&is_protected_meta($key,'post'))||current_user_can('edit_post_meta',$post_id,$key);
    }
    private static function plan($args,$post_id=0,$creating=false) {
        $definitions=self::selected($args['post_type']); if(is_wp_error($definitions)) { return $definitions; }
        $out=array('fields'=>array(),'terms'=>array(),'advanced_dates'=>array());
        foreach((array)($args['fields']??array()) as $key=>$value) {
            if(!isset($definitions[$key])) { return self::error($key,'Field is not selected.'); }
            if(self::format($definitions[$key])==='jetengine_single_date'){$date=Advanced_Date::plan($post_id,$key,$definitions[$key],$value);if(is_wp_error($date))return $date;$out['advanced_dates'][$key]=$date;continue;}
            if($post_id && !self::can_edit_meta($post_id,$key)) { return self::error($key,'Metadata authorization refused.'); }
            // JetEngine declares protected business fields without registering WP meta.
            // Their access is gated by the explicit schema selection and edit_post capability.
            $normal=self::normalize($key,$definitions[$key],$value); if(is_wp_error($normal)) { return $normal; }
            $out['fields'][$key]=$normal;
        }
        if(array_key_exists('repeater_updates',$args)) {
            if(!$post_id) { return self::error('repeater_updates','Existing draft rows are required.'); }
            foreach((array)$args['repeater_updates'] as $change) { if(is_array($change)&&isset($change['field'])&&is_string($change['field'])&&array_key_exists($change['field'],(array)($args['fields']??array()))) { return self::error('repeater_updates','Do not combine row changes and direct replacement of the same field.'); } }
            $rows=Event_Repeaters::plan($args['repeater_updates'],$definitions,$post_id);if(is_wp_error($rows)){return $rows;}$out['fields']=array_merge($out['fields'],$rows);
        }
        if(array_key_exists('initial_repeater_rows',$args)) {
            if(!$creating||array_key_exists('repeater_updates',$args)) { return self::error('initial_repeater_rows','Initial rows are supported only when creating a new draft.'); }
            $rows=Event_Repeaters::initial($args['initial_repeater_rows'],$definitions,$args['post_type'],$post_id);
            if(is_wp_error($rows)) { return $rows; }
            if(array_intersect_key($out['fields'],$rows)) { return self::error('initial_repeater_rows','Do not combine initial rows and direct field replacement.'); }
            $out['fields']=array_merge($out['fields'],$rows);
        }
        foreach((array)($args['terms']??array()) as $tax=>$ids) {
            $object=get_taxonomy($tax);
            if(!in_array($tax,self::scope()[$args['post_type']]['taxonomies']??array(),true)||!is_object_in_taxonomy($args['post_type'],$tax)||!$object||!current_user_can($object->cap->assign_terms)) { return new \WP_Error('taxonomy_not_selected','Taxonomy is not selected or cannot be assigned.'); }
            foreach($ids as $id) { if(!term_exists((int)$id,$tax)) { return new \WP_Error('event_term_missing','Use existing term IDs from event-terms.'); } }
            $out['terms'][$tax]=array_values(array_unique(array_map('intval',$ids)));
        }
        if(isset($args['featured_image_id']) && $args['featured_image_id']>0 && (!wp_attachment_is_image($args['featured_image_id'])||!current_user_can('read_post',$args['featured_image_id']))) { return new \WP_Error('event_image_invalid','Use a readable image attachment ID.'); }
        return $out;
    }
    private static function apply($id,$args,$plan) {
        $core=array('ID'=>$id); foreach(array('title'=>'post_title','content'=>'post_content','excerpt'=>'post_excerpt') as $from=>$to) { if(isset($args[$from])) { $core[$to]=$from==='title'?sanitize_text_field($args[$from]):wp_kses_post($args[$from]); } }
        if(count($core)>1) { $r=wp_update_post(wp_slash($core),true); if(is_wp_error($r)) { return $r; } }
        foreach($plan['fields'] as $key=>$value) {
            update_post_meta($id,$key,wp_slash($value));
            if(get_post_meta($id,$key,true)!==$value) { return new \WP_Error('event_save_failed','Field readback did not match: '.$key); }
        }
        foreach($plan['advanced_dates']??array()as $key=>$date){$saved=Advanced_Date::apply($id,$key,$date);if(is_wp_error($saved))return $saved;}
        foreach($plan['terms'] as $tax=>$ids) { $r=wp_set_object_terms($id,$ids,$tax,false); if(is_wp_error($r)) { return $r; } $read=wp_get_object_terms($id,$tax,array('fields'=>'ids')); sort($ids); if(is_wp_error($read)) { return $read; } sort($read); if($ids!==$read) { return new \WP_Error('event_save_failed','Taxonomy readback did not match.'); } }
        if(isset($args['featured_image_id'])) { if($args['featured_image_id']===0) { delete_post_thumbnail($id); } else { set_post_thumbnail($id,$args['featured_image_id']); } if((int)get_post_thumbnail_id($id)!==$args['featured_image_id']) { return new \WP_Error('event_save_failed','Featured image readback did not match.'); } }
        return self::get(array('post_type'=>$args['post_type'],'id'=>$id));
    }
    public static function create($args) {
        $auth=self::authorize('event-create-draft'); if(is_wp_error($auth)) { return $auth; }
        $object=get_post_type_object($args['post_type']); if(!$object||!current_user_can($object->cap->create_posts)) { return new \WP_Error('forbidden','Cannot create this event type.'); }
        $plan=self::plan($args,0,true); if(is_wp_error($plan)) { return $plan; }
        $id=wp_insert_post(wp_slash(array('post_type'=>$args['post_type'],'post_status'=>'draft','post_title'=>sanitize_text_field($args['title']),'post_author'=>get_current_user_id())),true);
        if(is_wp_error($id)) { return $id; }
        $plan=self::plan($args,$id,true);
        $result=is_wp_error($plan)?$plan:self::apply($id,$args,$plan);
        if(is_wp_error($result)) { $result->add_data(array('event_id'=>$id,'status'=>get_post_status($id),'note'=>'A draft was created. Read it before retrying; do not create a duplicate.')); }
        return $result;
    }
    public static function update($args) {
        $auth=self::authorize('event-update-draft'); if(is_wp_error($auth)) { return $auth; }
        $post=self::resolve($args['post_type'],$args['id']); if(is_wp_error($post)) { return $post; }
        if($post->post_status!=='draft'||!current_user_can('edit_post',$post->ID)) { return new \WP_Error('event_draft_only','Only draft events can be edited in this beta.'); }
        if(array_key_exists('content',$args)&&get_post_meta($post->ID,'_elementor_data',true)) { return new \WP_Error('elementor_not_enabled','Elementor body editing is not enabled.'); }
        $plan=self::plan($args,$post->ID); return is_wp_error($plan)?$plan:self::apply($post->ID,$args,$plan);
    }
    public static function update_existing($args) {
        $auth=self::authorize('cpt-update');if(is_wp_error($auth))return $auth;
        $post=self::resolve($args['post_type'],$args['id']);if(is_wp_error($post))return $post;
        if(!Policy::editable_status($post,true)||!current_user_can('edit_post',$post->ID))return new \WP_Error('content_status_denied','Use a draft, or enable published editing for an existing published item. Other statuses and status transitions are not supported.');
        if(array_key_exists('content',$args)&&get_post_meta($post->ID,'_elementor_data',true))return new \WP_Error('elementor_not_enabled','Use Elementor tools to edit an Elementor body.');
        $plan=self::plan($args,$post->ID);return is_wp_error($plan)?$plan:self::apply($post->ID,$args,$plan);
    }
    public static function rows($args) {
        $auth=self::authorize('cpt-repeater-rows');if(is_wp_error($auth))return $auth;
        $post=self::resolve($args['post_type'],$args['id']);if(is_wp_error($post))return $post;
        if(!Policy::editable_status($post,true)||!current_user_can('edit_post',$post->ID))return new \WP_Error('content_status_denied','Repeater operations require a draft or explicitly permitted published item.');
        $plan=Event_Repeaters::operation($args,self::selected($post->post_type),$post->ID);
        return is_wp_error($plan)?$plan:self::apply($post->ID,$args,array('fields'=>$plan,'terms'=>array()));
    }
}
