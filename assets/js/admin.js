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
     * Insert HTML into the active editor — supports both Classic (TinyMCE) and Gutenberg.
     */
    function insertIntoEditor(html) {
        // Gutenberg (Block Editor)
        if (typeof wp !== 'undefined' && wp.data && wp.data.dispatch) {
            try {
                var block = wp.blocks.createBlock('core/html', { content: html });
                wp.data.dispatch('core/editor').insertBlocks(block);
                return;
            } catch (e) {
                // fallthrough to TinyMCE
            }
        }

        // Classic Editor (TinyMCE)
        if (typeof tinymce !== 'undefined' && tinymce.get('content')) {
            tinymce.get('content').setContent(html);
            return;
        }

        // Plain textarea fallback
        var $textarea = $('#content');
        if ($textarea.length) {
            $textarea.val(html);
        }
    }

    /**
     * Trigger WordPress auto-save / save as draft.
     * Returns a Promise that resolves when save is done (or skips if nothing changed).
     */
    function autoSave() {
        return new Promise(function (resolve) {
            // Gutenberg auto-save
            if (typeof wp !== 'undefined' && wp.data) {
                try {
                    wp.data.dispatch('core/editor').savePost({ isAutosave: true });
                    // Give WP a moment to complete
                    setTimeout(resolve, 800);
                    return;
                } catch (e) { /* continue to classic */ }
            }

            // Classic Editor – click the hidden "Save Draft" if exists
            var $saveDraft = $('#save-post');
            if ($saveDraft.length) {
                $saveDraft.one('click', function () {
                    setTimeout(resolve, 600);
                });
                $saveDraft.trigger('click');
            } else {
                resolve();  // nothing to save, continue
            }
        });
    }

    // ─── Main: Generate copy ────────────────────────────────────────────────────

    $(document).on('click', '#verihuman-generate-btn', function () {
        var $btn = $(this);

        $btn.prop('disabled', true);
        $btn.find('.verihuman-btn-text').text('Generating…');
        $btn.find('.verihuman-btn-icon').hide();
        $btn.find('.verihuman-spinner').show();
        clearStatus();

        // Get the product ID from the page URL / post input
        var productId = $('#post_ID').val() || 0;
        var productName = $('#title').val() || $('#post_title').val() || '';

        // Step 1: Auto-save → step 2: generate
        autoSave().then(function () {
            $.post(verihumanData.ajaxUrl, {
                action: 'verihuman_generate_copy',
                nonce: verihumanData.nonce,
                product_id: productId,
                product_name: productName,
                platform: verihumanData.platform,
                tone: verihumanData.tone,
                language: verihumanData.language,
            })
                .done(function (res) {
                    if (res.success && res.data && res.data.generatedText) {
                        insertIntoEditor(res.data.generatedText);
                        setStatus('✅ AI copy generated and inserted into the editor! Refresh to see updated history.', 'success');
                    } else {
                        var errMsg = (res.data && res.data.message) ? res.data.message : 'Unknown error.';
                        setStatus('❌ ' + errMsg, 'error');
                    }
                })
                .fail(function (xhr) {
                    var msg = 'Request failed.';
                    try {
                        var json = JSON.parse(xhr.responseText);
                        if (json && json.data && json.data.message) msg = json.data.message;
                    } catch (e) { }
                    setStatus('❌ ' + msg, 'error');
                })
                .always(function () {
                    $btn.prop('disabled', false);
                    $btn.find('.verihuman-btn-text').text('Generate AI Copy');
                    $btn.find('.verihuman-btn-icon').show();
                    $btn.find('.verihuman-spinner').hide();
                });
        });
    });

    // ─── History: Use This Copy ─────────────────────────────────────────────────

    $(document).on('click', '.verihuman-use-history', function () {
        var html = $(this).data('copy');
        if (html) {
            insertIntoEditor(html);
            setStatus('✅ Historical copy inserted into the editor.', 'success');
        }
    });

    // ─── History: Delete ────────────────────────────────────────────────────────

    $(document).on('click', '.verihuman-delete-history', function () {
        var $btn = $(this);
        var id = $btn.data('id');
        var nonce = $btn.data('nonce');

        if (!confirm('Delete this copy from history?')) return;

        $.post(verihumanData.ajaxUrl, {
            action: 'verihuman_delete_history',
            nonce: nonce,
            history_id: id,
        })
            .done(function (res) {
                if (res.success) {
                    $btn.closest('.verihuman-history-item').fadeOut(300, function () {
                        $(this).remove();
                    });
                }
            });
    });

}(jQuery));
