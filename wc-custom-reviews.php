<?php
/**
 * Plugin Name: WC Custom Reviews
 * Description: Plugin personalizado de reviews para WooCommerce com shortcodes e interface administrativa
 * Version: 1.0.0
 * Author: Leonardo
 * Text Domain: wc-custom-reviews
 * Domain Path: /languages
 * Requires at least: 5.0
 * Tested up to: 6.4
 * Requires PHP: 7.4
 * WC requires at least: 6.0
 * WC tested up to: 8.5
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

require 'plugin-update-checker/plugin-update-checker.php'; 
use YahnisElsts\PluginUpdateChecker\v5\PucFactory; 

$myUpdateChecker = PucFactory::buildUpdateChecker(
    'https://github.com/leoviajar/wc-custom-reviews',
    __FILE__,
    'wc-custom-reviews.php'
);

// Previne acesso direto
if (!defined('ABSPATH')) {
    exit;
}

// Define constantes do plugin
define('WC_CUSTOM_REVIEWS_VERSION', '1.0.0');
define('WC_CUSTOM_REVIEWS_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('WC_CUSTOM_REVIEWS_PLUGIN_URL', plugin_dir_url(__FILE__));
define('WC_CUSTOM_REVIEWS_PLUGIN_FILE', __FILE__);

// Carrega dependência necessária para ativação
require_once WC_CUSTOM_REVIEWS_PLUGIN_DIR . 'includes/class-wc-custom-reviews-database.php';

/**
 * Classe principal do plugin
 */
class WC_Custom_Reviews {

    /**
     * Instância única da classe
     */
    private static $instance = null;

    /**
     * Obtém a instância única da classe
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Construtor privado para implementar Singleton
     */
    private function __construct() {
        $this->init_hooks();
    }

    /**
     * Inicializa os hooks do WordPress
     */
    private function init_hooks() {
        add_action('plugins_loaded', array($this, 'init'));
    }

    /**
     * Inicializa o plugin
     */
    public function init() {
        // Verifica se o WooCommerce está ativo
        if (!class_exists('WooCommerce')) {
            if (is_admin()) {
                add_action('admin_notices', array($this, 'woocommerce_missing_notice'));
            }
            return;
        }

        // Carrega arquivos necessários
        $this->load_dependencies();

        // Inicializa componentes
        $this->init_components();

        // Carrega textdomain
        load_plugin_textdomain(
            'wc-custom-reviews',
            false,
            dirname(plugin_basename(__FILE__)) . '/languages'
        );
    }

    /**
     * Carrega dependências do plugin
     */
    private function load_dependencies() {
        require_once WC_CUSTOM_REVIEWS_PLUGIN_DIR . 'includes/class-wc-custom-reviews-admin.php';
        require_once WC_CUSTOM_REVIEWS_PLUGIN_DIR . 'includes/class-wc-custom-reviews-shortcodes.php';
        require_once WC_CUSTOM_REVIEWS_PLUGIN_DIR . 'includes/class-wc-custom-reviews-frontend.php';
    }

    /**
     * Inicializa componentes do plugin
     */
    private function init_components() {
        // Inicializa banco de dados
        WC_Custom_Reviews_Database::get_instance();

        // Inicializa admin apenas no backend
        if (is_admin()) {
            WC_Custom_Reviews_Admin::get_instance();
        }

        // Inicializa shortcodes
        WC_Custom_Reviews_Shortcodes::get_instance();

        // Inicializa frontend
        WC_Custom_Reviews_Frontend::get_instance();
    }

    /**
     * Aviso quando WooCommerce não está ativo
     */
    public function woocommerce_missing_notice() {
        ?>
        <div class="notice notice-error">
            <p><?php _e('WC Custom Reviews requer que o WooCommerce esteja instalado e ativo.', 'wc-custom-reviews'); ?></p>
        </div>
        <?php
    }

    /**
     * Ativação do plugin
     */
    public static function activate() {
        // Cria tabelas do banco de dados
        WC_Custom_Reviews_Database::create_tables();

        // Define opções padrão
        $default_options = array(
            'star_color' => '#ffb400',
            'button_color' => '#0073aa',
            'enable_photos' => true,
            'moderate_reviews' => true,
            'auto_approve' => 'manual',
            'show_empty_stars' => 'yes',
            'review_order' => 'recent',
            'reviews_per_page' => 10
        );
        add_option('wc_custom_reviews_options', $default_options);

        // Flush rewrite rules
        flush_rewrite_rules();
    }

    /**
     * Desativação do plugin
     */
    public static function deactivate() {
        // Flush rewrite rules
        flush_rewrite_rules();
    }
}

// Inicializa o plugin
WC_Custom_Reviews::get_instance();

// Hooks de ativação e desativação
register_activation_hook(__FILE__, array('WC_Custom_Reviews', 'activate'));
register_deactivation_hook(__FILE__, array('WC_Custom_Reviews', 'deactivate'));

/**
 * Declara compatibilidade com HPOS (High-Performance Order Storage)
 */
add_action('before_woocommerce_init', function() {
    if (class_exists('\Automattic\WooCommerce\Utilities\FeaturesUtil')) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__, true);
    }
});
