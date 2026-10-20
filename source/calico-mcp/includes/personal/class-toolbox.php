<?php
namespace CalicoMCP\Personal;
use CalicoMCP\Abilities\Ability;
if (!defined('ABSPATH')) { exit; }
class Toolbox {
    public static function id($description='Existing item ID.') { return array('type'=>'integer','minimum'=>1,'description'=>$description); }
    public static function text($description='Text.') { return array('type'=>'string','maxLength'=>20000,'description'=>$description); }
    public static function tool($name,$description,$properties,$required,$callback,$read,$category='calicomcp-content-operations') {
        return new Ability('calicomcp/'.$name,array('label'=>ucwords(str_replace('-',' ',$name)),'description'=>$description,'category'=>$category,'input_schema'=>array('type'=>'object','properties'=>$properties?:new \stdClass(),'required'=>$required,'additionalProperties'=>false),'execute'=>$callback,'capability'=>'manage_options','annotations'=>array('readonly'=>$read,'destructive'=>false,'idempotent'=>$read||!preg_match('/(?:create|duplicate)$/',$name),'open_world'=>false)));
    }
    public static function content_type($post) { return $post && (in_array($post->post_type,array('post','page'),true)||Events::is_selected_type($post->post_type)); }
    public static function revision($value) {
        $sort=function(&$item) use (&$sort) { if(!is_array($item))return; ksort($item); foreach($item as &$child) { $sort($child); } };
        $sort($value); return hash_hmac('sha256',serialize($value),wp_salt('auth'));
    }
}
