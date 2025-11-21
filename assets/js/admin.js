/**
 * WC Custom Reviews - Admin JavaScript
 */

// Variáveis globais para gerenciar imagens e vídeos
var selectedImages = [];
var selectedVideo = null;
var mediaUploader;

// Função global para atualizar previews de imagens e vídeos
window.updateImagePreviews = function() {
    var $ = jQuery;
    var $container = $("#current-review-images-container");
    
    $container.empty();
    
    if (selectedImages.length === 0 && !selectedVideo) {
        $container.html('<p id="no-image-text" style="color: #666; font-style: italic;">Nenhuma mídia selecionada</p>');
        $("#edit_image_url").val('');
        $("#edit_video_url").val('');
        $("#remove-all-images-btn").hide();
        return;
    }
    
    // Armazena imagens como JSON e vídeo separado
    $("#edit_image_url").val(JSON.stringify(selectedImages));
    $("#edit_video_url").val(selectedVideo || '');
    $("#remove-all-images-btn").show();
    
    // Mostra preview do vídeo primeiro (se existir)
    if (selectedVideo) {
        var videoUrlWithTime = selectedVideo + '#t=0.1';
        var $videoPreview = $('<div style="position: relative; display: inline-block; margin-right: 10px;">' +
            '<video src="' + videoUrlWithTime + '" style="width: 100px; height: 100px; object-fit: cover; border: 1px solid #ddd; padding: 5px; border-radius: 3px; background: #000;" muted preload="metadata"></video>' +
            '<div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); width: 30px; height: 30px; background: rgba(0,0,0,0.7); border-radius: 50%; display: flex; align-items: center; justify-content: center; pointer-events: none;">' +
            '<i class="fa-solid fa-play" style="color: white; font-size: 12px; margin-left: 2px;"></i></div>' +
            '<button type="button" class="remove-video" style="position: absolute; top: -5px; right: -5px; background: #dc3232; color: white; border: none; border-radius: 50%; width: 24px; height: 24px; cursor: pointer; font-size: 14px; line-height: 1;">&times;</button>' +
            '</div>');
        $container.append($videoPreview);
        
        // Força carregamento
        $videoPreview.find('video')[0].load();
    }
    
    // Mostra previews de imagens
    selectedImages.forEach(function(url, index) {
        var $preview = $('<div style="position: relative; display: inline-block; margin-right: 10px;">' +
            '<img src="' + url + '" style="width: 100px; height: 100px; object-fit: cover; border: 1px solid #ddd; padding: 5px; border-radius: 3px;" />' +
            '<button type="button" class="remove-single-image" data-index="' + index + '" style="position: absolute; top: -5px; right: -5px; background: #dc3232; color: white; border: none; border-radius: 50%; width: 24px; height: 24px; cursor: pointer; font-size: 14px; line-height: 1;">&times;</button>' +
            '</div>');
        $container.append($preview);
    });
};

// Funções globais acessíveis via onclick
window.updateReviewStatus = function(reviewId, status) {
    if (!reviewId || !status) {
        alert(wcCustomReviewsAdmin.strings.error);
        return;
    }

    var $ = jQuery; // Garante que $ se refere a jQuery
    var $row = $("#review-" + reviewId);
    
    // Adiciona classe de loading
    $row.addClass("loading");

    $.ajax({
        url: wcCustomReviewsAdmin.ajax_url,
        type: "POST",
        data: {
            action: "wc_custom_reviews_update_status",
            nonce: wcCustomReviewsAdmin.nonce,
            review_id: reviewId,
            status: status
        },
        success: function(response) {
            if (response.success) {
                // Atualiza o status na tabela
                var $statusBadge = $row.find(".status-badge");
                $statusBadge.removeClass("status-pendente status-aprovado")
                          .addClass("status-" + status)
                          .text(capitalizeFirst(status));
                
                // Atualiza os botões de ação
                updateActionButtons($row, status);
                
                showAdminNotice("success", response.data.message);
            } else {
                showAdminNotice("error", response.data.message);
            }
        },
        error: function() {
            showAdminNotice("error", wcCustomReviewsAdmin.strings.error);
        },
        complete: function() {
            $row.removeClass("loading");
        }
    });
};

