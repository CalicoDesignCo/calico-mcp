<?php
namespace CalicoMCP\Personal;
if (!defined('ABSPATH')) { exit; }
class Guard {
    public static function subject($input) {
        foreach(array('id','post_id','page_id','media_id','attachment_id') as $key) { if(isset($input[$key]) && is_numeric($input[$key])) { return absint($input[$key]); } }
        return 0;
    }
    public static function creation($name) { return in_array($name,array('calicomcp/post-create-draft','calicomcp/page-create-draft','calicomcp/media-upload-from-url','calicomcp/media-upload-file','calicomcp/event-create-draft','calicomcp/cpt-create-draft','calicomcp/term-create'),true); }
    public static function snapshot($id) {
        clean_post_cache($id); $post=get_post($id); if(!$post) { return null; }
        $fields=array(); foreach(array('post_title','post_content','post_excerpt','post_status','post_type','post_name','post_parent','post_author','post_modified_gmt','post_mime_type') as $key) { $fields[$key]=$post->$key; }
        $meta=get_post_meta($id); unset($meta['_edit_lock'],$meta['_edit_last']); ksort($meta);
        $terms=array(); foreach(get_object_taxonomies($post->post_type) as $taxonomy) { $ids=wp_get_object_terms($id,$taxonomy,array('fields'=>'ids')); if(!is_wp_error($ids)) { sort($ids); $terms[$taxonomy]=$ids; } } ksort($terms);
        return array('fields'=>$fields,'meta'=>$meta,'terms'=>$terms);
    }
    public static function revision($snapshot) {
        // Rendering can regenerate these derived caches without any document edit.
        // Retain them in snapshots, but do not treat a preview as newer authored work.
        unset($snapshot['meta']['_elementor_css'],$snapshot['meta']['_elementor_element_cache'],$snapshot['meta']['_elementor_page_assets']);
        // Elementor 4 also records a version/manifest fingerprint during reads.
        // Any migration that changes actual layout/settings still changes the hash.
        foreach(array_keys($snapshot['meta']) as $key) { if(preg_match('/^_elementor_migrations_state_[a-f0-9]{4}$/D',$key)) { unset($snapshot['meta'][$key]); } }
        return hash_hmac('sha256',serialize($snapshot),wp_salt('auth'));
    }
    public static function input_schema($ability) {
        $schema=$ability->get_input_schema(); $name=$ability->get_name();
        if(in_array(Policy::group($name),array('seo','seo_advanced'),true)) { $schema['properties']['post_id']['description']='Existing post/page ID, or a selected custom draft/published item. Custom content SEO requires custom editing and the applicable Yoast permission; published custom items also require published editing.'; }
        if(Policy::writer($name) && !self::creation($name)) {
            $schema['properties']['expected_revision']=array('type'=>'string','description'=>'Revision returned by a fresh get/read of this exact item. A stale revision refuses the edit.','minLength'=>64,'maxLength'=>64);
            $schema['required']=array_values(array_unique(array_merge($schema['required']??array(),array('expected_revision'))));
        }
        return $schema;
    }
    public static function lock($id) {
        $key='calicomcp_item_lock_'.$id; $value=wp_generate_uuid4().'|'.time();
        if(add_option($key,$value,'',false)) { return array($key,$value); }
        $old=get_option($key); $parts=explode('|',(string)$old);
        if(count($parts)===2 && (int)$parts[1]<time()-120) {
            global $wpdb;
            $wpdb->query($wpdb->prepare('DELETE FROM %i WHERE option_name=%s AND option_value=%s',$wpdb->options,$key,$old));
            wp_cache_delete($key,'options');
            if(add_option($key,$value,'',false)) { return array($key,$value); }
        }
        return false;
    }
    public static function unlock($lock) {
        global $wpdb;
        $wpdb->query($wpdb->prepare('DELETE FROM %i WHERE option_name=%s AND option_value=%s',$wpdb->options,$lock[0],$lock[1]));
        wp_cache_delete($lock[0],'options');
    }
    private static function seo_meta($name,$args,$id,$custom) {
        if($custom&&!Policy::allowed('calicomcp/event-update-draft')) { return new \WP_Error('event_seo_denied','Custom content SEO requires custom editing and the applicable Yoast editing permission.'); }
        $maps=array(
            'update-seo-title'=>array('seo_title'=>array('title')),
            'update-meta-description'=>array('meta_description'=>array('metadesc')),
            'update-focus-keyword'=>array('keyword'=>array('focuskw'),'secondary_keywords'=>array('keyphrase_synonyms')),
            'update-og-tags'=>array('og_title'=>array('opengraph-title'),'og_description'=>array('opengraph-description'),'og_image_id'=>array('opengraph-image-id','opengraph-image')),
            'update-twitter-card'=>array('twitter_title'=>array('twitter-title'),'twitter_description'=>array('twitter-description'),'twitter_image_id'=>array('twitter-image-id','twitter-image'))
            ,'update-breadcrumb-title'=>array('breadcrumb_title'=>array('bctitle'))
            ,'update-canonical'=>array('canonical_url'=>array('canonical'))
            ,'update-robots-meta'=>array('noindex'=>array('meta-robots-noindex'),'nofollow'=>array('meta-robots-nofollow'))
            ,'update-robots-advanced'=>array('noimageindex'=>array('meta-robots-adv'),'noarchive'=>array('meta-robots-adv'),'nosnippet'=>array('meta-robots-adv'))
            ,'update-cornerstone'=>array('is_cornerstone'=>array('is_cornerstone'))
            ,'update-schema-types'=>array('page_type'=>array('schema_page_type'),'article_type'=>array('schema_article_type'))
            ,'update-sitemap-inclusion'=>array('exclude'=>array())
        );
        foreach($maps[substr($name,10)]??array() as $input=>$keys) {
            if(!array_key_exists($input,$args)) { continue; }
            foreach($keys as $key) { if(!Events::can_edit_meta($id,'_yoast_wpseo_'.$key)) { return new \WP_Error('event_seo_denied','Metadata authorization refused.'); } }
            if(in_array($input,array('og_image_id','twitter_image_id'),true)&&$args[$input]>0&&(!wp_attachment_is_image($args[$input])||!current_user_can('read_post',$args[$input]))) { return new \WP_Error('event_seo_image','Use a readable image attachment ID.'); }
        }
        if($name==='calicomcp/update-sitemap-inclusion'&&!Events::can_edit_meta($id,'_calicomcp_sitemap_excluded'))return new \WP_Error('event_seo_denied','Sitemap metadata authorization refused.');
        return true;
    }
    public static function execute($ability,$input) {
        $name=$ability->get_name();
        if(!Policy::is_owner(get_current_user_id()) || !Policy::allowed($name)) { return new \WP_Error('calicomcp_denied','This tool is not allowed for this account.'); }
        $args=is_array($input)?$input:array(); $id=self::subject($args); $writer=Policy::writer($name); $creation=self::creation($name);
        if(in_array(Policy::group($name),array('elementor_full','elementor_full_read'),true)&&$id) {
            $document=get_post($id);if(!$document||!in_array($document->post_type,array('post','page'),true))return new \WP_Error('elementor_document_denied','Full Elementor tools currently support posts and pages only.');
        }
        if(in_array(Policy::group($name),array('forms_read','forms_edit'),true)) {
            $entries=strpos($name,'entry')!==false||strpos($name,'entries')!==false;
            if(!Forms::can($entries?'gravityforms_view_entries':'gravityforms_edit_forms'))return new \WP_Error('forms_denied','Gravity Forms permission is missing.');
            if($writer)return Entity_Recovery::run($ability,$args);
            $result=$ability->execute($args);return $name==='calicomcp/gf-get-entry-by-id'?Forms::entry_revision($result,$args):$result;
        }
        if($name==='calicomcp/term-update')return Entity_Recovery::run($ability,$args);
        if($name==='calicomcp/term-create')return $ability->execute($args);
        if($name==='calicomcp/term-get')return $ability->execute($args);
        $lock=false; $backup=0;
        if($writer && !$creation) {
            if(!$id || !current_user_can('edit_post',$id)) { return new \WP_Error('calicomcp_denied','A writable item ID is required.'); }
            $post=get_post($id);
            $event_seo=in_array(Policy::group($name),array('seo','seo_advanced'),true) && $post && !in_array($post->post_type,array('post','page','attachment'),true) && Events::is_selected_type($post->post_type);
            $event=(in_array($name,array('calicomcp/event-update-draft','calicomcp/cpt-update-draft','calicomcp/cpt-update','calicomcp/cpt-repeater-rows'),true) && $post && Events::is_selected_type($post->post_type)) || $event_seo;
            $structural=$post&&in_array(Policy::group($name),array('structure','terms'),true)&&Toolbox::content_type($post);
            if(!$post || (!$event && !$structural && !in_array($post->post_type,array('post','page','attachment'),true))) { return new \WP_Error('unsupported_content_type','Use a supported content type or selected custom draft.'); }
            if($event&&!Policy::editable_status($post,$event_seo||in_array($name,array('calicomcp/cpt-update','calicomcp/cpt-repeater-rows'),true)))return new \WP_Error('content_status_denied','This tool requires a draft, or separate permission for already-published custom content. Legacy draft tools remain draft-only.');
            if(in_array(Policy::group($name),array('seo','seo_advanced'),true)) { $allowed=self::seo_meta($name,$args,$id,$event_seo); if(is_wp_error($allowed)) { return $allowed; } }
            if(Policy::group($name)==='elementor_full')foreach(array('_elementor_data','_elementor_edit_mode','_elementor_version','_elementor_template_type') as $meta_key)if(!Events::can_edit_meta($id,$meta_key))return new \WP_Error('elementor_meta_denied','Elementor metadata authorization refused.');
            if(in_array(Policy::group($name),array('elementor','elementor_full'),true)&&!Policy::editable_status($post,Policy::group($name)==='elementor_full'))return new \WP_Error('elementor_status_denied','Use a draft, or enable published editing for full Elementor tools. Legacy widget draft tools remain draft-only.');
            if(get_post_meta($id,'_elementor_data',true) && (array_key_exists('content',$args)||$name==='calicomcp/content-replace-in-post')) { return new \WP_Error('elementor_not_enabled','This item has an Elementor layout. Its body cannot be changed by this prototype.'); }
            $lock=self::lock($id); if(!$lock) { return new \WP_Error('item_busy','Another Calico edit is in progress. Read the item again and retry.'); }
        }
        try {
            if($writer && !$creation) {
                $before=self::snapshot($id);
                if(!isset($args['expected_revision']) || !is_string($args['expected_revision']) || !hash_equals(self::revision($before),$args['expected_revision'])) { return new \WP_Error('stale_revision','The item changed or no revision was supplied. Read it again before editing.'); }
                $backup=Recovery::begin($id,$before,$name);
                if(!$backup) { return new \WP_Error('backup_failed','Recovery data could not be saved. No edit was attempted.'); }
                unset($args['expected_revision']);
            }
            $result=$ability->execute($args);
            if($backup) {
                $after=self::snapshot($id);
                if(!Recovery::finish($backup,$after))return new \WP_Error('recovery_finalize_failed','The edit ran but its recovery record could not be finalized. Check the saved item before retrying.',array('recovery_id'=>$backup));
                if($before['fields']['post_status']!==$after['fields']['post_status'])return new \WP_Error('unexpected_status_change','A WordPress hook changed this item status during the edit. No status transition was requested. Inspect the item and its recovery record before retrying.',array('recovery_id'=>$backup));
            }
            if(is_array($result)) {
                $resultId=$name==='calicomcp/content-duplicate'?self::subject($result):($id?:self::subject($result));
                if($resultId && get_post($resultId)) { $result['revision']=self::revision(self::snapshot($resultId)); }
                if($backup) { $result['recovery_id']=$backup; }
            } elseif(is_wp_error($result) && $backup) { $result->add_data(array('recovery_id'=>$backup)); }
            return $result;
        } finally { if($lock) { self::unlock($lock); } }
    }
}
