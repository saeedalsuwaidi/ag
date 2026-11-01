<?php
if(!defined('ABSPATH'))exit;
/* Repair malformed legacy href values left by KSES interpreting {{URL:...}} as a scheme. */
function ag_fix_content_links($html){
 $html=preg_replace_callback('~(href=["\'])(?:\{\{URL:)?([a-z0-9-]+)\}\}(["\'])~i',function($m){return $m[1].esc_url(ag_url($m[2])).$m[3];},$html);
 return preg_replace_callback('~(href=["\'])(?:https?://ag\.ae/)?(?:\./)?([a-z0-9-]+)\.html(["\'])~i',function($m){$slug=$m[2]==='index'?'home':($m[2]==='index-ar'?'home-ar':$m[2]);return $m[1].esc_url(ag_url($slug)).$m[3];},$html);
}
add_filter('the_content','ag_fix_content_links',9);
remove_filter('the_content','wpautop');
add_filter('the_content',function($content){return strpos($content,'<section')!==false||strpos($content,'interior-cards')!==false?$content:wpautop($content);},10);
function ag_safe_seed_content($html){
 /* Preserve route tokens while KSES filters the rest of the markup. */
 $html=preg_replace('/\{\{URL:([a-z0-9-]+)\}\}/','https://ag-route.invalid/$1',$html);
 $html=wp_kses_post($html);
 return preg_replace('~https://ag-route\.invalid/([a-z0-9-]+)~','{{URL:$1}}',$html);
}
function ag_upgrade_v3(){
 if(!current_user_can('manage_options')||get_option('ag_upgrade_version')==='3.0.2')return;
 $old=json_decode(file_get_contents(get_template_directory().'/content-v23.json'),true);$new=ag_content_manifest();if(is_wp_error($new))return;
 $previous=json_decode(file_get_contents(get_template_directory().'/content-v30.json'),true);$previousmap=[];foreach($previous as $entry)$previousmap[$entry['slug']]=$entry['content'];
 $v301=json_decode(file_get_contents(get_template_directory().'/content-v301.json'),true);$map301=[];foreach($v301 as $entry)$map301[$entry['slug']]=$entry['content'];
 $oldmap=[];foreach($old as $x)$oldmap[$x['slug']]=$x['content'];$conflicts=[];$updated=[];
 foreach($new as $x){$p=ag_find_page($x['slug']);if(!$p||!isset($oldmap[$x['slug']])||$oldmap[$x['slug']]===$x['content'])continue;
  $existing=preg_replace('/\s+/u','',trim($p->post_content));$baseline=preg_replace('/\s+/u','',trim(wp_kses_post($oldmap[$x['slug']])));
  $baselineRaw=preg_replace('/\s+/u','',trim($oldmap[$x['slug']]));
  $prior=isset($previousmap[$x['slug']])?preg_replace('/\s+/u','',trim(ag_safe_seed_content($previousmap[$x['slug']]))):null;
  $prior301=isset($map301[$x['slug']])?preg_replace('/\s+/u','',trim(ag_safe_seed_content($map301[$x['slug']]))):null;
  if($existing===$baseline||$existing===$baselineRaw||$existing===$prior||$existing===$prior301){wp_save_post_revision($p->ID);wp_update_post(['ID'=>$p->ID,'post_content'=>wp_slash(ag_safe_seed_content($x['content']))]);$updated[]=$x['slug'];}
  else $conflicts[]=$x['slug'];
 }
 update_option('ag_upgrade_conflicts',$conflicts,false);update_option('ag_upgrade_updated',$updated,false);update_option('ag_upgrade_version','3.0.2');flush_rewrite_rules(false);
}
add_action('admin_init','ag_upgrade_v3',30);
add_action('admin_menu',function(){add_theme_page('AG Website Operations','AG إدارة الموقع','manage_options','ag-operations','ag_operations_screen');});
add_action('admin_init',function(){register_setting('ag_operations','ag_enquiry_email',['sanitize_callback'=>'sanitize_email','default'=>get_option('admin_email')]);});
function ag_operations_screen(){
 if(!current_user_can('manage_options'))return;
 if(isset($_POST['ag_apply_selected'])){check_admin_referer('ag_apply_content');$allowed=(array)get_option('ag_upgrade_conflicts',[]);$chosen=array_map('sanitize_title',(array)($_POST['ag_pages']??[]));$data=ag_content_manifest();foreach($data as $x){if(in_array($x['slug'],$chosen,true)&&in_array($x['slug'],$allowed,true)){$p=ag_find_page($x['slug']);if($p){wp_save_post_revision($p->ID);wp_update_post(['ID'=>$p->ID,'post_content'=>wp_slash(ag_safe_seed_content($x['content']))]);$allowed=array_values(array_diff($allowed,[$x['slug']]));}}}update_option('ag_upgrade_conflicts',$allowed);echo '<div class="notice notice-success"><p>Selected pages updated with revisions preserved.</p></div>';}
 echo '<div class="wrap"><h1>إدارة موقع المجموعة / Website Operations</h1><h2>استقبال الطلبات / Enquiries</h2><p>All submitted enquiries are stored privately under Website enquiries, including PDF CVs. Email is a notification only; configure and test SMTP through your host. A mailer acceptance is not proof of delivery.</p><form method="post" action="options.php">';settings_fields('ag_operations');echo '<label>Notification email / بريد التنبيهات <input type="email" class="regular-text" name="ag_enquiry_email" value="'.esc_attr(get_option('ag_enquiry_email',get_option('admin_email'))).'"></label>';submit_button();echo '</form><h2>سياسات الموظفين / Employee policies</h2><p>Create each employee through Users → Add New with the Group employee role. Publish approved Arabic/English policy text under Staff policies. Removing that role immediately removes policy access. Do not enable public registration for this role. Exclude employee-portal and employee-portal-ar from all host/CDN caching.</p><h2>روابط الموقع / Page readiness</h2><table class="widefat"><tr><th>Page</th><th>Status</th><th>Open</th></tr>';
 foreach(ag_content_manifest() as $x){if($x['status']!=='publish')continue;$p=ag_find_page($x['slug']);echo '<tr><td>'.esc_html($x['title']).'</td><td>'.esc_html($p?$p->post_status:'MISSING').'</td><td>'.($p?'<a href="'.esc_url(get_permalink($p)).'" target="_blank" rel="noopener">Open</a>':'Run AG Content Setup').'</td></tr>';}echo '</table>';
 $conflicts=(array)get_option('ag_upgrade_conflicts',[]);if($conflicts){echo '<h2>Content review / مراجعة المحتوى المعدّل</h2><p>These pages were edited since the previous theme. Select pages only if you want to apply the new supplied copy. WordPress revisions are saved first.</p><form method="post">';wp_nonce_field('ag_apply_content');foreach($conflicts as $slug)echo '<p><label><input type="checkbox" name="ag_pages[]" value="'.esc_attr($slug).'"> '.esc_html($slug).'</label></p>';submit_button('Apply selected updated content','secondary','ag_apply_selected');echo '</form>';}
 echo '<h2>SEO & sharing</h2><p>Settings → Reading: disable “Discourage search engines” only for your production domain. Sitemap: <a href="'.esc_url(home_url('/wp-sitemap.xml')).'">wp-sitemap.xml</a>. Add your domain to Google Search Console and Bing Webmaster Tools. Search positions and social preview refresh times are controlled by those platforms.</p></div>';
}
