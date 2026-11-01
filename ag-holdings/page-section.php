<?php
/* Template Name: AG Section Page */
get_header();$slug=ag_current_slug();$section=ag_section_data()[$slug];$ar=ag_is_ar();
?>
<main id="main"><section class="inner-hero page-banner section-hero"><img class="page-banner-image" src="<?php echo esc_url(get_template_directory_uri().'/assets/'.$section['banner']); ?>" alt="" fetchpriority="high"><div class="wrap"><p class="eyebrow"><?php echo $ar?'من الإمارات إلى العالم':'From the Emirates to the world'; ?></p><h1><?php the_title(); ?></h1></div></section><div class="wrap"><?php echo ag_breadcrumbs($slug); ?></div><div class="wrap interior-shell"><?php echo ag_section_navigation($slug); ?><div class="interior-content"><?php while(have_posts()){the_post();the_content();} ?></div></div></main>
<?php get_footer(); ?>
