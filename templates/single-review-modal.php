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
    <!-- Botão fechar -->
    <button type="button" class="wc-single-review-modal-close">&times;</button>
    
    <div class="wc-single-review-modal-grid">
        <!-- Lado Esquerdo: Galeria de Imagens e Vídeos -->
        <div class="modal-image-section">
            <?php 
            // Decodifica múltiplas imagens se estiver em JSON
            $image_urls = !empty($review->image_url) ? json_decode($review->image_url, true) : array();
            $video_url = !empty($review->video_url) ? $review->video_url : null;
            
            // Cria array com todas as mídias (vídeo primeiro, depois imagens)
            $all_media = array();
            if (!empty($video_url)) {
                $all_media[] = array('type' => 'video', 'url' => $video_url);
            }
            if (!empty($image_urls) && is_array($image_urls)) {
                foreach ($image_urls as $img_url) {
                    $all_media[] = array('type' => 'image', 'url' => $img_url);
                }
            }
            
            if (!empty($all_media)) : 
            ?>
                <div class="modal-gallery">
                    <!-- Mídia Principal -->
                    <div class="modal-gallery-main">
                        <?php if ($all_media[0]['type'] === 'video') : ?>
                            <video src="<?php echo esc_url($all_media[0]['url']); ?>" 
                                   class="modal-main-image modal-main-video"
                                   controls></video>
                        <?php else : ?>
                            <img src="<?php echo esc_url($all_media[0]['url']); ?>" 
                                 alt="<?php esc_attr_e('Imagem do Cliente', 'wc-custom-reviews'); ?>"
                                 class="modal-main-image">
                        <?php endif; ?>
                    </div>
                    
                    <?php if (count($all_media) > 1) : ?>
                        <!-- Navegação com setas -->
                        <button type="button" class="modal-gallery-arrow modal-gallery-prev">
                            <svg version="1.1" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 66.91 122.88">
                                <path d="M64.96,111.2c2.65,2.73,2.59,7.08-0.13,9.73c-2.73,2.65-7.08,2.59-9.73-0.14L1.97,66.01l4.93-4.8l-4.95,4.8 c-2.65-2.74-2.59-7.1,0.15-9.76c0.08-0.08,0.16-0.15,0.24-0.22L55.1,2.09c2.65-2.73,7-2.79,9.73-0.14 c2.73,2.65,2.78,7.01,0.13,9.73L16.5,61.23L64.96,111.2L64.96,111.2L64.96,111.2z"/>
                            </svg>
                        </button>
                        <button type="button" class="modal-gallery-arrow modal-gallery-next">
                            <svg version="1.1" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 66.91 122.88">
                                <path d="M1.95,111.2c-2.65,2.72-2.59,7.08,0.14,9.73c2.72,2.65,7.08,2.59,9.73-0.14L64.94,66l-4.93-4.79l4.95,4.8 c2.65-2.74,2.59-7.11-0.15-9.76c-0.08-0.08-0.16-0.15-0.24-0.22L11.81,2.09c-2.65-2.73-7-2.79-9.73-0.14 C-0.64,4.6-0.7,8.95,1.95,11.68l48.46,49.55L1.95,111.2L1.95,111.2L1.95,111.2z"/>
                            </svg>
                        </button>
                        
                        <!-- Miniaturas na parte inferior -->
                        <div class="modal-gallery-thumbs">
                            <?php foreach ($all_media as $index => $media) : ?>
                                <div class="modal-thumb <?php echo $index === 0 ? 'active' : ''; ?>" 
                                     data-index="<?php echo $index; ?>"
                                     data-type="<?php echo esc_attr($media['type']); ?>">
                                    <?php if ($media['type'] === 'video') : 
                                        $thumb_video_url = $media['url'] . '#t=0.1';
                                    ?>
                                        <video src="<?php echo esc_url($thumb_video_url); ?>" muted preload="metadata"></video>
                                        <div class="thumb-play-icon">
                                            <i class="fa-solid fa-play"></i>
                                        </div>
                                    <?php else : ?>
                                        <img src="<?php echo esc_url($media['url']); ?>" alt="">
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        
                        <!-- Armazena todas as mídias -->
                        <script type="application/json" class="gallery-images-data">
                            <?php echo json_encode($all_media); ?>
                        </script>
                    <?php endif; ?>
                </div>
            <?php elseif (!empty($review->image_url)) : 
                // Uma única imagem (formato antigo)
            ?>
                <div class="modal-gallery">
                    <div class="modal-gallery-main">
                        <img src="<?php echo esc_url($review->image_url); ?>" 
                             alt="<?php esc_attr_e('Imagem do Cliente', 'wc-custom-reviews'); ?>"
                             class="modal-main-image">
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <!-- Lado Direito: Informações do Review -->
        <div class="modal-info-section">
            <div class="modal-review-info">
                <!-- Nome e Badge Verificado -->
                <div class="modal-customer-header">
                    <h3 class="modal-customer-name"><?php echo esc_html($review->customer_name); ?></h3>
                    <span class="modal-verified-badge">
                        <i class="fa-solid fa-circle-check"></i>
                        <?php esc_html_e('Verificado', 'wc-custom-reviews'); ?>
                    </span>
                </div>

                <!-- Estrelas -->
                <div class="modal-rating">
                    <?php echo $shortcodes_instance->render_stars($review->rating); ?>
                </div>

                <!-- Texto do Review -->
                <div class="modal-review-text">
                    <p><?php echo nl2br(esc_html($review->review_text)); ?></p>
                </div>
            </div>

            <!-- Informações do Produto -->
            <?php if (!empty($product_data)) : ?>
                <div class="modal-product-info">
                    <hr class="divider">
                    <a href="<?php echo esc_url($product_data['permalink']); ?>" class="modal-product-link">
                        <?php if (!empty($product_data['image_url'])) : ?>
                            <div class="modal-product-image">
                                <img src="<?php echo esc_url($product_data['image_url']); ?>" 
                                     alt="<?php echo esc_attr($product_data['name']); ?>">
                            </div>
                        <?php endif; ?>
                        <div class="modal-product-name">
                            <?php echo esc_html($product_data['name']); ?>
                        </div>
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
