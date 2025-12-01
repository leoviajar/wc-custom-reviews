<?php
/**
 * Classe responsável pelo gerenciamento do banco de dados
 * Compatível com HPOS (High-Performance Order Storage)
 */

if (!defined('ABSPATH')) {
    exit;
}

class WC_Custom_Reviews_Database {

    /**
     * Instância única da classe
     */
    private static $instance = null;

    /**
     * Nome da tabela de reviews
     */
    private $table_name;

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
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'wc_custom_reviews';
        $this->update_table_structure();
    }

    public function get_total_all_reviews($status = "", $search = "") {
        global $wpdb;

        $search = sanitize_text_field($search);
        $where_conditions = array();
        
        // Filtro por status
        if (!empty($status)) {
            $status = sanitize_text_field($status);
            $where_conditions[] = $wpdb->prepare("r.status = %s", $status);
        }
        
        // Filtro por busca
        if (!empty($search)) {
            $search_like = '%' . $wpdb->esc_like($search) . '%';
            $where_conditions[] = $wpdb->prepare(
                "(r.customer_name LIKE %s OR r.customer_email LIKE %s OR r.review_text LIKE %s OR p.post_title LIKE %s)",
                $search_like,
                $search_like,
                $search_like,
                $search_like
            );
        }
        
        // Monta cláusula WHERE
        $where_clause = '';
        if (!empty($where_conditions)) {
            $where_clause = 'WHERE ' . implode(' AND ', $where_conditions);
        }

        $sql = "SELECT COUNT(*) FROM {$this->table_name} r 
                LEFT JOIN {$wpdb->posts} p ON r.product_id = p.ID 
                $where_clause";

        return $wpdb->get_var($sql);
    }

    /**
     * Obtém um review pelo ID
     */
    public function get_review_by_id($review_id) {
        global $wpdb;

        $review_id = absint($review_id);

        $sql = $wpdb->prepare(
            "SELECT * FROM {$this->table_name} 
             WHERE id = %d",
            $review_id
        );

        return $wpdb->get_row($sql);
    }

    /**
     * Cria as tabelas necessárias
     */
    public static function create_tables() {
        global $wpdb;

        $table_name = $wpdb->prefix . 'wc_custom_reviews';

        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            product_id bigint(20) NOT NULL,
            customer_name varchar(255) NOT NULL,
            customer_email varchar(255) NOT NULL,
            rating tinyint(1) NOT NULL,
            review_text text,
            status varchar(20) DEFAULT 'pendente',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            image_url TEXT DEFAULT NULL,
            video_url VARCHAR(500) DEFAULT NULL,
            PRIMARY KEY (id),
            KEY product_id (product_id),
            KEY status (status),
            KEY created_at (created_at)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);

        // Atualiza versão do banco
        update_option('wc_custom_reviews_db_version', WC_CUSTOM_REVIEWS_VERSION);
    }

    /**
     * Atualiza a estrutura da tabela se necessário
     */
    public function update_table_structure() {
        global $wpdb;
        
        $table_name = $this->get_table_name();
        
        // Verifica se a coluna image_url existe
        $column_exists = $wpdb->get_results($wpdb->prepare(
            "SHOW COLUMNS FROM $table_name LIKE %s",
            'image_url'
        ));
        
        // Se não existe, adiciona a coluna como TEXT para suportar múltiplas URLs
        if (empty($column_exists)) {
            $wpdb->query("ALTER TABLE $table_name ADD COLUMN image_url TEXT DEFAULT NULL AFTER updated_at");
        } else {
            // Se existe mas é VARCHAR, converte para TEXT
            $column_info = $wpdb->get_row($wpdb->prepare(
                "SHOW COLUMNS FROM $table_name LIKE %s",
                'image_url'
            ));
            
            if ($column_info && strpos($column_info->Type, 'varchar') !== false) {
                $wpdb->query("ALTER TABLE $table_name MODIFY COLUMN image_url TEXT DEFAULT NULL");
            }
        }
        
        // Verifica se a coluna video_url existe
        $video_column_exists = $wpdb->get_results($wpdb->prepare(
            "SHOW COLUMNS FROM $table_name LIKE %s",
            'video_url'
        ));
        
        // Se não existe, adiciona a coluna para vídeos
        if (empty($video_column_exists)) {
            $wpdb->query("ALTER TABLE $table_name ADD COLUMN video_url VARCHAR(500) DEFAULT NULL AFTER image_url");
        }
    }

    /**
     * Método para adicionar à classe WC_Custom_Reviews_Database
     * Adicione este método antes do último fechamento de chave da classe
     */
    public function bulk_delete_reviews($review_ids) {
        global $wpdb;
        
        if (empty($review_ids) || !is_array($review_ids)) {
            return false;
        }
        
        // Sanitiza os IDs
        $review_ids = array_map('absint', $review_ids);
        $review_ids = array_filter($review_ids); // Remove valores zero
        
        if (empty($review_ids)) {
            return false;
        }
        
        // Cria placeholders para a query
        $placeholders = implode(',', array_fill(0, count($review_ids), '%d'));
        
        // Executa a exclusão
        $sql = "DELETE FROM {$this->table_name} WHERE id IN ($placeholders)";
        $prepared_sql = $wpdb->prepare($sql, $review_ids);
        $result = $wpdb->query($prepared_sql);
        
        return $result !== false ? $result : 0;
    }

    /**
     * Insere um novo review
     */
    public function insert_review($data) {
        global $wpdb;

        $defaults = array(
            'product_id' => 0,
            'customer_name' => '',
            'customer_email' => '',
            'rating' => 5,
            'review_text' => '',
            'status' => 'pendente',
            'image_url' => null,
            'video_url' => null
        );

        $data = wp_parse_args($data, $defaults);

        // Validações (mantenha como está)
        if (empty($data['product_id']) || empty($data['customer_name']) || empty($data['customer_email'])) {
            return false;
        }

        // Sanitização (mantenha como está)
        $data['customer_name'] = sanitize_text_field($data['customer_name']);
        $data['customer_email'] = sanitize_email($data['customer_email']);
        $data['rating'] = absint($data['rating']);
        $data['review_text'] = sanitize_textarea_field($data['review_text']);
        $data['status'] = sanitize_text_field($data['status']);
        
        // Verifica se image_url é JSON (múltiplas imagens) ou URL única
        if (!empty($data['image_url'])) {
            // Tenta decodificar para verificar se é JSON
            $json_check = json_decode($data['image_url']);
            if (json_last_error() === JSON_ERROR_NONE && is_array($json_check)) {
                // É JSON válido, mantém como está (assumindo que as URLs internas já foram sanitizadas)
            } else {
                // Não é JSON, sanitiza como URL única
                $data['image_url'] = esc_url_raw($data['image_url']);
            }
        }

        // Sanitiza video_url
        if (!empty($data['video_url'])) {
            $data['video_url'] = esc_url_raw($data['video_url']);
        }

        // Verifica se o produto existe usando HPOS (mantenha como está)
        if (!$this->product_exists($data['product_id'])) {
            return false;
        }

        $result = $wpdb->insert(
            $this->table_name,
            $data,
            array('%d', '%s', '%s', '%d', '%s', '%s', '%s', '%s')
        );

        return $result ? $wpdb->insert_id : false;
    }

    /**
     * Obtém reviews por produto
     */
    public function get_reviews_by_product($product_id, $status = 'aprovado', $limit = 10, $offset = 0, $order_by = 'recent') {
        global $wpdb;

        $product_id = absint($product_id);
        $status = sanitize_text_field($status);
        $limit = absint($limit);
        $offset = absint($offset);
        $order_by = sanitize_text_field($order_by);

        // Define a cláusula ORDER BY baseada no parâmetro
        if ($order_by === 'photo') {
            // Ordena primeiro por reviews com foto (image_url IS NOT NULL), depois por data
            $order_clause = 'ORDER BY (image_url IS NOT NULL AND image_url != "") DESC, created_at DESC';
        } else {
            // Ordenação padrão: mais recentes primeiro
            $order_clause = 'ORDER BY created_at DESC';
        }

        $sql = $wpdb->prepare(
            "SELECT * FROM {$this->table_name} 
            WHERE product_id = %d AND status = %s 
            {$order_clause} 
            LIMIT %d OFFSET %d",
            $product_id,
            $status,
            $limit,
            $offset
        );

        return $wpdb->get_results($sql);
    }

    /**
     * Obtém o total de reviews para um produto
     */
    public function get_total_reviews_by_product($product_id, $status = 'aprovado') {
        global $wpdb;

        $product_id = absint($product_id);
        $status = sanitize_text_field($status);

        $sql = $wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->table_name} 
             WHERE product_id = %d AND status = %s",
            $product_id,
            $status
        );

        return $wpdb->get_var($sql);
    }

    /**
     * Obtém todos os reviews para admin
     */
    public function get_all_reviews($status = "", $limit = 20, $offset = 0, $order_by = 'recent', $search = '') {
        global $wpdb;

        $limit = absint($limit);
        $offset = absint($offset);
        $order_by = sanitize_text_field($order_by);
        $search = sanitize_text_field($search);

        $where_conditions = array();
        
        // Filtro por status
        if (!empty($status)) {
            $status = sanitize_text_field($status);
            $where_conditions[] = $wpdb->prepare("r.status = %s", $status);
        }
        
        // Filtro por busca (nome, email, comentário ou produto)
        if (!empty($search)) {
            $search_like = '%' . $wpdb->esc_like($search) . '%';
            $where_conditions[] = $wpdb->prepare(
                "(r.customer_name LIKE %s OR r.customer_email LIKE %s OR r.review_text LIKE %s OR p.post_title LIKE %s)",
                $search_like,
                $search_like,
                $search_like,
                $search_like
            );
        }
        
        // Monta cláusula WHERE
        $where_clause = '';
        if (!empty($where_conditions)) {
            $where_clause = 'WHERE ' . implode(' AND ', $where_conditions);
        }

        // Define a cláusula ORDER BY baseada no parâmetro
        if ($order_by === 'photo') {
            // Ordena primeiro por reviews com foto, depois por data
            $order_clause = 'ORDER BY (r.image_url IS NOT NULL AND r.image_url != "") DESC, r.created_at DESC';
        } else {
            // Ordenação padrão: mais recentes primeiro
            $order_clause = 'ORDER BY r.created_at DESC';
        }

        $sql = "SELECT r.*, p.post_title as product_name 
                FROM {$this->table_name} r 
                LEFT JOIN {$wpdb->posts} p ON r.product_id = p.ID 
                $where_clause 
                {$order_clause} 
                LIMIT $limit OFFSET $offset";

        return $wpdb->get_results($sql);
    }

    /**
     * Atualiza status de um review
     */
    public function update_review_status($review_id, $status) {
        global $wpdb;

        $review_id = absint($review_id);
        $status = sanitize_text_field($status);

        return $wpdb->update(
            $this->table_name,
            array('status' => $status),
            array('id' => $review_id),
            array('%s'),
            array('%d')
        );
    }

    /**
     * Deleta um review
     */
    public function delete_review($review_id) {
        global $wpdb;

        $review_id = absint($review_id);

        return $wpdb->delete(
            $this->table_name,
            array('id' => $review_id),
            array('%d')
        );
    }

    /**
     * Obtém estatísticas de rating de um produto
     */
    public function get_product_rating_stats($product_id) {
        global $wpdb;

        $product_id = absint($product_id);

        $sql = $wpdb->prepare(
            "SELECT 
                COUNT(*) as total_reviews,
                AVG(rating) as average_rating,
                SUM(CASE WHEN rating = 5 THEN 1 ELSE 0 END) as five_stars,
                SUM(CASE WHEN rating = 4 THEN 1 ELSE 0 END) as four_stars,
                SUM(CASE WHEN rating = 3 THEN 1 ELSE 0 END) as three_stars,
                SUM(CASE WHEN rating = 2 THEN 1 ELSE 0 END) as two_stars,
                SUM(CASE WHEN rating = 1 THEN 1 ELSE 0 END) as one_star
             FROM {$this->table_name} 
             WHERE product_id = %d AND status = 'aprovado'",
            $product_id
        );

        return $wpdb->get_row($sql);
    }

    /**
     * Verifica se um produto existe (compatível com HPOS)
     */
    private function product_exists($product_id) {
        $product = wc_get_product($product_id);
        return $product && $product->exists();
    }

    /**
     * Obtém nome da tabela
     */
    public function get_table_name() {
        return $this->table_name;
    }

    /**
     * Detecta reviews duplicados ou suspeitos
     * Retorna grupos de reviews que podem ser duplicados
     */
    public function find_duplicate_reviews($limit = 20, $offset = 0, $filters = array()) {
        global $wpdb;

        $duplicates = array();
        $limit = absint($limit);
        $offset = absint($offset);
        
        // Define critérios de agrupamento baseado nos filtros
        $group_by = array();
        $group_labels = array();
        
        if (isset($filters['same_product']) && $filters['same_product']) {
            $group_by[] = 'product_id';
            $group_labels[] = __('mesmo produto', 'wc-custom-reviews');
        }
        
        if (isset($filters['same_name']) && $filters['same_name']) {
            $group_by[] = 'customer_name';
            $group_labels[] = __('mesmo nome', 'wc-custom-reviews');
        }
        
        if (isset($filters['same_text']) && $filters['same_text']) {
            $group_by[] = 'review_text';
            $group_labels[] = __('mesmo texto', 'wc-custom-reviews');
        }
        
        // Se nenhum filtro foi selecionado, usa padrão (apenas texto)
        if (empty($group_by)) {
            $group_by[] = 'review_text';
            $group_labels[] = __('mesmo texto', 'wc-custom-reviews');
        }
        
        $group_by_clause = implode(', ', $group_by);
        $reason_label = ucfirst(implode(' + ', $group_labels));

        // Busca duplicados baseado nos critérios
        $select_fields = implode(', ', $group_by);
        $similar_reviews = $wpdb->get_results($wpdb->prepare("
            SELECT {$select_fields}, COUNT(*) as count
            FROM {$this->table_name}
            WHERE review_text != '' AND CHAR_LENGTH(review_text) > 10
            GROUP BY {$group_by_clause}
            HAVING count > 1
            ORDER BY count DESC
            LIMIT %d OFFSET %d
        ", $limit, $offset));

        if (!empty($similar_reviews)) {
            foreach ($similar_reviews as $sim) {
                // Constrói WHERE clause para buscar o grupo
                $where_conditions = array();
                $where_values = array();
                
                if (in_array('product_id', $group_by)) {
                    $where_conditions[] = 'r.product_id = %d';
                    $where_values[] = $sim->product_id;
                }
                
                if (in_array('customer_name', $group_by)) {
                    $where_conditions[] = 'r.customer_name = %s';
                    $where_values[] = $sim->customer_name;
                }
                
                if (in_array('review_text', $group_by)) {
                    $where_conditions[] = 'r.review_text = %s';
                    $where_values[] = $sim->review_text;
                }
                
                $where_clause = implode(' AND ', $where_conditions);
                
                $sql = "SELECT r.*, p.post_title as product_name
                        FROM {$this->table_name} r
                        LEFT JOIN {$wpdb->posts} p ON r.product_id = p.ID
                        WHERE {$where_clause}
                        ORDER BY r.created_at DESC";
                
                $group_reviews = $wpdb->get_results($wpdb->prepare($sql, $where_values));

                if (count($group_reviews) > 1) {
                    $duplicates[] = array(
                        'type' => 'filtered',
                        'reason' => sprintf(__('%s (%d ocorrências)', 'wc-custom-reviews'), $reason_label, $sim->count),
                        'count' => $sim->count,
                        'reviews' => $group_reviews
                    );
                }
            }
        }

        return $duplicates;
    }

    /**
     * Conta total de grupos de reviews duplicados
     */
    public function count_duplicate_groups($filters = array()) {
        global $wpdb;
        
        // Define critérios de agrupamento baseado nos filtros
        $group_by = array();
        
        if (isset($filters['same_product']) && $filters['same_product']) {
            $group_by[] = 'product_id';
        }
        
        if (isset($filters['same_name']) && $filters['same_name']) {
            $group_by[] = 'customer_name';
        }
        
        if (isset($filters['same_text']) && $filters['same_text']) {
            $group_by[] = 'review_text';
        }
        
        // Se nenhum filtro foi selecionado, usa padrão (apenas texto)
        if (empty($group_by)) {
            $group_by[] = 'review_text';
        }
        
        $group_by_clause = implode(', ', $group_by);

        $result = $wpdb->get_var("
            SELECT COUNT(*) FROM (
                SELECT {$group_by_clause}
                FROM {$this->table_name}
                WHERE review_text != '' AND CHAR_LENGTH(review_text) > 10
                GROUP BY {$group_by_clause}
                HAVING COUNT(*) > 1
            ) as duplicates
        ");

        return $result ? $result : 0;
    }
    
}

