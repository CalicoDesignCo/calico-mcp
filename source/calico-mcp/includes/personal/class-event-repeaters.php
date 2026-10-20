<?php
namespace CalicoMCP\Personal;
if (!defined('ABSPATH')) { exit; }

/** Declared post-meta rows; structural operations preserve surviving row identities. */
class Event_Repeaters {
    private static function flag($value) { return in_array($value,array(true,1,'1','true','yes'),true); }
    public static function children($field) {
        $out=array();
        if(($field['type']??'')!=='repeater'||self::flag($field['save_separate']??false)) { return $out; }
        foreach((array)($field['repeater-fields']??array()) as $child) {
            if(!is_array($child)) { continue; }
            $name=$child['name']??'';
            if(!Events::safe_key($name)||($child['type']??'')==='repeater'||(isset($child['object_type'])&&$child['object_type']!=='field')) { continue; }
            if(isset($out[$name])) { return array(); }
            if(Events::definition($name,$child)['writable']) { $out[$name]=$child; }
        }
        return $out;
    }
    public static function row_key($key) { return is_string($key)&&preg_match('/^(?:0|[1-9][0-9]{0,5}|item-[0-9]{1,6})$/D',$key); }
    public static function describe($field) {
        $children=array();foreach(self::children($field)as $name=>$child){$children[]=Events::definition($name,$child);}
        return array('row_updates_supported'=>!empty($children),'initial_rows_supported'=>!empty($children),'row_operations_supported'=>!empty($children),'row_fields'=>$children,'row_update_note'=>'Use initial_repeater_rows only on new drafts and repeater_updates for existing cells. Use cpt-repeater-rows with its separate permission for append/remove/reorder. Published items also need published editing. Surviving keys and omitted cells are preserved; reorder supplies every existing key. Nested writes and separate child storage are not supported.');
    }
    public static function view($field,$value) {
        $out=array();$children=self::children($field);
        if(!$children||!is_array($value)) { return $out; }
        foreach($value as $key=>$row) {
            if(!self::row_key((string)$key)||!is_array($row)) { continue; }
            $display=array();foreach($children as $name=>$child){if(array_key_exists($name,$row)){$display[$name]=Events::display($child,$row[$name]);}}
            $out[]=array('row_key'=>(string)$key,'field_display'=>(object)$display);
        }
        return $out;
    }
    private static function fail($message) { return new \WP_Error('invalid_event_repeater',$message); }
    public static function operation($args,$definitions,$post_id) {
        $field=$args['field'];$action=$args['operation'];
        if(!isset($definitions[$field])||!($children=self::children($definitions[$field]))||!Events::can_edit_meta($post_id,$field))return self::fail('Use an authorized selected repeater with declared scalar children and shared post-meta storage.');
        $entries=get_post_meta($post_id,$field,false);
        if(count($entries)>1||(count($entries)===1&&!is_array($entries[0])&&$entries[0]!==''))return self::fail('Expected one post-meta array, an empty value, or no rows yet.');
        $rows=count($entries)===1&&is_array($entries[0])?$entries[0]:array();
        if(count($rows)>500||strlen(maybe_serialize($rows))>1024*1024)return self::fail('Existing repeater exceeds 500 rows or 1 MB.');
        $style=null;$max=-1;
        foreach($rows as $key=>$row) {
            if(!self::row_key((string)$key)||!is_array($row))return self::fail('Malformed existing row identity or storage; no row operation was attempted.');
            $kind=is_int($key)?'numeric':'item';
            if($style!==null&&$style!==$kind)return self::fail('Mixed row-key styles require manual review.');
            $style=$kind;$max=max($max,$kind==='numeric'?$key:(int)substr($key,5));
        }
        if($action==='append') {
            if(isset($args['row_keys'])||!isset($args['rows'])||!is_array($args['rows'])||!$args['rows']||array_keys($args['rows'])!==range(0,count($args['rows'])-1)||count($args['rows'])>25)return self::fail('Append requires 1–25 new rows and no row_keys.');
            foreach($args['rows'] as $row) {
                if(!is_array($row)||!$row||count($row)>20)return self::fail('Supply 1–20 declared scalar child values per new row.');
                $normal=array();
                foreach($row as $child=>$value) {
                    if(!is_string($child)||!isset($children[$child]))return self::fail('Unknown or unsupported new-row child.');
                    $value=Events::normalize($field.'.new.'.$child,$children[$child],$value);if(is_wp_error($value))return $value;
                    $normal[$child]=$value;
                }
                if(++$max>999999)return self::fail('No supported new row key is available.');
                $key=$style==='item'?'item-'.$max:$max;$rows[$key]=$normal;
            }
        } elseif(in_array($action,array('remove','reorder'),true)) {
            if(isset($args['rows'])||empty($args['row_keys'])||!is_array($args['row_keys'])||array_keys($args['row_keys'])!==range(0,count($args['row_keys'])-1)||count($args['row_keys'])>500)return self::fail('Remove/reorder requires exact row_keys only.');
            $seen=array();foreach($args['row_keys'] as $key) {
                if(!self::row_key($key)||isset($seen[$key])||!array_key_exists($key,$rows))return self::fail('Unknown, duplicate or invalid row key. Read the exact item again.');
                $seen[$key]=true;
            }
            if($action==='remove') {
                if(count($seen)>25)return self::fail('Remove at most 25 rows per operation.');
                foreach($args['row_keys'] as $key)unset($rows[$key]);
            } else {
                if(count($seen)!==count($rows))return self::fail('Reorder must include every existing row exactly once.');
                $ordered=array();foreach($args['row_keys'] as $key)$ordered[$key]=$rows[$key];$rows=$ordered;
            }
        } else return self::fail('Use append, remove or reorder.');
        if(count($rows)>500||strlen(maybe_serialize($rows))>1024*1024)return self::fail('Resulting repeater exceeds 500 rows or 1 MB.');
        if(sanitize_meta($field,$rows,'post',get_post_type($post_id))!==$rows)return self::fail('The registered parent sanitizer would alter repeater rows. No edit was attempted.');
        return array($field=>$rows);
    }
    public static function initial($batches,$definitions,$type,$post_id=0) {
        if(!is_array($batches)||!$batches||array_keys($batches)!==range(0,count($batches)-1)||count($batches)>5) { return self::fail('Use 1–5 selected repeaters on a new draft only.'); }
        $planned=array(); $total_rows=0;
        foreach($batches as $batch) {
            if(!is_array($batch)||array_diff(array_keys($batch),array('field','rows'))||!isset($batch['field'],$batch['rows'])) { return self::fail('Each initial repeater requires field and rows only.'); }
            $field=$batch['field']; $rows=$batch['rows'];
            if(!is_string($field)||!isset($definitions[$field])||isset($planned[$field])) { return self::fail('Use each selected repeater only once.'); }
            $children=self::children($definitions[$field]);
            if(!$children||!is_array($rows)||!$rows||array_keys($rows)!==range(0,count($rows)-1)||count($rows)>25) { return self::fail('Use a list of 1–25 initial rows with declared scalar child fields.'); }
            $total_rows+=count($rows); if($total_rows>25) { return self::fail('Use at most 25 initial rows across all repeaters.'); }
            if($post_id&&(!Events::can_edit_meta($post_id,$field)||metadata_exists('post',$post_id,$field))) { return self::fail('Metadata authorization refused or initial repeater data already exists. Read the returned draft before retrying.'); }
            $normal_rows=array();
            foreach($rows as $index=>$row) {
                if(!is_array($row)||!$row||count($row)>20) { return self::fail('Use 1–20 declared scalar values per initial row.'); }
                $normal_row=array();
                foreach($row as $child=>$value) {
                    if(!is_string($child)||!isset($children[$child])) { return self::fail('Unknown or unsupported initial row field.'); }
                    $normal=Events::normalize($field.'.'.$index.'.'.$child,$children[$child],$value);
                    if(is_wp_error($normal)) { return $normal; }
                    $normal_row[$child]=$normal;
                }
                $normal_rows[]=$normal_row;
            }
            if(sanitize_meta($field,$normal_rows,'post',$type)!==$normal_rows) { return self::fail('The registered parent sanitizer would alter initial rows. No repeater was saved.'); }
            $planned[$field]=$normal_rows;
        }
        if(strlen(maybe_serialize($planned))>1024*1024) { return self::fail('Initial repeater data must not exceed 1 MB.'); }
        return $planned;
    }
    public static function plan($patches,$definitions,$post_id) {
        if(!$post_id||!is_array($patches)||!$patches||count($patches)>25) { return self::fail('Use 1–25 existing-row changes on a draft event.'); }
        $planned=array();$touched=array();
        foreach($patches as $patch) {
            if(!is_array($patch)||array_diff(array_keys($patch),array('field','row_key','values'))||!isset($patch['field'],$patch['row_key'],$patch['values'])) { return self::fail('Each row change requires field, row_key and values only.'); }
            $field=$patch['field'];$key=$patch['row_key'];$values=$patch['values'];
            if(!is_string($field)||!isset($definitions[$field])||!self::row_key($key)) { return self::fail('Use a selected repeater and an exact row_key from event-get.'); }
            if(!Events::can_edit_meta($post_id,$field)) { return self::fail('Metadata authorization refused.'); }
            $children=self::children($definitions[$field]);
            if(!$children||!is_array($values)||!$values||count($values)>20) { return self::fail('Use 1–20 declared scalar child fields; this repeater may not support row changes.'); }
            if(!array_key_exists($field,$planned)) {
                $entries=get_post_meta($post_id,$field,false);
                if(count($entries)!==1||!is_array($entries[0])||count($entries[0])>500||strlen(maybe_serialize($entries[0]))>1024*1024) { return self::fail('The repeater must contain one existing post-meta array with at most 500 rows and 1 MB of data.'); }
                $planned[$field]=$entries[0];
            }
            if(!array_key_exists($key,$planned[$field])||!is_array($planned[$field][$key])) { return self::fail('That existing row was not found. Read the event again; rows cannot be added.'); }
            foreach($values as $child=>$value) {
                if(!isset($children[$child])||isset($touched[$field][$key][$child])) { return self::fail('Unknown, unsupported or duplicate row field.'); }
                $normal=Events::normalize($field.'.'.$key.'.'.$child,$children[$child],$value);
                if(is_wp_error($normal)) { return $normal; }
                $planned[$field][$key][$child]=$normal;$touched[$field][$key][$child]=true;
            }
        }
        foreach($planned as $field=>$rows) {
            if(sanitize_meta($field,$rows,'post',get_post_type($post_id))!==$rows) { return self::fail('The registered parent sanitizer would alter this repeater. No edit was attempted.'); }
        }
        return $planned;
    }
}
