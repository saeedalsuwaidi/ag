<?php
/* Template Name: AG Company Profile */
get_header();
$catalog=json_decode(file_get_contents(get_template_directory().'/company-banners.json'),true);
$slug=preg_replace('/-ar$/','',get_post_field('post_name',get_queried_object_id()));
$company=$catalog[$slug] ?? null;
$ar=ag_is_ar(); $lang=$ar?'ar':'en';
?>
<main id="main"><?php if($company): ?>
<section class="company-hero company-hero--<?php echo esc_attr($slug); ?>">
<img class="company-banner" src="<?php echo esc_url(get_template_directory_uri().'/assets/'.$company['image']); ?>" alt="<?php echo esc_attr($company[$lang]); ?>" width="2047" height="896" fetchpriority="high">
<div class="wrap company-hero-content"><a class="company-back" href="<?php echo esc_url(ag_url('group')); ?>"><?php echo $ar?'← شركاتنا':'← Our Companies'; ?></a><p class="eyebrow"><?php echo esc_html($company['sector_'.$lang]); ?></p><h1><?php echo esc_html(get_the_title()); ?></h1><span class="company-year"><?php echo esc_html(($ar?'منذ ':'Since ').$company['year']); ?></span></div></section>
<div class="wrap company-breadcrumbs"><?php echo ag_breadcrumbs($slug); ?></div><section class="company-overview"><div class="wrap company-overview-grid"><div class="company-overview-heading reveal"><p class="eyebrow"><?php echo $ar?'نظرة عامة':'Overview'; ?></p><h2><?php echo $ar?($slug==='manar-al-sabeel'?'عن المركز':'عن الشركة'):($slug==='manar-al-sabeel'?'About the centre':'About the company'); ?></h2><a class="rule-link" href="<?php echo esc_url(ag_url('contact-us')); ?>"><?php echo $ar?'تواصل معنا ↗':'Get in touch ↗'; ?></a></div><article class="company-overview-copy reveal"><?php while(have_posts()){the_post();the_content();} ?></article></div></section>
<?php else: ?><section class="inner-hero"><div class="wrap"><h1><?php the_title(); ?></h1></div></section><article class="article"><?php while(have_posts()){the_post();the_content();} ?></article><?php endif; ?></main><?php get_footer(); ?>
