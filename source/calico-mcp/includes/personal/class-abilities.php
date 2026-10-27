<?php
namespace CalicoMCP\Personal;
use CalicoMCP\Abilities\Config;
use CalicoMCP\Abilities\Naming;
use CalicoMCP\Abilities\Ability_Registrar;
use CalicoMCP\Abilities\Packs\Yoast\YSOA_Helpers;
if (!defined('ABSPATH')) { exit; }
class Abilities {
    private static function packs() {
        $packs=array(new \CalicoMCP\Abilities\Packs\Core\Posts_Pack(),new \CalicoMCP\Abilities\Packs\Core\Pages_Pack(),new \CalicoMCP\Abilities\Packs\Core\Media_Pack(),new \CalicoMCP\Abilities\Packs\Core\Revisions_Pack(),new \CalicoMCP\Abilities\Packs\Editing\Editing_Pack(),new Events(),new Content_Types(),new Content_Operations(),new Taxonomies(),new Elementor(),new Shared_Templates(),new Organization(),new Lifecycle(),new Trash());
        if(Elementor::available())$packs[]=new \CalicoMCP\Abilities\Packs\Elementor\Elementor_Pack();
        return $packs;
    }
    public static function init() {
        Config::set(array('prefix'=>'calicomcp','version'=>CALICOMCP_VERSION,'text_domain'=>'calicomcp-abilities'));
        add_action('wp_abilities_api_categories_init',array(__CLASS__,'categories'));
        add_action('wp_abilities_api_init',array(__CLASS__,'register'));
    }
    public static function categories() {
        if(!function_exists('wp_register_ability_category')) { return; }
        $seen=array();foreach(self::packs() as $pack) { $cat=$pack->category(); if(isset($seen[$cat['slug']]))continue; $seen[$cat['slug']]=true; Naming::register_category($cat['slug'],array('label'=>$cat['label'],'description'=>$cat['description'])); }
        YSOA_Helpers::register_categories();
    }
    public static function register() {
        if(!function_exists('wp_register_ability')) { return; }
        foreach(self::packs() as $pack) { foreach($pack->abilities() as $ability) { if(Policy::supports($ability->get_name())) { Ability_Registrar::register($ability); } } }
        YSOA_Helpers::register_abilities();
    }
}

