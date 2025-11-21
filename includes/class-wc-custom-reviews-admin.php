<?php
/**
 * Classe responsável pela interface administrativa
 * Versão final com funcionalidade de importação CSV e download de imagens
 */

if (!defined('ABSPATH')) {
    exit;
}

class WC_Custom_Reviews_Admin {

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
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'init_settings'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        add_action('wp_ajax_wc_custom_reviews_update_status', array($this, 'ajax_update_review_status'));
        add_action('wp_ajax_wc_custom_reviews_delete_review', array($this, 'ajax_delete_review'));
        add_action('wp_ajax_wc_custom_reviews_get_review_for_edit', array($this, 'ajax_get_review_for_edit'));
        add_action('wp_ajax_wc_custom_reviews_save_review_edit', array($this, 'ajax_save_review_edit'));
        
        // NOVO: Adiciona handler para importação CSV
        add_action('wp_ajax_wc_custom_reviews_import_csv', array($this, 'ajax_import_csv'));
        add_action('admin_post_wc_custom_reviews_import_csv', array($this, 'handle_csv_import'));
        
        // NOVO: Adiciona handler para exportação CSV
        add_action('admin_post_wc_custom_reviews_export_csv', array($this, 'handle_csv_export'));
        
        // NOVO: Adiciona handler para processar todos os duplicados
        add_action('wp_ajax_wc_custom_reviews_process_all_duplicates', array($this, 'ajax_process_all_duplicates'));

