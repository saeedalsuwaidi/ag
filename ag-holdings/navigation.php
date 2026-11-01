<?php
if (!defined('ABSPATH')) { exit; }

function ag_navigation_data() {
    static $data=null;
    if($data===null)$data=json_decode(file_get_contents(get_template_directory().'/navigation.json'),true);
    return $data;
}
function ag_section_data() {
    static $data=null;
    if($data===null)$data=json_decode(file_get_contents(get_template_directory().'/page-sections.json'),true);
    return $data;
}
function ag_current_slug() {
    $obj=get_queried_object();
    return $obj instanceof WP_Post ? preg_replace('/-ar$/','',$obj->post_name) : 'home';
}
function ag_find_page($slug) {
 $slug=sanitize_title($slug);$page=get_page_by_path($slug);
 if($page&&$page->post_status==='publish')return $page;
 $pages=get_posts(['name'=>$slug,'post_type'=>'page','post_status'=>'publish','numberposts'=>1,'orderby'=>'ID','order'=>'ASC']);
 if($pages)return $pages[0];
 if($page)return $page;
 $pages=get_posts(['name'=>$slug,'post_type'=>'page','post_status'=>['draft','private'],'numberposts'=>1]);return $pages?$pages[0]:null;
}
function ag_navigation_link($slug,$label,$current,$class='') {
    $url=$slug==='home'&&!ag_is_ar()?home_url('/'):ag_url($slug);
    return '<a href="'.esc_url($url).'"'.($class?' class="'.esc_attr($class).'"':'').($current===$slug?' aria-current="page"':'').'>'.esc_html($label).'</a>';
}
function ag_site_navigation() {
    $ar=ag_is_ar();$lang=$ar?'ar':'en';$current=ag_current_slug();$data=ag_navigation_data();$sections=ag_section_data();
    $active=$sections[$current]['group'] ?? (in_array($current,array_column($data['companies'],0),true)?'group':'');
    $out='<nav id="main-nav" aria-label="'.($ar?'التنقل الرئيسي':'Main navigation').'">'.ag_navigation_link('home',$ar?'الصفحة الرئيسية':'Home',$current);
    foreach($data['groups'] as $group){
        if($group['slug']==='news')continue;
        $slug=$group['slug'];$label=$group['label'][$lang];$id='nav-'.$slug;
        $out.='<div class="nav-group'.($active===$slug?' is-current':'').'"><div class="nav-group-trigger">'.ag_navigation_link($slug,$label,$current).'<button class="nav-toggle" type="button" aria-expanded="false" aria-controls="'.esc_attr($id).'" aria-label="'.esc_attr($ar?'عرض قائمة '.$label:'Open '.$label.' menu').'"><span aria-hidden="true">⌄</span></button></div><div class="nav-dropdown'.($slug==='group'?' nav-dropdown--wide':'').'" id="'.esc_attr($id).'"><div class="nav-dropdown-column"><p class="nav-dropdown-label">'.esc_html($label).'</p><ul>';
        foreach($group['children'] as $child)$out.='<li>'.ag_navigation_link($child[0],$child[$ar?2:1],$current).'</li>';
        $out.='</ul></div>';
        if($slug==='group'){
            $out.='<div class="nav-dropdown-column"><p class="nav-dropdown-label">'.($ar?'شركات المجموعة':'Our Companies').'</p><ul>';
            foreach($data['companies'] as $child)$out.='<li>'.ag_navigation_link($child[0],$child[$ar?2:1],$current).'</li>';
            $out.='</ul></div>';
        }
        $out.='</div></div>';
    }
    $out.=ag_navigation_link('contact-us',$ar?'الاتصال':'Contact',$current,'nav-contact');
    $out.='<a class="language-switch" href="'.esc_url(ag_other_language_url()).'" lang="'.($ar?'en':'ar').'" hreflang="'.($ar?'en':'ar').'" aria-label="'.($ar?'Switch to English':'التبديل إلى العربية').'">'.($ar?'English':'العربية').'</a></nav>';
    return $out;
}
function ag_section_navigation($current) {
    $ar=ag_is_ar();$lang=$ar?'ar':'en';$data=ag_navigation_data();$sections=ag_section_data();$root=$sections[$current]['group']??'group';
    foreach($data['groups'] as $candidate)if($candidate['slug']===$root){$group=$candidate;break;}
    if(!isset($group))return '';
    $out='<aside class="page-sidebar"><p class="eyebrow">'.($ar?'في هذا القسم':'In this section').'</p><h2>'.esc_html($group['label'][$lang]).'</h2><nav class="section-links" aria-label="'.esc_attr($ar?'صفحات '.$group['label'][$lang]:$group['label'][$lang].' pages').'">';
    foreach($group['children'] as $child)$out.=ag_navigation_link($child[0],$child[$ar?2:1],$current);
    if($root==='group'){
        $out.='<span class="sidebar-label">'.($ar?'شركات المجموعة':'Our Companies').'</span>';
        foreach($data['companies'] as $child)$out.=ag_navigation_link($child[0],$child[$ar?2:1],$current);
    }
    return $out.'</nav></aside>';
}
function ag_breadcrumbs($current) {
    $ar=ag_is_ar();$lang=$ar?'ar':'en';$sections=ag_section_data();$data=ag_navigation_data();$root=$sections[$current]['group']??'group';
    $out='<nav class="breadcrumbs" aria-label="'.($ar?'مسار التصفح':'Breadcrumb').'">'.ag_navigation_link('home',$ar?'الرئيسية':'Home','');
    if($root!==$current){foreach($data['groups'] as $g)if($g['slug']===$root){$out.='<span aria-hidden="true">/</span>'.ag_navigation_link($root,$g['label'][$lang],'');break;}}
    return $out.'<span aria-hidden="true">/</span><span aria-current="page">'.esc_html(get_the_title()).'</span></nav>';
}
function ag_section_template($template) {
    if(is_page() && !in_array(ag_current_slug(), ['about','group'], true) && isset(ag_section_data()[ag_current_slug()]))return get_template_directory().'/page-section.php';
    return $template;
}
add_filter('template_include','ag_section_template',30);
function ag_career_application($atts) {
 $atts=shortcode_atts(['kind'=>'internship'],$atts);
 $kind=$atts['kind']==='career'?'career':'internship';$ar=ag_is_ar();
 return '<section class="application-section"><h2>'.($ar?'تقديم الطلب':'Submit your application').'</h2><p>'.($ar?'أرسل بياناتك مباشرةً من الصفحة. ستتلقى رقم متابعة بعد حفظ الطلب.':'Submit your details directly on this page. A reference number confirms that your enquiry has been saved.').'</p>'.ag_enquiry_form(['kind'=>$kind,'lang'=>$ar?'ar':'en']).'</section>';
}
add_shortcode('ag_career_application','ag_career_application');
function ag_section_legacy_redirect() {
    if(is_admin())return;
    $path=basename((string)wp_parse_url(isset($_SERVER['REQUEST_URI'])?wp_unslash($_SERVER['REQUEST_URI']):'',PHP_URL_PATH));
    if(!preg_match('/^([a-z0-9-]+)\.html$/',$path,$match))return;
    $base=preg_replace('/-ar$/','',$match[1]);
    if(isset(ag_section_data()[$base])){$page=ag_find_page($match[1]);if($page){wp_safe_redirect(get_permalink($page),301);exit;}}
}
add_action('template_redirect','ag_section_legacy_redirect',9);

// Apply hierarchy to theme-created pages only. Existing editorial pages retain their placement.
function ag_apply_page_hierarchy() {
    $sections=ag_section_data();
    foreach($sections as $slug=>$section){
        if($slug===$section['group'])continue;
        foreach(['','-ar'] as $suffix){
            $page=ag_find_page($slug.$suffix);$parent=ag_find_page($section['group'].$suffix);
            if($page&&$parent&&get_post_meta($page->ID,'_ag_seeded',true)==='1'&&(int)$page->post_parent!==$parent->ID)wp_update_post(['ID'=>$page->ID,'post_parent'=>$parent->ID]);
        }
    }
    foreach(ag_navigation_data()['companies'] as $company)foreach(['','-ar'] as $suffix){
        $page=ag_find_page($company[0].$suffix);$parent=ag_find_page('group'.$suffix);
        if($page&&$parent&&get_post_meta($page->ID,'_ag_seeded',true)==='1'&&(int)$page->post_parent!==$parent->ID)wp_update_post(['ID'=>$page->ID,'post_parent'=>$parent->ID]);
    }
}
