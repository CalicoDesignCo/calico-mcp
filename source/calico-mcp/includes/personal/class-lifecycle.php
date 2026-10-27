<?php
namespace CalicoMCP\Personal;
use CalicoMCP\Abilities\Ability_Pack;
if (!defined('ABSPATH')) { exit; }

class Lifecycle extends Ability_Pack {
    public function category() { return array('slug'=>'calicomcp-lifecycle','label'=>'Publication and status','description'=>'Explicit publication, scheduling and status changes on existing selected content.'); }
    public function abilities() {
        $base=array('id'=>Toolbox::id(),'expected_status'=>array('type'=>'string','enum'=>self::statuses(),'description'=>'Status observed in a fresh content-status-get.'));
        $out=array(Toolbox::tool('content-status-get','Read publication status, WordPress publication dates, site timezone, scheduled cron and a fresh revision for an existing post, page or selected CPT. Event dates are separate.',array('id'=>Toolbox::id()),array('id'),array(__CLASS__,'get'),true,'calicomcp-lifecycle'));
        foreach(array(
            array('content-publish','Publish an existing item immediately. Requires explicit user authorization and publishing permission. This affects the live site and can trigger third-party notifications. Does not edit content or event dates.',$base,'publish'),
            array('content-schedule','Schedule an existing item using an explicit ISO 8601 timestamp with UTC offset or Z, at least two minutes ahead. Uses WordPress cron; actual publication depends on cron running. Does not change the event date. Requires scheduling permission.',$base+array('publish_at'=>array('type'=>'string','maxLength'=>25,'description'=>'Exact publication instant, for example 2026-12-01T10:00:00-06:00. No timezone guessing.')),'schedule'),
            array('content-set-status','Move an existing item to draft, pending review or private. Can unpublish live content or cancel scheduled publication. Requires status permission; private also requires the native publish capability. Publish/schedule use separate tools.',$base+array('status'=>array('type'=>'string','enum'=>array('draft','pending','private'),'description'=>'Destination WordPress status: draft, pending review, or private.')),'set_status')
        )as $s){
            $required=array_keys($s[2]);
            $tool=Toolbox::tool($s[0],$s[1],$s[2],$required,array(__CLASS__,$s[3]),false,'calicomcp-lifecycle');
            $definition=$tool->to_args();
            $definition['annotations']=array('readonly'=>false,'destructive'=>true,'idempotent'=>false,'open_world'=>true);
            $out[]=new \CalicoMCP\Abilities\Ability($tool->get_name(),$definition);
        }
        return $out;
    }
    public static function statuses() { return array('draft','pending','publish','future','private'); }
    private static function post($id) {
        $p=get_post($id);
        if(!Toolbox::content_type($p)||in_array($p->post_type,Shared_Templates::types(),true)||!in_array($p->post_status,self::statuses(),true)||!current_user_can('edit_post',$id))return new \WP_Error('lifecycle_denied','Use an editable post, page or explicitly selected CPT in Draft, Pending, Published, Scheduled or Private status. Shared templates, attachments, revisions and Trash are excluded.');
        $type=get_post_type_object($p->post_type);
        if(!$type||!$type->show_ui)return new \WP_Error('lifecycle_denied','This type does not support ordinary WordPress content administration.');
        return $p;
    }
    public static function get($a) {
        $p=self::post($a['id']);if(is_wp_error($p))return $p;
        return array('id'=>(int)$p->ID,'post_type'=>$p->post_type,'title'=>$p->post_title,'status'=>$p->post_status,'date'=>$p->post_date,'date_gmt'=>$p->post_date_gmt,'site_timezone'=>wp_timezone_string(),'scheduled_timestamp'=>wp_next_scheduled('publish_future_post',array((int)$p->ID))?:null,'revision'=>Guard::revision(Guard::snapshot($p->ID)));
    }
    public static function publish($a) { return self::change($a,'publish','publish'); }
    public static function schedule($a) { return self::change($a,'future','schedule'); }
    public static function set_status($a) {
        if(!in_array($a['status']??'',array('draft','pending','private'),true))return new \WP_Error('status_denied','Use the separate publish or schedule tool; this tool only accepts draft, pending or private.');
        return self::change($a,$a['status'],'status');
    }
    private static function instant($value) {
        if(!is_string($value)||!preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:Z|[+-](?:0\d|1[0-4]):[0-5]\d)$/D',$value))return false;
        $d=\DateTimeImmutable::createFromFormat('!Y-m-d\TH:i:sP',str_replace('Z','+00:00',$value));$errors=\DateTimeImmutable::getLastErrors();
        if(!$d||($errors&&($errors['warning_count']||$errors['error_count']))||$d->format('Y-m-d\TH:i:sP')!==str_replace('Z','+00:00',$value))return false;
        if(abs($d->getOffset())>14*HOUR_IN_SECONDS)return false;
        return $d->getTimestamp();
    }
    private static function change($a,$target,$permission) {
        if(empty(Policy::settings()[$permission]))return new \WP_Error('lifecycle_denied','This publication permission is disabled.');
        $p=self::post($a['id']);if(is_wp_error($p))return $p;
        if(($a['expected_status']??'')!==$p->post_status)return new \WP_Error('stale_status','The observed status does not match. Read content-status-get again.');
        if(!in_array($target,self::statuses(),true))return new \WP_Error('status_denied','Unsupported destination status.');
        $type=get_post_type_object($p->post_type);
        if(in_array($target,array('publish','future','private'),true)&&!current_user_can($type->cap->publish_posts))return new \WP_Error('publish_denied','WordPress refused the native publishing capability for this content type.');
        // WordPress otherwise discards supplied dates when the old draft's GMT
        // date is zero, which could turn a schedule into immediate publication.
        $data=array('ID'=>$p->ID,'post_status'=>$target,'edit_date'=>true);
        if($target==='future'){
            $time=self::instant($a['publish_at']??null);
            if($time===false||$time<time()+120)return new \WP_Error('invalid_schedule','Use a valid ISO 8601 timestamp with explicit UTC offset or Z, at least two minutes in the future.');
            $data['post_date_gmt']=gmdate('Y-m-d H:i:s',$time);
            $data['post_date']=get_date_from_gmt($data['post_date_gmt']);
            // Editing an existing schedule must replace the old cron event.
        }elseif($target==='publish'&&$p->post_status!=='publish'){
            $data['post_date_gmt']=gmdate('Y-m-d H:i:s');
            $data['post_date']=get_date_from_gmt($data['post_date_gmt']);
        }
        $saved=wp_update_post(wp_slash($data),true);if(is_wp_error($saved))return $saved;
        clean_post_cache($p->ID);$fresh=get_post($p->ID);
        if($fresh->post_status!==$target)return new \WP_Error('status_readback_failed','WordPress did not save the requested status. Inspect the item and recovery record before retrying.');
        foreach(array('post_date','post_date_gmt')as $key)if(isset($data[$key])&&$fresh->$key!==$data[$key])return new \WP_Error('schedule_readback_failed','WordPress altered the requested publication date. Inspect the item and recovery record.');
        if($target!=='future')wp_clear_scheduled_hook('publish_future_post',array((int)$p->ID));
        $cron=wp_next_scheduled('publish_future_post',array((int)$p->ID));
        if($target==='future'&&$cron!==$time)return new \WP_Error('schedule_cron_failed','The publication date was saved but its cron event was not verified. Inspect the item and recovery record.');
        if($target!=='future'&&$cron)return new \WP_Error('schedule_cron_failed','The status was saved but the old publication cron event remains. Inspect the item and recovery record.');
        $result=self::get($a);$result['previous_status']=$p->post_status;
        $result['notice']='Status changes can trigger WordPress/plugin notifications or external services. Undo restores saved state; it cannot retract external effects. Scheduled publication depends on WordPress cron.';
        return $result;
    }
}
