<?php
/**
 * Classe responsável pelos shortcodes do plugin
 * VERSÃO CORRIGIDA - Configuração de reviews por página funcionando
 */

if (!defined('ABSPATH')) {
    exit;
}

class WC_Custom_Reviews_Shortcodes {

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
     * Construtor
     */
    private function __construct() {
        add_shortcode('wc_custom_reviews_stars', array($this, 'stars_shortcode'));
        add_shortcode('wc_custom_reviews_widget', array($this, 'widget_shortcode'));
        add_action('wp_ajax_wc_custom_reviews_load_reviews', array($this, 'ajax_load_reviews'));
        add_action('wp_ajax_nopriv_wc_custom_reviews_load_reviews', array($this, 'ajax_load_reviews'));
        $this->hook_rankmath_schema();

    }

    /**
     * Adiciona o aggregateRating no schema do Rank Math.
     */
    public function hook_rankmath_schema() {
        add_filter( 'rank_math/snippet/rich_snippet_product_entity', function( $entity ) {
            if ( is_product() ) {
                global $product;
                if ( ! $product ) {
                    return $entity;
                }

                $db    = WC_Custom_Reviews_Database::get_instance();
                $stats = $db->get_product_rating_stats( $product->get_id() );

                if ( $stats && $stats->total_reviews > 0 ) {
                    $entity['aggregateRating'] = [
                        '@type'       => 'AggregateRating',
                        'ratingValue' => (string) $stats->average_rating,
                        'reviewCount' => (string) $stats->total_reviews,
                    ];
                }
            }
            return $entity;
        });
    }

