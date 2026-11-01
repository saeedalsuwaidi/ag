<?php
if (!defined('ABSPATH')) { exit; }
function ag_setup() {
    add_theme_support('title-tag');
    add_theme_support('post-thumbnails');
    add_theme_support('custom-logo', ['height'=>160,'width'=>260,'flex-height'=>true,'flex-width'=>true]);
    add_theme_support('html5', ['search-form','comment-form','comment-list','gallery','caption','style','script']);
    add_theme_support('responsive-embeds');
    add_theme_support('align-wide');
    register_nav_menus(['primary'=>'AG English Main Menu','primary_ar'=>'AG Arabic Main Menu']);
}
add_action('after_setup_theme', 'ag_setup');
function ag_assets() {
    wp_enqueue_style('ag-cairo-font','https://fonts.googleapis.com/css2?family=Cairo:wght@400;500;600;700&display=swap',[],null);
    wp_enqueue_style('ag-holdings',get_stylesheet_uri(),['ag-cairo-font'],wp_get_theme()->get('Version'));
    wp_enqueue_script('ag-holdings',get_template_directory_uri().'/assets/main.js',[],wp_get_theme()->get('Version'),true);
}
add_action('wp_enqueue_scripts','ag_assets');
function ag_url($slug) {
    if (ag_is_ar() && !str_ends_with($slug, '-ar')) { $slug .= '-ar'; }
    $page=ag_find_page(sanitize_title($slug));
    return $page && $page->post_status==='publish' ? get_permalink($page) : home_url('/'.sanitize_title($slug).'/');
}
function ag_resolve_content($content) {
    $content=str_replace('{{ASSET}}',esc_url(get_template_directory_uri().'/assets'),$content);
    return preg_replace_callback('/\{\{URL:([a-z0-9-]+)\}\}/', function($m){return esc_url(ag_url($m[1]));}, $content);
}
add_filter('the_content','ag_resolve_content',8);
function ag_home_content(){return ag_resolve_content(file_get_contents(get_template_directory().'/home.html'));}
class AG_Flat_Menu extends Walker_Nav_Menu {
    public function start_lvl(&$output,$depth=0,$args=null) {}
    public function end_lvl(&$output,$depth=0,$args=null) {}
    public function start_el(&$output,$data_object,$depth=0,$args=null,$current_object_id=0){
        if(in_array(strtolower(trim($data_object->title)),['media','المركز الإعلامي'],true))return;
        $group=get_page_by_path('group');
        if(($group && (int)$data_object->object_id===$group->ID) || in_array(strtolower($data_object->title),['our companies','شركاتنا'],true)){$output.=ag_company_dropdown();return;}
        $output.='<a href="'.esc_url($data_object->url).'">'.esc_html($data_object->title).'</a>';
    }
    public function end_el(&$output,$data_object,$depth=0,$args=null) {}
}
function ag_import_menu(){add_theme_page('AG Content Setup','AG Content Setup','manage_options','ag-content-setup','ag_import_screen');}
add_action('admin_menu','ag_import_menu');
function ag_import_screen(){
    if(!current_user_can('manage_options')) return;
    echo '<div class="wrap"><h1>AG Holdings — Automatic Content Setup</h1>';
    echo '<p>This theme automatically creates its published pages, review drafts, bilingual menus, and default homepage on activation (or on its first dashboard load after updating the ZIP). Existing page content and menu entries are never overwritten.</p>';
    if(isset($_POST['ag_import'])){
        check_admin_referer('ag_content_import');
        $report=ag_setup_site(true);
        update_option('ag_holdings_setup_notice_seen',1);
        if(!empty($report['failed'])){
            echo '<div class="notice notice-error"><p>Some pages could not be imported: '.esc_html(implode(', ',$report['failed'])).'</p></div>';
        }else{
            echo '<div class="notice notice-success"><p>Setup complete: '.(int)$report['created'].' new pages, '.(int)$report['existing'].' preserved. English and Arabic menus linked.</p></div>';
        }
    }
    $report=get_option('ag_holdings_setup_report',[]);
    if($report){
        echo '<p><strong>Last setup:</strong> '.(int)($report['created']??0).' pages created; '.(int)($report['existing']??0).' pages preserved.</p>';
        if(!empty($report['failed']))echo '<p><strong>Errors:</strong> '.esc_html(implode(', ',$report['failed'])).'</p>';
    }
    echo '<p>Both native WordPress menus are visible under Appearance → Menus. The website header preserves the theme\'s custom dropdown layout. Every link uses WordPress page permalinks. If your server still returns 404 after activation, visit Settings → Permalinks and click Save Changes once (the host must route requests to WordPress).</p>';
    echo '<form method="post">';wp_nonce_field('ag_content_import');
    echo '<p><button class="button button-primary" name="ag_import" value="1">Re-sync Missing Pages &amp; Menus</button></p></form></div>';
}
// Preserve old .html routes when the server sends these requests to WordPress.
function ag_legacy_redirect(){
    if(is_admin())return;
    $path=basename((string)wp_parse_url(isset($_SERVER['REQUEST_URI'])?wp_unslash($_SERVER['REQUEST_URI']):'',PHP_URL_PATH));
    $map=['index.html'=>'home','about.html'=>'about','group.html'=>'group','al-ghanem-real-estate.html'=>'al-ghanem-real-estate','al-ghanem-building-contracting.html'=>'al-ghanem-building-contracting','emirates-shipping.html'=>'emirates-navigation','master-general-trading.html'=>'manar-al-sabeel','contact-us.html'=>'contact-us','news.html'=>'news','gallery.html'=>'gallery','awards.html'=>'awards','T&C.html'=>'payment-terms','T%26C.html'=>'payment-terms'];
    if(isset($map[$path])){wp_safe_redirect($map[$path]==='home'?home_url('/'):ag_url($map[$path]),301);exit;}
}
add_action('template_redirect','ag_legacy_redirect');