window.deleteReview = function(reviewId) {
    if (!reviewId) {
        alert(wcCustomReviewsAdmin.strings.error);
        return;
    }

    if (!confirm(wcCustomReviewsAdmin.strings.confirm_delete)) {
        return;
    }

    var $ = jQuery; // Garante que $ se refere a jQuery
    var $row = $("#review-" + reviewId);
    
    // Adiciona classe de loading
    $row.addClass("loading");

    $.ajax({
        url: wcCustomReviewsAdmin.ajax_url,
        type: "POST",
        data: {
            action: "wc_custom_reviews_delete_review",
            nonce: wcCustomReviewsAdmin.nonce,
            review_id: reviewId
        },
        success: function(response) {
            if (response.success) {
                // Remove a linha da tabela com animação
                $row.fadeOut(300, function() {
                    $(this).remove();
                    
                    // Verifica se não há mais reviews
                    if ($(".wp-list-table tbody tr").length === 0) {
                        location.reload();
                    }
                });
                
                showAdminNotice("success", response.data.message);
            } else {
                showAdminNotice("error", response.data.message);
            }
        },
        error: function() {
            showAdminNotice("error", wcCustomReviewsAdmin.strings.error);
        },
        complete: function() {
            $row.removeClass("loading");
        }
    });
};

window.filterReviews = function() {
    var $ = jQuery; // Garante que $ se refere a jQuery
    var status = $("#status-filter").val();
    var currentUrl = window.location.href.split("?")[0];
    var newUrl = currentUrl + "?page=wc-custom-reviews-comments";
    
    if (status) {
        newUrl += "&status=" + encodeURIComponent(status);
    }
    
    window.location.href = newUrl;
};

window.editReview = function(reviewId) {
    if (!reviewId) {
        alert(wcCustomReviewsAdmin.strings.error);
        return;
    }

    var $ = jQuery; // Garante que $ se refere a jQuery
    // Busca dados do review
    $.ajax({
        url: wcCustomReviewsAdmin.ajax_url,
        type: "POST",
        data: {
            action: "wc_custom_reviews_get_review_for_edit",
            nonce: wcCustomReviewsAdmin.nonce,
            review_id: reviewId
        },
        success: function(response) {
            if (response.success) {
                var review = response.data.review;
                
                // Preenche o formulário
                $("#edit_review_id").val(review.id);
                $("#edit_customer_name").val(review.customer_name);
                $("#edit_customer_email").val(review.customer_email);
                $("#edit_rating").val(review.rating);
                $("#edit_status").val(review.status);
                $("#edit_review_text").val(review.review_text);
                
                // Processa múltiplas imagens
                selectedImages = []; // Limpa array global
                
                if (review.image_url) {
                    try {
                        // Tenta parsear como JSON (múltiplas imagens)
                        var parsed = JSON.parse(review.image_url);
                        if (Array.isArray(parsed)) {
                            selectedImages = parsed;
                        } else {
                            selectedImages = [review.image_url];
                        }
                    } catch (e) {
                        // Se não for JSON válido, trata como string única
                        selectedImages = [review.image_url];
                    }
                }
                
                // Carrega vídeo se existir
                if (review.video_url) {
                    selectedVideo = review.video_url;
                } else {
                    selectedVideo = null;
                }
                
                window.updateImagePreviews();
                window.updateImagePreviews();
                
                // Mostra o modal
                $("#edit-review-modal").show();
            } else {
                alert(response.data.message || wcCustomReviewsAdmin.strings.error);
            }
        },
        error: function() {
            alert(wcCustomReviewsAdmin.strings.error);
        }
    });
};

// Variável global para armazenar o ID do review atual no modal de imagem
var currentImageReviewId = null;

window.openImageModal = function(imageUrl, reviewId) {
    var $ = jQuery;
    currentImageReviewId = reviewId;
    $("#modal-image").attr('src', imageUrl);
    $("#image-view-modal").show();
};

window.closeImageModal = function() {
    var $ = jQuery;
    $("#image-view-modal").hide();
    currentImageReviewId = null;
};

