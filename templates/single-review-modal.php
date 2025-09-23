<?php
/**
 * Template para exibir um único review em um modal.
 *
 * @var object $review O objeto review com todos os dados.
 * @var array $product_data Array com dados do produto (name, permalink, image_url).
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$shortcodes_instance = WC_Custom_Reviews_Shortcodes::get_instance();
?>

<div class="wc-single-review-modal-content">
    <div class="wc-single-review-modal-header">
        <button type="button" class="wc-single-review-modal-close">&times;</button>
    </div>
    
    <div class="wc-single-review-modal-body">
        <div class="review-details-wrapper">
            <?php if ( ! empty( $review->image_url ) ) : ?>
                <div class="review-detail-image">
                    <img src="<?php echo esc_url( $review->image_url ); ?>" alt="<?php esc_attr_e( 'Imagem do Cliente', 'wc-custom-reviews' ); ?>">
                </div>
            <?php endif; ?>

            <div class="review-detail-content">
                <div class="customer-info">
                    <span class="customer-name"><?php echo esc_html( $review->customer_name ); ?></span>
                    <span class="review-verified"><?php esc_html_e( 'Verificado', 'wc-custom-reviews' ); ?> <i class="fa-solid fa-circle-check"></i></span>
                </div>
                <div class="review-rating">
                    <?php echo $shortcodes_instance->render_stars( $review->rating ); ?>
                </div>
                <div class="review-text">
                    <p><?php echo esc_html( $review->review_text ); ?></p>
                </div>
            </div>
        </div>

        <?php if ( ! empty( $product_data ) ) : ?>
            <div class="review-product-info">
                <?php if ( ! empty( $product_data["image_url"] ) ) : ?>
                        <img src="<?php echo esc_url( $product_data["image_url"] ); ?>" alt="<?php echo esc_attr( $product_data["name"] ); ?>">
                    </a>
                <?php endif; ?>
                    <span class="product-name"><?php echo esc_html( $product_data["name"] ); ?></span>
                </a>
            </div>
        <?php endif; ?>
    </div>
</div>