        add_action('wp_ajax_wc_custom_reviews_bulk_delete', array($this, 'ajax_bulk_delete_reviews'));
    }

    /**
     * Adiciona menu administrativo
     */
    public function add_admin_menu() {
        // Menu principal
        add_menu_page(
            __('Custom Reviews', 'wc-custom-reviews'),
            __('Custom Reviews', 'wc-custom-reviews'),
            'manage_options',
            'wc-custom-reviews',
            array($this, 'admin_page_settings'),
            'dashicons-star-filled',
            56
        );

        // Submenu - Configurações
        add_submenu_page(
            'wc-custom-reviews',
            __('Configurações', 'wc-custom-reviews'),
            __('Configurações', 'wc-custom-reviews'),
            'manage_options',
            'wc-custom-reviews',
            array($this, 'admin_page_settings')
        );

        // Submenu - Comentários
        add_submenu_page(
            'wc-custom-reviews',
            __('Comentários', 'wc-custom-reviews'),
            __('Comentários', 'wc-custom-reviews'),
            'manage_options',
            'wc-custom-reviews-comments',
            array($this, 'admin_page_comments')
        );

        // Submenu - Duplicados
        add_submenu_page(
            'wc-custom-reviews',
            __('Duplicados', 'wc-custom-reviews'),
            __('Duplicados', 'wc-custom-reviews'),
            'manage_options',
            'wc-custom-reviews-duplicates',
            array($this, 'admin_page_duplicates')
        );

        // NOVO: Submenu - Importar CSV
        add_submenu_page(
            'wc-custom-reviews',
            __('Importar CSV', 'wc-custom-reviews'),
            __('Importar CSV', 'wc-custom-reviews'),
            'manage_options',
            'wc-custom-reviews-import',
            array($this, 'admin_page_import_csv')
        );
    }

    /**
     * Método para adicionar à classe WC_Custom_Reviews_Admin
     * Adicione este método antes do último fechamento de chave da classe
     */
    public function ajax_bulk_delete_reviews() {
        // Verifica nonce
        if (!wp_verify_nonce($_POST['nonce'], 'wc_custom_reviews_bulk_action')) {
            wp_die(__('Erro de segurança.', 'wc-custom-reviews'));
        }
        
        // Verifica permissões
        if (!current_user_can('manage_options')) {
            wp_die(__('Você não tem permissão para realizar esta ação.', 'wc-custom-reviews'));
        }
        
        // Obtém os IDs dos reviews selecionados
        $review_ids = isset($_POST['review_ids']) ? $_POST['review_ids'] : array();
        
        if (empty($review_ids)) {
            wp_send_json_error(__('Nenhum review selecionado.', 'wc-custom-reviews'));
        }
        
        // Executa a exclusão em massa
        $db = WC_Custom_Reviews_Database::get_instance();
        $deleted_count = $db->bulk_delete_reviews($review_ids);
        
        if ($deleted_count > 0) {
            wp_send_json_success(array(
                'message' => sprintf(__('%d reviews excluídos com sucesso.', 'wc-custom-reviews'), $deleted_count),
                'deleted_count' => $deleted_count
            ));
        } else {
            wp_send_json_error(__('Erro ao excluir reviews.', 'wc-custom-reviews'));
        }
    }

    /**
     * NOVA FUNÇÃO: Página de importação CSV
     */
    public function admin_page_import_csv() {
        // Processa mensagens de feedback
        $message = '';
        $message_type = '';
        
        if (isset($_GET['import_result'])) {
            switch ($_GET['import_result']) {
                case 'success':
                    $imported = isset($_GET['imported']) ? intval($_GET['imported']) : 0;
                    $downloaded = isset($_GET['downloaded']) ? intval($_GET['downloaded']) : 0;
                    $message = sprintf(__('%d avaliações importadas com sucesso! %d imagens baixadas e armazenadas na biblioteca de mídia.', 'wc-custom-reviews'), $imported, $downloaded);
                    $message_type = 'success';
                    break;
                case 'error':
                    $error = isset($_GET['error']) ? sanitize_text_field($_GET['error']) : __('Erro desconhecido', 'wc-custom-reviews');
                    $message = __('Erro na importação: ', 'wc-custom-reviews') . $error;
                    $message_type = 'error';
                    break;
                case 'invalid_file':
                    $message = __('Arquivo CSV inválido ou não foi possível fazer upload.', 'wc-custom-reviews');
                    $message_type = 'error';
                    break;
            }
        }
        ?>
        <div class="wrap">
            <h1><?php _e('Custom Reviews - Importar CSV', 'wc-custom-reviews'); ?></h1>
            
            <?php if (!empty($message)): ?>
                <div class="notice notice-<?php echo esc_attr($message_type); ?> is-dismissible">
                    <p><?php echo esc_html($message); ?></p>
                </div>
            <?php endif; ?>
            
            <div class="card">
                <h2><?php _e('Importar Avaliações via CSV', 'wc-custom-reviews'); ?></h2>
                <p><?php _e('Use esta ferramenta para importar avaliações de produtos em lote através de um arquivo CSV. As imagens referenciadas serão automaticamente baixadas e armazenadas na biblioteca de mídia do WordPress.', 'wc-custom-reviews'); ?></p>
                
                <form method="post" action="<?php echo admin_url('admin-post.php'); ?>" enctype="multipart/form-data" id="csv-import-form">
                    <?php wp_nonce_field('wc_custom_reviews_import_csv', 'csv_import_nonce'); ?>
                    <input type="hidden" name="action" value="wc_custom_reviews_import_csv">
                    
                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="csv_file"><?php _e('Arquivo CSV', 'wc-custom-reviews'); ?></label>
                            </th>
                            <td>
                                <input type="file" name="csv_file" id="csv_file" accept=".csv" required>
                                <p class="description">
                                    <?php _e('Selecione um arquivo CSV com as avaliações. Tamanho máximo: 2MB', 'wc-custom-reviews'); ?>
                                </p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="import_mode"><?php _e('Modo de Importação', 'wc-custom-reviews'); ?></label>
                            </th>
                            <td>
                                <select name="import_mode" id="import_mode">
                                    <option value="insert"><?php _e('Inserir apenas (pular duplicatas)', 'wc-custom-reviews'); ?></option>
                                    <option value="update"><?php _e('Atualizar existentes e inserir novos', 'wc-custom-reviews'); ?></option>
                                </select>
                                <p class="description">
                                    <?php _e('Escolha como tratar avaliações que já existem no sistema.', 'wc-custom-reviews'); ?>
                                </p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="default_status"><?php _e('Status Padrão', 'wc-custom-reviews'); ?></label>
                            </th>
                            <td>
                                <select name="default_status" id="default_status">
                                    <option value="pendente"><?php _e('pendente (requer aprovação)', 'wc-custom-reviews'); ?></option>
                                    <option value="aprovado"><?php _e('aprovado (publicado imediatamente)', 'wc-custom-reviews'); ?></option>
                                </select>
                                <p class="description">
                                    <?php _e('Status que será aplicado às avaliações importadas.', 'wc-custom-reviews'); ?>
                                </p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="download_images"><?php _e('Download de Imagens', 'wc-custom-reviews'); ?></label>
                            </th>
                            <td>
                                <label>
                                    <input type="checkbox" name="download_images" id="download_images" value="1" checked>
                                    <?php _e('Baixar imagens automaticamente para a biblioteca de mídia', 'wc-custom-reviews'); ?>
                                </label>
                                <p class="description">
                                    <?php _e('Quando marcado, as imagens referenciadas no CSV serão baixadas e armazenadas localmente. Recomendado para garantir que as imagens não sejam perdidas.', 'wc-custom-reviews'); ?>
                                </p>
                            </td>
                        </tr>
                    </table>
                    
                    <?php submit_button(__('Importar CSV', 'wc-custom-reviews'), 'primary', 'submit', true, array('id' => 'import-submit')); ?>
                </form>
            </div>
            
            <div class="card">
                <h3><?php _e('Formato do Arquivo CSV', 'wc-custom-reviews'); ?></h3>
                <p><?php _e('O arquivo CSV deve conter as seguintes colunas (na primeira linha):', 'wc-custom-reviews'); ?></p>
                
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th><?php _e('Coluna', 'wc-custom-reviews'); ?></th>
                            <th><?php _e('Descrição', 'wc-custom-reviews'); ?></th>
                            <th><?php _e('Obrigatório', 'wc-custom-reviews'); ?></th>
                            <th><?php _e('Exemplo', 'wc-custom-reviews'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><code>id</code></td>
                            <td><?php _e('ID do produto', 'wc-custom-reviews'); ?></td>
                            <td><span class="required"><?php _e('Sim', 'wc-custom-reviews'); ?></span></td>
                            <td>123</td>
                        </tr>
                        <tr>
                            <td><code>rating</code></td>
                            <td><?php _e('Nota da avaliação (1-5)', 'wc-custom-reviews'); ?></td>
                            <td><span class="required"><?php _e('Sim', 'wc-custom-reviews'); ?></span></td>
                            <td>5</td>
                        </tr>
                        <tr>
                            <td><code>name</code></td>
                            <td><?php _e('Nome do cliente', 'wc-custom-reviews'); ?></td>
                            <td><span class="required"><?php _e('Sim', 'wc-custom-reviews'); ?></span></td>
                            <td>João Silva</td>
                        </tr>
                        <tr>
                            <td><code>email</code></td>
                            <td><?php _e('E-mail do cliente', 'wc-custom-reviews'); ?></td>
                            <td><?php _e('Não', 'wc-custom-reviews'); ?></td>
                            <td>joao@email.com</td>
                        </tr>
                        <tr>
                            <td><code>review</code></td>
                            <td><?php _e('Texto da avaliação', 'wc-custom-reviews'); ?></td>
                            <td><span class="required"><?php _e('Sim', 'wc-custom-reviews'); ?></span></td>
                            <td>Produto excelente!</td>
                        </tr>
                        <tr>
                            <td><code>photo</code></td>
                            <td><?php _e('URL da foto (será baixada automaticamente)', 'wc-custom-reviews'); ?></td>
                            <td><?php _e('Não', 'wc-custom-reviews'); ?></td>
                            <td>https://exemplo.com/foto.jpg</td>
                        </tr>
                    </tbody>
                </table>
                
                <h4><?php _e('Exemplo de arquivo CSV:', 'wc-custom-reviews'); ?></h4>
                <pre style="background: #f1f1f1; padding: 10px; border-radius: 4px; overflow-x: auto;">id,rating,name,email,review,photo
123,5,João Silva,joao@email.com,Produto excelente! Recomendo.,https://exemplo.com/foto1.jpg
124,4,Maria Santos,maria@email.com,Muito bom produto.,
125,5,Pedro Costa,,Adorei a qualidade!,https://exemplo.com/foto2.jpg</pre>
                
                <div class="notice notice-info inline">
                    <p><strong><?php _e('Dicas importantes:', 'wc-custom-reviews'); ?></strong></p>
                    <ul>
                        <li><?php _e('A primeira linha deve conter exatamente os nomes das colunas mostrados acima', 'wc-custom-reviews'); ?></li>
                        <li><?php _e('Use vírgulas para separar os campos', 'wc-custom-reviews'); ?></li>
                        <li><?php _e('Se um campo contém vírgulas, coloque-o entre aspas duplas', 'wc-custom-reviews'); ?></li>
                        <li><?php _e('Campos opcionais podem ficar vazios, mas as vírgulas devem ser mantidas', 'wc-custom-reviews'); ?></li>
                        <li><?php _e('O ID do produto deve existir na sua loja', 'wc-custom-reviews'); ?></li>
                        <li><?php _e('A nota deve ser um número de 1 a 5', 'wc-custom-reviews'); ?></li>
                        <li><strong><?php _e('NOVO: As imagens serão automaticamente baixadas e armazenadas na sua biblioteca de mídia', 'wc-custom-reviews'); ?></strong></li>
                    </ul>
                </div>
            </div>
        </div>
        
        <style>
        .required {
            color: #d63638;
            font-weight: bold;
        }
        .card {
            background: #fff;
            border: 1px solid #c3c4c7;
            border-radius: 4px;
            padding: 20px;
            margin-bottom: 20px;
        }
        .card h2, .card h3 {
            margin-top: 0;
        }
        #import-submit {
            margin-top: 10px;
        }
        </style>
        
        <script>
        jQuery(document).ready(function($) {
            $('#csv-import-form').on('submit', function() {
                $('#import-submit').prop('disabled', true).val('<?php _e('Importando...', 'wc-custom-reviews'); ?>');
            });
        });
        </script>
        <?php
    }

    /**
     * NOVA FUNÇÃO: Processa a importação do CSV
     */
    public function handle_csv_import() {
        // Verifica nonce
        if (!wp_verify_nonce($_POST['csv_import_nonce'], 'wc_custom_reviews_import_csv')) {
            wp_die(__('Erro de segurança. Tente novamente.', 'wc-custom-reviews'));
        }

        // Verifica permissões
        if (!current_user_can('manage_options')) {
            wp_die(__('Você não tem permissão para realizar esta ação.', 'wc-custom-reviews'));
        }

        // Verifica se arquivo foi enviado
        if (!isset($_FILES['csv_file']) || $_FILES['csv_file']['error'] !== UPLOAD_ERR_OK) {
            $this->redirect_with_message('invalid_file');
            return;
        }

        $file = $_FILES['csv_file'];
        
        // Validações do arquivo
        if ($file['size'] > 2 * 1024 * 1024) { // 2MB
            $this->redirect_with_message('error', __('Arquivo muito grande. Máximo 2MB.', 'wc-custom-reviews'));
            return;
        }

        if (pathinfo($file['name'], PATHINFO_EXTENSION) !== 'csv') {
            $this->redirect_with_message('error', __('Apenas arquivos CSV são aceitos.', 'wc-custom-reviews'));
            return;
        }

        // Processa o arquivo
        $import_mode = sanitize_text_field($_POST['import_mode']);
        $default_status = sanitize_text_field($_POST['default_status']);
        $download_images = isset($_POST['download_images']) && $_POST['download_images'] === '1';
        
        $result = $this->process_csv_file($file['tmp_name'], $import_mode, $default_status, $download_images);
        
        if ($result['success']) {
            $this->redirect_with_message('success', '', $result['imported'], $result['downloaded']);
        } else {
            $this->redirect_with_message('error', $result['error']);
        }
    }

    /**
     * NOVA FUNÇÃO: Processa o arquivo CSV com download de imagens
     */
    private function process_csv_file($file_path, $import_mode, $default_status, $download_images = true) {
        $db = WC_Custom_Reviews_Database::get_instance();
        $imported = 0;
        $downloaded = 0;
        $errors = array();
        
        // Abre o arquivo CSV
        if (($handle = fopen($file_path, 'r')) === FALSE) {
            return array('success' => false, 'error' => __('Não foi possível abrir o arquivo CSV.', 'wc-custom-reviews'));
        }

        // Lê o cabeçalho
        $header = fgetcsv($handle);
        if (!$header) {
            fclose($handle);
            return array('success' => false, 'error' => __('Arquivo CSV vazio ou inválido.', 'wc-custom-reviews'));
        }

        // Valida o cabeçalho
        $required_columns = array('id', 'rating', 'name', 'review');
        $optional_columns = array('email', 'photo');
        $all_columns = array_merge($required_columns, $optional_columns);
        
        foreach ($required_columns as $required) {
            if (!in_array($required, $header)) {
                fclose($handle);
                return array('success' => false, 'error' => sprintf(__('Coluna obrigatória "%s" não encontrada.', 'wc-custom-reviews'), $required));
            }
        }

        // Mapeia índices das colunas
        $column_map = array();
        foreach ($all_columns as $column) {
            $index = array_search($column, $header);
            if ($index !== false) {
                $column_map[$column] = $index;
            }
        }

        // Processa cada linha
        $line_number = 1;
        while (($data = fgetcsv($handle)) !== FALSE) {
            $line_number++;
            
            // Valida se a linha tem dados suficientes
            if (count($data) < count($required_columns)) {
                $errors[] = sprintf(__('Linha %d: dados insuficientes.', 'wc-custom-reviews'), $line_number);
                continue;
            }

            // Extrai dados da linha
            $review_data = array();
            
            // Campos obrigatórios
            $review_data['product_id'] = isset($column_map['id']) ? absint($data[$column_map['id']]) : 0;
            $review_data['rating'] = isset($column_map['rating']) ? absint($data[$column_map['rating']]) : 0;
            $review_data['customer_name'] = isset($column_map['name']) ? sanitize_text_field($data[$column_map['name']]) : '';
            $review_data['review_text'] = isset($column_map['review']) ? sanitize_textarea_field($data[$column_map['review']]) : '';
            
            // Campos opcionais
            $review_data['customer_email'] = isset($column_map['email']) && !empty($data[$column_map['email']]) 
                ? sanitize_email($data[$column_map['email']]) 
                : 'noreply@' . parse_url(home_url(), PHP_URL_HOST);
                
            $photo_url = isset($column_map['photo']) && !empty($data[$column_map['photo']]) 
                ? esc_url_raw($data[$column_map['photo']]) 
                : null;
            
            $review_data['status'] = $default_status;

            // Validações
            if (empty($review_data['product_id'])) {
                $errors[] = sprintf(__('Linha %d: ID do produto inválido.', 'wc-custom-reviews'), $line_number);
                continue;
            }

            if ($review_data['rating'] < 1 || $review_data['rating'] > 5) {
                $errors[] = sprintf(__('Linha %d: nota deve ser entre 1 e 5.', 'wc-custom-reviews'), $line_number);
                continue;
            }

            if (empty($review_data['customer_name'])) {
                $errors[] = sprintf(__('Linha %d: nome do cliente é obrigatório.', 'wc-custom-reviews'), $line_number);
                continue;
            }

            if (empty($review_data['review_text'])) {
                $errors[] = sprintf(__('Linha %d: texto da avaliação é obrigatório.', 'wc-custom-reviews'), $line_number);
                continue;
            }

            // Verifica se o produto existe
            $product = wc_get_product($review_data['product_id']);
            if (!$product || !$product->exists()) {
                $errors[] = sprintf(__('Linha %d: produto com ID %d não encontrado.', 'wc-custom-reviews'), $line_number, $review_data['product_id']);
                continue;
            }

            // NOVO: Processa download das imagens se necessário
            if ($download_images && !empty($photo_url)) {
                // Separa múltiplas URLs por vírgula
                $photo_urls = array_map('trim', explode(',', $photo_url));
                $image_urls_array = array();
                
                foreach ($photo_urls as $single_photo_url) {
                    if (empty($single_photo_url)) continue;
                    
                    $attachment_id = $this->download_and_store_image($single_photo_url, $review_data['product_id']);
                    if (!is_wp_error($attachment_id) && $attachment_id) {
                        $image_urls_array[] = wp_get_attachment_url($attachment_id);
                        $downloaded++;
                    } else {
                        // Se falhou o download, mantém a URL original
                        $image_urls_array[] = $single_photo_url;
                        $errors[] = sprintf(__('Linha %d: não foi possível baixar a imagem %s.', 'wc-custom-reviews'), $line_number, $single_photo_url);
                    }
                }
                
                // Armazena como JSON se houver múltiplas imagens, ou única URL se for só uma
                if (count($image_urls_array) > 1) {
                    $review_data['image_url'] = json_encode($image_urls_array);
                } elseif (count($image_urls_array) === 1) {
                    $review_data['image_url'] = $image_urls_array[0];
                } else {
                    $review_data['image_url'] = null;
                }
            } elseif (!empty($photo_url)) {
                // Sem download, mas ainda processa múltiplas URLs
                $photo_urls = array_map('trim', explode(',', $photo_url));
                if (count($photo_urls) > 1) {
                    $review_data['image_url'] = json_encode($photo_urls);
                } else {
                    $review_data['image_url'] = $photo_url;
                }
            } else {
                $review_data['image_url'] = null;
            }

            // Insere a avaliação
            $result = $db->insert_review($review_data);
            if ($result) {
                $imported++;
            } else {
                $errors[] = sprintf(__('Linha %d: erro ao inserir avaliação.', 'wc-custom-reviews'), $line_number);
            }
        }

        fclose($handle);

        if (!empty($errors) && $imported === 0) {
            return array('success' => false, 'error' => implode(' ', array_slice($errors, 0, 3)));
        }

        return array('success' => true, 'imported' => $imported, 'downloaded' => $downloaded, 'errors' => $errors);
    }

    /**
     * NOVA FUNÇÃO: Baixa e armazena imagem na biblioteca de mídia
     */
    private function download_and_store_image($url, $post_id = 0) {
        // Validação da URL
        if (!wp_http_validate_url($url)) {
            return new WP_Error('invalid_url', __('URL da imagem inválida.', 'wc-custom-reviews'));
        }

        // Carrega funções necessárias
        if (!function_exists('download_url') || !function_exists('media_handle_sideload')) {
            require_once ABSPATH . 'wp-admin/includes/image.php';
            require_once ABSPATH . 'wp-admin/includes/file.php';
            require_once ABSPATH . 'wp-admin/includes/media.php';
        }

        // Baixa o arquivo para diretório temporário
        $temp_file = download_url($url);

        // Verifica se houve erro no download
        if (is_wp_error($temp_file)) {
            return $temp_file;
        }

        // Prepara informações do arquivo
        $file_url_path = parse_url($url, PHP_URL_PATH);
        $file_info = wp_check_filetype($file_url_path);
        
        // Verifica se é um tipo de imagem válido
        $allowed_types = array('image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp');
        if (!in_array($file_info['type'], $allowed_types)) {
            @unlink($temp_file);
            return new WP_Error('invalid_image_type', __('Tipo de imagem não suportado.', 'wc-custom-reviews'));
        }

        // Cria array similar ao $_FILES
        $file = array(
            'tmp_name' => $temp_file,
            'type'     => $file_info['type'],
            'name'     => basename($file_url_path),
            'size'     => filesize($temp_file),
        );

        // Dados do post para o attachment
        $post_data = array(
            'post_title' => __('Avaliação de cliente', 'wc-custom-reviews'),
            'post_content' => __('Avaliação de cliente', 'wc-custom-reviews'),
        );

        // Move o arquivo para a biblioteca de mídia
        $attachment_id = media_handle_sideload($file, $post_id, null, $post_data);

        // Remove arquivo temporário
        @unlink($temp_file);

        return $attachment_id;
    }

    /**
     * NOVA FUNÇÃO: Redireciona com mensagem (atualizada para incluir contador de downloads)
     */
    private function redirect_with_message($result, $error = '', $imported = 0, $downloaded = 0) {
        $url = admin_url('admin.php?page=wc-custom-reviews-import&import_result=' . $result);
        
        if (!empty($error)) {
            $url .= '&error=' . urlencode($error);
        }
        
        if ($imported > 0) {
            $url .= '&imported=' . $imported;
        }
        
        if ($downloaded > 0) {
            $url .= '&downloaded=' . $downloaded;
        }
        
        wp_redirect($url);
        exit;
    }

    // ... resto dos métodos da classe original permanecem inalterados ...
    // (Aqui você manteria todos os outros métodos existentes da classe)

    /**
     * Inicializa configurações
     */
    public function init_settings() {
        register_setting('wc_custom_reviews_settings', 'wc_custom_reviews_options');

        // Seção de configurações visuais
        add_settings_section(
            'wc_custom_reviews_visual_section',
            __('Configurações Visuais', 'wc-custom-reviews'),
            array($this, 'visual_section_callback'),
            'wc_custom_reviews_settings'
        );

        // Campo cor das estrelas
        add_settings_field(
            'star_color',
            __('Cor das Estrelas', 'wc-custom-reviews'),
            array($this, 'star_color_callback'),
            'wc_custom_reviews_settings',
            'wc_custom_reviews_visual_section'
        );

        // Campo cor dos botões
        add_settings_field(
            'button_color',
            __('Cor dos Botões', 'wc-custom-reviews'),
            array($this, 'button_color_callback'),
            'wc_custom_reviews_settings',
            'wc_custom_reviews_visual_section'
        );

        // Campo de aprovação automática
        add_settings_field(
            'auto_approve',
            __('Aprovação de Reviews', 'wc-custom-reviews'),
            array($this, 'auto_approve_callback'),
            'wc_custom_reviews_settings',
            'wc_custom_reviews_visual_section'
        );

        // Campo para mostrar estrelas vazias
        add_settings_field(
            'show_empty_stars',
            __('Exibir Estrelas Vazias', 'wc-custom-reviews'),
            array($this, 'show_empty_stars_callback'),
            'wc_custom_reviews_settings',
            'wc_custom_reviews_visual_section'
        );

        // Campo para habilitar upload de imagens
        add_settings_field(
            'enable_image_upload',
            __('Upload de Imagens', 'wc-custom-reviews'),
            array($this, 'enable_image_upload_callback'),
            'wc_custom_reviews_settings',
            'wc_custom_reviews_visual_section'
        );

        // Campo para ordem dos comentários
        add_settings_field(
            'review_order',
            __('Ordem dos Comentários', 'wc-custom-reviews'),
            array($this, 'review_order_callback'),
            'wc_custom_reviews_settings',
            'wc_custom_reviews_visual_section'
        );

        // Campo para quantidade de reviews por página
        add_settings_field(
            'reviews_per_page',
            __('Reviews por Página', 'wc-custom-reviews'),
            array($this, 'reviews_per_page_callback'),
            'wc_custom_reviews_settings',
            'wc_custom_reviews_visual_section'
        );

        // Seção de shortcodes
        add_settings_section(
            'wc_custom_reviews_shortcode_section',
            __('Shortcodes Disponíveis', 'wc-custom-reviews'),
            array($this, 'shortcode_section_callback'),
            'wc_custom_reviews_settings'
        );
    }

    /**
     * Callback para campo de reviews por página
     */
    public function reviews_per_page_callback() {
        $options = get_option('wc_custom_reviews_options');
        $reviews_per_page = isset($options['reviews_per_page']) ? $options['reviews_per_page'] : 10;
        ?>
        <select name="wc_custom_reviews_options[reviews_per_page]" id="reviews_per_page">
            <option value="10" <?php selected($reviews_per_page, 10); ?>>10</option>
            <option value="15" <?php selected($reviews_per_page, 15); ?>>15</option>
            <option value="20" <?php selected($reviews_per_page, 20); ?>>20</option>
        </select>
        <p class="description"><?php _e('Quantidade de reviews exibidos por página no frontend.', 'wc-custom-reviews'); ?></p>
        <?php
    }

    /**
     * Página de configurações
     */
    public function admin_page_settings() {
        ?>
        <div class="wrap">
            <h1><?php _e('Custom Reviews - Configurações', 'wc-custom-reviews'); ?></h1>
            
            <form method="post" action="options.php">
                <?php
                settings_fields('wc_custom_reviews_settings');
                do_settings_sections('wc_custom_reviews_settings');
                submit_button();
                ?>
            </form>
        </div>
        <?php
    }

    /**
     * Página de comentários
     */
    
    /**
     * Página de comentários com funcionalidade de exclusão em massa
     * Substitua a função admin_page_comments() existente por esta versão
     */
    public function admin_page_comments() {
        $db = WC_Custom_Reviews_Database::get_instance();
        
        // Paginação
        $page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
        $per_page = 20; // Número de reviews por página
        $offset = ($page - 1) * $per_page;
        
        // Filtro por status
        $status_filter = isset($_GET['status']) ? sanitize_text_field($_GET['status']) : '';
        
        // Filtro por busca
        $search_query = isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '';
        
        // Obtém reviews - SEMPRE ordena por data no admin (independente da configuração do frontend)
        $reviews = $db->get_all_reviews($status_filter, $per_page, $offset, 'recent', $search_query);
        
        // Obtém total de reviews para paginação
        $total_reviews = $db->get_total_all_reviews($status_filter, $search_query);
        $total_pages = ceil($total_reviews / $per_page);

        ?>
        <div class="wrap">
            <h1><?php _e('Custom Reviews - Comentários', 'wc-custom-reviews'); ?>
                <a href="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=wc_custom_reviews_export_csv'), 'wc-custom-reviews-export-csv')); ?>" class="page-title-action">
                    <span class="dashicons dashicons-download" style="margin-top: 3px;"></span>
                    <?php _e('Exportar CSV', 'wc-custom-reviews'); ?>
                </a>
            </h1>
            
            <!-- Campo de busca -->
            <form method="get" action="" style="margin: 20px 0;">
                <input type="hidden" name="page" value="wc-custom-reviews-comments">
                <?php if (!empty($status_filter)) : ?>
                    <input type="hidden" name="status" value="<?php echo esc_attr($status_filter); ?>">
                <?php endif; ?>
                <p class="search-box">
                    <label class="screen-reader-text" for="review-search-input"><?php _e('Buscar reviews:', 'wc-custom-reviews'); ?></label>
                    <input type="search" id="review-search-input" name="s" value="<?php echo esc_attr($search_query); ?>" placeholder="<?php _e('Buscar por nome, email, comentário ou produto...', 'wc-custom-reviews'); ?>" style="width: 400px;">
                    <button type="submit" class="button"><?php _e('Buscar', 'wc-custom-reviews'); ?></button>
                    <?php if (!empty($search_query)) : ?>
                        <a href="<?php echo esc_url(admin_url('admin.php?page=wc-custom-reviews-comments' . (!empty($status_filter) ? '&status=' . $status_filter : ''))); ?>" class="button"><?php _e('Limpar busca', 'wc-custom-reviews'); ?></a>
                    <?php endif; ?>
                </p>
            </form>
            
            <!-- Filtros e Ações em Massa -->
            <div class="tablenav top">
                <div class="alignleft actions bulkactions">
                    <label for="bulk-action-selector-top" class="screen-reader-text"><?php _e('Selecionar ação em massa', 'wc-custom-reviews'); ?></label>
                    <select name="action" id="bulk-action-selector-top">
                        <option value="-1"><?php _e('Ações em massa', 'wc-custom-reviews'); ?></option>
                        <option value="delete"><?php _e('Mover para a Lixeira', 'wc-custom-reviews'); ?></option>
                    </select>
                    <input type="submit" id="doaction" class="button action" value="<?php _e('Aplicar', 'wc-custom-reviews'); ?>">
                </div>
                
                <div class="alignleft actions">
                    <select name="status" id="status-filter">
                        <option value=""><?php _e('Todos os status', 'wc-custom-reviews'); ?></option>
                        <option value="pendente" <?php selected($status_filter, 'pendente'); ?>><?php _e('pendente', 'wc-custom-reviews'); ?></option>
                        <option value="aprovado" <?php selected($status_filter, 'aprovado'); ?>><?php _e('aprovado', 'wc-custom-reviews'); ?></option>
                    </select>
                    <button type="button" class="button" onclick="filterReviews()"><?php _e('Filtrar', 'wc-custom-reviews'); ?></button>
                </div>
                
                <!-- Paginação Superior -->
                <?php if ($total_pages > 1) : ?>
                    <div class="tablenav-pages">
                        <span class="displaying-num">
                            <?php 
                            if (!empty($search_query)) {
                                printf(
                                    _n('%s resultado encontrado', '%s resultados encontrados', $total_reviews, 'wc-custom-reviews'), 
                                    number_format_i18n($total_reviews)
                                );
                            } else {
                                printf(
                                    _n('%s item', '%s itens', $total_reviews, 'wc-custom-reviews'), 
                                    number_format_i18n($total_reviews)
                                );
                            }
                            ?>
                        </span>
                        <?php
                        $base_args = array('page' => 'wc-custom-reviews-comments');
                        if (!empty($status_filter)) {
                            $base_args['status'] = $status_filter;
                        }
                        if (!empty($search_query)) {
                            $base_args['s'] = $search_query;
                        }
                        
                        $pagination_args = array(
                            'base' => add_query_arg(array_merge($base_args, array('paged' => '%#%'))),
                            'format' => '',
                            'prev_text' => '&laquo;',
                            'next_text' => '&raquo;',
                            'total' => $total_pages,
                            'current' => $page,
                            'show_all' => false,
                            'end_size' => 1,
                            'mid_size' => 2,
                            'type' => 'plain',
                        );
                        
                        echo '<span class="pagination-links">';
                        echo paginate_links($pagination_args);
                        echo '</span>';
                        ?>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- Tabela de reviews -->
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <td id="cb" class="manage-column column-cb check-column">
                            <label class="screen-reader-text" for="cb-select-all-1"><?php _e('Selecionar Todos', 'wc-custom-reviews'); ?></label>
                            <input id="cb-select-all-1" type="checkbox">
                        </td>
                        <th><?php _e('ID', 'wc-custom-reviews'); ?></th>
                        <th><?php _e('Produto', 'wc-custom-reviews'); ?></th>
                        <th><?php _e('Cliente', 'wc-custom-reviews'); ?></th>
                        <th><?php _e('Avaliação', 'wc-custom-reviews'); ?></th>
                        <th><?php _e('Comentário', 'wc-custom-reviews'); ?></th>
                        <th><?php _e('Imagem', 'wc-custom-reviews'); ?></th>
                        <th><?php _e('Status', 'wc-custom-reviews'); ?></th>
                        <th><?php _e('Data', 'wc-custom-reviews'); ?></th>
                        <th><?php _e('Ações', 'wc-custom-reviews'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($reviews)) : ?>
                        <?php foreach ($reviews as $review) : ?>
                            <tr id="review-<?php echo esc_attr($review->id); ?>">
                                <th scope="row" class="check-column">
                                    <input type="checkbox" name="review[]" value="<?php echo esc_attr($review->id); ?>" class="review-checkbox">
                                </th>
                                <td><?php echo esc_html($review->id); ?></td>
                                <td>
                                    <?php if ($review->product_name) : ?>
                                        <a href="<?php echo get_edit_post_link($review->product_id); ?>" target="_blank">
                                            <?php echo esc_html($review->product_name); ?>
                                        </a>
                                    <?php else : ?>
                                        <?php _e('Produto não encontrado', 'wc-custom-reviews'); ?>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <strong><?php echo esc_html($review->customer_name); ?></strong><br>
                                    <small><?php echo esc_html($review->customer_email); ?></small>
                                </td>
                                <td>
                                    <div class="stars-display">
                                        <?php echo $this->render_stars($review->rating); ?>
                                        <span>(<?php echo esc_html($review->rating); ?>/5)</span>
                                    </div>
                                </td>
                                <td>
                                    <div class="review-text" style="max-width: 300px; overflow: hidden;">
                                        <?php echo esc_html(wp_trim_words($review->review_text, 20)); ?>
                                    </div>
                                </td>
                                <td>
                                    <?php 
                                    // Decodifica múltiplas imagens se estiver em JSON
                                    $image_urls = !empty($review->image_url) ? json_decode($review->image_url, true) : array();
                                    $video_url = !empty($review->video_url) ? $review->video_url : null;
                                    
                                    // Prioriza vídeo
                                    if (!empty($video_url)) : 
                                        $video_url_with_time = $video_url . '#t=0.1';
                                        ?>
                                        <div style="position: relative; width: 50px; height: 50px; cursor: pointer;" 
                                             onclick="openImageModal('<?php echo esc_url($video_url); ?>', <?php echo $review->id; ?>)">
                                            <video src="<?php echo esc_url($video_url_with_time); ?>" 
                                                   muted 
                                                   preload="metadata"
                                                   style="width: 50px; height: 50px; object-fit: cover; border-radius: 3px; background: #000;"></video>
                                            <div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); color: white; font-size: 16px;">
                                                <i class="dashicons dashicons-controls-play"></i>
                                            </div>
                                        </div>
                                    <?php elseif (is_array($image_urls)) : 
                                        // Múltiplas imagens
                                        ?>
                                        <div class="admin-review-images" style="display: flex; gap: 3px; flex-wrap: wrap;">
                                            <?php foreach ($image_urls as $index => $img_url) : ?>
                                                <img src="<?php echo esc_url($img_url); ?>" 
                                                    alt="<?php echo esc_attr(sprintf(__('Imagem %d', 'wc-custom-reviews'), $index + 1)); ?>" 
                                                    style="width: 40px; height: 40px; object-fit: cover; cursor: pointer; border-radius: 3px;" 
                                                    onclick="openImageModal('<?php echo esc_url($img_url); ?>', <?php echo $review->id; ?>)">
                                            <?php endforeach; ?>
                                        </div>
                                    <?php elseif (!empty($review->image_url)) : 
                                        // Imagem única (formato antigo)
                                        ?>
                                        <img src="<?php echo esc_url($review->image_url); ?>" 
                                            alt="<?php echo esc_attr__('Imagem da Avaliação', 'wc-custom-reviews'); ?>" 
                                            style="width: 50px; height: 50px; object-fit: cover; cursor: pointer; border-radius: 3px;" 
                                            onclick="openImageModal('<?php echo esc_url($review->image_url); ?>', <?php echo $review->id; ?>)">
                                    <?php else : ?>
                                        <span class="dashicons dashicons-camera" style="color: #ccc; font-size: 20px;" title="<?php _e('Sem imagem', 'wc-custom-reviews'); ?>"></span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="status-badge status-<?php echo esc_attr($review->status); ?>">
                                        <?php echo esc_html(ucfirst($review->status)); ?>
                                    </span>
                                </td>
                                <td><?php echo esc_html(date_i18n(get_option('date_format'), strtotime($review->created_at))); ?></td>
                                <td>
                                    <div class="row-actions">
                                        <?php if ($review->status !== 'aprovado') : ?>
                                            <span class="approve">
                                                <a href="#" onclick="updateReviewStatus(<?php echo $review->id; ?>, 'aprovado')" class="button button-small button-primary">
                                                    <?php _e('Aprovar', 'wc-custom-reviews'); ?>
                                                </a>
                                            </span>
                                        <?php endif; ?>
                                        
                                        <span class="edit">
                                            <a href="#" onclick="editReview(<?php echo $review->id; ?>)" class="button button-small">
                                                <?php _e('Editar', 'wc-custom-reviews'); ?>
                                            </a>
                                        </span>
                                        
                                        <span class="delete">
                                            <a href="#" onclick="deleteReview(<?php echo $review->id; ?>)" class="button button-small button-link-delete">
                                                <?php _e('Excluir', 'wc-custom-reviews'); ?>
                                            </a>
                                        </span>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else : ?>
                        <tr>
                            <td colspan="10"><?php _e('Nenhum comentário encontrado.', 'wc-custom-reviews'); ?></td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>

             <!-- Modal de Edição de Review -->
        <div id="edit-review-modal" style="display: none; position: fixed; z-index: 100000; left: 0; top: 0; width: 100%; height: 100%; overflow: auto; background-color: rgba(0,0,0,0.4);">
            <div style="background-color: #fefefe; margin: 5% auto; padding: 20px; border: 1px solid #888; width: 80%; max-width: 600px; border-radius: 5px;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                    <h2><?php _e('Editar Avaliação', 'wc-custom-reviews'); ?></h2>
                    <span onclick="closeEditModal()" style="color: #aaa; float: right; font-size: 28px; font-weight: bold; cursor: pointer;">&times;</span>
                </div>
                
                <form id="edit-review-form">
                    <input type="hidden" id="edit_review_id" name="review_id" />
                    
                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="edit_customer_name"><?php _e('Nome do Cliente', 'wc-custom-reviews'); ?></label>
                            </th>
                            <td>
                                <input type="text" id="edit_customer_name" name="customer_name" class="regular-text" required />
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">
                                <label for="edit_customer_email"><?php _e('Email do Cliente', 'wc-custom-reviews'); ?></label>
                            </th>
                            <td>
                                <input type="email" id="edit_customer_email" name="customer_email" class="regular-text" />
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">
                                <label for="edit_rating"><?php _e('Avaliação', 'wc-custom-reviews'); ?></label>
                            </th>
                            <td>
                                <select id="edit_rating" name="rating" required>
                                    <option value="1">1 <?php _e('estrela', 'wc-custom-reviews'); ?></option>
                                    <option value="2">2 <?php _e('estrelas', 'wc-custom-reviews'); ?></option>
                                    <option value="3">3 <?php _e('estrelas', 'wc-custom-reviews'); ?></option>
                                    <option value="4">4 <?php _e('estrelas', 'wc-custom-reviews'); ?></option>
                                    <option value="5">5 <?php _e('estrelas', 'wc-custom-reviews'); ?></option>
                                </select>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">
                                <label for="edit_status"><?php _e('Status', 'wc-custom-reviews'); ?></label>
                            </th>
                            <td>
                                <select id="edit_status" name="status">
                                    <option value="pendente"><?php _e('pendente', 'wc-custom-reviews'); ?></option>
                                    <option value="aprovado"><?php _e('aprovado', 'wc-custom-reviews'); ?></option>
                                </select>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">
                                <label for="edit_review_text"><?php _e('Comentário', 'wc-custom-reviews'); ?></label>
                            </th>
                            <td>
                                <textarea id="edit_review_text" name="review_text" rows="4" class="large-text" required></textarea>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">
                                <label for="edit_image_url"><?php _e('Imagens e Vídeos', 'wc-custom-reviews'); ?></label>
                            </th>
                            <td>
                                <div id="current-review-images-container" style="margin-bottom: 10px; display: flex; flex-wrap: wrap; gap: 10px;">
                                    <p id="no-image-text" style="color: #666; font-style: italic;"><?php _e('Nenhuma mídia selecionada', 'wc-custom-reviews'); ?></p>
                                </div>
                                
                                <input type="hidden" id="edit_image_url" name="image_url" />
                                <input type="hidden" id="edit_video_url" name="video_url" />
                                
                                <button type="button" id="upload-image-btn" class="button"><?php _e('Selecionar Mídias', 'wc-custom-reviews'); ?></button>
                                <button type="button" id="remove-all-images-btn" class="button" style="display: none;"><?php _e('Remover Todas', 'wc-custom-reviews'); ?></button>
                                <p class="description"><?php _e('Você pode selecionar até 10 imagens e 1 vídeo para esta avaliação', 'wc-custom-reviews'); ?></p>
                            </td>
                        </tr>
                    </table>
                    
                    <div style="text-align: right; margin-top: 20px;">
                        <button type="button" onclick="closeEditModal()" class="button"><?php _e('Cancelar', 'wc-custom-reviews'); ?></button>
                        <button type="submit" class="button button-primary"><?php _e('Salvar Alterações', 'wc-custom-reviews'); ?></button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Modal de Visualização de Imagem -->
        <div id="image-view-modal" style="display: none; position: fixed; z-index: 100001; left: 0; top: 0; width: 100%; height: 100%; overflow: auto; background-color: rgba(0,0,0,0.9);">
            <div style="position: relative; margin: auto; display: block; width: 80%; max-width: 700px; margin-top: 50px;">
                <img id="modal-image" style="width: 100%; height: auto;" />
                
                <div style="position: absolute; top: 15px; right: 35px;">
                    <span onclick="closeImageModal()" style="color: #f1f1f1; font-size: 40px; font-weight: bold; cursor: pointer;">&times;</span>
                </div>
                
                <div style="text-align: center; margin-top: 20px;">
                    <button type="button" onclick="editReviewFromImage()" class="button button-primary"><?php _e('Editar Avaliação', 'wc-custom-reviews'); ?></button>
                </div>
            </div>
        </div>
            
            <!-- Paginação Inferior -->
            <div class="tablenav bottom">
                <div class="alignleft actions bulkactions">
                    <label for="bulk-action-selector-bottom" class="screen-reader-text"><?php _e('Selecionar ação em massa', 'wc-custom-reviews'); ?></label>
                    <select name="action2" id="bulk-action-selector-bottom">
                        <option value="-1"><?php _e('Ações em massa', 'wc-custom-reviews'); ?></option>
                        <option value="delete"><?php _e('Mover para a Lixeira', 'wc-custom-reviews'); ?></option>
                    </select>
                    <input type="submit" id="doaction2" class="button action" value="<?php _e('Aplicar', 'wc-custom-reviews'); ?>">
                </div>
                
                <?php if ($total_pages > 1) : ?>
                    <div class="tablenav-pages">
                        <span class="displaying-num">
                            <?php 
                            printf(
                                _n('%s item', '%s itens', $total_reviews, 'wc-custom-reviews'), 
                                number_format_i18n($total_reviews)
                            ); 
                            ?>
                        </span>
                        <?php
                        echo '<span class="pagination-links">';
                        echo paginate_links($pagination_args);
                        echo '</span>';
                        ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- JavaScript para funcionalidade de exclusão em massa e paginação -->
        <script type="text/javascript">
        jQuery(document).ready(function($) {
            // Checkbox "Selecionar Todos"
            $('#cb-select-all-1').on('change', function() {
                $('.review-checkbox').prop('checked', this.checked);
            });
            
            // Atualiza o checkbox "Selecionar Todos" quando checkboxes individuais mudam
            $('.review-checkbox').on('change', function() {
                var total = $('.review-checkbox').length;
                var checked = $('.review-checkbox:checked').length;
                $('#cb-select-all-1').prop('checked', total === checked);
            });
            
            // Ação em massa (superior e inferior)
            $('#doaction, #doaction2').on('click', function(e) {
                e.preventDefault();
                
                var action = $(this).attr('id') === 'doaction' ? 
                    $('#bulk-action-selector-top').val() : 
                    $('#bulk-action-selector-bottom').val();
                    
                var selectedReviews = $('.review-checkbox:checked').map(function() {
                    return this.value;
                }).get();
                
                if (action === '-1') {
                    alert('<?php _e('Selecione uma ação.', 'wc-custom-reviews'); ?>');
                    return;
                }
                
                if (selectedReviews.length === 0) {
                    alert('<?php _e('Selecione pelo menos um review.', 'wc-custom-reviews'); ?>');
                    return;
                }
                
                if (action === 'delete') {
                    if (!confirm('<?php _e('Tem certeza que deseja excluir os reviews selecionados? Esta ação não pode ser desfeita.', 'wc-custom-reviews'); ?>')) {
                        return;
                    }
                    
                    // Executa exclusão em massa via AJAX
                    $.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        data: {
                            action: 'wc_custom_reviews_bulk_delete',
                            review_ids: selectedReviews,
                            nonce: '<?php echo wp_create_nonce('wc_custom_reviews_bulk_action'); ?>'
                        },
                        beforeSend: function() {
                            $('#doaction, #doaction2').prop('disabled', true).val('<?php _e('Processando...', 'wc-custom-reviews'); ?>');
                        },
                        success: function(response) {
                            if (response.success) {
                                alert(response.data.message);
                                // Recarrega a página para atualizar a paginação
                                window.location.reload();
                            } else {
                                alert('<?php _e('Erro: ', 'wc-custom-reviews'); ?>' + response.data);
                            }
                        },
                        error: function() {
                            alert('<?php _e('Erro na comunicação com o servidor.', 'wc-custom-reviews'); ?>');
                        },
                        complete: function() {
                            $('#doaction, #doaction2').prop('disabled', false).val('<?php _e('Aplicar', 'wc-custom-reviews'); ?>');
                        }
                    });
                }
            });
        });
        
        // Função para filtrar reviews (mantém a página atual se possível)
        function filterReviews() {
            var status = document.getElementById('status-filter').value;
            var url = new URL(window.location);
            
            if (status) {
                url.searchParams.set('status', status);
            } else {
                url.searchParams.delete('status');
            }
            
            // Remove a paginação ao filtrar para começar da primeira página
            url.searchParams.delete('paged');
            
            window.location.href = url.toString();
        }
        </script>
        
        <style>
        .check-column {
            width: 2.2em;
            padding: 6px 0 25px;
            vertical-align: top;
        }
        .column-cb {
            width: 2.2em;
        }
        .status-badge {
            padding: 2px 8px;
            border-radius: 3px;
            font-size: 11px;
            font-weight: bold;
            text-transform: uppercase;
        }
        .status-pendente {
            background-color: #f56e28;
            color: white;
        }
        .status-aprovado {
            background-color: #46b450;
            color: white;
        }
        
        /* Estilos para paginação */
        .tablenav-pages {
            float: right;
            margin: 0;
        }
        .tablenav-pages .displaying-num {
            color: #646970;
            font-style: italic;
            margin-right: 10px;
        }
        .pagination-links {
            display: inline-block;
        }
        .pagination-links a,
        .pagination-links span {
            color: #0073aa;
            text-decoration: none;
            padding: 3px 5px;
            margin: 0 1px;
            border: 1px solid transparent;
            border-radius: 3px;
        }
        .pagination-links a:hover {
            background-color: #0073aa;
            color: #fff;
        }
        .pagination-links .current {
            background-color: #0073aa;
            color: #fff;
            border-color: #0073aa;
        }
        .pagination-links .dots {
            color: #646970;
            border: none;
        }
        
        /* Responsividade para telas menores */
        @media screen and (max-width: 782px) {
            .tablenav-pages {
                float: none;
                clear: both;
                text-align: center;
                margin-top: 10px;
            }
            
            .displaying-num {
                display: block;
                margin-bottom: 5px;
            }
        }
        </style>
        <?php
    }

    /**
     * Callback da seção visual
     */
    public function visual_section_callback() {
        echo '<p>' . __('Configure as cores e aparência dos elementos do plugin.', 'wc-custom-reviews') . '</p>';
    }

    /**
     * Callback da seção de shortcodes
     */
    public function shortcode_section_callback() {
        echo '<div class="shortcode-info">';
        echo '<h3>' . __('Shortcodes Disponíveis:', 'wc-custom-reviews') . '</h3>';
        echo '<p><strong>[wc_custom_reviews_stars product_id="123"]</strong> - ' . __('Exibe apenas as estrelas de avaliação do produto', 'wc-custom-reviews') . '</p>';
        echo '<p><strong>[wc_custom_reviews_widget product_id="123"]</strong> - ' . __('Exibe o widget completo de comentários e formulário de avaliação', 'wc-custom-reviews') . '</p>';
        echo '<p><em>' . __('Substitua "123" pelo ID do produto desejado. Se não especificar product_id, será usado o produto atual.', 'wc-custom-reviews') . '</em></p>';
        echo '</div>';
    }

    /**
     * Callback do campo cor das estrelas
     */
    public function star_color_callback() {
        $options = get_option('wc_custom_reviews_options');
        $value = isset($options['star_color']) ? $options['star_color'] : '#ffb400';
        ?>
        <input type="color" name="wc_custom_reviews_options[star_color]" value="<?php echo esc_attr($value); ?>" />
        <p class="description"><?php _e('Escolha a cor das estrelas de avaliação.', 'wc-custom-reviews'); ?></p>
        <?php
    }

    /**
     * Callback do campo cor dos botões
     */
    public function button_color_callback() {
        $options = get_option('wc_custom_reviews_options');
        $value = isset($options['button_color']) ? $options['button_color'] : '#0073aa';
        ?>
        <input type="color" name="wc_custom_reviews_options[button_color]" value="<?php echo esc_attr($value); ?>" />
        <p class="description"><?php _e('Escolha a cor dos botões do plugin.', 'wc-custom-reviews'); ?></p>
        <?php
    }

    /**
     * Callback do campo de aprovação automática
     */
    public function auto_approve_callback() {
        $options = get_option('wc_custom_reviews_options');
        $value = isset($options['auto_approve']) ? $options['auto_approve'] : 'manual';
        ?>
        <select name="wc_custom_reviews_options[auto_approve]">
            <option value="manual" <?php selected($value, 'manual'); ?>><?php _e('Aprovação Manual (Recomendado)', 'wc-custom-reviews'); ?></option>
            <option value="auto" <?php selected($value, 'auto'); ?>><?php _e('Aprovação Automática', 'wc-custom-reviews'); ?></option>
        </select>
        <p class="description">
            <?php _e('Escolha se os novos reviews devem ser aprovados automaticamente ou aguardar aprovação manual.', 'wc-custom-reviews'); ?>
        </p>
        <?php
    }

    /**
     * Callback do campo de exibição de estrelas vazias
     */
    public function show_empty_stars_callback() {
        $options = get_option('wc_custom_reviews_options');
        $value = isset($options['show_empty_stars']) ? $options['show_empty_stars'] : 'yes';
        ?>
        <select name="wc_custom_reviews_options[show_empty_stars]">
            <option value="yes" <?php selected($value, 'yes'); ?>><?php _e('Mostrar estrelas vazias', 'wc-custom-reviews'); ?></option>
            <option value="no" <?php selected($value, 'no'); ?>><?php _e('Ocultar quando não há avaliações', 'wc-custom-reviews'); ?></option>
        </select>
        <p class="description">
            <?php _e('Escolha se as estrelas devem ser exibidas mesmo quando o produto não possui avaliações.', 'wc-custom-reviews'); ?>
        </p>
        <?php
    }

    /**
     * Callback do campo de upload de imagens
     */
    public function enable_image_upload_callback() {
        $options = get_option('wc_custom_reviews_options');
        $value = isset($options['enable_image_upload']) ? $options['enable_image_upload'] : 'yes';
        ?>
        <select name="wc_custom_reviews_options[enable_image_upload]">
            <option value="yes" <?php selected($value, 'yes'); ?>><?php _e('Permitir upload de imagens', 'wc-custom-reviews'); ?></option>
            <option value="no" <?php selected($value, 'no'); ?>><?php _e('Desabilitar upload de imagens', 'wc-custom-reviews'); ?></option>
        </select>
        <p class="description">
            <?php _e('Permite que os clientes enviem imagens junto com suas avaliações.', 'wc-custom-reviews'); ?>
        </p>
        <?php
    }

    /**
     * Callback para o campo de ordem dos comentários
     */
    public function review_order_callback() {
        $options = get_option('wc_custom_reviews_options');
        $value = isset($options['review_order']) ? $options['review_order'] : 'recent';
        ?>
        <select name="wc_custom_reviews_options[review_order]">
            <option value="recent" <?php selected($value, 'recent'); ?>><?php _e('Mais recentes primeiro', 'wc-custom-reviews'); ?></option>
            <option value="photo" <?php selected($value, 'photo'); ?>><?php _e('Com foto primeiro', 'wc-custom-reviews'); ?></option>
        </select>
        <p class="description">
            <?php _e('Escolha a ordem de exibição dos comentários.', 'wc-custom-reviews'); ?>
        </p>
        <?php
    }

    /**
     * Renderiza estrelas para exibição
     */
    private function render_stars($rating) {
    $rating = intval($rating);
        $stars = '';
        
        for ($i = 1; $i <= 5; $i++) {
            if ($i <= $rating) {
                $stars .= '<span class="dashicons dashicons-star-filled" style="color: #ffb400; font-size: 16px;"></span>';
            } else {
                $stars .= '<span class="dashicons dashicons-star-empty" style="color: #ccc; font-size: 16px;"></span>';
            }
        }
        
        return $stars;
    }

    /**
     * Enfileira scripts do admin
     */
    public function enqueue_admin_scripts($hook) {
        if (strpos($hook, 'wc-custom-reviews') === false) {
            return;
        }

        wp_enqueue_script('jquery');
        wp_enqueue_media();
        
        wp_enqueue_script(
            'wc-custom-reviews-admin',
            WC_CUSTOM_REVIEWS_PLUGIN_URL . 'assets/js/admin.js',
            array('jquery'),
            WC_CUSTOM_REVIEWS_VERSION,
            true
        );

        wp_localize_script('wc-custom-reviews-admin', 'wcCustomReviewsAdmin', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('wc_custom_reviews_admin_nonce'),
            'strings' => array(
                'confirm_delete' => __('Tem certeza que deseja excluir esta avaliação?', 'wc-custom-reviews'),
                'error' => __('Erro ao processar solicitação.', 'wc-custom-reviews'),
                'success' => __('Operação realizada com sucesso.', 'wc-custom-reviews'),
            )
        ));

        wp_enqueue_style(
            'wc-custom-reviews-admin',
            WC_CUSTOM_REVIEWS_PLUGIN_URL . 'assets/css/admin.css',
            array(),
            WC_CUSTOM_REVIEWS_VERSION
        );
    }

    /**
     * AJAX - Atualiza status do review
     */
    public function ajax_update_review_status() {
        check_ajax_referer('wc_custom_reviews_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_die(__('Sem permissão.', 'wc-custom-reviews'));
        }

        $review_id = intval($_POST['review_id']);
        $status = sanitize_text_field($_POST['status']);

        $db = WC_Custom_Reviews_Database::get_instance();
        $result = $db->update_review_status($review_id, $status);

        if ($result) {
            wp_send_json_success(array('message' => __('Status atualizado com sucesso.', 'wc-custom-reviews')));
        } else {
            wp_send_json_error(array('message' => __('Erro ao atualizar status.', 'wc-custom-reviews')));
        }
    }

    /**
     * AJAX - Deleta review
     */
    public function ajax_delete_review() {
        check_ajax_referer('wc_custom_reviews_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_die(__('Sem permissão.', 'wc-custom-reviews'));
        }

        $review_id = intval($_POST['review_id']);

        $db = WC_Custom_Reviews_Database::get_instance();
        $result = $db->delete_review($review_id);

        if ($result) {
            wp_send_json_success(array('message' => __('Comentário excluído com sucesso.', 'wc-custom-reviews')));
        } else {
            wp_send_json_error(array('message' => __('Erro ao excluir comentário.', 'wc-custom-reviews')));
        }
    }

    /**
     * AJAX - Obtém dados do review para edição
     */
    public function ajax_get_review_for_edit() {
        check_ajax_referer('wc_custom_reviews_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_die(__('Sem permissão.', 'wc-custom-reviews'));
        }

        $review_id = intval($_POST['review_id']);

        global $wpdb;
        $db = WC_Custom_Reviews_Database::get_instance();
        $table_name = $db->get_table_name();
        
        $review = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table_name} WHERE id = %d",
            $review_id
        ));

        if ($review) {
            wp_send_json_success(array(
                'review' => $review
            ));
        } else {
            wp_send_json_error(array('message' => __('Review não encontrado.', 'wc-custom-reviews')));
        }
    }

    /**
     * AJAX - Salva edição de review
     */
    public function ajax_save_review_edit() {
        check_ajax_referer('wc_custom_reviews_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permissão negada.', 'wc-custom-reviews')));
        }

        $review_id = intval($_POST['review_id']);
        $customer_name = sanitize_text_field($_POST['customer_name']);
        $customer_email = sanitize_email($_POST['customer_email']);
        $rating = intval($_POST['rating']);
        $status = sanitize_text_field($_POST['status']);
        $review_text = sanitize_textarea_field($_POST['review_text']);
        
        // Processa múltiplas imagens
        $image_url = '';
        if (!empty($_POST['image_url'])) {
            $raw_value = stripslashes($_POST['image_url']); // Remove escapes do jQuery
            
            // Verifica se já é JSON válido
            $decoded = json_decode($raw_value, true);
            if (is_array($decoded)) {
                // Já é JSON, sanitiza cada URL
                $sanitized_urls = array_map('esc_url_raw', $decoded);
                $image_url = json_encode($sanitized_urls);
            } else {
                // É uma URL única, sanitiza
                $image_url = esc_url_raw($raw_value);
            }
        }
        
        // Processa vídeo
        $video_url = '';
        if (!empty($_POST['video_url'])) {
            $video_url = esc_url_raw(stripslashes($_POST['video_url']));
        }

        if (empty($review_id) || empty($customer_name) || empty($customer_email)) {
            wp_send_json_error(array('message' => __('Dados obrigatórios não preenchidos.', 'wc-custom-reviews')));
        }

        global $wpdb;
        $db = WC_Custom_Reviews_Database::get_instance();
        $table_name = $db->get_table_name();

        $result = $wpdb->update(
            $table_name,
            array(
                'customer_name' => $customer_name,
                'customer_email' => $customer_email,
                'rating' => $rating,
                'status' => $status,
                'review_text' => $review_text,
                'image_url' => $image_url,
                'video_url' => $video_url
            ),
            array('id' => $review_id),
            array('%s', '%s', '%d', '%s', '%s', '%s', '%s'),
            array('%d')
        );

        if ($result !== false) {
            wp_send_json_success(array('message' => __('Review atualizado com sucesso!', 'wc-custom-reviews')));
        } else {
            wp_send_json_error(array('message' => __('Erro ao atualizar review.', 'wc-custom-reviews')));
        }
    }

    /**
     * Handler para exportação CSV
     * Exporta todos os reviews no formato compatível com importação
     */
    public function handle_csv_export() {
        // Verifica permissões
        if (!current_user_can('manage_options')) {
            wp_die(__('Você não tem permissão para acessar esta página.', 'wc-custom-reviews'));
        }

        // Verifica nonce
        check_admin_referer('wc-custom-reviews-export-csv', '_wpnonce');

        global $wpdb;
        $db = WC_Custom_Reviews_Database::get_instance();
        $table_name = $db->get_table_name();

        // Busca TODOS os reviews (sem limite)
        $reviews = $wpdb->get_results("SELECT * FROM {$table_name} ORDER BY id ASC");

        // Define headers para download
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="wc-custom-reviews-' . date('Y-m-d-His') . '.csv"');
        header('Pragma: no-cache');
        header('Expires: 0');

        // Abre output buffer
        $output = fopen('php://output', 'w');

        // Escreve cabeçalho do CSV (formato compatível com importação)
        fputcsv($output, array('id', 'rating', 'name', 'email', 'review', 'photo'));

        // Escreve cada review
        if (!empty($reviews)) {
            foreach ($reviews as $review) {
                // Decodifica múltiplas imagens se estiver em JSON
                $photo_value = '';
                if (!empty($review->image_url)) {
                    $decoded = json_decode($review->image_url, true);
                    if (is_array($decoded)) {
                        // Se for array, junta com vírgulas
                        $photo_value = implode(',', $decoded);
                    } else {
                        // Se não for JSON, é uma URL única
                        $photo_value = $review->image_url;
                    }
                }
                
                $row = array(
                    $review->product_id,                                    // id (product_id)
                    $review->rating,                                        // rating
                    $review->customer_name,                                 // name
                    $review->customer_email,                                // email
                    $review->review_text,                                   // review
                    $photo_value                                            // photo (URLs separadas por vírgula)
                );
                fputcsv($output, $row);
            }
        }

        fclose($output);
        exit;
    }

    /**
     * AJAX - Processa todos os duplicados em lote
     */
    public function ajax_process_all_duplicates() {
        // Verifica nonce
        if (!wp_verify_nonce($_POST['nonce'], 'wc_custom_reviews_process_all')) {
            wp_send_json_error(array('message' => __('Erro de segurança.', 'wc-custom-reviews')));
        }
        
        // Verifica permissões
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Sem permissão.', 'wc-custom-reviews')));
        }
        
        $batch_size = isset($_POST['batch_size']) ? intval($_POST['batch_size']) : 5;
        $filters = isset($_POST['filters']) ? $_POST['filters'] : array();
        
        // Sanitiza filtros
        $filters = array(
            'same_product' => isset($filters['same_product']) && $filters['same_product'] === 'true',
            'same_name' => isset($filters['same_name']) && $filters['same_name'] === 'true',
            'same_text' => isset($filters['same_text']) && $filters['same_text'] === 'true',
        );
        
        $db = WC_Custom_Reviews_Database::get_instance();
        
        // Busca duplicados sempre do offset 0 (pois após excluir, a lista muda)
        $duplicate_groups = $db->find_duplicate_reviews($batch_size, 0, $filters);
        
        $total_deleted = 0;
        $processed = 0;
        
        foreach ($duplicate_groups as $group) {
            $reviews = $group['reviews'];
            
            if (count($reviews) > 1) {
                // Remove o primeiro (mais antigo) da lista
                array_shift($reviews);
                
                // Exclui os demais
                foreach ($reviews as $review) {
                    if ($db->delete_review($review->id)) {
                        $total_deleted++;
                    }
                }
            }
            
            $processed++;
        }
        
        wp_send_json_success(array(
            'processed' => $processed,
            'deleted' => $total_deleted,
            'message' => sprintf(__('%d reviews excluídos.', 'wc-custom-reviews'), $total_deleted)
        ));
    }

    /**
     * Página de reviews duplicados
     */
    public function admin_page_duplicates() {
        $db = WC_Custom_Reviews_Database::get_instance();
        
        // Paginação
        $page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
        $per_page = 10; // Grupos por página
        $offset = ($page - 1) * $per_page;
        
        // Filtros personalizados
        $filters = array(
            'same_product' => isset($_GET['same_product']) ? true : false,
            'same_name' => isset($_GET['same_name']) ? true : false,
            'same_text' => isset($_GET['same_text']) ? true : false,
        );
        
        // Se nenhum filtro foi selecionado na primeira visita, ativa "mesmo texto" como padrão
        if (!isset($_GET['same_product']) && !isset($_GET['same_name']) && !isset($_GET['same_text']) && !isset($_GET['paged'])) {
            $filters['same_text'] = true;
        }
        
        $duplicates = $db->find_duplicate_reviews($per_page, $offset, $filters);
        $total_groups = $db->count_duplicate_groups($filters);
        $total_pages = ceil($total_groups / $per_page);
        
        ?>
        <div class="wrap">
            <h1><?php _e('Custom Reviews - Duplicados Detectados', 'wc-custom-reviews'); ?></h1>
            
            <!-- Filtros Personalizados -->
            <div style="background: #fff; padding: 15px; margin: 20px 0; border: 1px solid #ddd; border-radius: 4px;">
                <h3 style="margin-top: 0;"><?php _e('🔍 Filtros de Detecção', 'wc-custom-reviews'); ?></h3>
                <form method="get" action="" style="display: flex; gap: 20px; align-items: center; flex-wrap: wrap;">
                    <input type="hidden" name="page" value="wc-custom-reviews-duplicates">
                    
                    <label style="display: flex; align-items: center; gap: 5px;">
                        <input type="checkbox" name="same_product" value="1" <?php checked($filters['same_product']); ?>>
                        <strong><?php _e('Mesmo Produto', 'wc-custom-reviews'); ?></strong>
                    </label>
                    
                    <label style="display: flex; align-items: center; gap: 5px;">
                        <input type="checkbox" name="same_name" value="1" <?php checked($filters['same_name']); ?>>
                        <strong><?php _e('Mesmo Nome', 'wc-custom-reviews'); ?></strong>
                    </label>
                    
                    <label style="display: flex; align-items: center; gap: 5px;">
                        <input type="checkbox" name="same_text" value="1" <?php checked($filters['same_text']); ?>>
                        <strong><?php _e('Mesmo Texto', 'wc-custom-reviews'); ?></strong>
                    </label>
                    
                    <button type="submit" class="button button-primary"><?php _e('Aplicar Filtros', 'wc-custom-reviews'); ?></button>
                    
                    <?php if ($filters['same_product'] || $filters['same_name'] || !$filters['same_text']) : ?>
                        <a href="<?php echo esc_url(admin_url('admin.php?page=wc-custom-reviews-duplicates&same_text=1')); ?>" class="button"><?php _e('Limpar Filtros', 'wc-custom-reviews'); ?></a>
                    <?php endif; ?>
                </form>
                <p class="description" style="margin: 10px 0 0 0;">
                    <?php _e('Selecione uma ou mais opções para definir o que considera duplicado. Exemplo: "Mesmo Produto + Mesmo Nome" encontra reviews do mesmo cliente no mesmo produto.', 'wc-custom-reviews'); ?>
                </p>
            </div>
            
            <?php if ($total_groups == 0) : ?>
                <div class="notice notice-success">
                    <p><?php _e('✅ Nenhum review duplicado encontrado! Seu banco de dados está limpo.', 'wc-custom-reviews'); ?></p>
                </div>
            <?php else : ?>
                <div class="notice notice-warning">
                    <p><?php printf(__('⚠️ Encontramos %d grupo(s) de reviews com texto idêntico. Revise abaixo:', 'wc-custom-reviews'), $total_groups); ?></p>
                </div>
                
                <!-- Botão Global para Processar Todos -->
                <div style="background: #fff; padding: 20px; margin: 20px 0; border: 2px solid #dc3232; border-radius: 4px;">
                    <h3 style="margin-top: 0; color: #dc3232;">⚡ <?php _e('Ação Global', 'wc-custom-reviews'); ?></h3>
                    <p><?php _e('Processe TODOS os grupos de duplicados de uma vez. Esta ação irá manter o review mais antigo de cada grupo e excluir todos os duplicados.', 'wc-custom-reviews'); ?></p>
                    <button type="button" id="process-all-duplicates" class="button button-primary button-large" style="background: #dc3232; border-color: #dc3232;">
                        <?php printf(__('Processar Todos os %d Grupos (Manter Antigos)', 'wc-custom-reviews'), $total_groups); ?>
                    </button>
                    <span id="processing-status" style="margin-left: 15px; font-weight: bold; display: none;"></span>
                    <div id="progress-bar" style="display: none; margin-top: 15px; background: #f0f0f0; height: 30px; border-radius: 4px; overflow: hidden; position: relative;">
                        <div id="progress-bar-fill" style="background: #46b450; height: 100%; width: 0%; transition: width 0.3s;"></div>
                        <span id="progress-text" style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); font-weight: bold; color: #333;">0%</span>
                    </div>
                </div>

                <!-- Paginação Superior -->
                <?php if ($total_pages > 1) : ?>
                    <div class="tablenav top">
                        <div class="tablenav-pages">
                            <span class="displaying-num">
                                <?php 
                                printf(
                                    _n('%s grupo', '%s grupos', $total_groups, 'wc-custom-reviews'), 
                                    number_format_i18n($total_groups)
                                ); 
                                ?>
                            </span>
                            <?php
                            $base_args = array('page' => 'wc-custom-reviews-duplicates');
                            if ($filters['same_product']) {
                                $base_args['same_product'] = '1';
                            }
                            if ($filters['same_name']) {
                                $base_args['same_name'] = '1';
                            }
                            if ($filters['same_text']) {
                                $base_args['same_text'] = '1';
                            }
                            
                            $pagination_args = array(
                                'base' => add_query_arg(array_merge($base_args, array('paged' => '%#%'))),
                                'format' => '',
                                'prev_text' => '&laquo;',
                                'next_text' => '&raquo;',
                                'total' => $total_pages,
                                'current' => $page,
                                'show_all' => false,
                                'end_size' => 1,
                                'mid_size' => 2,
                                'type' => 'plain',
                            );
                            
                            echo '<span class="pagination-links">';
                            echo paginate_links($pagination_args);
                            echo '</span>';
                            ?>
                        </div>
                    </div>
                <?php endif; ?>

                <?php foreach ($duplicates as $index => $duplicate_group) : ?>
                    <div style="background: #fff; padding: 20px; margin: 20px 0; border-left: 4px solid #f0ad4e; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
                        <h3 style="margin-top: 0;">
                            <?php echo esc_html($duplicate_group['reason']); ?>
                            <span style="background: #f0ad4e; color: #fff; padding: 3px 8px; font-size: 12px; border-radius: 3px; margin-left: 10px;">TEXTO DUPLICADO</span>
                        </h3>
                        
                        <!-- Ações em Massa para este grupo -->
                        <div style="margin-bottom: 15px; padding: 10px; background: #f9f9f9; border-radius: 4px;">
                            <label style="margin-right: 15px;">
                                <input type="checkbox" class="select-all-group" data-group="<?php echo $index; ?>">
                                <strong><?php _e('Selecionar todos', 'wc-custom-reviews'); ?></strong>
                            </label>
                            <button type="button" class="button bulk-delete-group" data-group="<?php echo $index; ?>" style="margin-left: 10px;">
                                <?php _e('Excluir Selecionados', 'wc-custom-reviews'); ?>
                            </button>
                            <button type="button" class="button button-link-delete keep-one-delete-rest" data-group="<?php echo $index; ?>" style="color: #dc3232;">
                                <?php _e('Manter mais antigo e excluir os outros', 'wc-custom-reviews'); ?>
                            </button>
                        </div>

                        <table class="wp-list-table widefat fixed striped" style="margin-top: 15px;">
                            <thead>
                                <tr>
                                    <td class="check-column">
                                        <input type="checkbox" class="group-select-all" data-group="<?php echo $index; ?>">
                                    </td>
                                    <th style="width: 60px;"><?php _e('ID', 'wc-custom-reviews'); ?></th>
                                    <th><?php _e('Produto', 'wc-custom-reviews'); ?></th>
                                    <th><?php _e('Cliente', 'wc-custom-reviews'); ?></th>
                                    <th><?php _e('Email', 'wc-custom-reviews'); ?></th>
                                    <th><?php _e('Avaliação', 'wc-custom-reviews'); ?></th>
                                    <th><?php _e('Comentário', 'wc-custom-reviews'); ?></th>
                                    <th><?php _e('Data', 'wc-custom-reviews'); ?></th>
                                    <th style="width: 100px;"><?php _e('Ações', 'wc-custom-reviews'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($duplicate_group['reviews'] as $review) : ?>
                                    <tr id="review-<?php echo esc_attr($review->id); ?>" class="group-<?php echo $index; ?>-review">
                                        <th scope="row" class="check-column">
                                            <input type="checkbox" class="review-checkbox group-<?php echo $index; ?>-checkbox" value="<?php echo esc_attr($review->id); ?>">
                                        </th>
                                        <td><strong>#<?php echo esc_html($review->id); ?></strong></td>
                                        <td>
                                            <?php if ($review->product_name) : ?>
                                                <a href="<?php echo get_edit_post_link($review->product_id); ?>" target="_blank">
                                                    <?php echo esc_html($review->product_name); ?>
                                                </a>
                                            <?php else : ?>
                                                <?php _e('Produto não encontrado', 'wc-custom-reviews'); ?>
                                            <?php endif; ?>
                                        </td>
                                        <td><strong><?php echo esc_html($review->customer_name); ?></strong></td>
                                        <td><?php echo esc_html($review->customer_email); ?></td>
                                        <td>
                                            <?php
                                            for ($i = 1; $i <= 5; $i++) {
                                                if ($i <= $review->rating) {
                                                    echo '<span style="color: #ffb400;">★</span>';
                                                } else {
                                                    echo '<span style="color: #ddd;">★</span>';
                                                }
                                            }
                                            ?>
                                        </td>
                                        <td>
                                            <div style="max-width: 300px; overflow: hidden; text-overflow: ellipsis;">
                                                <?php echo esc_html(wp_trim_words($review->review_text, 15)); ?>
                                            </div>
                                        </td>
                                        <td><?php echo date_i18n('d/m/Y H:i', strtotime($review->created_at)); ?></td>
                                        <td>
                                            <button type="button" 
                                                    class="button button-small button-link-delete" 
                                                    onclick="if(confirm('<?php _e('Tem certeza que deseja excluir este review?', 'wc-custom-reviews'); ?>')) { deleteReview(<?php echo esc_js($review->id); ?>); }"
                                                    style="color: #b32d2e;">
                                                <?php _e('Excluir', 'wc-custom-reviews'); ?>
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endforeach; ?>

                <!-- Paginação Inferior -->
                <?php if ($total_pages > 1) : ?>
                    <div class="tablenav bottom">
                        <div class="tablenav-pages">
                            <?php
                            echo '<span class="pagination-links">';
                            echo paginate_links($pagination_args);
                            echo '</span>';
                            ?>
                        </div>
                    </div>
                <?php endif; ?>

                <div class="notice notice-info">
                    <p>
                        <strong><?php _e('💡 Dica:', 'wc-custom-reviews'); ?></strong>
                        <?php _e('Estes são reviews com texto exatamente igual. Podem ser spam, cópias ou reviews legítimos similares. Revise cada caso individualmente.', 'wc-custom-reviews'); ?>
                    </p>
                </div>
            <?php endif; ?>
        </div>

        <script>
        jQuery(document).ready(function($) {
            // Botão para processar todos os duplicados
            $('#process-all-duplicates').on('click', function() {
                var filters = {
                    same_product: <?php echo $filters['same_product'] ? 'true' : 'false'; ?>,
                    same_name: <?php echo $filters['same_name'] ? 'true' : 'false'; ?>,
                    same_text: <?php echo $filters['same_text'] ? 'true' : 'false'; ?>
                };
                
                if (!confirm('ATENÇÃO: Isso irá processar TODOS os grupos de duplicados!\n\n' +
                             'A ação irá manter o review MAIS ANTIGO de cada grupo e excluir os outros.\n\n' +
                             'Esta ação NÃO PODE SER DESFEITA!\n\n' +
                             'Deseja continuar?')) {
                    return;
                }
                
                $('#process-all-duplicates').prop('disabled', true).text('Processando...');
                $('#processing-status').show().text('Iniciando processamento...');
                $('#progress-bar').show();
                
                processAllDuplicates(0, 0, filters, 5); // Começa com 5%
            });
            
            // Função recursiva para processar duplicados em lotes
            function processAllDuplicates(processed, totalDeleted, filters, lastProgress) {
                if (typeof lastProgress === 'undefined') {
                    lastProgress = 0;
                }
                
                var batchSize = 5; // Processa 5 grupos por vez
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'wc_custom_reviews_process_all_duplicates',
                        batch_size: batchSize,
                        filters: filters,
                        nonce: '<?php echo wp_create_nonce('wc_custom_reviews_process_all'); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            var batchProcessed = response.data.processed;
                            var batchDeleted = response.data.deleted;
                            
                            processed += batchProcessed;
                            totalDeleted += batchDeleted;
                            
                            // Atualiza barra de progresso gradualmente
                            // Cada lote processado adiciona um pequeno incremento
                            if (batchProcessed > 0) {
                                // Incremento variável: quanto mais deletar, mais avança
                                var increment = Math.min(5, 1 + (batchDeleted * 0.2));
                                lastProgress = Math.min(95, lastProgress + increment);
                            }
                            
                            $('#progress-bar-fill').css('width', lastProgress + '%');
                            $('#progress-text').text(Math.round(lastProgress) + '%');
                            $('#processing-status').text('Processados: ' + processed + ' grupos | Reviews excluídos: ' + totalDeleted);
                            
                            // Se ainda processou algo neste lote, continua
                            if (batchProcessed > 0) {
                                setTimeout(function() {
                                    processAllDuplicates(processed, totalDeleted, filters, lastProgress);
                                }, 500); // Pausa de 500ms entre lotes
                            } else {
                                // Concluído! Não há mais duplicados
                                $('#progress-bar-fill').css({'background': '#46b450', 'width': '100%'});
                                $('#progress-text').text('100%');
                                $('#processing-status').text('✅ Processamento concluído! Total: ' + processed + ' grupos processados, ' + totalDeleted + ' reviews excluídos').css('color', '#46b450');
                                $('#process-all-duplicates').text('✓ Concluído!');
                                
                                setTimeout(function() {
                                    location.reload();
                                }, 2000);
                            }
                        } else {
                            $('#processing-status').text('❌ Erro: ' + response.data.message).css('color', '#dc3232');
                            $('#process-all-duplicates').prop('disabled', false).text('Tentar Novamente');
                        }
                    },
                    error: function() {
                        $('#processing-status').text('❌ Erro na comunicação com o servidor').css('color', '#dc3232');
                        $('#process-all-duplicates').prop('disabled', false).text('Tentar Novamente');
                    }
                });
            }
            
            // Selecionar todos os checkboxes de um grupo
            $('.select-all-group, .group-select-all').on('change', function() {
                var group = $(this).data('group');
                var isChecked = $(this).prop('checked');
                $('.group-' + group + '-checkbox').prop('checked', isChecked);
            });
            
            // Excluir reviews selecionados de um grupo
            $('.bulk-delete-group').on('click', function() {
                var group = $(this).data('group');
                var reviewIds = [];
                
                $('.group-' + group + '-checkbox:checked').each(function() {
                    reviewIds.push($(this).val());
                });
                
                if (reviewIds.length === 0) {
                    alert('<?php _e('Selecione pelo menos um review para excluir.', 'wc-custom-reviews'); ?>');
                    return;
                }
                
                if (!confirm('<?php _e('Tem certeza que deseja excluir', 'wc-custom-reviews'); ?> ' + reviewIds.length + ' <?php _e('review(s)?', 'wc-custom-reviews'); ?>')) {
                    return;
                }
                
                bulkDeleteReviews(reviewIds);
            });
            
            // Manter o mais antigo e excluir os outros
            $('.keep-one-delete-rest').on('click', function() {
                var group = $(this).data('group');
                var allReviewIds = [];
                
                $('.group-' + group + '-review').each(function() {
                    var reviewId = $(this).find('.review-checkbox').val();
                    allReviewIds.push(reviewId);
                });
                
                if (allReviewIds.length <= 1) {
                    alert('<?php _e('Não há reviews suficientes neste grupo.', 'wc-custom-reviews'); ?>');
                    return;
                }
                
                // Remove o primeiro (mais antigo) da lista
                var reviewsToDelete = allReviewIds.slice(1);
                
                if (!confirm('<?php _e('Isso vai manter o review mais antigo e excluir', 'wc-custom-reviews'); ?> ' + reviewsToDelete.length + ' <?php _e('review(s) mais recente(s). Confirma?', 'wc-custom-reviews'); ?>')) {
                    return;
                }
                
                bulkDeleteReviews(reviewsToDelete);
            });
            
            // Função para excluir múltiplos reviews
            function bulkDeleteReviews(reviewIds) {
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'wc_custom_reviews_bulk_delete',
                        review_ids: reviewIds,
                        nonce: '<?php echo wp_create_nonce('wc_custom_reviews_bulk_action'); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            reviewIds.forEach(function(id) {
                                $('#review-' + id).fadeOut(300, function() {
                                    $(this).remove();
                                });
                            });
                            
                            alert(response.data.message);
                            
                            // Recarrega a página após 1 segundo
                            setTimeout(function() {
                                location.reload();
                            }, 1000);
                        } else {
                            alert('<?php _e('Erro ao excluir reviews.', 'wc-custom-reviews'); ?>');
                        }
                    },
                    error: function() {
                        alert('<?php _e('Erro na comunicação com o servidor.', 'wc-custom-reviews'); ?>');
                    }
                });
            }
        });
        
        // Função para excluir review individual (mantida para compatibilidade)
        function deleteReview(reviewId) {
            jQuery.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'wc_custom_reviews_delete_review',
                    review_id: reviewId,
                    nonce: '<?php echo wp_create_nonce('wc_custom_reviews_admin_nonce'); ?>'
                },
                success: function(response) {
                    if (response.success) {
                        jQuery('#review-' + reviewId).fadeOut(300, function() {
                            jQuery(this).remove();
                        });
                        alert('<?php _e('Review excluído com sucesso!', 'wc-custom-reviews'); ?>');
                        
                        // Recarrega a página após 1 segundo para atualizar a lista
                        setTimeout(function() {
                            location.reload();
                        }, 1000);
                    } else {
                        alert('<?php _e('Erro ao excluir review.', 'wc-custom-reviews'); ?>');
                    }
                },
                error: function() {
                    alert('<?php _e('Erro na comunicação com o servidor.', 'wc-custom-reviews'); ?>');
                }
            });
        }
        </script>
        <?php
    }
}

