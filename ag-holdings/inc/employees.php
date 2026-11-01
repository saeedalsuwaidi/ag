<?php
if(!defined('ABSPATH'))exit;
function ag_employee_allowed(){return is_user_logged_in()&&(current_user_can('ag_read_policies')||current_user_can('manage_options'));}
add_action('init',function(){
 add_role('ag_employee','موظف المجموعة / Group employee',['read'=>true,'ag_read_policies'=>true]);
 register_post_type('ag_policy',['labels'=>['name'=>'سياسات الموظفين / Staff policies','singular_name'=>'Policy'],'public'=>false,'publicly_queryable'=>false,'exclude_from_search'=>true,'show_in_rest'=>false,'show_ui'=>true,'supports'=>['title','editor','revisions'],'capability_type'=>'page','capabilities'=>['edit_post'=>'manage_options','read_post'=>'manage_options','delete_post'=>'manage_options','edit_posts'=>'manage_options','edit_others_posts'=>'manage_options','publish_posts'=>'manage_options','read_private_posts'=>'manage_options','delete_posts'=>'manage_options','create_posts'=>'manage_options'],'map_meta_cap'=>false]);
});
function ag_is_portal(){return in_array(ag_current_slug(),['employee-portal','work-policies'],true);}
add_action('template_redirect',function(){if(ag_is_portal()){
 if(!defined('DONOTCACHEPAGE'))define('DONOTCACHEPAGE',true);
 nocache_headers();header('X-Robots-Tag: noindex, nofollow, noarchive');
 if(!ag_employee_allowed()&&is_user_logged_in())status_header(403);
}},1);
add_filter('wp_robots',function($robots){if(ag_is_portal()){$robots['noindex']=true;$robots['nofollow']=true;unset($robots['index']);}return $robots;});
function ag_employee_portal(){
 $ar=ag_is_ar();if(!is_user_logged_in())return '<section class="employee-gate"><h2>'.($ar?'دخول الموظفين':'Employee sign-in').'</h2><p>'.($ar?'هذه المساحة مخصصة للموظفين المعتمدين للاطلاع على سياسات العمل. يرجى استخدام حسابك الصادر عن إدارة المجموعة.':'This area is reserved for approved employees to access workplace policies. Use the account issued by Group administration.').'</p><a class="button" href="'.esc_url(wp_login_url(get_permalink())).'">'.($ar?'تسجيل الدخول الآمن':'Secure sign-in').'</a></section>';
 if(!ag_employee_allowed())return '<p>'.($ar?'حسابك غير مخوّل للاطلاع على السياسات. يرجى التواصل مع الإدارة لتفعيل صلاحية الموظف.':'Your account is not authorised to view staff policies. Please contact administration to request employee access.').'</p>';
 $out='<section class="employee-policies"><p>'.($ar?'مرحباً، ':'Welcome, ').esc_html(wp_get_current_user()->display_name).'</p><a class="rule-link" href="'.esc_url(wp_logout_url(home_url('/'))).'">'.($ar?'تسجيل الخروج':'Sign out').'</a>';
 $items=get_posts(['post_type'=>'ag_policy','post_status'=>'publish','numberposts'=>-1,'orderby'=>'title','order'=>'ASC','meta_query'=>[['key'=>'_ag_policy_language','value'=>[$ar?'ar':'en','both'],'compare'=>'IN']]]);
 if(!$items)$out.='<p>'.($ar?'لا توجد سياسات معتمدة منشورة بهذه اللغة حالياً.':'No approved policies have been published in this language yet.').'</p>';
 foreach($items as $p)$out.='<article class="employee-policy"><h2>'.esc_html($p->post_title).'</h2><p class="eyebrow">'.($ar?'آخر تحديث: ':'Updated: ').esc_html(get_the_modified_date('Y-m-d',$p)).'</p>'.wpautop(wp_kses_post($p->post_content)).'</article>';
 return $out.'</section>';
}
add_shortcode('ag_employee_portal','ag_employee_portal');
add_action('add_meta_boxes',function(){add_meta_box('ag-policy-language','Policy language / لغة السياسة',function($post){$v=get_post_meta($post->ID,'_ag_policy_language',true)?:'both';wp_nonce_field('ag_policy_meta','ag_policy_nonce');echo '<select name="ag_policy_language">';foreach(['both'=>'العربية والإنجليزية / Both','ar'=>'العربية','en'=>'English'] as $key=>$label)echo '<option value="'.esc_attr($key).'" '.selected($v,$key,false).'>'.esc_html($label).'</option>';echo '</select><p>انشر نص السياسة المعتمد داخل المحرر. لا ترفع مستنداً سرياً إلى مكتبة الوسائط العامة.</p><p>Publish approved policy text in the editor. Do not upload confidential documents to the public Media Library.</p>';},'ag_policy','side');});
add_action('save_post_ag_policy',function($id){if(wp_is_post_revision($id)||!current_user_can('manage_options')||empty($_POST['ag_policy_nonce'])||!wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['ag_policy_nonce'])),'ag_policy_meta'))return;$v=sanitize_key($_POST['ag_policy_language']??'both');update_post_meta($id,'_ag_policy_language',in_array($v,['ar','en','both'],true)?$v:'both');});
/* Guard even if a policy is accidentally rendered outside its intended template. */
add_filter('the_content',function($content){if(get_post_type()==='ag_policy'&&!is_admin()&&!ag_employee_allowed())return '';return $content;},1);

/* Never serialize private portal output through the public pages API. */
add_filter('rest_prepare_page',function($response,$post,$request){
 if(in_array(preg_replace('/-ar$/','',$post->post_name),['employee-portal','work-policies'],true)){
  $data=$response->get_data(); if(isset($data['content']))$data['content']['rendered']='';
  $response->set_data($data);$response->header('Cache-Control','private, no-store, max-age=0');$response->header('X-Robots-Tag','noindex, nofollow');
 }return $response;
},10,3);
