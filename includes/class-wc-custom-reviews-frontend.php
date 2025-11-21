<?php
/**
 * Classe responsável pelas funcionalidades do frontend
 */

if (!defined('ABSPATH')) {
    exit;
}

class WC_Custom_Reviews_Frontend {

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
        add_action('wp_enqueue_scripts', array($this, 'enqueue_frontend_scripts'));
        add_action('wp_ajax_wc_custom_reviews_submit_review', array($this, 'ajax_submit_review'));
        add_action('wp_ajax_nopriv_wc_custom_reviews_submit_review', array($this, 'ajax_submit_review'));
        add_action('wp_head', array($this, 'add_dynamic_css'));
    }

    /**
     * Enfileira scripts e estilos do frontend
     */
    public function enqueue_frontend_scripts() {
        // Carrega scripts e estilos apenas em páginas de produto ou onde o WooCommerce está ativo
        // Isso é uma abordagem mais segura para garantir que os estilos e scripts sejam carregados
        // sem depender da detecção de shortcodes no conteúdo do post.
        if (is_product() || is_singular('product') || is_shop() || is_woocommerce()) {
            wp_enqueue_script('jquery');

            wp_enqueue_style(
                'stars-awesome',
                'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css',
                array(),
                '6.5.0'
            );

            wp_enqueue_style(
                'wc-custom-reviews-frontend',
                WC_CUSTOM_REVIEWS_PLUGIN_URL . 'assets/css/frontend.css',
                array(),
                WC_CUSTOM_REVIEWS_VERSION
            );

            // Enfileira imagesLoaded
            wp_enqueue_script(
                'imagesloaded',
                'https://unpkg.com/imagesloaded@5/imagesloaded.pkgd.min.js',
                array('jquery'),
                '5.0.0',
                true
            );

            // Enfileira Masonry
            wp_enqueue_script(
                'masonry',
                'https://unpkg.com/masonry-layout@4/dist/masonry.pkgd.min.js',
                array('imagesloaded'),
                '4.2.2',
                true
            );

            // Enfileira Confetti
            wp_enqueue_script(
                'confetti-js',
                'https://cdn.jsdelivr.net/npm/canvas-confetti@1.9.2/dist/confetti.browser.min.js',
                array(),
                '1.9.2',
                true
            );

            // Enfileira script principal (consolidado com todas as dependências)
            wp_enqueue_script(
                'wc-custom-reviews-frontend',
                WC_CUSTOM_REVIEWS_PLUGIN_URL . 'assets/js/frontend.js',
                array('jquery', 'masonry', 'imagesloaded', 'confetti-js'),
                WC_CUSTOM_REVIEWS_VERSION,
                true
            );

            wp_localize_script('wc-custom-reviews-frontend', 'wcCustomReviews', array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('wc_custom_reviews_frontend_nonce'),
                'strings' => array(
                    'previous' => __('<i class="fa fa-chevron-left" aria-hidden="true"></i>', 'wc-custom-reviews'),
                    'next'     => __('<i class="fa fa-chevron-right" aria-hidden="true"></i>', 'wc-custom-reviews'),
                    'required_fields' => __('Por favor, preencha todos os campos obrigatórios.', 'wc-custom-reviews'),
                    'invalid_email' => __('Por favor, insira um e-mail válido.', 'wc-custom-reviews'),
                    'select_rating' => __('Por favor, selecione uma avaliação.', 'wc-custom-reviews'),
                    'submitting' => __('Enviando...', 'wc-custom-reviews'),
                    'submit_review' => __('Enviar Avaliação', 'wc-custom-reviews'),
                    'success' => __('Sua avaliação foi enviada com sucesso! Ela será analisada antes de ser publicada.', 'wc-custom-reviews'),
                    'error' => __('Erro ao enviar avaliação. Tente novamente.', 'wc-custom-reviews')
                )
            ));


        }
    }

    /**
     * Adiciona CSS dinâmico baseado nas configurações
     */
    public function add_dynamic_css() {
        $options = get_option('wc_custom_reviews_options');
        $star_color = isset($options['star_color']) ? $options['star_color'] : '#ffb400';
        $button_color = isset($options['button_color']) ? $options['button_color'] : '#0073aa';

        ?>
        <style type="text/css">
            :root {
                --wc-custom-reviews-star-color: <?php echo esc_attr($star_color); ?>;
                --wc-custom-reviews-button-color: <?php echo esc_attr($button_color); ?>;
            }
        </style>
        <?php
    }

     /**
     * AJAX - Submete nova avaliação
     */
    public function ajax_submit_review() {
        check_ajax_referer('wc_custom_reviews_frontend_nonce', 'nonce');

        // Sanitiza e valida dados
        $product_id = isset($_POST['product_id']) ? intval($_POST['product_id']) : 0;
        $customer_name = sanitize_text_field($_POST['customer_name']);
        $customer_email = sanitize_email($_POST['customer_email']);
        $rating = isset($_POST['rating']) ? intval($_POST['rating']) : 0;
        $review_text = sanitize_textarea_field($_POST['review_text']);
        
        $image_urls = array();
        $video_url = null;

        // Processa o upload de múltiplas imagens e vídeos (até 10 itens, sendo 1 vídeo)
        $options = get_option('wc_custom_reviews_options');
        $enable_image_upload = isset($options['enable_image_upload']) ? $options['enable_image_upload'] : 'yes';

        if ($enable_image_upload === 'yes' && isset($_FILES['review_images'])) {
            // Log para debug
            error_log('WC Custom Reviews: FILES recebidos - ' . print_r($_FILES['review_images'], true));
            
            // Inclui os arquivos necessários
            if (!function_exists('wp_handle_upload')) {
                require_once(ABSPATH . 'wp-admin/includes/file.php');
            }
            if (!function_exists('wp_generate_attachment_metadata')) {
                require_once(ABSPATH . 'wp-admin/includes/image.php');
            }
            if (!function_exists('media_handle_upload')) {
                require_once(ABSPATH . 'wp-admin/includes/media.php');
            }

            // Limite de 10 arquivos no total
            $max_files = 10;
            $uploaded_count = 0;
            
            // $_FILES['review_images'] contém arrays de name, type, tmp_name, error, size
            $files = $_FILES['review_images'];
            
            // Reorganiza o array para facilitar o processamento
            if (is_array($files['name'])) {
                $file_count = count($files['name']);
                error_log('WC Custom Reviews: ' . $file_count . ' arquivos detectados');
                
                for ($i = 0; $i < min($file_count, $max_files); $i++) {
                    if ($files['error'][$i] === UPLOAD_ERR_OK) {
                        $file_type = $files['type'][$i];
                        $is_video = strpos($file_type, 'video/') === 0;
                        
                        // Limite de tamanho: 2MB para imagens, 50MB para vídeos
                        $max_file_size = $is_video ? (50 * 1024 * 1024) : (2 * 1024 * 1024);
                        if ($files['size'][$i] > $max_file_size) {
                            continue; // Pula arquivos muito grandes
                        }
                        
                        // Se já temos um vídeo, pula outros vídeos
                        if ($is_video && $video_url !== null) {
                            continue;
                        }

                        $upload_overrides = array(
                            'test_form' => false,
                            'mimes' => array(
                                'jpg|jpeg|jpe' => 'image/jpeg',
                                'png' => 'image/png',
                                'gif' => 'image/gif',
                                'mp4' => 'video/mp4',
                                'webm' => 'video/webm',
                                'ogg' => 'video/ogg'
                            )
                        );

                        // Cria array similar ao $_FILES para cada arquivo
                        $single_file = array(
                            'name' => $files['name'][$i],
                            'type' => $files['type'][$i],
                            'tmp_name' => $files['tmp_name'][$i],
                            'error' => $files['error'][$i],
                            'size' => $files['size'][$i]
                        );

                        $movefile = wp_handle_upload($single_file, $upload_overrides);

                        if ($movefile && !isset($movefile['error'])) {
                            if ($is_video) {
                                $video_url = $movefile['url'];
                            } else {
                                $image_urls[] = $movefile['url'];
                            }

                            // Anexa à biblioteca de mídia
                            $attachment = array(
                                'guid' => $movefile['url'],
                                'post_mime_type' => $movefile['type'],
                                'post_title' => sanitize_file_name($files['name'][$i]),
                                'post_content' => '',
                                'post_status' => 'inherit'
                            );
                            $attach_id = wp_insert_attachment($attachment, $movefile['file']);
                            wp_update_attachment_metadata($attach_id, wp_generate_attachment_metadata($attach_id, $movefile['file']));
                            
                            $uploaded_count++;
                        }
                    }
                }
            }
        }
        
        // Converte array de URLs em JSON para armazenar no banco
        $images_json = !empty($image_urls) ? json_encode($image_urls) : null;
        error_log('WC Custom Reviews: Imagens - ' . count($image_urls) . ' | Vídeo: ' . ($video_url ? 'sim' : 'não'));

        // Validações (mantenha como está)
                if (empty($product_id) || empty($customer_name) || empty($customer_email) || empty($rating) || empty($review_text)) {
            wp_send_json_error(array('message' => __('Por favor, preencha todos os campos obrigatórios (Nome, E-mail, Avaliação e Comentário).', 'wc-custom-reviews')));
        }
        if (!is_email($customer_email)) {
            wp_send_json_error(array('message' => __('Por favor, insira um e-mail válido.', 'wc-custom-reviews')));
        }
        if ($rating < 1 || $rating > 5) {
            wp_send_json_error(array('message' => __('Avaliação deve ser entre 1 e 5 estrelas.', 'wc-custom-reviews')));
        }
        $product = wc_get_product($product_id);
        if (!$product) {
            wp_send_json_error(array('message' => __('Produto não encontrado.', 'wc-custom-reviews')));
        }
        if ($this->customer_already_reviewed($product_id, $customer_email)) {
            wp_send_json_error(array('message' => __('Você já avaliou este produto.', 'wc-custom-reviews')));
        }

        $db = WC_Custom_Reviews_Database::get_instance();
        
        // Determina o status inicial do review
        $options = get_option('wc_custom_reviews_options');
        $auto_approve = isset($options['auto_approve']) ? $options['auto_approve'] : 'manual';
        $review_status = ($auto_approve === 'auto') ? 'aprovado' : 'pendente';

        $review_data = array(
            'product_id' => $product_id,
            'customer_name' => $customer_name,
            'customer_email' => $customer_email,
            'rating' => $rating,
            'review_text' => $review_text,
            'status' => $review_status,
            'image_url' => $images_json, // Passa JSON com múltiplas URLs de imagens
            'video_url' => $video_url // URL do vídeo (se houver)
        );
        
        $review_id = $db->insert_review($review_data);

        if ($review_id) {
            // Mensagem de sucesso baseada no status
            if ($review_status === 'aprovado') {
                $success_message = __('Sua avaliação foi enviada e publicada com sucesso!', 'wc-custom-reviews');
            } else {
                $success_message = __('Sua avaliação foi enviada com sucesso! Ela será analisada antes de ser publicada.', 'wc-custom-reviews');
            }

            wp_send_json_success(array(
                'message' => $success_message,
                'review_id' => $review_id
            ));
        } else {
            wp_send_json_error(array('message' => __('Erro ao salvar avaliação. Tente novamente.', 'wc-custom-reviews')));
        }
    }

    /**
     * Verifica se o cliente já avaliou o produto
     */
    private function customer_already_reviewed($product_id, $customer_email) {
        global $wpdb;
        
        $db = WC_Custom_Reviews_Database::get_instance();
        $table_name = $db->get_table_name();

        $count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table_name} WHERE product_id = %d AND customer_email = %s",
            $product_id,
            $customer_email
        ));

        return $count > 0;
    }

    /**
     * Notifica admin sobre nova avaliação
     */
    private function notify_admin_new_review($review_id, $product) {
        $admin_email = get_option('admin_email');
        $site_name = get_bloginfo('name');
        
        $subject = sprintf(__('[%s] Nova avaliação recebida', 'wc-custom-reviews'), $site_name);
        
        $message = sprintf(
            __('Uma nova avaliação foi recebida para o produto "%s".' . "\n\n" .
               'Você pode gerenciar as avaliações em: %s' . "\n\n" .
               'ID da avaliação: %d', 'wc-custom-reviews'),
            $product->get_name(),
            admin_url('admin.php?page=wc-custom-reviews-comments'),
            $review_id
        );

        wp_mail($admin_email, $subject, $message);
    }
}

