jQuery(document).ready(function($) {
    function showMessage(text, isError) {
        $('#wm-action-message').html('<span style="color:' + (isError ? 'red' : 'green') + ';">' + text + '</span>');
        setTimeout(function() { $('#wm-action-message').html(''); }, 8000);
    }

    // ========== AÑADIR/ELIMINAR FUENTES ==========
    function createNewFeedBlock(index) {
        var container = $('#writeman-feeds-container');
        var firstBlock = container.find('.feed-item:first');
        if (!firstBlock.length) return null;
        var newBlock = firstBlock.clone();
        newBlock.find('input[type="url"], input[type="text"]').val('');
        newBlock.find('select').each(function() {
            if ($(this).prop('multiple')) $(this).val([]);
            else $(this).val('0');
        });
        newBlock.find('textarea').val('');
        newBlock.find('h4').text('📡 Fuente RSS #' + (index+1));
        newBlock.find('[name]').each(function() {
            var name = $(this).attr('name');
            if (name) {
                var newName = name.replace(/\[\d+?\]/, '[' + index + ']');
                $(this).attr('name', newName);
            }
        });
        return newBlock;
    }

    $('#add-feed').on('click', function(e) {
        e.preventDefault();
        var container = $('#writeman-feeds-container');
        var newIndex = container.children('.feed-item').length;
        var newBlock = createNewFeedBlock(newIndex);
        if (newBlock) container.append(newBlock);
        else alert('Error: no se pudo crear el bloque.');
    });

    $(document).on('click', '.remove-feed', function(e) {
        e.preventDefault();
        var container = $('#writeman-feeds-container');
        if (container.children('.feed-item').length === 1) {
            showMessage('❌ No puedes eliminar la última fuente.', true);
            return;
        }
        $(this).closest('.feed-item').remove();
        $('#writeman-feeds-container .feed-item').each(function(idx) {
            $(this).find('h4').text('📡 Fuente RSS #' + (idx+1));
            $(this).find('[name]').each(function() {
                var name = $(this).attr('name');
                if (name) {
                    var newName = name.replace(/\[\d+?\]/, '[' + idx + ']');
                    $(this).attr('name', newName);
                }
            });
        });
    });

    // ========== EJECUTAR COLA ==========
    function runQueue(btn) {
        btn.prop('disabled', true).text('Procesando...');
        $.post(writeman_ajax.ajax_url, {
            action: 'writeman_run_now',
            nonce: writeman_ajax.nonce
        }, function(resp) {
            if (resp.success) showMessage(resp.data.message, false);
            else showMessage(resp.data, true);
            btn.prop('disabled', false).text('▶ Ejecutar cola ahora');
        }).fail(function() {
            showMessage('❌ Error de conexión al ejecutar cola.', true);
            btn.prop('disabled', false).text('▶ Ejecutar cola ahora');
        });
    }
    $('#wm-run-now, #wm-run-queue').on('click', function() { runQueue($(this)); });

    // ========== SINCRONIZAR FUENTES ==========
    $('#wm-sync-feeds').on('click', function() {
        var btn = $(this);
        btn.prop('disabled', true).text('Sincronizando...');
        $.post(writeman_ajax.ajax_url, {
            action: 'writeman_sync_feeds',
            nonce: writeman_ajax.nonce
        }, function(resp) {
            if (resp.success) showMessage(resp.data.message, false);
            else showMessage(resp.data, true);
            btn.prop('disabled', false).text('🔄 Sincronizar fuentes');
        }).fail(function() {
            showMessage('❌ Error de conexión al sincronizar.', true);
            btn.prop('disabled', false).text('🔄 Sincronizar fuentes');
        });
    });

    // ========== PROBAR PRIMERA FUENTE ==========
    $('#wm-test-feed').on('click', function() {
        var btn = $(this);
        btn.prop('disabled', true).text('Probando...');
        $.post(writeman_ajax.ajax_url, {
            action: 'writeman_test_feed',
            nonce: writeman_ajax.nonce
        }, function(resp) {
            if (resp.success) showMessage(resp.data, false);
            else showMessage(resp.data, true);
            btn.prop('disabled', false).text('📡 Probar primera fuente');
        }).fail(function() {
            showMessage('❌ Error de conexión al probar fuente.', true);
            btn.prop('disabled', false).text('📡 Probar primera fuente');
        });
    });

    // ========== LIMPIAR COLA ==========
    $('#wm-clear-queue').on('click', function() {
        if (!confirm('⚠️ Esto ELIMINARÁ TODOS los pendientes y fallidos. ¿Continuar?')) return;
        var btn = $(this);
        btn.prop('disabled', true).text('Limpiando...');
        $.post(writeman_ajax.ajax_url, {
            action: 'writeman_clear_queue',
            nonce: writeman_ajax.nonce
        }, function(resp) {
            if (resp.success) showMessage(resp.data, false);
            else showMessage(resp.data, true);
            btn.prop('disabled', false).text('🗑️ Limpiar cola');
            setTimeout(function() { location.reload(); }, 1500);
        }).fail(function() {
            showMessage('❌ Error de conexión al limpiar.', true);
            btn.prop('disabled', false).text('🗑️ Limpiar cola');
        });
    });

    // ========== REFRESCAR COLA ==========
    $('#wm-refresh-queue').on('click', function() {
        $.post(ajaxurl, { action: 'writeman_refresh_queue' }, function(response) {
            $('#wm-queue-stats').html(response);
        });
    });

    // ========== PROBAR CONEXIÓN IA (MEJORADA) ==========
    $('#wm-test-ai-connection').on('click', function() {
        var btn = $(this);
        var resultSpan = $('#wm-ai-test-result');
        btn.prop('disabled', true).text('Probando...');
        resultSpan.html('🔄 Conectando...');
        $.ajax({
            url: writeman_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'writeman_test_ai_connection',
                nonce: writeman_ajax.nonce
            },
            dataType: 'json',
            timeout: 30000,
            success: function(resp) {
                if (resp.success) {
                    resultSpan.html('<span style="color:green;">✅ ' + resp.data + '</span>');
                } else {
                    resultSpan.html('<span style="color:red;">❌ ' + resp.data + '</span>');
                }
                btn.prop('disabled', false).text('🔌 Probar conexión con IA');
            },
            error: function(xhr, status, error) {
                var errorMsg = 'Error: ' + status;
                if (xhr.responseJSON && xhr.responseJSON.data) {
                    errorMsg = xhr.responseJSON.data;
                } else if (xhr.responseText) {
                    errorMsg = 'Respuesta del servidor: ' + xhr.responseText.substring(0, 200);
                }
                resultSpan.html('<span style="color:red;">❌ ' + errorMsg + '</span>');
                btn.prop('disabled', false).text('🔌 Probar conexión con IA');
                console.error('AJAX Error:', status, error, xhr.responseText);
            }
	$('#wm-test-ai-connection').on('click', function() {
    var btn = $(this);
    var resultSpan = $('#wm-ai-test-result');
    btn.prop('disabled', true).text('Probando...');
    resultSpan.html('');
    $.post(writeman_ajax.ajax_url, {
        action: 'writeman_test_ai_connection',
        nonce: writeman_ajax.nonce
    }, function(resp) {
        if (resp.success) {
            resultSpan.html('<span style="color:green;">✅ ' + resp.data + '</span>');
        } else {
            resultSpan.html('<span style="color:red;">❌ ' + resp.data + '</span>');
        }
        btn.prop('disabled', false).text('🔌 Probar conexión con IA');
    }).fail(function() {
        resultSpan.html('<span style="color:red;">❌ Error de conexión con el servidor.</span>');
        btn.prop('disabled', false).text('🔌 Probar conexión con IA');
    		});
		});
    });
});