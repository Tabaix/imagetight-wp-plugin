(function($) {
    if (typeof tsp_itc_data === 'undefined') return;

    var nonce = tsp_itc_data.nonce;
    var hasKey = tsp_itc_data.hasKey;

    // Tab switching
    $('.tss-tab').on('click', function(e) {
        e.preventDefault();
        $('.tss-tab').removeClass('active');
        $(this).addClass('active');
        $('.tsp-tab-pane').hide();
        $('#' + $(this).data('tab')).show();
    });

    // Load quota on page load
    if (hasKey) {
        $.post(ajaxurl, { action: 'tabaix_seo_itc_quota', nonce: nonce }, function(r) {
            if (r.success && r.data) {
                var left = r.data.credits_remaining ?? r.data.remaining ?? '';
                if (left !== '') {
                    $('#tsp-quota-val').text(left);
                    $('#tsp-quota-badge').show();
                }
            }
        });
    }

    // Scan
    $('#tsp-scan-btn').on('click', function() {
        var $btn = $(this);
        $btn.text('⏳ Scanning...').prop('disabled', true);
        $('#tsp-scan-results').html('');
        $('#tsp-scan-status').text('');
        $.post(ajaxurl, { action: 'tabaix_seo_itc_scan', nonce: nonce }, function(r) {
            $btn.text('🔍 Scan for Heavy Images').prop('disabled', false);
            if (!r.success || !r.data || !r.data.images) {
                $('#tsp-scan-results').html('<p style="color:#94A3B8;">No heavy images found. Your library is clean! 🎉</p>');
                return;
            }
            var images = r.data.images;
            $('#tsp-scan-status').text(images.length + ' heavy images found');
            if (images.length > 0) $('#tsp-bulk-btn').show();
            var html = '<table class="tss-table"><tr><th>Image</th><th>Filename</th><th>Size</th><th>Action</th></tr>';
            $.each(images, function(i, img) {
                html += '<tr id="tsp-row-' + img.id + '">';
                html += '<td><img src="' + img.thumb + '" style="width:50px;height:50px;object-fit:cover;border-radius:6px;"></td>';
                html += '<td style="font-size:12px;color:#64748B;">' + img.filename + '</td>';
                html += '<td><strong>' + img.size_fmt + '</strong></td>';
                html += '<td><button class="tss-btn tss-btn-sm tsp-compress-btn" data-id="' + img.id + '" data-path="' + img.path + '">🗜️ Compress</button></td>';
                html += '</tr>';
            });
            html += '</table>';
            $('#tsp-scan-results').html(html);
        });
    });

    // Single compress
    $(document).on('click', '.tsp-compress-btn', function() {
        var $btn = $(this);
        var id = $btn.data('id');
        $btn.text('⏳').prop('disabled', true);
        $.post(ajaxurl, { action: 'tabaix_seo_itc_compress', nonce: nonce, image_id: id }, function(r) {
            if (r.success) {
                $('#tsp-row-' + id + ' td:last').html('<span style="color:#16A34A;font-weight:700;">✅ ' + (r.data.saved_fmt || 'Saved') + '</span>');
            } else {
                $btn.text('❌ Failed').prop('disabled', false);
            }
        });
    });

    // Bulk compress
    $('#tsp-bulk-btn').on('click', function() {
        var $btn = $(this);
        var $rows = $('.tsp-compress-btn');
        var total = $rows.length;
        var done = 0;
        if (!total) return;
        $btn.prop('disabled', true);
        $('#tsp-progress-wrap').show();
        function processNext() {
            if (done >= total) {
                $btn.text('✅ All Done').prop('disabled', true);
                return;
            }
            var $b = $($rows[done]);
            var id = $b.data('id');
            $.post(ajaxurl, { action: 'tabaix_seo_itc_compress', nonce: nonce, image_id: id }, function() {
                done++;
                $('#tsp-progress-bar').css('width', Math.round((done/total)*100) + '%');
                processNext();
            });
        }
        processNext();
    });

    // Restore
    $(document).on('click', '.tsp-restore-btn', function() {
        if (!confirm('Restore original image? The compressed version will be replaced.')) return;
        var id = $(this).data('id');
        var $btn = $(this);
        $btn.text('⏳').prop('disabled', true);
        $.post(ajaxurl, { action: 'tabaix_seo_itc_restore', nonce: nonce, image_id: id }, function(r) {
            if (r.success) {
                $btn.closest('div').find('div').first().text('↩ Restored');
                $btn.remove();
            } else {
                $btn.text('❌').prop('disabled', false);
            }
        });
    });

    // Save settings
    $('#tsp-itc-save-settings, #tsp-itc-test-key').on('click', function() {
        var $btn = $(this);
        $btn.text('⏳ Saving...').prop('disabled', true);
        $.post(ajaxurl, {
            action:    'tabaix_seo_itc_save_settings',
            nonce:     nonce,
            api_key:   $('#tsp-itc-apikey').val(),
            quality:   $('#tsp-itc-quality').val(),
            format:    $('#tsp-itc-format').val(),
            threshold: $('#tsp-itc-threshold').val(),
            auto:      $('#tsp-itc-auto').is(':checked') ? 1 : 0,
            backup:    $('#tsp-itc-backup').is(':checked') ? 1 : 0,
            gemini_key: $('#tsp-itc-gemini-key').val(),
            language:  $('#tsp-itc-language').val(),
        }, function(r) {
            $btn.text($(this).attr('id') === 'tsp-itc-test-key' ? '🔑 Test & Save Key' : '💾 Save Settings').prop('disabled', false);
            if (r.success) {
                $('#tsp-itc-save-status').text('✅ Saved!').css('color','#16A34A');
            } else {
                $('#tsp-itc-save-status').text('❌ ' + (r.data || 'Error')).css('color','#DC2626');
            }
        });
    });

})(jQuery);
