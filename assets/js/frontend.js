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
        initReviewForm();
        initRatingInput();
        initReviewModal();
        initPagination(); // Nova função para inicializar paginação

        // Inicializa o carregamento de reviews para todos os widgets na página
        $('.wc-custom-reviews-widget').each(function() {
            var $widget = $(this);
            var productId = $widget.data('product-id');
            var reviewsPerPage = parseInt($widget.find('.wc-custom-reviews-pagination').data('reviews-per-page'));
            var reviewOrder = $widget.data('review-order');
            loadReviews(productId, 1, reviewsPerPage, reviewOrder, $widget);
        });
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
            if (page > 1) { // Só faz scroll se não for a primeira página
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

        $("#review_image").on("change", function() {
            var input = this;
            var $preview = $("#review-image-preview");
            var $removeBtn = $("#remove-image-preview");

            if (input.files && input.files[0]) {
                var reader = new FileReader();

                reader.onload = function(e) {
                    $preview.attr("src", e.target.result).show();
                    $removeBtn.show();
                };

                reader.readAsDataURL(input.files[0]);
            } else {
                $preview.hide().attr("src", "");
                $removeBtn.hide();
            }
        });

        $("#remove-image-preview").on("click", function() {
            $("#review_image").val("");
            $("#review-image-preview").hide().attr("src", "");
            $(this).hide();
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
                    $("#review-image-preview").hide().attr("src", "");
                    $("#remove-image-preview").hide();
                    
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
        var $reviewsList = $widget.find(".reviews-list .wc-custom-reviews-grid");
        var $paginationContainer = $widget.find(".wc-custom-reviews-pagination");
        
        $.ajax({
            url: wcCustomReviews.ajax_url,
            type: "POST",
            data: {
                action: "wc_custom_reviews_load_reviews",
                nonce: wcCustomReviews.nonce,
                product_id: productId,
                page: page,
                reviews_per_page: reviewsPerPage,
                review_order: reviewOrder
            },
            beforeSend: function() {
                $("#reviews-loader").show();
                $reviewsList.css("opacity", "0.5");
            },
            success: function(response) {
                if (response.success && response.data.reviews_html) {
                    $reviewsList.html(response.data.reviews_html);
                    
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

                } else {
                    showMessage($widget.find(".form-messages"), "error", wcCustomReviews.strings.error);
                }
            },
            error: function() {
                showMessage($widget.find(".form-messages"), "error", wcCustomReviews.strings.error);
            },
            complete: function() {
                setTimeout(function() {
                    $("#reviews-loader").hide();
                    $reviewsList.css("opacity", "1");
                    
                    // REMOVIDO: O scroll agora é feito no clique do botão, não aqui
                    // Isso evita que a tela "desça e depois suba"
                }, 300);
            }
        });
    }

})(jQuery);

