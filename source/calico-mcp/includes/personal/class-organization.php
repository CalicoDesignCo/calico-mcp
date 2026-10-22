<?php
namespace CalicoMCP\Personal;
use CalicoMCP\Abilities\Ability;
use CalicoMCP\Abilities\Ability_Pack;
if(!defined('ABSPATH'))exit;
/** Bounded Yoast organization profiles, never arbitrary options or generated JSON-LD. */
class Organization extends Ability_Pack {
    public static function fields(){return array('facebook_site','instagram_url','linkedin_url','myspace_url','pinterest_url','youtube_url','wikipedia_url','mastodon_url','other_social_urls');}
    public function category(){return array('slug'=>'calicomcp-organization','label'=>'Yoast organization','description'=>'Site-wide organization social profiles.');}
    public function abilities(){
        $props=array();foreach(self::fields()as $key)$props[$key]=$key==='other_social_urls'?array('type'=>'array','maxItems'=>20,'items'=>array('type'=>'string','maxLength'=>2048)):array('type'=>'string','maxLength'=>2048);
        $out=array();foreach(array(array('organization-get','Read Yoast organization social profiles',array('type'=>'object','properties'=>array(),'additionalProperties'=>false),'get',true),array('organization-update','Edit Yoast organization social profiles',array('type'=>'object','properties'=>array('profiles'=>array('description'=>'Only the social profile fields to change. Omitted fields are preserved.','type'=>'object','properties'=>$props,'minProperties'=>1,'additionalProperties'=>false)),'required'=>array('profiles'),'additionalProperties'=>false),'update',false))as $s)$out[]=new Ability('calicomcp/'.$s[0],array('label'=>$s[1],'description'=>'Read stored Yoast organization profiles before editing. Patches only supplied profile fields; preserves all other options. Site-wide changes require separate organization permission. Generated schema, organization identity, logos and theme settings are not directly edited.','category'=>'calicomcp-organization','input_schema'=>$s[2],'execute'=>array(__CLASS__,$s[3]),'capability'=>'manage_options','annotations'=>array('readonly'=>$s[4],'destructive'=>false,'idempotent'=>true,'open_world'=>false)));
        return $out;
    }
    public static function snapshot($args){
        if(!defined('WPSEO_VERSION')||!class_exists('WPSEO_Options'))return new \WP_Error('yoast_unavailable','Yoast SEO is unavailable.');
        $values=get_option('wpseo_social');if(!is_array($values))return new \WP_Error('yoast_options_unavailable','Stored Yoast social options are unavailable.');
        return array('entity'=>'yoast_organization','id'=>0,'args'=>array(),'values'=>$values);
    }
    public static function get($args){$s=self::snapshot(array());if(is_wp_error($s))return $s;$profiles=array();foreach(self::fields()as $key)if(array_key_exists($key,$s['values']))$profiles[$key]=$s['values'][$key];return array('provider'=>'Yoast SEO','profiles'=>$profiles,'revision'=>Toolbox::revision($s),'note'=>'Stored values only. Public schema may be filtered or cached; verify rendered schema separately.');}
    public static function update($args){
        $s=self::snapshot(array());if(is_wp_error($s))return $s;
        $patch=$args['profiles']??null;if(!is_array($patch)||!$patch||array_diff(array_keys($patch),self::fields()))return new \WP_Error('invalid_profiles','Supply only supported profile fields.');
        foreach($patch as $key=>$value){
            if($key==='other_social_urls'&&(!is_array($value)||array_values($value)!==$value||count($value)>20))return new \WP_Error('invalid_profiles','Other profiles must be a list of at most 20 URLs.');
            foreach($key==='other_social_urls'?$value:array($value)as $url){if(!is_string($url)||strlen($url)>2048||($url!==''&&(!filter_var($url,FILTER_VALIDATE_URL)||!in_array(strtolower(wp_parse_url($url,PHP_URL_SCHEME)??''),array('https','http'),true)||wp_parse_url($url,PHP_URL_USER)||wp_parse_url($url,PHP_URL_PASS))))return new \WP_Error('invalid_profile_url','Use HTTP(S) profile URLs without credentials, or an empty string to clear one.');}
        }
        foreach($patch as $key=>$value)\WPSEO_Options::set($key,$value,'wpseo_social');
        $after=self::snapshot(array());if(is_wp_error($after))return $after;
        foreach($patch as $key=>$value)if(($after['values'][$key]??null)!==$value)return new \WP_Error('organization_readback_failed','Yoast normalized or refused a supplied value. Inspect stored profiles and Undo before retrying.');
        foreach($s['values']as $key=>$value)if(!array_key_exists($key,$patch)&&($after['values'][$key]??null)!==$value)return new \WP_Error('organization_preservation_failed','A Yoast hook changed another setting. Inspect Undo before retrying.');
        return self::get(array());
    }
    public static function restore_entity($before){update_option('wpseo_social',$before['values']);return self::snapshot(array());}
}

