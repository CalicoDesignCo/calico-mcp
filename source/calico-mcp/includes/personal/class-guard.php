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
        $fields=array(); foreach(array('post_title','post_content','post_excerpt','post_status','post_type','post_name','post_parent','post_author','post_date','post_date_gmt','post_modified_gmt','post_mime_type') as $key) { $fields[$key]=$post->$key; }
        $meta=get_post_meta($id); unset($meta['_edit_lock'],$meta['_edit_last']); ksort($meta);
        $terms=array(); foreach(get_object_taxonomies($post->post_type) as $taxonomy) { $ids=wp_get_object_terms($id,$taxonomy,array('fields'=>'ids')); if(!is_wp_error($ids)) { sort($ids); $terms[$taxonomy]=$ids; } } ksort($terms);
        return array('fields'=>$fields,'meta'=>$meta,'terms'=>$terms);
    }
    public static function derived_counter($key,$values) {
        return $key==='_eael_post_view_count'&&is_array($values)&&count($values)===1&&isset($values[0])&&is_string($values[0])&&preg_match('/^\d+$/D',$values[0]);
    }
    private static function revision_view($snapshot,$ignore_counter=true) {
        // Rendering can regenerate these derived caches without any document edit.
        // Retain them in snapshots, but do not treat a preview as newer authored work.
        unset($snapshot['meta']['_elementor_css'],$snapshot['meta']['_elementor_element_cache'],$snapshot['meta']['_elementor_page_assets']);
        // Elementor 4 also records a version/manifest fingerprint during reads.
        // Any migration that changes actual layout/settings still changes the hash.
        foreach(array_keys($snapshot['meta']) as $key) { if(preg_match('/^_elementor_migrations_state_[a-f0-9]{4}$/D',$key)) { unset($snapshot['meta'][$key]); } }
        // Essential Addons increments this numeric analytics value on page view.
        // Never ignore an arbitrary prefix, duplicate records or malformed values.
        if($ignore_counter&&self::derived_counter('_eael_post_view_count',$snapshot['meta']['_eael_post_view_count']??null))unset($snapshot['meta']['_eael_post_view_count']);
        return $snapshot;
    }
    public static function revision($snapshot,$ignore_counter=true) {
        return hash_hmac('sha256',serialize(self::revision_view($snapshot,$ignore_counter)),wp_salt('auth'));
    }
    public static function fingerprints($snapshot,$ignore_counter=true) {
        $out=array();
        foreach(self::revision_view($snapshot,$ignore_counter)as $section=>$values)foreach($values as $key=>$value)$out[$section][$key]=hash_hmac('sha256',serialize($value),wp_salt('auth'));
        return $out;
    }
    public static function input_schema($ability) {
        $schema=$ability->get_input_schema(); $name=$ability->get_name();
        if(in_array(Policy::group($name),array('elementor_full','elementor_full_read'),true)&&isset($schema['properties']['id']))$schema['properties']['id']['description']='Existing post, page or shared Elementor template ID. Use shared-template-list to discover templates. Shared writes need separate permission and may affect many pages.';
        if(in_array(Policy::group($name),array('seo','seo_advanced'),true)) { $schema['properties']['post_id']['description']='Existing post/page ID, or a selected custom item in an enabled status. Custom content SEO requires custom editing, the applicable Yoast permission and the applicable published or other-status editing opt-in.'; }
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
            $document=get_post($id);if(!$document||(!in_array($document->post_type,array('post','page'),true)&&!Shared_Templates::supported($document)))return new \WP_Error('elementor_document_denied','Use a post, page or supported existing shared Elementor template.');
            if($writer&&Shared_Templates::supported($document)&&empty(Policy::settings()['shared_templates']))return new \WP_Error('shared_template_denied','Shared-template editing is disabled. This document may affect many pages.');
        }
        if(in_array($name,array('calicomcp/content-trash','calicomcp/content-restore-from-trash'),true))return Trash::execute($ability,$args);
        if($name==='calicomcp/organization-update')return Entity_Recovery::run($ability,$args);
        if($name==='calicomcp/term-update')return Entity_Recovery::run($ability,$args);
        if($name==='calicomcp/term-create')return $ability->execute($args);
        if($name==='calicomcp/term-get')return $ability->execute($args);
        $lock=false; $backup=0; $preserve_dates=false;
        if($writer && !$creation) {
            if(!$id || !current_user_can('edit_post',$id)) { return new \WP_Error('calicomcp_denied','A writable item ID is required.'); }
            $post=get_post($id);
            $event_seo=in_array(Policy::group($name),array('seo','seo_advanced'),true) && $post && !in_array($post->post_type,array('post','page','attachment'),true) && Events::is_selected_type($post->post_type);
            $event=(in_array($name,array('calicomcp/event-update-draft','calicomcp/cpt-update-draft','calicomcp/cpt-update','calicomcp/cpt-repeater-rows'),true) && $post && Events::is_selected_type($post->post_type)) || $event_seo;
            $lifecycle=in_array($name,array('calicomcp/content-publish','calicomcp/content-schedule','calicomcp/content-set-status'),true)&&Toolbox::content_type($post);
            $structural=$post&&in_array(Policy::group($name),array('structure','terms'),true)&&Toolbox::content_type($post);
            $shared=$post&&Policy::group($name)==='elementor_full'&&Shared_Templates::supported($post)&&!empty(Policy::settings()['shared_templates']);
            if(!$post || (!$event && !$structural && !$shared && !$lifecycle && !in_array($post->post_type,array('post','page','attachment'),true))) { return new \WP_Error('unsupported_content_type','Use a supported content type or selected custom draft.'); }
            if($event&&!Policy::editable_status($post,$event_seo||in_array($name,array('calicomcp/cpt-update','calicomcp/cpt-repeater-rows'),true)))return new \WP_Error('content_status_denied','This tool needs the applicable status editing permission. Legacy draft aliases remain draft-only.');
            if(in_array(Policy::group($name),array('seo','seo_advanced'),true)) { $allowed=self::seo_meta($name,$args,$id,$event_seo); if(is_wp_error($allowed)) { return $allowed; } }
            if(Policy::group($name)==='elementor_full')foreach(array('_elementor_data','_elementor_edit_mode','_elementor_version','_elementor_template_type') as $meta_key)if(!Events::can_edit_meta($id,$meta_key))return new \WP_Error('elementor_meta_denied','Elementor metadata authorization refused.');
            if(in_array(Policy::group($name),array('elementor','elementor_full'),true)&&!Policy::editable_status($post,Policy::group($name)==='elementor_full'))return new \WP_Error('elementor_status_denied','Enable the applicable status editing permission for full Elementor tools. Legacy widget tools remain draft-only.');
            if($post->post_status==='future'&&!$lifecycle&&strtotime($post->post_date_gmt.' UTC')<time()+120)return new \WP_Error('schedule_imminent','Publication is imminent or overdue. Read the saved status after cron runs before editing.');
            if(get_post_meta($id,'_elementor_data',true) && (array_key_exists('content',$args)||$name==='calicomcp/content-replace-in-post')) { return new \WP_Error('elementor_not_enabled','This item has an Elementor layout. Its body cannot be changed by this prototype.'); }
            $lock=self::lock($id); if(!$lock) { return new \WP_Error('item_busy','Another Calico edit is in progress. Read the item again and retry.'); }
        }
        try {
            if($writer && !$creation) {
                $before=self::snapshot($id);
                if(!isset($args['expected_revision']) || !is_string($args['expected_revision']) || !hash_equals(self::revision($before),$args['expected_revision'])) { return new \WP_Error('stale_revision','The item changed or no revision was supplied. Read it again before editing.'); }
                $publication_cron=wp_next_scheduled('publish_future_post',array($id));
                $backup=Recovery::begin($id,$before,$name);
                if(!$backup) { return new \WP_Error('backup_failed','Recovery data could not be saved. No edit was attempted.'); }
                unset($args['expected_revision']);
                if(!in_array($name,array('calicomcp/content-publish','calicomcp/content-schedule','calicomcp/content-set-status'),true)) {
                    // wp_update_post() otherwise replaces an unpublished item's
                    // local date when its GMT date is zero. Scope this filter to
                    // the exact existing item, never new copies or revisions.
                    $preserve_dates=static function($data,$postarr)use($id,$before){
                        if((int)($postarr['ID']??0)===$id)foreach(array('post_date','post_date_gmt')as $key)$data[$key]=$before['fields'][$key];
                        return $data;
                    };
                    add_filter('wp_insert_post_data',$preserve_dates,PHP_INT_MAX,2);
                }
            }
            $result=$ability->execute($args);
            if($backup) {
                $after=self::snapshot($id);
                $preserves_schedule=!in_array($name,array('calicomcp/content-publish','calicomcp/content-schedule','calicomcp/content-set-status'),true);
                $schedule_changed=$preserves_schedule&&($before['fields']['post_date']!==$after['fields']['post_date']||$before['fields']['post_date_gmt']!==$after['fields']['post_date_gmt']||$publication_cron!==wp_next_scheduled('publish_future_post',array($id)));
                if(!Recovery::finish($backup,$after))return new \WP_Error('recovery_finalize_failed','The edit ran but its recovery record could not be finalized. Check the saved item before retrying.',array('recovery_id'=>$backup));
                if($schedule_changed)return new \WP_Error('unexpected_schedule_change','A hook changed publication dates or cron. Inspect the saved item and recovery checkpoint before retrying.',array('recovery_id'=>$backup));
                if(!in_array($name,array('calicomcp/content-publish','calicomcp/content-schedule','calicomcp/content-set-status'),true)&&$before['fields']['post_status']!==$after['fields']['post_status'])return new \WP_Error('unexpected_status_change','A WordPress hook changed this item status during the edit. No status transition was requested. Inspect the item and its recovery record before retrying.',array('recovery_id'=>$backup));
            }
            if(is_array($result)) {
                $resultId=$name==='calicomcp/content-duplicate'?self::subject($result):($id?:self::subject($result));
                if($resultId && get_post($resultId)) { $result['revision']=self::revision(self::snapshot($resultId)); }
                if($backup) { $result['recovery_id']=$backup; }
                if($id&&Shared_Templates::supported(get_post($id)))$result['shared_template_note']='This shared document may affect multiple pages. Its assignment and display conditions are preserved; verify rendered output separately.';
            } elseif(is_wp_error($result) && $backup) { $result->add_data(array('recovery_id'=>$backup)); }
            return $result;
        } finally { if($preserve_dates)remove_filter('wp_insert_post_data',$preserve_dates,PHP_INT_MAX); if($lock) { self::unlock($lock); } }
    }
}
