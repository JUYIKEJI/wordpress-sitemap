<?php
/**
 * Plugin Name: 阿彦网站地图
 * Plugin URI: https://github.com/JUYIKEJI/wordpress-sitemap
 * Description: 给 WordPress 博客生成网站地图。启用后，打开 网站地址/sitemap.xml 就能拿到整站地图，方便搜索引擎抓取和收录。只适用于 WordPress，不适用于 Typecho。
 * Version: 1.0.0
 * Author: 阿彦能行
 * License: GPLv2 or later
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// 注册 sitemap.xml 的访问规则。
function ayan_sitemap_add_rewrite_rule() {
    add_rewrite_rule( 'sitemap\.xml$', 'index.php?ayan_sitemap=1', 'top' );
}
add_action( 'init', 'ayan_sitemap_add_rewrite_rule' );

function ayan_sitemap_add_query_var( $vars ) {
    $vars[] = 'ayan_sitemap';
    return $vars;
}
add_filter( 'query_vars', 'ayan_sitemap_add_query_var' );

// 启用时刷新一次访问规则，停用时也刷新一次。
function ayan_sitemap_activate() {
    ayan_sitemap_add_rewrite_rule();
    flush_rewrite_rules();
}
register_activation_hook( __FILE__, 'ayan_sitemap_activate' );

function ayan_sitemap_deactivate() {
    flush_rewrite_rules();
}
register_deactivation_hook( __FILE__, 'ayan_sitemap_deactivate' );

// 输出一条地图链接：跳过空链接、去重、转义。
function ayan_sitemap_output_url( $url, $lastmod, $changefreq, $priority, &$seen ) {
    if ( empty( $url ) ) {
        return;
    }
    if ( isset( $seen[ $url ] ) ) {
        return;
    }
    $seen[ $url ] = true;

    echo "  <url>\n";
    echo '    <loc>' . htmlspecialchars( $url, ENT_XML1, 'UTF-8' ) . "</loc>\n";
    if ( ! empty( $lastmod ) ) {
        echo '    <lastmod>' . htmlspecialchars( $lastmod, ENT_XML1, 'UTF-8' ) . "</lastmod>\n";
    }
    echo '    <changefreq>' . $changefreq . "</changefreq>\n";
    echo '    <priority>' . $priority . "</priority>\n";
    echo "  </url>\n";
}

// 访问 /sitemap.xml 时，生成地图文件并返回。
function ayan_sitemap_template_redirect() {
    if ( '1' !== get_query_var( 'ayan_sitemap' ) ) {
        return;
    }

    header( 'Content-Type: application/xml; charset=UTF-8' );
    echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
    echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

    $seen = array();

    // 首页：权重最高，每天更新，最后修改时间取最新一篇文章的修改时间。
    $latest = get_lastpostmodified( 'blog' );
    $home_lastmod = $latest ? gmdate( 'Y-m-d', strtotime( $latest ) ) : gmdate( 'Y-m-d' );
    ayan_sitemap_output_url( home_url( '/' ), $home_lastmod, 'daily', '1.0', $seen );

    // 已发布页面：每周更新。
    $pages = get_pages( array( 'post_status' => 'publish' ) );
    foreach ( $pages as $page ) {
        $url = get_permalink( $page->ID );
        $lastmod = get_post_modified_time( 'Y-m-d', false, $page->ID );
        ayan_sitemap_output_url( $url, $lastmod, 'weekly', '0.8', $seen );
    }

    // 已发布文章：每月更新，只取已发布的，分批取完，不设篇数上限。
    $paged = 1;
    do {
        $posts = get_posts( array(
            'post_type'      => 'post',
            'post_status'    => 'publish',
            'posts_per_page' => 500,
            'paged'          => $paged,
        ) );
        foreach ( $posts as $post ) {
            $url = get_permalink( $post->ID );
            $lastmod = get_post_modified_time( 'Y-m-d', false, $post->ID );
            ayan_sitemap_output_url( $url, $lastmod, 'monthly', '0.5', $seen );
        }
        $paged++;
    } while ( count( $posts ) === 500 );

    echo "</urlset>\n";
    exit;
}
add_action( 'template_redirect', 'ayan_sitemap_template_redirect' );
