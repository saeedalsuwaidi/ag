<?php
if(!defined('ABSPATH'))exit;
function ag_plain_summary($text,$length=170){$text=trim(preg_replace('/\s+/u',' ',html_entity_decode(wp_strip_all_tags(strip_shortcodes($text)),ENT_QUOTES,'UTF-8')));return function_exists('mb_substr')?mb_substr($text,0,$length):wp_trim_words($text,28,'');}
function ag_page_description(){
 $id=get_queried_object_id();$custom=get_post_meta($id,'_ag_seo_description',true);if($custom)return ag_plain_summary($custom);
 $map=json_decode(file_get_contents(get_template_directory().'/seo-descriptions.json'),true);$slug=get_post_field('post_name',$id);if(isset($map[$slug]))return $map[$slug];
 $p=get_post($id);return $p?ag_plain_summary($p->post_excerpt?:$p->post_content):get_bloginfo('description');
}
add_action('wp_head',function(){
 if(!is_singular('page'))return;
 $url=get_permalink();$ar=ag_is_ar();$title=wp_get_document_title();$desc=ag_page_description();$image=get_template_directory_uri().'/assets/share-brand.png';$logo=has_custom_logo()?wp_get_attachment_image_url(get_theme_mod('custom_logo'),'full'):get_template_directory_uri().'/assets/holdings-logo.png';
 /* Let an installed SEO plugin own its head tags; avoid duplicate canonical/schema. */
 if(defined('WPSEO_VERSION')||defined('RANK_MATH_VERSION')||defined('AIOSEO_VERSION'))return;
 echo '<meta name="description" content="'.esc_attr($desc).'">';
 foreach(['og:type'=>'website','og:site_name'=>'Ghanem Al Suwaidi Holdings','og:title'=>$title,'og:description'=>$desc,'og:url'=>$url,'og:locale'=>$ar?'ar_AE':'en_AE','og:image'=>$image,'og:image:width'=>'1200','og:image:height'=>'630','og:image:alt'=>'Ghanem Al Suwaidi Holdings — غانم السويدي القابضة','twitter:card'=>'summary_large_image','twitter:title'=>$title,'twitter:description'=>$desc,'twitter:image'=>$image] as $key=>$value)echo '<meta '.(strpos($key,'og:')===0?'property':'name').'="'.esc_attr($key).'" content="'.esc_attr($value).'">';
 $other=ag_other_language_url();echo '<link rel="alternate" hreflang="'.($ar?'ar':'en').'" href="'.esc_url($url).'"><link rel="alternate" hreflang="'.($ar?'en':'ar').'" href="'.esc_url($other).'">';
 if(ag_is_portal())return;
 $org=['@type'=>'Organization','@id'=>home_url('/#organization'),'name'=>'Ghanem Al Suwaidi Holdings','alternateName'=>['غانم السويدي القابضة','Ghanim Al Suwaidi Holdings'],'url'=>home_url('/'),'logo'=>['@type'=>'ImageObject','url'=>$logo],'sameAs'=>['https://www.instagram.com/gaholdings.ae/','https://www.linkedin.com/company/ghanem-al-suwaidi-holdings/']];
 $schema=['@context'=>'https://schema.org','@graph'=>[$org,['@type'=>'WebSite','@id'=>home_url('/#website'),'url'=>home_url('/'),'name'=>'Ghanem Al Suwaidi Holdings','publisher'=>['@id'=>home_url('/#organization')],'inLanguage'=>['ar','en']],['@type'=>'WebPage','@id'=>$url.'#webpage','url'=>$url,'name'=>$title,'description'=>$desc,'inLanguage'=>$ar?'ar':'en','isPartOf'=>['@id'=>home_url('/#website')],'about'=>['@id'=>home_url('/#organization')]]]];
 echo '<script type="application/ld+json">'.wp_json_encode($schema,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_HEX_TAG|JSON_HEX_AMP).'</script>';
},5);
add_filter('wp_sitemaps_posts_query_args',function($args,$type){if($type==='page'){$ids=[];foreach(['employee-portal','employee-portal-ar','work-policies','work-policies-ar'] as $slug){$p=ag_find_page($slug);if($p)$ids[]=$p->ID;}$args['post__not_in']=array_merge($args['post__not_in']??[],$ids);}return $args;},10,2);
add_action('add_meta_boxes',function(){add_meta_box('ag-seo-description','Search & sharing description / وصف البحث والمشاركة',function($post){wp_nonce_field('ag_seo_meta','ag_seo_nonce');echo '<textarea name="ag_seo_description" rows="4" style="width:100%" maxlength="320">'.esc_textarea(get_post_meta($post->ID,'_ag_seo_description',true)).'</textarea><p>Optional: write a unique summary of this page. Leave blank to use the supplied description.</p>';},'page','side');});
add_action('save_post_page',function($id){if(wp_is_post_revision($id)||!current_user_can('edit_post',$id)||empty($_POST['ag_seo_nonce'])||!wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['ag_seo_nonce'])),'ag_seo_meta'))return;update_post_meta($id,'_ag_seo_description',sanitize_textarea_field(wp_unslash($_POST['ag_seo_description']??'')));});
