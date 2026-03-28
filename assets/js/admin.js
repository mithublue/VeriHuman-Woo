/* global verihumanData, tinymce, wp */
(function ($) {
    'use strict';

    // ─── Helpers ────────────────────────────────────────────────────────────────

    function setStatus(msg, type) {
        var $el = $('#verihuman-status');
        $el.attr('class', 'verihuman-status ' + (type || '')).html(msg).show();
    }

    function clearStatus() {
        $('#verihuman-status').hide().html('');
    }

    /**
     * Get the active target editor ID based on UI toggle.
     */
    function getTargetId() {
        return $('input[name="verihuman_target"]:checked').val() === 'excerpt' ? 'excerpt' : 'content';
    }

    /**
     * Get content from the editor (optionally only selection).
     */
    function getEditorContent(onlySelection) {
        var targetId = getTargetId();

        // Gutenberg (Block Editor)
        if (typeof wp !== 'undefined' && wp.data && wp.data.select('core/block-editor')) {
            try {
                if (onlySelection) {
                    var selectedBlocks = wp.data.select('core/block-editor').getSelectedBlocks();
                    if (selectedBlocks.length > 0) {
                        return selectedBlocks.map(function (block) {
                            return block.attributes.content || '';
                        }).join('\n\n');
                    }
                }
                // Fallback to full content of specific area
                if (targetId === 'excerpt') {
                    return wp.data.select('core/editor').getEditedPostAttribute('excerpt') || '';
                }
                return wp.data.select('core/editor').getEditedPostContent() || '';
            } catch (e) { }
        }

        // Classic Editor (TinyMCE)
        if (typeof tinymce !== 'undefined' && tinymce.get(targetId)) {
            var editor = tinymce.get(targetId);
            return onlySelection ? editor.selection.getContent({ format: 'text' }) : editor.getContent();
        }

        // Plain textarea fallback
        var $textarea = $('#' + targetId);
        return $textarea.length ? $textarea.val() : '';
    }

    /**
     * Insert HTML into the active editor.
     */
    function insertIntoEditor(html) {
        var targetId = getTargetId();

        // Gutenberg
        if (typeof wp !== 'undefined' && wp.data && wp.data.dispatch) {
            try {
                if (targetId === 'excerpt') {
                    wp.data.dispatch('core/editor').editPost({ excerpt: html });
                } else {
                    var block = wp.blocks.createBlock('core/freeform', { content: html });
                    wp.data.dispatch('core/editor').insertBlocks(block);
                }
                return;
            } catch (e) { }
        }

        // Classic Editor
        if (typeof tinymce !== 'undefined' && tinymce.get(targetId)) {
            var editor = tinymce.get(targetId);
            if (editor.selection.getContent()) {
                editor.selection.setContent(html);
            } else {
                editor.setContent(html);
            }
            return;
        }

        // Plain textarea fallback
        var $textarea = $('#' + targetId);
        if ($textarea.length) $textarea.val(html);
    }

    /**
     * Trigger WordPress auto-save.
     */
    function autoSave() {
        return new Promise(function (resolve) {
            if (typeof wp !== 'undefined' && wp.data) {
                try {
                    wp.data.dispatch('core/editor').savePost({ isAutosave: true });
                    setTimeout(resolve, 800);
                    return;
                } catch (e) { }
            }
            var $saveDraft = $('#save-post');
            if ($saveDraft.length) {
                $saveDraft.one('click', function () { setTimeout(resolve, 600); });
                $saveDraft.trigger('click');
            } else {
                resolve();
            }
        });
    }

    // ─── Selection Monitoring ───────────────────────────────────────────────────

    function checkSelection() {
        var selection = getEditorContent(true);
        if (selection && selection.trim().length > 5) {
            $('.verihuman-selection-notice').show();
        } else {
            $('.verihuman-selection-notice').hide();
        }
    }
    setInterval(checkSelection, 2000);

    // ─── Tabs ───────────────────────────────────────────────────────────────────

    $(document).on('click', '.verihuman-tab-btn', function () {
        var tab = $(this).data('tab');
        $('.verihuman-tab-btn').removeClass('active');
        $(this).addClass('active');
        $('.verihuman-tab-content').removeClass('active');
        $('#verihuman-tab-' + tab).addClass('active');
        clearStatus();
    });

    // ─── Feature: Generate ──────────────────────────────────────────────────────

    $(document).on('click', '#verihuman-generate-btn', function () {
        var $btn = $(this);
        $btn.prop('disabled', true).find('.verihuman-spinner').show();
        $btn.find('.verihuman-btn-icon').hide();
        clearStatus();

        var productId = $('#post_ID').val() || 0;
        var productName = $('#title').val() || $('#post_title').val() || '';

        var tone = $('#verihuman-gen-tone').val();
        var language = $('#verihuman-gen-language').val();
        var copyLength = $('input[name="verihuman_gen_length"]:checked').val();

        autoSave().then(function () {
            $.post(verihumanData.ajaxUrl, {
                action: 'verihuman_generate_copy',
                nonce: verihumanData.nonce,
                product_id: productId,
                product_name: productName,
                tone: tone,
                language: language,
                copy_length: copyLength,
                target: getTargetId()
            })
                .done(function (res) {
                    if (res.success && res.data.generatedText) {
                        insertIntoEditor(res.data.generatedText);
                        setStatus('✅ AI copy generated!', 'success');
                    } else {
                        setStatus('❌ ' + (res.data.message || 'Error'), 'error');
                    }
                })
                .fail(function (xhr) {
                    var msg = 'API Request failed.';
                    if (xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) {
                        msg = xhr.responseJSON.data.message;
                    }
                    setStatus('❌ ' + msg, 'error');
                })
                .always(function () {
                    $btn.prop('disabled', false).find('.verihuman-spinner').hide();
                    $btn.find('.verihuman-btn-icon').show();
                });
        });
    });

    // ─── Feature: Detect ────────────────────────────────────────────────────────

    $(document).on('click', '#verihuman-detect-btn', function () {
        var $btn = $(this);
        var text = getEditorContent(true) || getEditorContent(false);

        if (!text || text.length < 20) {
            setStatus('❌ Text too short to analyze.', 'error');
            return;
        }

        $btn.prop('disabled', true).find('.verihuman-spinner').show();
        $('#verihuman-detect-result').hide();

        $.post(verihumanData.ajaxUrl, {
            action: 'verihuman_detect_text',
            nonce: verihumanData.nonce,
            text: text
        })
            .done(function (res) {
                if (res.success) {
                    var html = '<strong>Score: ' + res.data.score + '% AI</strong><br>';
                    html += '<small>' + res.data.verdict + '</small><br>';
                    html += '<p style="font-size:11px; margin-top:5px;">' + res.data.reason + '</p>';
                    $('#verihuman-detect-result').html(html).fadeIn();
                } else {
                    setStatus('❌ ' + (res.data.message || 'Error'), 'error');
                }
            })
            .fail(function (xhr) {
                var msg = 'Detection failed.';
                if (xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) {
                    msg = xhr.responseJSON.data.message;
                }
                setStatus('❌ ' + msg, 'error');
            })
            .always(function () { $btn.prop('disabled', false).find('.verihuman-spinner').hide(); });
    });

    // ─── Feature: Humanize ──────────────────────────────────────────────────────

    $(document).on('click', '#verihuman-humanize-btn', function () {
        var $btn = $(this);
        var text = getEditorContent(true) || getEditorContent(false);
        var tone = $('#verihuman-humanize-tone').val();

        if (!text || text.length < 20) {
            setStatus('❌ Text too short to humanize.', 'error');
            return;
        }

        $btn.prop('disabled', true).find('.verihuman-spinner').show();
        $('#verihuman-humanize-result').hide();

        $.post(verihumanData.ajaxUrl, {
            action: 'verihuman_humanize_text',
            nonce: verihumanData.nonce,
            text: text,
            tone: tone
        })
            .done(function (res) {
                if (res.success) {
                    $('#verihuman-humanize-result .verihuman-result-text').text(res.data.humanizedText);
                    $('#verihuman-humanize-result').fadeIn();
                } else {
                    setStatus('❌ ' + (res.data.message || 'Error'), 'error');
                }
            })
            .fail(function (xhr) {
                var msg = 'Humanization failed.';
                if (xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message) {
                    msg = xhr.responseJSON.data.message;
                }
                setStatus('❌ ' + msg, 'error');
            })
            .always(function () { $btn.prop('disabled', false).find('.verihuman-spinner').hide(); });
    });

    $(document).on('click', '#verihuman-apply-humanized', function () {
        var text = $('#verihuman-humanize-result .verihuman-result-text').text();
        if (text) {
            insertIntoEditor(text);
            setStatus('✅ Humanized text applied!', 'success');
            $('#verihuman-humanize-result').hide();
        }
    });

    // ─── History Logic ──────────────────────────────────────────────────────────

    $(document).on('click', '.verihuman-use-history', function () {
        insertIntoEditor($(this).data('copy'));
        setStatus('✅ Used history copy.', 'success');
    });

    $(document).on('click', '.verihuman-delete-history', function () {
        var $btn = $(this);
        if (!confirm('Delete this?')) return;
        $.post(verihumanData.ajaxUrl, {
            action: 'verihuman_delete_history',
            nonce: $btn.data('nonce'),
            history_id: $btn.data('id')
        }).done(function (res) {
            if (res.success) $btn.closest('.verihuman-history-item').fadeOut();
        });
    });

    // ─── Bulk Generation Logic ──────────────────────────────────────────────

    var bulkTotal = 0;
    var bulkProcessed = 0;
    var bulkQueue = [];
    var isBulkRunning = false;

    $(document).on('change', '#vh-bulk-all-cats', function () {
        $('#vh-cat-list').toggle(!this.checked);
    });

    $(document).on('change', 'input[name="vh_bulk_length"]', function () {
        if ($(this).val() === 'Custom') {
            $('#vh-bulk-custom-length-wrap').slideDown(200);
        } else {
            $('#vh-bulk-custom-length-wrap').slideUp(200);
        }
    });

    $(document).on('click', '#vh-bulk-start', function () {
        if (isBulkRunning) return;

        var $btn = $(this);
        var originalText = $btn.text();
        $btn.prop('disabled', true).text('Initializing...');

        var selectedCats = [];
        if (!$('#vh-bulk-all-cats').is(':checked')) {
            $('.vh-cat-item:checked').each(function () { selectedCats.push($(this).val()); });
        }

        var statuses = $('#vh-bulk-status').val() || ['publish'];

        // Step 1: Analyze and find products
        $.post(verihumanData.ajaxUrl, {
            action: 'verihuman_bulk_analyze',
            nonce: verihumanData.nonce,
            target: $('#vh-bulk-target').val(),
            scope: $('#vh-bulk-scope').val(),
            status: statuses,
            categories: selectedCats
        }).done(function (res) {
            if (res.success && res.data.ids && res.data.ids.length > 0) {
                bulkQueue = res.data.ids;
                bulkTotal = bulkQueue.length;
                bulkProcessed = 0;
                isBulkRunning = true;

                // Disable form
                $('table.form-table select, table.form-table input').prop('disabled', true);

                // Prepare UI
                $('#vh-bulk-summary').slideUp();
                $('#vh-bulk-progress-wrap').slideDown();
                $('#vh-bulk-log').empty();
                $('#vh-total').text(bulkTotal);
                $('#vh-processed').text(0);
                $('#vh-percent').text('0%');
                $('#vh-progress-bar').css('width', '0%');

                addBulkLog('🚀 Starting generation for ' + bulkTotal + ' products...', 'info');

                // Step 2: Start processing loop
                processNextBulkItem();
            } else {
                alert('No products found matching your active filters (Status/Categories/Scope).');
                $btn.prop('disabled', false).text(originalText);
            }
        }).fail(function () {
            alert('Error analyzing selection.');
            $btn.prop('disabled', false).text(originalText);
        });
    });

    function processNextBulkItem() {
        if (!isBulkRunning || bulkQueue.length === 0) {
            if (bulkQueue.length === 0) {
                addBulkLog('🎉 All done!', 'success');
            }
            $('#vh-bulk-stop').text('Clear & Reset').addClass('button-secondary').removeClass('button-link-delete');
            return;
        }

        var productId = bulkQueue.shift();

        var lengthVal = $('input[name="vh_bulk_length"]:checked').val();
        if (lengthVal === 'Custom') {
            var inputWords = $('#vh-bulk-custom-length').val();
            if (inputWords && inputWords > 0) {
                lengthVal = inputWords;
            }
        }

        $.post(verihumanData.ajaxUrl, {
            action: 'verihuman_bulk_process_item',
            nonce: verihumanData.nonce,
            product_id: productId,
            target: $('#vh-bulk-target').val(),
            tone: $('#vh-bulk-tone').val(),
            language: $('#vh-bulk-lang').val(),
            copy_length: lengthVal
        }).done(function (res) {
            if (res.success) {
                addBulkLog('✅ Generated description for ' + res.data.name, 'success');
            } else {
                addBulkLog('❌ Failed: ' + (res.data.message || 'Error'), 'error');
            }
        }).fail(function () {
            addBulkLog('❌ Request failed for ID ' + productId, 'error');
        }).always(function () {
            bulkProcessed++;
            var percent = Math.round((bulkProcessed / bulkTotal) * 100);
            $('#vh-processed').text(bulkProcessed);
            $('#vh-percent').text(percent + '%');
            $('#vh-progress-bar').css('width', percent + '%');

            // Artificial delay to be gentle on API/Server
            setTimeout(processNextBulkItem, 500);
        });
    }

    function addBulkLog(msg, type) {
        var $log = $('#vh-bulk-log');
        $log.prepend('<li class="vh-log-' + type + '">' + msg + '</li>');
    }

    $(document).on('click', '#vh-bulk-stop', function () {
        if (isBulkRunning) {
            if (confirm('Stop the bulk process?')) {
                isBulkRunning = false;
                addBulkLog('🛑 Process stopped by user.', 'error');
                $(this).text('Clear & Reset').addClass('button-secondary');
            }
        } else {
            // Reset UI
            if (confirm('Reset and start over?')) {
                location.reload();
            }
        }
    });

}(jQuery));
