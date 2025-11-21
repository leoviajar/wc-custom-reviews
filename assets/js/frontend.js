/**
 * WC Custom Reviews - Frontend JavaScript - VERSÃO CORRIGIDA V4
 * 
 * CORREÇÃO APLICADA: Scroll imediato ao clicar na paginação, antes do AJAX
 * para evitar que a tela desça e depois suba
 */

(function($) {
    'use strict';

    // Inicializa quando o DOM estiver pronto
    $(document).ready(function() {
        console.log('[INIT] DOM Ready - Iniciando plugin WC Custom Reviews');
        
        initReviewForm();
        initRatingInput();
        initReviewModal();
        initPagination(); // Nova função para inicializar paginação
        initStarsScrollToReviews(); // Nova função para scroll nas estrelas
        initModalGalleryNavigation(); // Navegação de imagens dentro do modal
        
        // NOTA: initVideoThumbnails() será chamado APÓS o AJAX carregar os reviews

        // Inicializa o carregamento de reviews para todos os widgets na página
        $('.wc-custom-reviews-widget').each(function() {
            var $widget = $(this);
            var productId = $widget.data('product-id');
            var reviewsPerPage = parseInt($widget.find('.wc-custom-reviews-pagination').data('reviews-per-page')) || 10;
            var reviewOrder = $widget.data('review-order') || 'recent';
            console.log('[INIT] Carregando reviews para produto:', productId, 'reviews_per_page:', reviewsPerPage);
            loadReviews(productId, 1, reviewsPerPage, reviewOrder, $widget);
        });

        $(".wc-custom-reviews-grid").each(function() {
            var grid = this;
            console.log('[INIT] Inicializando Masonry no grid');
            var msnry = new Masonry(grid, {
                itemSelector: ".review-grid-item",
                gutter: 15,
                percentPosition: true
            });

            imagesLoaded(grid).on("progress", function() {
                msnry.layout();
            });
        });
        
        // NOVO: Verifica se já existem vídeos renderizados na página inicial
        setTimeout(function() {
            var videosNaPagina = $('.review-video-grid video').length;
            console.log('[INIT] Verificando vídeos após 500ms - Total encontrado:', videosNaPagina);
            if (videosNaPagina > 0) {
                console.log('[INIT] Vídeos encontrados! Chamando initVideoThumbnails()');
                initVideoThumbnails();
            }
        }, 500);
    });


    /**
     * FUNÇÃO CORRIGIDA: Inicializa eventos de paginação com scroll imediato
     */
    function initPagination() {
        $(document).on('click', '.pagination-button', function(e) {
            e.preventDefault();
            
            var $button = $(this);
            var $widget = $button.closest('.wc-custom-reviews-widget');
            var page = parseInt($button.data('page'));
            var productId = $widget.data('product-id');
            var reviewsPerPage = parseInt($widget.find('.wc-custom-reviews-pagination').data('reviews-per-page'));
            var reviewOrder = $widget.data('review-order');
            
            // CORREÇÃO: Faz scroll IMEDIATAMENTE ao clicar, antes do AJAX
            if (page > 0) { // Só faz scroll se não for a primeira página
                $('html, body').animate({
                    scrollTop: $widget.offset().top - 50 // 50px de margem do topo
                }, 300); // Animação mais rápida (300ms)
            }
            
            // Carrega a nova página
            loadReviews(productId, page, reviewsPerPage, reviewOrder, $widget);
        });
    }

    /**
     * Mostra modal de sucesso com confetes
     */
    function showSuccessModal(message) {
        if (message) {
            $('#wc-success-modal .success-message').text(message);
        }
        
        $('#wc-success-modal').fadeIn(300);
        $('body').addClass('modal-open');
        
        createConfetti();
        
        $('#wc-success-modal .wc-success-modal-close').off('click').on('click', function() {
            closeSuccessModal();
        });
        
        setTimeout(function() {
            closeSuccessModal();
        }, 5000);
    }

    /**
     * Fecha o modal de sucesso
     */
    function closeSuccessModal() {
        $('#wc-success-modal').fadeOut(300);
        $('body').removeClass('modal-open');
        $('.confetti').remove();
    }

    /**
     * Cria animação de confetes
     */
    function createConfetti() {
        confetti({
            particleCount: 100,
            spread: 70,
            startVelocity: 30,
            angle: 90,
            origin: { y: 0.6 },
            disableForReducedMotion: true,
        });
    }

    /**
     * Inicializa o modal de review
     */
    function initReviewModal() {
        $(document).on('click', '.open-review-modal-btn', function() {
            $('#wc-review-modal').fadeIn(300);
            $('body').addClass('modal-open');
        });

        $(document).on('click', '.wc-review-modal-close', function() {
            closeReviewModal();
        });

        $(document).on('click', '.wc-review-modal', function(e) {
            if (e.target === this) {
                closeReviewModal();
            }
        });

        $(document).on('keydown', function(e) {
            if (e.keyCode === 27 && $('#wc-review-modal').is(':visible')) {
                closeReviewModal();
            }
        });
    }

    /**
     * Fecha o modal de review
     */
    function closeReviewModal() {
        $('#wc-review-modal').fadeOut(300);
        $('body').removeClass('modal-open');
        
        $('.wc-custom-review-form')[0].reset();
        $('.form-messages').empty();
        $("#review-image-preview").hide().attr("src", "");
        $("#remove-image-preview").hide();
        
        $('.rating-input .star-label').removeClass('hovered');
        $('.rating-input input[type="radio"]').prop('checked', false);
    }

    /**
     * Inicializa o formulário de review
     */
    function initReviewForm() {
        $(".wc-custom-review-form").on("submit", function(e) {
            e.preventDefault();
            submitReview($(this));
        });

        // Atualizado para múltiplas imagens E vídeos
        $("#review_images").on("change", function() {
            var input = this;
            var $previewContainer = $("#review-images-preview");
            
            $previewContainer.empty(); // Limpa previews anteriores
            
            if (input.files && input.files.length > 0) {
                var maxFiles = Math.min(input.files.length, 10); // Limite de 10 arquivos
                
                for (var i = 0; i < maxFiles; i++) {
                    (function(index) {
                        var file = input.files[index];
                        var reader = new FileReader();
                        var isVideo = file.type.startsWith('video/');
                        
                        reader.onload = function(e) {
                            var mediaHtml;
                            if (isVideo) {
                                // Preview de vídeo
                                mediaHtml = '<video src="' + e.target.result + '" muted></video>' +
                                           '<div class="play-icon"><i class="fa-solid fa-play"></i></div>';
                            } else {
                                // Preview de imagem
                                mediaHtml = '<img src="' + e.target.result + '" alt="Preview">';
                            }
                            
                            var $previewItem = $('<div class="review-image-preview-item' + (isVideo ? ' video-preview' : '') + '">' +
                                mediaHtml +
                                '<button type="button" class="remove-preview-image" data-index="' + index + '">&times;</button>' +
                                '</div>');
                            $previewContainer.append($previewItem);
                            $previewContainer.parent().show();
                        };
                        
                        reader.readAsDataURL(file);
                    })(i);
                }
            } else {
                $previewContainer.parent().hide();
            }
        });

        // Remover imagem individual do preview
        $(document).on("click", ".remove-preview-image", function() {
            var $input = $("#review_images");
            var index = $(this).data("index");
            
            // Remove o preview visual
            $(this).closest(".review-image-preview-item").remove();
            
            // Se não houver mais previews, esconde o container
            if ($("#review-images-preview").children().length === 0) {
                $("#review-images-preview").parent().hide();
            }
            
            // Recria o FileList sem o arquivo removido
            var dt = new DataTransfer();
            var files = $input[0].files;
            
            for (var i = 0; i < files.length; i++) {
                if (i !== index) {
                    dt.items.add(files[i]);
                }
            }
            
            $input[0].files = dt.files;
        });
    }

    /**
     * Inicializa a interação com as estrelas de rating
     */
    function initRatingInput() {
        $('.rating-input').each(function() {
            var $ratingContainer = $(this);
            var $labels = $ratingContainer.find('.star-label');
            var $inputs = $ratingContainer.find('input[type="radio"]');

            $labels.on('mouseenter', function() {
                var index = $(this).index() / 2;
                highlightStars($labels, index);
            });

            $ratingContainer.on('mouseleave', function() {
                var checkedIndex = $inputs.filter(':checked').index() / 2;
                if (checkedIndex >= 0) {
                    highlightStars($labels, checkedIndex);
                } else {
                    $labels.removeClass('hovered');
                }
            });

            $labels.on('click', function() {
                var $input = $(this).prev('input[type="radio"]');
                $input.prop('checked', true);
                var index = $input.index() / 2;
                highlightStars($labels, index);
            });
        });
    }

    /**
     * Destaca as estrelas até o índice especificado
     */
    function highlightStars($labels, index) {
        $labels.each(function(i) {
            if (i <= index) {
                $(this).addClass('hovered');
            } else {
                $(this).removeClass('hovered');
            }
        });
    }

    /**
     * Submete o formulário de review
     */
    function submitReview($form) {
        var $submitBtn = $form.find(".submit-review-btn");
        var $messages = $form.find(".form-messages");
        
        if (!validateForm($form)) {
            return;
        }

        $submitBtn.prop("disabled", true).text(wcCustomReviews.strings.submitting);
        $messages.empty();

        var formData = new FormData($form[0]);
        formData.append("action", "wc_custom_reviews_submit_review");
        formData.append("nonce", wcCustomReviews.nonce);
        formData.append("product_id", $form.data("product-id"));

        $.ajax({
            url: wcCustomReviews.ajax_url,
            type: "POST",
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                if (response.success) {
                    showMessage($messages, "success", response.data.message);
                    closeReviewModal();
                    showSuccessModal(response.data.message);
                    $form[0].reset();
                    
                    // Limpa previews de múltiplas imagens
                    $("#review-images-preview").empty();
                    $(".images-preview-container").hide();
                    
                    setTimeout(function() {
                        closeReviewModal();
                    }, 2000);
                } else {
                    showMessage($messages, "error", response.data.message);
                }
            },
            error: function() {
                showMessage($messages, "error", wcCustomReviews.strings.error);
            },
            complete: function() {
                $submitBtn.prop("disabled", false).text(wcCustomReviews.strings.submit_review);
            }
        });
    }

    /**
     * Valida o formulário
     */
    function validateForm($form) {
        var $messages = $form.find('.form-messages');
        $messages.empty();

        var customerName = $form.find('input[name="customer_name"]').val().trim();
        var customerEmail = $form.find('input[name="customer_email"]').val().trim();
        var rating = $form.find('input[name="rating"]:checked').val();
        var reviewText = $form.find('textarea[name="review_text"]').val().trim();

        $form.find('input[name="customer_name"]').removeClass('error-field');
        $form.find('input[name="customer_email"]').removeClass('error-field');
        $form.find('textarea[name="review_text"]').removeClass('error-field');

        if (!customerName) {
            $form.find('input[name="customer_name"]').addClass('error-field');
        }
        if (!customerEmail) {
            $form.find('input[name="customer_email"]').addClass('error-field');
        }
        if (!reviewText) {
            $form.find('textarea[name="review_text"]').addClass('error-field');
        }

        if (!customerName || !customerEmail || !rating || !reviewText) {
            showMessage($messages, 'error', wcCustomReviews.strings.required_fields);
            return false;
        }

        if (!isValidEmail(customerEmail)) {
            showMessage($messages, 'error', wcCustomReviews.strings.invalid_email);
            return false;
        }

        return true;
    }

    /**
     * Valida formato de email
     */
    function isValidEmail(email) {
        var emailRegex = /^\S+@\S+\.\S+$/;
        return emailRegex.test(email);
    }

    /**
     * Mostra mensagem para o usuário
     */
    function showMessage($container, type, message) {
        var $message = $('<div class="' + type + '">' + message + '</div>');
        $container.html($message);
    }

    /**
     * Atualiza as estrelas dinamicamente
     */
    function updateStarsDisplay($container, rating) {
        var $stars = $container.find('.star');
        
        $stars.each(function(index) {
            var $star = $(this);
            if (index < Math.floor(rating)) {
                $star.removeClass('empty half').addClass('filled');
            } else if (index < rating && rating % 1 !== 0) {
                $star.removeClass('empty filled').addClass('half');
            } else {
                $star.removeClass('filled half').addClass('empty');
            }
        });
    }

    /**
     * Carrega mais reviews (para paginação futura)
     */
    function loadMoreReviews($widget, page) {
        var productId = $widget.data('product-id');
        
        $.ajax({
            url: wcCustomReviews.ajax_url,
            type: 'POST',
            data: {
                action: 'wc_custom_reviews_load_more',
                nonce: wcCustomReviews.nonce,
                product_id: productId,
                page: page
            },
            success: function(response) {
                if (response.success && response.data.reviews) {
                    var $reviewsList = $widget.find('.reviews-list');
                    $reviewsList.append(response.data.reviews);
                    
                    if (!response.data.has_more) {
                        $widget.find('.load-more-btn').hide();
                    }
                }
            }
        });
    }

    // Expõe funções globalmente se necessário
    window.wcCustomReviewsFrontend = {
        updateStarsDisplay: updateStarsDisplay,
        loadReviews: loadReviews
    };

    /**
     * FUNÇÃO CORRIGIDA: Carrega reviews paginados SEM scroll automático
     * (o scroll agora é feito no clique do botão, antes do AJAX)
     */
    function loadReviews(productId, page, reviewsPerPage, reviewOrder, $widget) {
        console.log('[LOAD REVIEWS] Iniciando loadReviews - produto:', productId, 'página:', page);
        
        // CORREÇÃO: Garante que reviews_per_page seja um número válido
        reviewsPerPage = parseInt(reviewsPerPage) || 10;
        page = parseInt(page) || 1;
        
        var $reviewsList = $widget.find(".reviews-list .wc-custom-reviews-grid");
        var $paginationContainer = $widget.find(".wc-custom-reviews-pagination");
        
        var ajaxData = {
            action: "wc_custom_reviews_load_reviews",
            nonce: wcCustomReviews.nonce,
            product_id: productId,
            page: page,
            reviews_per_page: reviewsPerPage,
            review_order: reviewOrder
        };
        
        console.log('[LOAD REVIEWS] Dados AJAX sendo enviados:', ajaxData);
        console.log('[LOAD REVIEWS] Tipo de product_id:', typeof productId, '- Valor:', productId);
        console.log('[LOAD REVIEWS] wcCustomReviews.nonce:', wcCustomReviews.nonce);
        
        $.ajax({
            url: wcCustomReviews.ajax_url,
            type: "POST",
            data: ajaxData,
            beforeSend: function() {
                console.log('[LOAD REVIEWS] AJAX beforeSend');
                $("#reviews-loader").show();
                $reviewsList.css("opacity", "0.5");
            },
            success: function(response) {
                console.log('[LOAD REVIEWS] AJAX success - response:', response);
                if (response.success && response.data.reviews_html) {
                    console.log('[LOAD REVIEWS] Reviews HTML recebido, tamanho:', response.data.reviews_html.length);
                    $reviewsList.html(response.data.reviews_html);
                    
                    // DEBUG: Verifica contadores de imagens
                    console.log('[DEBUG] Verificando contadores de imagens nos cards:');
                    $('.review-grid-item').each(function(index) {
                        var $item = $(this);
                        var reviewId = $item.data('review-id');
                        var counter = $item.find('.images-counter').text();
                        console.log('  Card #' + index + ' - Review ID:', reviewId, '- Contador:', counter || 'SEM CONTADOR');
                    });
                    
                    // Atualiza os botões de paginação com a lógica aprimorada
                    $paginationContainer.empty();
                    var totalPages = response.data.total_pages;
                    var currentPage = response.data.current_page;
                    var range = 2; // Número de páginas a mostrar antes e depois da página atual

                    // Botão 'Anterior'
                    if (currentPage > 1) {
                        $paginationContainer.append('<button type="button" class="pagination-button" data-page="' + (currentPage - 1) + '">' + wcCustomReviews.strings.previous + '</button>');
                    }

                    // Botão '1' se não estiver no range
                    if (currentPage - range > 1) {
                        $paginationContainer.append('<button type="button" class="pagination-button" data-page="1">1</button>');
                        if (currentPage - range > 2) {
                            $paginationContainer.append('<span class="pagination-dots">...</span>');
                        }
                    }

                    // Números das páginas
                    for (var i = Math.max(1, currentPage - range); i <= Math.min(totalPages, currentPage + range); i++) {
                        var activeClass = (i == currentPage) ? 'active' : '';
                        $paginationContainer.append('<button type="button" class="pagination-button ' + activeClass + '" data-page="' + i + '">' + i + '</button>');
                    }

                    // Botão 'Última' se não estiver no range
                    if (currentPage + range < totalPages) {
                        if (currentPage + range < totalPages - 1) {
                            $paginationContainer.append('<span class="pagination-dots">...</span>');
                        }
                        $paginationContainer.append('<button type="button" class="pagination-button" data-page="' + totalPages + '">' + totalPages + '</button>');
                    }

                    // Botão 'Próximo'
                    if (currentPage < totalPages) {
                        $paginationContainer.append('<button type="button" class="pagination-button" data-page="' + (currentPage + 1) + '">' + wcCustomReviews.strings.next + '</button>');
                    }

                    // Re-inicializa Masonry após carregar novos itens
                    var grid = $reviewsList[0];
                    if (grid) {
                        var msnry = Masonry.data(grid);
                        if (msnry) {
                            msnry.destroy();
                        }
                        msnry = new Masonry(grid, {
                            itemSelector: ".review-grid-item",
                            gutter: 15,
                            percentPosition: true
                        });
                        imagesLoaded(grid).on("progress", function() {
                            msnry.layout();
                        });
                    }
                    
                    // Força carregamento de thumbnails de vídeos carregados via AJAX
                    console.log('[AJAX LOAD] Aguardando renderização completa...');
                    setTimeout(function() {
                        console.log('[AJAX LOAD] Chamando initVideoThumbnails() após AJAX');
                        initVideoThumbnails();
                    }, 100);

                } else {
                    console.warn('[LOAD REVIEWS] Response inválido ou sem HTML:', response);
                    console.warn('[LOAD REVIEWS] Mensagem de erro:', response.data ? response.data.message : 'sem mensagem');
                    console.warn('[LOAD REVIEWS] Data completa:', JSON.stringify(response.data));
                    showMessage($widget.find(".form-messages"), "error", wcCustomReviews.strings.error);
                }
            },
            error: function(xhr, status, error) {
                console.error('[LOAD REVIEWS] AJAX error:', {xhr: xhr, status: status, error: error});
                showMessage($widget.find(".form-messages"), "error", wcCustomReviews.strings.error);
            },
            complete: function() {
                console.log('[LOAD REVIEWS] AJAX complete');
                setTimeout(function() {
                    $("#reviews-loader").hide();
                    $reviewsList.css("opacity", "1");
                    
                    // REMOVIDO: O scroll agora é feito no clique do botão, não aqui
                    // Isso evita que a tela "desça e depois suba"
                }, 300);
            }
        });
    }

        // Adiciona a lógica para abrir o modal de review detalhado
    $(document).on("click", ".review-grid-item", function() {
        var reviewId = $(this).data("review-id");
        if (reviewId) {
            loadSingleReview(reviewId);
        }
    });

    // Fecha o modal de review detalhado
    $(document).on("click", ".wc-single-review-modal-close", function() {
        closeSingleReviewModal();
    });

    // Fecha o modal ao clicar fora dele
    $(document).on("click", ".wc-single-review-modal", function(e) {
        if (e.target === this) {
            closeSingleReviewModal();
        }
    });

    // Fecha o modal ao pressionar ESC
    $(document).on("keydown", function(e) {
        if (e.keyCode === 27 && $("#wc-single-review-modal").is(":visible")) {
            closeSingleReviewModal();
        }
    });

    function loadSingleReview(reviewId) {
        $.ajax({
            url: wcCustomReviews.ajax_url,
            type: "POST",
            data: {
                action: "wc_custom_reviews_get_single_review",
                nonce: wcCustomReviews.nonce,
                review_id: reviewId
            },
            beforeSend: function() {
                // Pode adicionar um loader aqui
            },
            success: function(response) {
                if (response.success && response.data.review_html) {
                    $("#wc-single-review-modal .wc-single-review-modal-inner-content").html(response.data.review_html);
                    $("#wc-single-review-modal").fadeIn(300);
                    $("body").addClass("modal-open");
                    
                    // NOVO: Auto-play do vídeo principal se existir
                    setTimeout(function() {
                        var $mainVideo = $("#wc-single-review-modal .modal-main-video");
                        if ($mainVideo.length > 0) {
                            var videoElement = $mainVideo[0];
                            videoElement.play().catch(function(error) {
                                console.log('Autoplay bloqueado pelo navegador:', error);
                            });
                        }
                    }, 400); // Aguarda modal aparecer completamente
                } else {
                    console.error("Erro ao carregar review: ", response.data.message);
                }
            },
            error: function() {
                console.error("Erro na requisição AJAX para carregar review.");
            },
            complete: function() {
                // Pode remover o loader aqui
            }
        });
    }

    function closeSingleReviewModal() {
        // Pausa o vídeo antes de fechar
        var $mainVideo = $("#wc-single-review-modal .modal-main-video");
        if ($mainVideo.length > 0) {
            $mainVideo[0].pause();
        }
        
        $("#wc-single-review-modal").fadeOut(300, function() {
            $("#wc-single-review-modal .wc-single-review-modal-inner-content").empty(); // Limpa o conteúdo
        });
        $("body").removeClass("modal-open");
    }

    /**
     * Inicializa scroll suave ao clicar nas estrelas
     */
    function initStarsScrollToReviews() {
        $(document).on('click', '.wc-custom-reviews-stars-link', function(e) {
            e.preventDefault();
            
            var targetId = $(this).attr('href');
            var $target = $(targetId);
            
            if ($target.length) {
                $('html, body').animate({
                    scrollTop: $target.offset().top - 100 // 100px de margem do topo
                }, 800); // Animação de 800ms (suave)
            }
        });
    }

    /**
     * Inicializa navegação de galeria de imagens dentro do modal de review
     */
    function initModalGalleryNavigation() {
        // Navegação por setas (prev/next)
        $(document).on('click', '.modal-gallery-prev, .modal-gallery-next', function() {
            var $button = $(this);
            var $gallery = $button.closest('.modal-gallery');
            var $mainContainer = $gallery.find('.modal-gallery-main');
            var $dataScript = $gallery.find('.gallery-images-data');
            var $thumbs = $gallery.find('.modal-thumb');
            
            if (!$dataScript.length) return;
            
            var allMedia = JSON.parse($dataScript.text());
            var $activeThumbnail = $gallery.find('.modal-thumb.active');
            var currentIndex = parseInt($activeThumbnail.data('index')) || 0;
            
            // Pausa vídeo atual se existir
            var $currentVideo = $mainContainer.find('video');
            if ($currentVideo.length) {
                $currentVideo[0].pause();
            }
            
            // Determina o próximo índice
            if ($button.hasClass('modal-gallery-next')) {
                currentIndex = (currentIndex + 1) % allMedia.length;
            } else {
                currentIndex = (currentIndex - 1 + allMedia.length) % allMedia.length;
            }
            
            var media = allMedia[currentIndex];
            
            // Atualiza mídia principal
            if (media.type === 'video') {
                $mainContainer.html('<video src="' + media.url + '" class="modal-main-image modal-main-video" controls></video>');
                // Autoplay do vídeo
                setTimeout(function() {
                    var videoElement = $mainContainer.find('video')[0];
                    if (videoElement) {
                        videoElement.play().catch(function(error) {
                            console.log('Autoplay bloqueado:', error);
                        });
                    }
                }, 100);
            } else {
                $mainContainer.html('<img src="' + media.url + '" alt="" class="modal-main-image">');
            }
            
            // Atualiza thumbnails ativos
            $thumbs.removeClass('active');
            $thumbs.eq(currentIndex).addClass('active');
        });
        
        // Clique nas miniaturas
        $(document).on('click', '.modal-thumb', function() {
            var $thumb = $(this);
            var index = parseInt($thumb.data('index'));
            var $gallery = $thumb.closest('.modal-gallery');
            var $mainContainer = $gallery.find('.modal-gallery-main');
            var $dataScript = $gallery.find('.gallery-images-data');
            
            if (!$dataScript.length) return;
            
            var allMedia = JSON.parse($dataScript.text());
            
            // Pausa vídeo atual se existir
            var $currentVideo = $mainContainer.find('video');
            if ($currentVideo.length) {
                $currentVideo[0].pause();
            }
            
            var media = allMedia[index];
            
            // Atualiza mídia principal
            if (media.type === 'video') {
                $mainContainer.html('<video src="' + media.url + '" class="modal-main-image modal-main-video" controls></video>');
                // Autoplay do vídeo
                setTimeout(function() {
                    var videoElement = $mainContainer.find('video')[0];
                    if (videoElement) {
                        videoElement.play().catch(function(error) {
                            console.log('Autoplay bloqueado:', error);
                        });
                    }
                }, 100);
            } else {
                $mainContainer.html('<img src="' + media.url + '" alt="" class="modal-main-image">');
            }
            
            // Atualiza thumbnail ativo
            $gallery.find('.modal-thumb').removeClass('active');
            $thumb.addClass('active');
        });
        
        // Navegação por teclado (quando modal estiver aberto)
        $(document).on('keydown', function(e) {
            if ($('.wc-single-review-modal').is(':visible')) {
                var $gallery = $('.modal-gallery');
                if (!$gallery.length) return;
                
                if (e.keyCode === 37) { // Seta esquerda
                    $gallery.find('.modal-gallery-prev').click();
                } else if (e.keyCode === 39) { // Seta direita
                    $gallery.find('.modal-gallery-next').click();
                } else if (e.keyCode === 27) { // ESC
                    closeReviewModal();
                }
            }
        });
    }

    /**
     * Força o carregamento de thumbnails de vídeos
     */
    function initVideoThumbnails() {
        console.log('[VIDEO THUMBNAILS] Iniciando função initVideoThumbnails');
        
        // Força o carregamento dos vídeos nos cards
        var videos = $('.review-video-grid video, .modal-thumb video');
        console.log('[VIDEO THUMBNAILS] Total de vídeos encontrados:', videos.length);
        
        videos.each(function(index) {
            var video = this;
            console.log('[VIDEO THUMBNAILS] Processando vídeo ' + (index + 1) + ':', {
                src: video.src,
                readyState: video.readyState,
                currentTime: video.currentTime,
                duration: video.duration
            });
            
            // Define tempo para 0.1s
            try {
                video.currentTime = 0.1;
                console.log('[VIDEO THUMBNAILS] currentTime definido para 0.1s no vídeo ' + (index + 1));
            } catch(e) {
                console.error('[VIDEO THUMBNAILS] Erro ao definir currentTime:', e);
            }
            
            // Força load
            video.load();
            console.log('[VIDEO THUMBNAILS] load() chamado no vídeo ' + (index + 1));
            
            // Quando metadados carregarem, renderiza o frame
            $(video).on('loadedmetadata', function() {
                console.log('[VIDEO THUMBNAILS] Event loadedmetadata disparado no vídeo ' + (index + 1), {
                    duration: video.duration,
                    readyState: video.readyState
                });
                video.currentTime = 0.1;
            });
            
            $(video).on('loadeddata', function() {
                console.log('[VIDEO THUMBNAILS] Event loadeddata disparado no vídeo ' + (index + 1), {
                    currentTime: video.currentTime,
                    readyState: video.readyState
                });
                video.currentTime = 0.1;
            });
            
            $(video).on('canplay', function() {
                console.log('[VIDEO THUMBNAILS] Event canplay disparado no vídeo ' + (index + 1));
            });
            
            $(video).on('error', function(e) {
                console.error('[VIDEO THUMBNAILS] Erro ao carregar vídeo ' + (index + 1) + ':', e, video.error);
            });
            
            // Fallback: tenta novamente após 500ms
            setTimeout(function() {
                console.log('[VIDEO THUMBNAILS] Fallback 500ms - vídeo ' + (index + 1), {
                    readyState: video.readyState,
                    currentTime: video.currentTime
                });
                if (video.readyState >= 2) {
                    video.currentTime = 0.1;
                    console.log('[VIDEO THUMBNAILS] currentTime ajustado para 0.1s no fallback');
                }
            }, 500);
            
            // Fallback 2: tenta após 1s
            setTimeout(function() {
                console.log('[VIDEO THUMBNAILS] Fallback 1000ms - vídeo ' + (index + 1), {
                    readyState: video.readyState,
                    currentTime: video.currentTime,
                    paused: video.paused
                });
                if (video.readyState >= 2) {
                    video.currentTime = 0.1;
                }
            }, 1000);
        });
        
        // Observer para vídeos carregados dinamicamente
        var observer = new MutationObserver(function(mutations) {
            console.log('[VIDEO THUMBNAILS] MutationObserver disparado');
            mutations.forEach(function(mutation) {
                if (mutation.addedNodes.length) {
                    console.log('[VIDEO THUMBNAILS] Nós adicionados detectados:', mutation.addedNodes.length);
                    $(mutation.addedNodes).find('video').each(function(index) {
                        var video = this;
                        console.log('[VIDEO THUMBNAILS] Novo vídeo dinâmico encontrado:', video.src);
                        video.currentTime = 0.1;
                        video.load();
                        
                        $(video).on('loadedmetadata loadeddata', function() {
                            console.log('[VIDEO THUMBNAILS] Metadados carregados para vídeo dinâmico');
                            video.currentTime = 0.1;
                        });
                    });
                }
            });
        });
        
        // Observa mudanças no grid de reviews
        var grid = document.querySelector('.wc-custom-reviews-grid');
        if (grid) {
            console.log('[VIDEO THUMBNAILS] MutationObserver iniciado no grid');
            observer.observe(grid, { childList: true, subtree: true });
        } else {
            console.warn('[VIDEO THUMBNAILS] Grid .wc-custom-reviews-grid não encontrado');
        }
    }


})(jQuery);

