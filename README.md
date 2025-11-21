# WC Custom Reviews

Plugin personalizado de reviews para WooCommerce com interface administrativa e shortcodes para frontend, totalmente compatível com HPOS (High-Performance Order Storage).

## Características

- ✅ **Compatível com HPOS** - Totalmente compatível com o novo sistema de armazenamento de pedidos do WooCommerce
- 📸 **Múltiplas Imagens** - Clientes podem enviar até 10 fotos por avaliação com visualização em lightbox
- 🎨 **Personalizável** - Configure cores das estrelas e botões através do painel administrativo
- 📱 **Responsivo** - Interface adaptada para desktop e mobile
- 🔧 **Fácil de usar** - Dois shortcodes simples para implementar em qualquer lugar
- 👥 **Gerenciamento completo** - Interface administrativa para moderar comentários
- 🔒 **Seguro** - Validações e sanitização de dados, proteção contra spam
- 📊 **Import/Export CSV** - Importação em massa com download automático de imagens
- 🔍 **Busca Avançada** - Pesquise por nome, e-mail, comentário ou produto
- 🔄 **Detecção de Duplicados** - Identifique e remova avaliações duplicadas automaticamente

## Funcionalidades

### Interface Administrativa
- Menu lateral no WordPress admin
- Configuração de cores (estrelas e botões)
- Exibição de shortcodes disponíveis
- Gerenciamento de comentários (aprovar, rejeitar, excluir)
- Filtros por status dos comentários
- **Importação/Exportação CSV** com download automático de imagens
- **Busca em múltiplos campos** (nome, e-mail, comentário, produto)
- **Detecção de duplicados** com filtros customizáveis e processamento em lote

### Frontend
- **Shortcode de Estrelas**: `[wc_custom_reviews_stars product_id="123"]`
- **Widget Completo**: `[wc_custom_reviews_widget product_id="123"]`
- **Upload de múltiplas imagens** (até 10 fotos por avaliação)
- **Galeria de thumbnails** clicáveis com lightbox para visualização
- Formulário de avaliação com validação
- Exibição de estatísticas de avaliações
- Sistema de estrelas interativo
- Navegação por teclado no lightbox (setas, ESC)

## Instalação

1. Faça upload da pasta `wc-custom-reviews` para `/wp-content/plugins/`
2. Ative o plugin através do menu 'Plugins' no WordPress
3. Configure as opções em 'Custom Reviews' no menu administrativo

## Requisitos

- WordPress 5.0 ou superior
- WooCommerce 6.0 ou superior
- PHP 7.4 ou superior

## Shortcodes

### Estrelas de Avaliação
```
[wc_custom_reviews_stars product_id="123"]
```
Exibe apenas as estrelas de avaliação do produto. Se `product_id` não for especificado, usa o produto atual.

### Widget Completo
```
[wc_custom_reviews_widget product_id="123"]
```
Exibe o widget completo com:
- Resumo das avaliações
- Lista de comentários dos clientes
- Formulário para nova avaliação

## Configuração

### Cores Personalizadas
1. Acesse 'Custom Reviews' > 'Configurações'
2. Configure a cor das estrelas
3. Configure a cor dos botões
4. Salve as alterações

### Gerenciamento de Comentários
1. Acesse 'Custom Reviews' > 'Comentários'
2. Visualize todos os comentários recebidos
3. Aprove, rejeite ou exclua comentários
4. Use filtros para visualizar por status

## Estrutura do Banco de Dados

O plugin cria uma tabela personalizada `wp_wc_custom_reviews` com os seguintes campos:

- `id` - ID único do review
- `product_id` - ID do produto
- `customer_name` - Nome do cliente
- `customer_email` - Email do cliente
- `rating` - Avaliação (1-5 estrelas)
- `review_text` - Texto do comentário
- `status` - Status (pendente, aprovado, rejected)
- `created_at` - Data de criação
- `updated_at` - Data de atualização

## Compatibilidade HPOS

Este plugin é totalmente compatível com o HPOS (High-Performance Order Storage) do WooCommerce:

- Usa `wc_get_product()` para acessar produtos
- Declara compatibilidade através de `FeaturesUtil::declare_compatibility()`
- Não depende da tabela `wp_posts` para funcionalidades relacionadas a pedidos

## Segurança

- Validação e sanitização de todos os dados de entrada
- Verificação de nonce em formulários
- Verificação de permissões de usuário
- Proteção contra SQL injection
- Escape de saída de dados

## Suporte

Para suporte e dúvidas, entre em contato através do email: [seu-email@exemplo.com]

## Changelog

### 1.0.0
- Lançamento inicial
- Interface administrativa completa
- Dois shortcodes funcionais
- Compatibilidade com HPOS
- Sistema de moderação de comentários

## Licença

GPL v2 ou posterior - https://www.gnu.org/licenses/gpl-2.0.html