function ag_is_ar(){
    $obj=get_queried_object();
    return $obj instanceof WP_Post && str_ends_with($obj->post_name,'-ar');
}
function ag_language_attributes($attrs){
    return ag_is_ar() ? 'lang="ar" dir="rtl"' : 'lang="en" dir="ltr"';
}
add_filter('language_attributes','ag_language_attributes');
function ag_other_language_url(){
    $obj=get_queried_object();
    $slug=$obj instanceof WP_Post ? $obj->post_name : 'home';
    $slug=ag_is_ar()?substr($slug,0,-3):$slug.'-ar';
    $page=ag_find_page($slug);
    if($page)return get_permalink($page);
    return home_url('/');
}
function ag_translate_output($output){
    if(!ag_is_ar())return $output;
    $dictionary=json_decode(file_get_contents(get_template_directory().'/translations-ar.json'),true);
    return preg_replace_callback('/>([^<>]+)</u',function($m)use($dictionary){
        $key=preg_replace('/\s+/u',' ',trim(html_entity_decode($m[1],ENT_QUOTES|ENT_HTML5,'UTF-8')));
        return isset($dictionary[$key]) ? '>'.esc_html($dictionary[$key]).'<' : $m[0];
    },$output);
}
function ag_start_language_output(){if(!is_admin() && ag_is_ar())ob_start('ag_translate_output');}
add_action('template_redirect','ag_start_language_output',20);

function ag_company_template($template){
    if(!is_page())return $template;
    $slug=preg_replace('/-ar$/','',get_post_field('post_name',get_queried_object_id()));
    if(in_array($slug,['al-ghanem-real-estate','al-ghanem-building-contracting','emirates-navigation','manar-al-sabeel'],true))return get_template_directory().'/page-company.php';
    return $template;
}
add_filter('template_include','ag_company_template');

function ag_company_dropdown(){
    $ar=ag_is_ar();
    $companies=[
      ['al-ghanem-real-estate','Al Ghanem Investment & Development','الغانم للاستثمار والتطوير'],
      ['al-ghanem-building-contracting','Al Ghanem Building Contracting','الغانم لمقاولات البناء'],
      ['emirates-navigation','EMIRATES SHIPPING','الإمارات للملاحة'],
      ['manar-al-sabeel','Manar Al Sabeel Quran Karim Keeping Centre','مركز منار السبيل لتحفيظ القرآن الكريم']
    ];
    $out='<div class="nav-company"><div class="nav-company-trigger"><a href="'.esc_url(ag_url('group')).'">'.($ar?'شركاتنا':'Our Companies').'</a><button class="companies-toggle" type="button" aria-expanded="false" aria-controls="companies-menu" aria-label="'.esc_attr($ar?'عرض شركات المجموعة':'Show group companies').'"><span aria-hidden="true">⌄</span></button></div><ul class="companies-dropdown" id="companies-menu">';
    foreach($companies as $company){$out.='<li><a href="'.esc_url(ag_url($company[0])).'">'.esc_html($company[$ar?2:1]).'</a></li>';}
    return $out.'</ul></div>';
}

function ag_about_template($template){
    if(is_page(['about','about-ar']))return get_template_directory().'/page-about.php';
    return $template;
}
add_filter('template_include','ag_about_template',20);

function ag_group_template($template){
    if(is_page(['group','group-ar']))return get_template_directory().'/page-group.php';
    return $template;
}
add_filter('template_include','ag_group_template',20);

require_once get_template_directory().'/navigation.php';
require_once get_template_directory().'/setup.php';

require_once get_template_directory().'/inc/forms.php';
require_once get_template_directory().'/inc/employees.php';
require_once get_template_directory().'/inc/seo.php';
require_once get_template_directory().'/inc/upgrade.php';
