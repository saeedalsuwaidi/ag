<?php
if (!defined('ABSPATH')) exit;
/* Public submissions are stored privately, never as public posts or media. */
add_action('init', function(){
 register_post_type('ag_submission', ['labels'=>['name'=>'طلبات الموقع / Website enquiries','singular_name'=>'Enquiry'], 'public'=>false,'publicly_queryable'=>false,'exclude_from_search'=>true,'show_in_rest'=>false,'show_ui'=>true,'show_in_menu'=>true,'supports'=>['title'],'capabilities'=>['edit_post'=>'manage_options','read_post'=>'manage_options','delete_post'=>'manage_options','edit_posts'=>'manage_options','edit_others_posts'=>'manage_options','publish_posts'=>'manage_options','read_private_posts'=>'manage_options','delete_posts'=>'manage_options','create_posts'=>'do_not_allow'],'map_meta_cap'=>false]);
});
function ag_submission_error($code,$message,$status=400){return new WP_Error($code,$message,['status'=>$status]);}
function ag_form_origin_ok(){
 $origin=get_http_origin();if(!$origin)return true;
 return strtolower(rtrim($origin,'/'))===strtolower(rtrim((string)preg_replace('~^(https?://[^/]+).*~','$1',home_url('/')),'/'));
}
function ag_form_token($time){return hash_hmac('sha256','ag-form:'.$time,wp_salt('nonce'));}
add_action('rest_api_init',function(){
 register_rest_route('ag/v1','/form-token',['methods'=>'GET','permission_callback'=>'__return_true','callback'=>function(){if(!ag_form_origin_ok())return ag_submission_error('origin','Request not allowed.',403);$t=time();$r=new WP_REST_Response(['time'=>$t,'token'=>ag_form_token($t)]);$r->header('Cache-Control','no-store');return $r;}]);
 register_rest_route('ag/v1','/enquiries',['methods'=>'POST','permission_callback'=>'ag_form_origin_ok','callback'=>'ag_receive_enquiry']);
});
function ag_receive_enquiry($request){
 $p=$request->get_params();$lang=($p['lang']??'en')==='ar'?'ar':'en';$ar=$lang==='ar';
 if(!empty($p['website']))return ag_submission_error('invalid',$ar?'تعذّر قبول الطلب.':'Unable to accept submission.');
 $time=absint($p['time']??0);$token=sanitize_text_field($p['token']??'');
 if(!$time||$time>time()+30||time()-$time>7200||!hash_equals(ag_form_token($time),$token))return ag_submission_error('token',$ar?'حدّث الصفحة وأعد المحاولة.':'Refresh the page and try again.',403);
 $ip=(string)($_SERVER['REMOTE_ADDR']??'unknown');$limit='ag_rate_'.hash_hmac('sha256',$ip,wp_salt());$hits=(int)get_transient($limit);
 if($hits>=5)return ag_submission_error('rate',$ar?'طلبات كثيرة. يرجى المحاولة بعد 15 دقيقة.':'Too many submissions. Please try again in 15 minutes.',429);
 $kind=sanitize_key($p['kind']??'');if(!in_array($kind,['career','internship','contact','partnership'],true))return ag_submission_error('kind','Invalid enquiry type.');
 $fields=[];
 foreach(['given-name','family-name','email','tel','company','sector','qualification','nationality','major','university','message'] as $key){
  $v=$p[$key]??'';if(!is_scalar($v))return ag_submission_error('field','Invalid field.');
  $fields[$key]=$key==='message'?sanitize_textarea_field($v):sanitize_text_field($v);
  if(strlen($fields[$key])>($key==='message'?12000:600))return ag_submission_error('length','Field too long.');
 }
 if(!$fields['given-name']||!is_email($fields['email'])||empty($p['consent'])||($p['consent']!=='1'))return ag_submission_error('required',$ar?'أكمل الاسم والبريد والموافقة.':'Complete your name, valid email and consent.');
 if(in_array($kind,['career','internship'],true)&&(!$fields['family-name']||!$fields['tel']||!$fields['qualification']))return ag_submission_error('required',$ar?'أكمل بيانات التقديم المطلوبة.':'Complete the required application fields.');
 if(in_array($kind,['contact','partnership'],true)&&!$fields['message'])return ag_submission_error('required',$ar?'يرجى كتابة رسالتك.':'Please enter your message.');
 $key=sanitize_text_field($p['submission_id']??'');if(!preg_match('/^[a-zA-Z0-9-]{20,80}$/',$key))return ag_submission_error('id','Invalid submission identifier.');
 $dedupe='ag_done_'.hash_hmac('sha256',$fields['email'].$key,wp_salt());$prior=get_transient($dedupe);
 if($prior)return new WP_REST_Response(['success'=>true,'reference'=>$prior,'message'=>$ar?'تم استلام طلبك مسبقاً.':'Your submission has already been received.']);
 $cv=null;$files=$request->get_file_params();
 if(isset($files['cv'])&&($files['cv']['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_NO_FILE){
  $f=$files['cv'];if(!in_array($kind,['career','internship'],true)||$f['error']!==UPLOAD_ERR_OK||$f['size']>5*1024*1024||$f['size']<5||!is_uploaded_file($f['tmp_name']))return ag_submission_error('file',$ar?'ارفع سيرة ذاتية PDF بحجم لا يتجاوز 5 ميغابايت.':'Upload a PDF CV no larger than 5 MB.');
  $bytes=file_get_contents($f['tmp_name']);$mime=(new finfo(FILEINFO_MIME_TYPE))->buffer($bytes);
  if(strtolower(pathinfo($f['name'],PATHINFO_EXTENSION))!=='pdf'||$mime!=='application/pdf'||substr($bytes,0,5)!=='%PDF-')return ag_submission_error('file',$ar?'يُقبل ملف PDF فقط.':'Only PDF files are accepted.');
  $cv=['name'=>sanitize_file_name($f['name']),'data'=>base64_encode($bytes)];
 }
 $id=wp_insert_post(['post_type'=>'ag_submission','post_status'=>'private','post_title'=>ucfirst($kind).' — '.$fields['given-name'].' '.$fields['family-name'],'post_content'=>''],true);
 if(is_wp_error($id)||!$id)return ag_submission_error('storage',$ar?'تعذّر حفظ الطلب. حاول مرة أخرى.':'Unable to save your enquiry. Please try again.',500);
 update_post_meta($id,'_ag_fields',$fields);update_post_meta($id,'_ag_kind',$kind);update_post_meta($id,'_ag_lang',$lang);update_post_meta($id,'_ag_consent_at',gmdate('c'));if($cv)update_post_meta($id,'_ag_cv',$cv);
 $ref='AG-'.gmdate('Y').'-'.$id;update_post_meta($id,'_ag_reference',$ref);set_transient($dedupe,$ref,DAY_IN_SECONDS);set_transient($limit,$hits+1,15*MINUTE_IN_SECONDS);
 $to=sanitize_email(get_option('ag_enquiry_email',get_option('admin_email')));if(!is_email($to))$to=get_option('admin_email');
 $sent=wp_mail($to,'New AG Holdings enquiry: '.$ref,"A new ".$kind." enquiry has been saved securely.\nReference: ".$ref."\nReview in WordPress: ".admin_url('post.php?post='.$id.'&action=edit'));
 update_post_meta($id,'_ag_notification',$sent?'accepted_by_mailer':'failed');
 $response=new WP_REST_Response(['success'=>true,'reference'=>$ref,'message'=>$ar?'تم استلام طلبك بنجاح. يرجى الاحتفاظ بالرقم المرجعي.':'Your enquiry has been received. Please keep your reference number.'],201);$response->header('Cache-Control','no-store');return $response;
}
add_action('add_meta_boxes',function(){add_meta_box('ag-enquiry-details','Enquiry details / تفاصيل الطلب',function($post){
 if(!current_user_can('manage_options'))return;$data=(array)get_post_meta($post->ID,'_ag_fields',true);echo '<table class="widefat">';foreach($data as $k=>$v)echo '<tr><th>'.esc_html($k).'</th><td>'.nl2br(esc_html($v)).'</td></tr>';echo '</table><p>Reference: '.esc_html(get_post_meta($post->ID,'_ag_reference',true)).'</p><p>Notification: '.esc_html(get_post_meta($post->ID,'_ag_notification',true)).'</p>';
 if(get_post_meta($post->ID,'_ag_cv',true))echo '<a class="button" href="'.esc_url(wp_nonce_url(admin_url('admin-post.php?action=ag_download_cv&id='.$post->ID),'ag_cv_'.$post->ID)).'">Download private PDF CV</a>';
},'ag_submission','normal','high');});
add_action('admin_post_ag_download_cv',function(){
 $id=absint($_GET['id']??0);if(!current_user_can('manage_options')||get_post_type($id)!=='ag_submission')wp_die('Access denied.',403);check_admin_referer('ag_cv_'.$id);$cv=get_post_meta($id,'_ag_cv',true);if(!$cv)wp_die('File not found.',404);nocache_headers();header('X-Content-Type-Options: nosniff');header('Content-Type: application/pdf');header('Content-Security-Policy: sandbox');header('Content-Disposition: attachment; filename="cv-'.$id.'.pdf"');echo base64_decode($cv['data']);exit;
});
add_action('wp_enqueue_scripts',function(){
 wp_enqueue_script('ag-forms',get_template_directory_uri().'/assets/forms.js',[],wp_get_theme()->get('Version'),true);
 wp_localize_script('ag-forms','AGForms',['tokenUrl'=>rest_url('ag/v1/form-token'),'submitUrl'=>rest_url('ag/v1/enquiries')]);
});
function ag_enquiry_form($atts=[]){
 $atts=shortcode_atts(['kind'=>'contact','lang'=>ag_is_ar()?'ar':'en'],$atts);$kind=in_array($atts['kind'],['career','internship','partnership','contact'],true)?$atts['kind']:'contact';$ar=$atts['lang']==='ar';$career=in_array($kind,['career','internship'],true);
 ob_start();?><form class="ag-direct-form" data-kind="<?php echo esc_attr($kind); ?>" data-lang="<?php echo $ar?'ar':'en'; ?>" enctype="multipart/form-data" method="post"><div class="application-grid">
 <?php $labels=['given-name'=>[$ar?'الاسم الأول':'First name','text',true],'family-name'=>[$ar?'اسم العائلة':'Last name','text',$career],'email'=>[$ar?'البريد الإلكتروني':'Email','email',true],'tel'=>[$ar?'رقم الهاتف':'Phone','tel',$career]];
 if($career){$labels['qualification']=[$ar?'المؤهل العلمي':'Qualification','text',true];$labels['major']=[$ar?'التخصص':'Specialism','text',false];}else{$labels['company']=[$ar?'الشركة':'Company','text',false];$labels['sector']=[$ar?'القطاع أو مجال التعاون':'Sector / area of collaboration','text',false];}
 foreach($labels as $name=>$field):?><label><?php echo esc_html($field[0]).($field[2]?' *':''); ?><input name="<?php echo esc_attr($name); ?>" type="<?php echo $field[1]; ?>" maxlength="180" <?php echo $field[2]?'required':''; ?>></label><?php endforeach; ?>
 <label class="application-wide"><?php echo $ar?'الرسالة':'Message'; ?><textarea name="message" rows="5" maxlength="2500" <?php echo !$career?'required':''; ?>></textarea></label>
 <?php if($career):?><label class="application-wide"><?php echo $ar?'السيرة الذاتية — PDF فقط، بحد أقصى 5 ميغابايت (اختياري)':'CV — PDF only, up to 5 MB (optional)'; ?><input type="file" name="cv" accept="application/pdf,.pdf"></label><?php endif; ?></div>
 <div class="ag-honeypot" aria-hidden="true"><label>Website<input name="website" type="text" tabindex="-1" autocomplete="off"></label></div>
 <label class="application-consent"><input type="checkbox" name="consent" value="1" required><span><?php echo $ar?'أوافق على معالجة بياناتي ومرفقاتي لغرض متابعة هذا الطلب.':'I consent to processing my details and attachments to respond to this enquiry.'; ?> <a href="<?php echo esc_url(ag_url('privacy')); ?>"><?php echo $ar?'الخصوصية':'Privacy'; ?></a></span></label>
 <button class="button" type="submit"><?php echo $ar?'إرسال الطلب':'Submit enquiry'; ?></button><p class="application-status" role="status" aria-live="polite"></p><noscript><?php echo $ar?'يرجى تفعيل JavaScript لإرسال النموذج من هذه الصفحة.':'Enable JavaScript to submit this form on this page.'; ?></noscript></form><?php return ob_get_clean();
}
add_shortcode('ag_enquiry','ag_enquiry_form');
