<?php
/**
 * One-time, repeat-safe content and navigation installation for AG Holdings.
 * Runs on theme activation and on the first dashboard load after a theme ZIP update.
 * Never overwrites the body of an existing WordPress page or an existing menu item.
 */
if (!defined('ABSPATH')) { exit; }

if (!defined('AG_HOLDINGS_SETUP_VERSION')) {
    define('AG_HOLDINGS_SETUP_VERSION', '3.0.2');
}

function ag_content_manifest() {
    $file = get_template_directory() . '/content.json';
    if (!is_readable($file)) { return new WP_Error('ag_manifest_missing', 'AG content.json is missing.'); }
    $data = json_decode(file_get_contents($file), true);
    if (!is_array($data) || json_last_error() !== JSON_ERROR_NONE) {
        return new WP_Error('ag_manifest_invalid', 'AG content.json is invalid.');
    }
    return $data;
}

function ag_seed_pages() {
    $pages = ag_content_manifest();
    $result = ['created' => 0, 'existing' => 0, 'failed' => []];
    if (is_wp_error($pages)) {
        $result['failed'][] = $pages->get_error_message();
        return $result;
    }
    foreach ($pages as $entry) {
        if (empty($entry['slug']) || empty($entry['title']) || !isset($entry['content'])) { continue; }
        $slug = sanitize_title($entry['slug']);
        $existing = ag_find_page($slug);
        if ($existing) {
            $result['existing']++;
            // Only repair the original theme's own Arabic homepage template.
            if ($slug === 'home-ar' && get_post_meta($existing->ID, '_ag_seeded', true) === '1') {
                update_post_meta($existing->ID, '_wp_page_template', 'page-home-ar.php');
            }
            continue;
        }
        $id = wp_insert_post([
            'post_type'    => 'page',
            'post_name'    => $slug,
            'post_title'   => sanitize_text_field($entry['title']),
            'post_content' => wp_slash(ag_safe_seed_content($entry['content'])),
            'post_status'  => ($entry['status'] ?? 'draft') === 'publish' ? 'publish' : 'draft',
        ], true);
        if (is_wp_error($id) || !$id) {
            $result['failed'][] = $slug . (is_wp_error($id) ? ': ' . $id->get_error_message() : '');
            continue;
        }
        update_post_meta($id, '_ag_seeded', '1');
        update_post_meta($id, '_ag_seed_slug', $slug);
        if ($slug === 'home-ar') { update_post_meta($id, '_wp_page_template', 'page-home-ar.php'); }
        $result['created']++;
    }
    return $result;
}

/** Find/create one menu item, but preserve any item an editor has already customized. */
function ag_seed_menu_page($menu_id, $slug, $label, $parent_id, &$existing_items) {
    $page = ag_find_page($slug);
    if (!$page || $page->post_status !== 'publish') { return 0; }
    $id = (int) $page->ID;
    if (isset($existing_items[$id])) { return $existing_items[$id]; }
    $menu_item_id = wp_update_nav_menu_item($menu_id, 0, [
        'menu-item-object-id' => $id,
        'menu-item-object'    => 'page',
        'menu-item-type'      => 'post_type',
        'menu-item-status'    => 'publish',
        'menu-item-title'     => $label,
        'menu-item-parent-id' => (int) $parent_id,
    ]);
    if (is_wp_error($menu_item_id) || !$menu_item_id) { return 0; }
    $existing_items[$id] = (int) $menu_item_id;
    return (int) $menu_item_id;
}

