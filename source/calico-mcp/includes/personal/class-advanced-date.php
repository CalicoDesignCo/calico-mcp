<?php
namespace CalicoMCP\Personal;
if (!defined('ABSPATH')) { exit; }

/** JetEngine's own single-date storage adapter. No recurrence generation. */
class Advanced_Date {
    public static function provider() {
        if(!class_exists('Jet_Engine_Advanced_Date_Field'))return null;
        $provider=\Jet_Engine_Advanced_Date_Field::instance()->data;
        return is_object($provider)&&method_exists($provider,'update_field_with_value')&&method_exists($provider,'get_date_pairs_cache_key')?$provider:null;
    }
    public static function keys($field) { return array($field,$field.'__config',$field.'__end_date'); }
    private static function error($key,$message) { return new \WP_Error('invalid_advanced_date',$key.': '.$message); }
    public static function view($id,$key) {
        $raw=get_post_meta($id,$key.'__config',true);
        $config=$raw===''?array():(is_string($raw)?json_decode($raw,true):null);
        $start=get_post_meta($id,$key,false);$end=get_post_meta($id,$key.'__end_date',false);
        $single=is_array($config)&&empty($config['is_recurring'])&&empty($config['dates'])&&count($start)<=1&&count($end)<=1&&count(get_post_meta($id,$key.'__config',false))<=1;
        if(is_array($config))foreach(array_keys($config)as $part)if(!is_string($part))$single=false;
        return array('config'=>is_array($config)?(object)$config:null,'start_values'=>$start,'end_values'=>$end,'single_edit_supported'=>$single&&self::provider()!==null);
    }
    public static function plan($id,$key,$field,$patch) {
        if(!self::provider()||($field['source']??'')!=='jetengine')return self::error($key,'The native JetEngine Advanced Date provider must be active.');
        if(date_default_timezone_get()!=='UTC')return self::error($key,'WordPress must retain its native UTC PHP timezone for JetEngine storage.');
        if(!is_array($patch)||!$patch||array_diff(array_keys($patch),array('date','time','end_date','end_time')))return self::error($key,'Supply date, time, end_date and/or end_time only. Recurrence and direct storage edits are not supported.');
        $view=$id?self::view($id,$key):array('config'=>(object)array(),'single_edit_supported'=>true);
        if(!$view['single_edit_supported'])return self::error($key,'Existing recurring, multiple or malformed dates are read-only.');
        $config=(array)$view['config'];
        foreach($patch as $name=>$value){if(!is_string($value))return self::error($key,'Date parts must be strings.');$config[$name]=$value;}
        if(array_key_exists('end_date',$patch)){if($patch['end_date']!=='')$config['is_end_date']='1';else unset($config['is_end_date']);}
        $has_end=!empty($config['is_end_date']);
        if(empty($config['date']))return self::error($key,'A start date is required.');
        foreach(array('date','end_date')as $name){$value=$config[$name]??'';if($value==='')continue;$date=\DateTimeImmutable::createFromFormat('!Y-m-d',$value,new \DateTimeZone('UTC'));if(!preg_match('/^[1-9]\d{3}-\d{2}-\d{2}$/D',$value)||!$date||$date->format('Y-m-d')!==$value)return self::error($key,'Use valid YYYY-MM-DD dates.');}
        foreach(array('time','end_time')as $name){$value=$config[$name]??'';if($value!==''&&(!in_array($field['allow_timepicker']??false,array(true,1,'1','true','yes'),true)||!preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/D',$value)))return self::error($key,'Use HH:MM only when this field enables its timepicker.');}
        if(($has_end&&empty($config['end_date']))||(!$has_end&&!empty($patch['end_time'])))return self::error($key,'An end time requires an enabled end date.');
        $start=strtotime($config['date'].' '.($config['time']??'00:00').' UTC');
        $end=$has_end?strtotime($config['end_date'].' '.($config['end_time']??'00:00').' UTC'):false;
        if($end!==false&&$end<=$start)return self::error($key,'End must be after start; supply the following date for an overnight event.');
        foreach(self::keys($key)as $storage_key)if($id&&!Events::can_edit_meta($id,$storage_key))return self::error($key,'Metadata authorization refused.');
        return array('config'=>$config,'start'=>(string)$start,'end'=>$end===false?null:(string)$end);
    }
    public static function apply($id,$key,$plan) {
        self::provider()->update_field_with_value($plan['config'],$id,$key);
        $config=json_decode(get_post_meta($id,$key.'__config',true),true);
        if($config!==$plan['config']||get_post_meta($id,$key,false)!==array($plan['start'])||get_post_meta($id,$key.'__end_date',false)!==($plan['end']===null?array():array($plan['end'])))return new \WP_Error('advanced_date_save_failed','Native Advanced Date readback did not match. Inspect the item before retrying.');
        return true;
    }
    public static function recovery_keys($type,$fields) {
        $all=Content_Discovery::candidates();$out=$fields;
        foreach($fields as $key)if(($all[$type]['fields'][$key]['type']??'')==='advanced-date')$out=array_merge($out,self::keys($key));
        return array_values(array_unique($out));
    }
    public static function invalidate($id,$meta_keys) {
        $provider=self::provider();if(!$provider)return;
        foreach($meta_keys as $key)if(substr($key,-8)==='__config')wp_cache_delete($provider->get_date_pairs_cache_key($id,substr($key,0,-8)),'jet-engine');
    }
}