window.editReviewFromImage = function() {
    if (currentImageReviewId) {
        closeImageModal();
        editReview(currentImageReviewId);
    }
};

window.closeEditModal = function() {
    var $ = jQuery; // Garante que $ se refere a jQuery
    $("#edit-review-modal").hide();
    $("#edit-review-form")[0].reset();
};

// Funções que dependem do DOM estar pronto
(function($) {
    'use strict';

    $(document).ready(function() {
        initAdminFunctions();

        $("#edit-review-form").on("submit", function(e) {
            e.preventDefault();
            
            var imageUrlValue = $("#edit_image_url").val();
            var videoUrlValue = $("#edit_video_url").val();
            
            var formData = {
                action: "wc_custom_reviews_save_review_edit",
                nonce: wcCustomReviewsAdmin.nonce,
                review_id: $("#edit_review_id").val(),
                customer_name: $("#edit_customer_name").val(),
                customer_email: $("#edit_customer_email").val(),
                rating: $("#edit_rating").val(),
                status: $("#edit_status").val(),
                review_text: $("#edit_review_text").val(),
                image_url: imageUrlValue,
                video_url: videoUrlValue
            };
            
            $.ajax({
                url: wcCustomReviewsAdmin.ajax_url,
                type: "POST",
                data: formData,
                success: function(response) {
                    if (response.success) {
                        showAdminNotice("success", response.data.message);
                        closeEditModal();
                        location.reload(); // Recarrega a página para mostrar as alterações
                    } else {
                        alert(response.data.message || wcCustomReviewsAdmin.strings.error);
                    }
                },
                error: function() {
                    alert(wcCustomReviewsAdmin.strings.error);
                }
            });
        });

        // Inicializa o uploader de mídia do WordPress
        $("#upload-image-btn").on("click", function(e) {
            e.preventDefault();
            
            // Se o uploader já existe, abre ele
            if (mediaUploader) {
                mediaUploader.open();
                return;
            }
            
            // Cria o uploader com múltipla seleção para imagens E vídeos
            mediaUploader = wp.media({
                title: 'Selecionar Imagens e Vídeos',
                button: {
                    text: 'Usar estas mídias'
                },
                multiple: true, // Permite múltiplas seleções
                library: {
                    type: ['image', 'video']
                }
            });
            
            // Quando mídias são selecionadas
            mediaUploader.on('select', function() {
                var selection = mediaUploader.state().get('selection');
                var newImages = [];
                var newVideo = null;
                
                selection.map(function(attachment) {
                    attachment = attachment.toJSON();
                    
                    // Separa vídeos de imagens
                    if (attachment.type === 'video') {
                        if (!newVideo) { // Apenas 1 vídeo permitido
                            newVideo = attachment.url;
                        }
                    } else {
                        newImages.push(attachment.url);
                    }
                });
                
                // Atualiza vídeo (substitui o anterior)
                if (newVideo) {
                    selectedVideo = newVideo;
                }
                
                // Adiciona novas imagens às existentes
                selectedImages = selectedImages.concat(newImages);
                
                // Limita a 10 imagens
                if (selectedImages.length > 10) {
                    selectedImages = selectedImages.slice(0, 10);
                    alert('Você pode selecionar no máximo 10 imagens. Apenas as 10 primeiras foram mantidas.');
                }
                
                // Atualiza o campo hidden e mostra previews
                window.updateImagePreviews();
            });
            
            // Abre o uploader
            mediaUploader.open();
        });
        
        // Remove uma imagem específica
        $(document).on("click", ".remove-single-image", function(e) {
            e.preventDefault();
            var index = $(this).data("index");
            selectedImages.splice(index, 1);
            window.updateImagePreviews();
        });
        
        // Remove vídeo
        $(document).on("click", ".remove-video", function(e) {
            e.preventDefault();
            selectedVideo = null;
            window.updateImagePreviews();
        });
        
        // Botão para remover todas as mídias
        $("#remove-all-images-btn").on("click", function(e) {
            e.preventDefault();
            selectedImages = [];
            selectedVideo = null;
            window.updateImagePreviews();
        });

        // Inicializa color pickers quando a página carrega
        $(window).on("load", function() {
            initColorPickers();
        });
    });

    function initAdminFunctions() {
        // Não precisa inicializar nada específico aqui por enquanto
        // As funções são chamadas diretamente pelos botões
    }

    /**
     * Atualiza os botões de ação baseado no status
     */
    function updateActionButtons($row, status) {
        var $actions = $row.find(".row-actions");
        var reviewId = $row.attr("id").replace("review-", "");
        
        var buttonsHtml = "";
        
        if (status !== "aprovado") {
            buttonsHtml += 
                '<span class="approve">' +
                '<a href="#" onclick="updateReviewStatus(' + reviewId + ', \'aprovado\')" class="button button-small button-primary">' +
                'Aprovar</a></span> ';
        }
    
        // Adiciona o botão Editar
        buttonsHtml += 
            '<span class="edit">' +
            '<a href="#" onclick="editReview(' + reviewId + ')" class="button button-small">' +
            'Editar</a></span> ';

        buttonsHtml += 
            '<span class="delete">' +
            '<a href="#" onclick="deleteReview(' + reviewId + ')" class="button button-small button-link-delete">' +
            'Excluir</a></span>';
        
        $actions.html(buttonsHtml);
    }

    /**
     * Mostra notificação administrativa
     */
    function showAdminNotice(type, message) {
        // Remove notificações existentes
        $(".wc-custom-reviews-notice").remove();
        
        var noticeClass = type === "success" ? "notice-success" : "notice-error";
        var $notice = $("<div class=\"notice " + noticeClass + " is-dismissible wc-custom-reviews-notice\">" +
                       "<p>" + message + "</p>" +
                       "<button type=\"button\" class=\"notice-dismiss\">" +
                       "<span class=\"screen-reader-text\">Dispensar este aviso.</span>" +
                       "</button></div>");
        
        // Adiciona a notificação no topo da página
        $(".wrap h1").after($notice);
        
        // Adiciona funcionalidade de fechar
        $notice.find(".notice-dismiss").on("click", function() {
            $notice.fadeOut(300, function() {
                $(this).remove();
            });
        });
        
        // Remove automaticamente após 5 segundos
        setTimeout(function() {
            $notice.fadeOut(300, function() {
                $(this).remove();
            });
        }, 5000);
        
        // Scroll suave até a notificação
        $("html, body").animate({
            scrollTop: $notice.offset().top - 50
        }, 300);
    }

    /**
     * Capitaliza a primeira letra
     */
    function capitalizeFirst(str) {
        return str.charAt(0).toUpperCase() + str.slice(1);
    }

    /**
     * Inicializa seletor de cores personalizados
     */
    function initColorPickers() {
        if (typeof wp !== "undefined" && wp.colorPicker) {
            $(".color-picker").wpColorPicker({
                change: function(event, ui) {
                    // Atualiza preview em tempo real se necessário
                    var color = ui.color.toString();
                    $(this).siblings(".color-preview").css("background-color", color);
                }
            });
        }
    }

    /**
     * Exporta estatísticas (funcionalidade futura)
     */
    window.exportReviewsData = function(format) {
        var data = {
            action: "wc_custom_reviews_export_data",
            nonce: wcCustomReviewsAdmin.nonce,
            format: format || "csv"
        };
        
        // Cria um formulário temporário para download
        var $form = $("<form method=\"post\" action=\"" + wcCustomReviewsAdmin.ajax_url + "\"> ");
        
        $.each(data, function(key, value) {
            $form.append("<input type=\"hidden\" name=\"" + key + "\" value=\"" + value + "\"> ");
        });
        
        $("body").append($form);
        $form.submit();
        $form.remove();
    };

    // Inicializa color pickers quando a página carrega
    $(window).on("load", function() {
        initColorPickers();
        initAdminVideoThumbnails();
    });
    
    // Função para inicializar thumbnails de vídeos no admin
    function initAdminVideoThumbnails() {
        $('table.wp-list-table video').each(function() {
            var video = this;
            video.currentTime = 0.1;
            video.load();
        });
    }

})(jQuery);