    /**
     * Shortcode para exibir apenas as estrelas
     * [wc_custom_reviews_stars product_id="123"]
     */
    public function stars_shortcode($atts) {
        $atts = shortcode_atts(array(
            'product_id' => get_the_ID()
        ), $atts);

        $product_id = intval($atts['product_id']);
        
        if (!$product_id) {
            return '<p>' . __('ID do produto não especificado.', 'wc-custom-reviews') . '</p>';
        }

        // Verifica se o produto existe
        $product = wc_get_product($product_id);
        if (!$product) {
            return '<p>' . __('Produto não encontrado.', 'wc-custom-reviews') . '</p>';
        }

        $db = WC_Custom_Reviews_Database::get_instance();
        $stats = $db->get_product_rating_stats($product_id);

        if (!$stats || $stats->total_reviews == 0) {
            // Verifica a configuração para mostrar estrelas vazias
            $options = get_option('wc_custom_reviews_options');
            $show_empty_stars = isset($options['show_empty_stars']) ? $options['show_empty_stars'] : 'yes';
            
            if ($show_empty_stars === 'no') {
                // Não exibe nada quando não há avaliações
                return '';
            } else {
                // Exibe estrelas vazias
                return '<div class="wc-custom-reviews-stars no-reviews">' .
                            '<span class="stars-display">' . $this->render_empty_stars() . '</span>' .
                            '<span class="no-reviews-text">' . __('Sem avaliações', 'wc-custom-reviews') . '</span>' .
                        '</div>';
            }
        }

        $average_rating = round($stats->average_rating, 1);
        
        ob_start();
        ?>
        <div class="wc-custom-reviews-stars" data-product-id="<?php echo esc_attr($product_id); ?>">
            <div class="stars-display">
                <?php echo $this->render_stars($average_rating); ?>
                <span class="rating-average"><?php echo esc_html($average_rating); ?></span>
                <span class="rating-count">(<?php echo esc_html($stats->total_reviews); ?>)</span>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * AJAX - Carrega reviews paginados
     * CORRIGIDO: Lógica simplificada para usar configuração do admin
     */
    public function ajax_load_reviews() {
        check_ajax_referer("wc_custom_reviews_frontend_nonce", "nonce");

        $product_id = isset($_POST["product_id"]) ? intval($_POST["product_id"]) : 0;
        $page = isset($_POST["page"]) ? intval($_POST["page"]) : 1;
        
        // CORREÇÃO: Obtém configuração do admin uma única vez
        $options = get_option('wc_custom_reviews_options');
        $admin_reviews_per_page = isset($options['reviews_per_page']) ? intval($options['reviews_per_page']) : 10;

        // CORREÇÃO: Usa configuração do admin, mas permite override via POST
        $reviews_per_page = isset($_POST["reviews_per_page"]) ? intval($_POST["reviews_per_page"]) : $admin_reviews_per_page;
        
        $review_order = isset($_POST["review_order"]) ? sanitize_text_field($_POST["review_order"]) : "recent";

        if (empty($product_id) || $page < 1 || $reviews_per_page < 1) {
            wp_send_json_error(array("message" => __("Dados inválidos para carregar avaliações.", "wc-custom-reviews")));
        }

        $offset = ($page - 1) * $reviews_per_page;

        $db = WC_Custom_Reviews_Database::get_instance();
        $reviews = $db->get_reviews_by_product($product_id, "aprovado", $reviews_per_page, $offset, $review_order);
        $total_reviews = $db->get_total_reviews_by_product($product_id, "aprovado");
        $total_pages = ceil($total_reviews / $reviews_per_page);

        ob_start();
        if (!empty($reviews)) : 
            foreach ($reviews as $review) : 
                ?>
                <div class="review-grid-item" data-review-id="<?php echo esc_attr($review->id); ?>">
                    <?php if (!empty($review->image_url)) : ?>
                        <div class="review-image-grid">
                            <img src="<?php echo esc_url($review->image_url); ?>" alt="<?php echo esc_attr__("Imagem da Avaliação", "wc-custom-reviews"); ?>">
                        </div>
                    <?php endif; ?>
                    <div class="review-grid-content">
                        <div class="customer-info-grid">
                            <span class="customer-name-grid"><?php echo esc_html($review->customer_name); ?></span>
                            <div class="review-rating-grid">
                                <?php echo WC_Custom_Reviews_Shortcodes::get_instance()->render_stars($review->rating); ?>
                            </div>
                        </div>
                        <?php if (!empty($review->review_text)) : ?>
                            <div class="review-text-grid">
                                <p><?php echo esc_html(wp_trim_words($review->review_text, 30)); ?></p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php
            endforeach;
        else : ?>
        <?php endif;
        $reviews_html = ob_get_clean();

        wp_send_json_success(array(
            "reviews_html" => $reviews_html,
            "total_pages" => $total_pages,
            "current_page" => $page
        ));
    }


    /**
     * Shortcode para exibir widget completo de comentários
     * [wc_custom_reviews_widget product_id="123"]
     * CORRIGIDO: Lógica simplificada para usar configuração do admin
     */
    public function widget_shortcode($atts) {
        $atts = shortcode_atts(array(
            'product_id' => get_the_ID(),
            'reviews_per_page' => '', // Deixa vazio por padrão
            'current_page' => 1 
        ), $atts);

        $product_id = intval($atts['product_id']);
        
        // CORREÇÃO: Obtém configuração do admin uma única vez
        $options = get_option('wc_custom_reviews_options');
        $admin_reviews_per_page = isset($options['reviews_per_page']) ? intval($options['reviews_per_page']) : 10;

        // CORREÇÃO: Usa configuração do admin, mas permite override via shortcode
        if (!empty($atts['reviews_per_page']) && intval($atts['reviews_per_page']) > 0) {
            $reviews_per_page = intval($atts['reviews_per_page']);
        } else {
            $reviews_per_page = $admin_reviews_per_page;
        }
        
        $current_page = intval($atts['current_page']);
        $offset = ($current_page - 1) * $reviews_per_page;
        
        if (!$product_id) {
            return '<p>' . __('ID do produto não especificado.', 'wc-custom-reviews') . '</p>';
        }

        // Verifica se o produto existe
        $product = wc_get_product($product_id);
        if (!$product) {
            return '<p>' . __('Produto não encontrado.', 'wc-custom-reviews') . '</p>';
        }

        $db = WC_Custom_Reviews_Database::get_instance();
        $reviews = $db->get_reviews_by_product($product_id, 'aprovado', $reviews_per_page, $offset, $review_order);
        $total_reviews = $db->get_total_reviews_by_product($product_id, 'aprovado');
        $total_pages = ceil($total_reviews / $reviews_per_page);
        $stats = $db->get_product_rating_stats($product_id);

        ob_start();
        ?>
        <div class="wc-custom-reviews-widget" data-product-id="<?php echo esc_attr($product_id); ?>" data-review-order="<?php echo esc_attr($review_order); ?>">
            
            <!-- Resumo das avaliações -->
            <div class="reviews-summary">
                <div class="reviews-title">
                    <span><?php _e('Avaliações de Clientes', 'wc-custom-reviews'); ?></span>
                </div>
                <?php if ($stats && $stats->total_reviews > 0) : ?>
                <div class="rating-overview">
                    <div class="average-rating">
                        <span class="rating-number"><?php echo esc_html(round($stats->average_rating, 1)); ?></span>
                        <div class="stars-display">
                            <?php echo $this->render_stars($stats->average_rating); ?>
                        </div>
                        <span class="total-reviews"><?php echo sprintf(__('%d avaliações', 'wc-custom-reviews'), $stats->total_reviews); ?></span>
                    </div>
                    
                    <div class="rating-breakdown">
                        <?php for ($i = 5; $i >= 1; $i--) : ?>
                            <?php 
                            $count = $stats->{$this->number_to_word($i) . '_star' . ($i > 1 ? 's' : '')};
                            $percentage = $stats->total_reviews > 0 ? ($count / $stats->total_reviews) * 100 : 0;
                            ?>
                            <div class="rating-bar">
                                <span class="star-label"><?php echo $i; ?> <i class="fa-solid fa-star"></i></span>
                                <div class="bar-container">
                                    <div class="bar-fill" style="width: <?php echo esc_attr($percentage); ?>%"></div>
                                </div>
                                <span class="bar-count"><?php echo esc_html($count); ?></span>
                            </div>
                        <?php endfor; ?>
                    </div>
                    <!-- Botão para abrir modal de avaliação -->
                    <div class="review-button-container">
                        <button type="button" class="open-review-modal-btn" data-product-id="<?php echo esc_attr($product_id); ?>">
                            <?php _e('Avaliar Produto', 'wc-custom-reviews'); ?>
                        </button>
                    </div>
                </div>
                <?php else : ?>
                <div class="no-reviews">
                    <div class="no-reviews-summary">
                        <p><?php _e('Este produto ainda não possui avaliações.', 'wc-custom-reviews'); ?></p>
                    </div>
                    <!-- Botão para abrir modal de avaliação -->
                    <div class="review-button-container">
                        <button type="button" class="open-review-modal-btn" data-product-id="<?php echo esc_attr($product_id); ?>">
                            <?php _e('Avaliar Produto', 'wc-custom-reviews'); ?>
                        </button>
                    </div>
                </div>
                <?php endif; ?>
            </div>

            <!-- Lista de comentários -->
            <?php if (!empty($reviews)) : ?>
            <div class="reviews-list">
                <!-- Spinner de carregamento -->
                <div class="loader" id="reviews-loader" style="display: none;"></div>
                <div class="wc-custom-reviews-grid">     
                    <?php foreach ($reviews as $review) : ?>
                        <div class="review-grid-item" data-review-id="<?php echo esc_attr($review->id); ?>">
                            <?php if (!empty($review->image_url)) : ?>
                                <div class="review-image-grid">
                                    <img src="<?php echo esc_url($review->image_url); ?>" alt="<?php echo esc_attr__("Imagem da Avaliação", "wc-custom-reviews"); ?>">
                                </div>
                            <?php endif; ?>
                            <div class="review-grid-content">
                                <div class="customer-info-grid">
                                    <span class="customer-name-grid"><?php echo esc_html($review->customer_name); ?></span>
                                    <div class="review-rating-grid">
                                        <?php echo $this->render_stars($review->rating); ?>
                                    </div>
                                </div>
                                <?php if (!empty($review->review_text)) : ?>
                                    <div class="review-text-grid">
                                        <p><?php echo esc_html(wp_trim_words($review->review_text, 30)); ?></p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            
                <!-- Paginação -->
                <?php if ($total_pages > 1) : ?>
                <div class="wc-custom-reviews-pagination" data-product-id="<?php echo esc_attr($product_id); ?>" data-reviews-per-page="<?php echo esc_attr($reviews_per_page); ?>">
                    <?php for ($p = 1; $p <= $total_pages; $p++) : ?>
                        <button type="button" class="pagination-button <?php echo ($p == $current_page) ? 'active' : ''; ?>" data-page="<?php echo esc_attr($p); ?>">
                            <?php echo esc_html($p); ?>
                        </button>
                    <?php endfor; ?>
                </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <!-- Modal de avaliação -->
            <div id="wc-review-modal" class="wc-review-modal" style="display: none;">
                <div class="wc-review-modal-content">
                    <div class="wc-review-modal-header">
                        <span><?php _e('Deixe sua Avaliação', 'wc-custom-reviews'); ?></span>
                        <button type="button" class="wc-review-modal-close">&times;</button>
                    </div>
                    
                    <div class="wc-review-modal-body">
                        <form class="wc-custom-review-form" data-product-id="<?php echo esc_attr($product_id); ?>" enctype="multipart/form-data">
                            <?php wp_nonce_field('wc_custom_reviews_submit', 'wc_custom_reviews_nonce'); ?>
                            
                            <div class="form-row">
                                <label for="customer_name"><?php _e('Seu Nome *', 'wc-custom-reviews'); ?></label>
                                <input type="text" id="customer_name" name="customer_name" required>
                            </div>
                            
                            <div class="form-row">
                                <label for="customer_email"><?php _e('Seu E-mail *', 'wc-custom-reviews'); ?></label>
                                <input type="email" id="customer_email" name="customer_email" required>
                            </div>
                            
                            <div class="form-row">
                                <label><?php _e('Sua Avaliação *', 'wc-custom-reviews'); ?></label>
                                <div class="rating-input">
                                    <?php for ($i = 1; $i <= 5; $i++) : ?>
                                        <input type="radio" id="rating_<?php echo $i; ?>" name="rating" value="<?php echo $i; ?>" required>
                                        <label for="rating_<?php echo $i; ?>" class="star-label">★</label>
                                    <?php endfor; ?>
                                </div>
                            </div>
                            
                            <div class="form-row">
                                <label for="review_text"><?php _e('Seu Comentário *', 'wc-custom-reviews'); ?></label>
                                <textarea id="review_text" name="review_text" rows="4" required placeholder="<?php _e('Conte-nos sobre sua experiência com este produto...', 'wc-custom-reviews'); ?>"></textarea>
                            </div>
                            
                            <?php
                            $options = get_option('wc_custom_reviews_options');
                            $enable_photos = isset($options['enable_photos']) ? $options['enable_photos'] : true;
                            if ($enable_photos) :
                            ?>
                            <div class="form-row">
                                <label for="review_image"><?php _e('Adicionar Foto (opcional)', 'wc-custom-reviews'); ?></label>
                                <input type="file" id="review_image" name="review_image" accept="image/*">
                                <p class="description"><?php _e('Formatos aceitos: JPG, PNG, GIF. Tamanho máximo: 2MB', 'wc-custom-reviews'); ?></p>
                                
                                <div class="image-preview-container" style="display: none;">
                                    <img id="review-image-preview" src="" alt="Preview" style="max-width: 200px; max-height: 200px;">
                                    <button type="button" id="remove-image-preview"><?php _e('Remover', 'wc-custom-reviews'); ?></button>
                                </div>
                            </div>
                            <?php endif; ?>
                            
                            <div class="form-messages"></div>
                            
                            <div class="form-row">
                                <button type="submit" class="submit-review-btn"><?php _e('Enviar Avaliação', 'wc-custom-reviews'); ?></button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Renderiza estrelas baseado na avaliação
     */
    public function render_stars($rating) {
        $rating = floatval($rating);
        $full_stars = floor($rating);
        $half_star = ($rating - $full_stars) >= 0.5 ? 1 : 0;
        $empty_stars = 5 - $full_stars - $half_star;

        $html = '<div class="stars-container">';
        
        // Estrelas cheias
        for ($i = 0; $i < $full_stars; $i++) {
            $html .= '<i class="fa-solid fa-star"></i>';
        }
        
        // Meia estrela
        if ($half_star) {
            $html .= '<i class="fa-solid fa-star-half-stroke"></i>';
        }
        
        // Estrelas vazias
        for ($i = 0; $i < $empty_stars; $i++) {
            $html .= '<i class="fa-regular fa-star"></i>';
        }
        
        $html .= '</div>';
        
        return $html;
    }

    /**
     * Renderiza estrelas vazias
     */
    public function render_empty_stars() {
        $html = '<div class="stars-container">';
        for ($i = 0; $i < 5; $i++) {
            $html .= '<i class="fa-regular fa-star"></i>';
        }
        $html .= '</div>';
        return $html;
    }

    /**
     * Converte número para palavra (para estatísticas)
     */
    private function number_to_word($number) {
        $words = array(
            1 => 'one',
            2 => 'two', 
            3 => 'three',
            4 => 'four',
            5 => 'five'
        );
        
        return isset($words[$number]) ? $words[$number] : 'one';
    }
}