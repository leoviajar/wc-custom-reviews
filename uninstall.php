<?php
/**
 * Arquivo de desinstalação do plugin WC Custom Reviews
 * Este arquivo é executado quando o plugin é desinstalado
 */

// Se a desinstalação não foi chamada pelo WordPress, saia
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// Remove opções do plugin
delete_option('wc_custom_reviews_options');
delete_option('wc_custom_reviews_db_version');

// Remove tabela do banco de dados
global $wpdb;
$table_name = $wpdb->prefix . 'wc_custom_reviews';
$wpdb->query("DROP TABLE IF EXISTS {$table_name}");

// Limpa cache
wp_cache_flush();