function ag_seed_menu($lang) {
    $is_ar = $lang === 'ar';
    $name = $is_ar ? 'AG Main Menu — Arabic' : 'AG Main Menu — English';
    $menu = wp_get_nav_menu_object($name);
    if (!$menu) {
        $menu_id = wp_create_nav_menu($name);
        if (is_wp_error($menu_id)) { return 0; }
    } else {
        $menu_id = (int) $menu->term_id;
    }

    $existing_items = [];
    $menu_items = wp_get_nav_menu_items($menu_id, ['post_status' => 'publish']);
    if (is_array($menu_items)) {
        foreach ($menu_items as $item) {
            if ($item->type === 'post_type' && $item->object === 'page') {
                $existing_items[(int) $item->object_id] = (int) $item->ID;
            }
        }
    }
    $suffix = $is_ar ? '-ar' : '';
    $data = ag_navigation_data();
    ag_seed_menu_page($menu_id, 'home' . $suffix, $is_ar ? 'الرئيسية' : 'Home', 0, $existing_items);
    foreach ($data['groups'] as $group) {
        // 'Our News' was intentionally removed from the public header.
        if ($group['slug'] === 'news') { continue; }
        $top = ag_seed_menu_page($menu_id, $group['slug'] . $suffix, $group['label'][$lang], 0, $existing_items);
        foreach ($group['children'] as $child) {
            if ($child[0] === $group['slug']) { continue; }
            ag_seed_menu_page($menu_id, $child[0] . $suffix, $child[$is_ar ? 2 : 1], $top, $existing_items);
        }
        if ($group['slug'] === 'group') {
            foreach ($data['companies'] as $company) {
                ag_seed_menu_page($menu_id, $company[0] . $suffix, $company[$is_ar ? 2 : 1], $top, $existing_items);
            }
        }
    }
    ag_seed_menu_page($menu_id, 'contact-us' . $suffix, $is_ar ? 'الاتصال' : 'Contact', 0, $existing_items);
    return $menu_id;
}

function ag_seed_navigation() {
    $english_id = ag_seed_menu('en');
    $arabic_id  = ag_seed_menu('ar');
    $locations = (array) get_theme_mod('nav_menu_locations', []);
    if ($english_id && empty($locations['primary'])) { $locations['primary'] = $english_id; }
    if ($arabic_id  && empty($locations['primary_ar'])) { $locations['primary_ar'] = $arabic_id; }
    set_theme_mod('nav_menu_locations', $locations);
    return ['english' => $english_id, 'arabic' => $arabic_id];
}

/** Safe to run multiple times; never replace an already authored page or menu item. */
function ag_setup_site($force = false) {
    if (!$force && get_option('ag_holdings_setup_version') === AG_HOLDINGS_SETUP_VERSION) {
        return get_option('ag_holdings_setup_report', []);
    }
    $result = ag_seed_pages();
    if (!$result['failed']) {
        ag_apply_page_hierarchy();
        $result['menus'] = ag_seed_navigation();
        if (!get_option('ag_holdings_homepage_initialized')) {
            $home = ag_find_page('home');
            if ($home && $home->post_status === 'publish') {
                update_option('show_on_front', 'page');
                update_option('page_on_front', (int) $home->ID);
                update_option('ag_holdings_homepage_initialized', 1);
            }
        }
        // The core theme-switch flow also performs a hard rewrite refresh.
        // A soft refresh covers in-place ZIP upgrades without rewriting .htaccess.
        if ($result['created']) { flush_rewrite_rules(false); }
        update_option('ag_holdings_setup_version', AG_HOLDINGS_SETUP_VERSION);
    }
    update_option('ag_holdings_setup_report', $result, false);
    return $result;
}

function ag_auto_setup_on_activation() { ag_setup_site(); }
add_action('after_switch_theme', 'ag_auto_setup_on_activation', 25);

function ag_auto_setup_on_upgrade() {
    if (current_user_can('manage_options') && get_option('ag_holdings_setup_version') !== AG_HOLDINGS_SETUP_VERSION) {
        ag_setup_site();
    }
}
add_action('admin_init', 'ag_auto_setup_on_upgrade', 20);

function ag_setup_dashboard_notice() {
    if (!current_user_can('manage_options') || get_option('ag_holdings_setup_notice_seen')) { return; }
    $report = get_option('ag_holdings_setup_report', []);
    if (!$report) { return; }
    $failed = !empty($report['failed']);
    echo '<div class="notice ' . ($failed ? 'notice-error' : 'notice-success') . ' is-dismissible"><p>';
    if ($failed) {
        echo 'AG Holdings setup needs attention: ' . esc_html(implode(', ', $report['failed'])) . '. ';
    } else {
        echo 'AG Holdings setup complete: ' . (int) $report['created'] . ' pages created; ' . (int) $report['existing'] . ' existing pages preserved. English and Arabic menus are available. ';
    }
    echo '<a href="' . esc_url(admin_url('themes.php?page=ag-content-setup')) . '">Review AG Content Setup</a></p></div>';
    update_option('ag_holdings_setup_notice_seen', 1);
}
add_action('admin_notices', 'ag_setup_dashboard_notice');
