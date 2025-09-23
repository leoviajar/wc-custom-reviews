<?php
/**
 * Classe responsável por buscar e exibir um único review detalhado.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WC_Custom_Reviews_Single_Review {

    private static $instance = null;

    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action( 'wp_ajax_wc_custom_reviews_get_single_review', array( $this, 'ajax_get_single_review' ) );
        add_action( 'wp_ajax_nopriv_wc_custom_reviews_get_single_review', array( $this, 'ajax_get_single_review' ) );
    }

    public function ajax_get_single_review() {
        check_ajax_referer( 'wc_custom_reviews_frontend_nonce', 'nonce' );

        $review_id = isset( $_POST['review_id'] ) ? intval( $_POST['review_id'] ) : 0;

        if ( empty( $review_id ) ) {
            wp_send_json_error( array( 'message' => __( 'ID do review não especificado.', 'wc-custom-reviews' ) ) );
        }

        $db = WC_Custom_Reviews_Database::get_instance();
        $review = $db->get_review_by_id( $review_id );

        if ( ! $review || $review->status !== 'aprovado' ) {
            wp_send_json_error( array( 'message' => __( 'Review não encontrado ou não aprovado.', 'wc-custom-reviews' ) ) ) ;
        }

        // Obter informações do produto
        $product = wc_get_product( $review->product_id );
        $product_data = array();
        if ( $product ) {
            $product_data = array(
                'name' => $product->get_name(),
                'permalink' => $product->get_permalink(),
                'image_url' => wp_get_attachment_image_url( $product->get_image_id(), 'thumbnail' ),
            );
        }

        ob_start();
        // Incluir o template do modal aqui
        include WC_CUSTOM_REVIEWS_PLUGIN_DIR . 'templates/single-review-modal.php';
        $modal_content = ob_get_clean();

        wp_send_json_success( array(
            'review_html' => $modal_content,
            'review_data' => array(
                'customer_name' => $review->customer_name,
                'rating' => $review->rating,
                'review_text' => $review->review_text,
                'image_url' => $review->image_url,
                'product' => $product_data
            )
        ) );
    }

    public function render_stars($rating) {
        return WC_Custom_Reviews_Shortcodes::get_instance()->render_stars($rating);
    }
}
