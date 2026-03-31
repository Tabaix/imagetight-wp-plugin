jQuery(document).ready(function($) {
    if ($('#itc-api-key').val()) {
        $('#itc-quota-display').show();
        $.post(itc_data.ajax_url, { action: 'itc_check_quota', nonce: itc_data.nonce }, function(res) {
            if (res.success && res.data) {
                var quota = res.data.credits_remaining;
                var processed = typeof res.data.total_processed !== 'undefined' ? res.data.total_processed : 0;
                var quotaText = quota !== 'Unlimited' ? quota + ' Credits Left' : 'Unlimited Credits';
                var processedText = ' | ' + processed + ' Images Optimized';
                $('#itc-quota-val').text(quotaText + processedText);
            } else {
                $('#itc-quota-val').text('Error loading quota');
            }
        });
    }

    $('#itc-scan-btn').on('click', function() {
        var $btn = $(this);
        $btn.text('Scanning...').prop('disabled', true);
        
        $.ajax({
            url: itc_data.ajax_url,
            type: 'POST',
            data: { action: 'itc_scan_images', nonce: itc_data.nonce },
            success: function(response) {
                if (response.success) {
                    var data = response.data;
                    $('#itc-heavy-count').text(data.count);
                    
                    var cards = '';
                    if(data.count === 0) {
                        $('#itc-results-grid').html('<p style="grid-column: 1/-1; text-align: center; color: #64748b; font-weight: 800; font-size: 18px; padding: 40px;">All clear! Your media library is optimized.</p>');
                    } else {
                        var apiKey = $('#itc-api-key').val();
                        if(!apiKey) {
                            $('#itc-results-grid').html('<p style="grid-column: 1/-1; text-align: center; color: #d63638; font-weight: 800; font-size: 16px; padding: 20px;">Please enter your ImageTight Production API Key above to unlock Edge compression.</p>');
                            $('#itc-results-grid').slideDown();
                            $btn.text('Scan Complete').css('background', '#0F172A');
                            $('#itc-bulk-btn').hide();
                            return;
                        }
                        
                        $('#itc-bulk-btn').show();
                        $.each(data.images, function(index, img) {
                            cards += '<div class="itc-card" id="itc-card-' + img.id + '">';
                            cards += '  <img src="' + img.thumb + '" class="itc-card-img" />';
                            cards += '  <div class="itc-card-content">';
                            cards += '    <div style="display:flex; justify-content:space-between; align-items:start;">';
                            cards += '      <strong style="color:#0F172A; font-size:14px; word-break:break-all; max-width:65%;">' + img.title + '</strong>';
                            cards += '      <span class="itc-size-badge">' + img.size + 'MB</span>';
                            cards += '    </div>';
                            cards += '    <div class="itc-input-group">';
                            cards += '      <label>SEO Title</label>';
                            cards += '      <input type="text" class="itc-input" id="itc-title-' + img.id + '" value="' + img.title + '" />';
                            cards += '    </div>';
                            cards += '    <div class="itc-input-group">';
                            cards += '      <label>Alt Text (SEO)</label>';
                            cards += '      <input type="text" class="itc-input" id="itc-alt-' + img.id + '" value="' + img.alt + '" placeholder="Describe the image..." />';
                            cards += '    </div>';
                            cards += '    <div class="itc-card-footer">';
                            cards += '      <button class="itc-btn-compress" onclick="itc_auto_compress(' + img.id + ')">API Optimize</button>';
                            cards += '      <button class="itc-btn-save" onclick="itc_save_seo(' + img.id + ')">💾 Save SEO</button>';
                            cards += '    </div>';
                            cards += '    <div class="itc-status" id="itc-status-' + img.id + '" style="font-size: 11px; font-weight: 800; text-transform: uppercase; text-align: center; margin-top: 5px;"></div>';
                            cards += '  </div>';
                            cards += '</div>';
                        });
                        $('#itc-results-grid').html(cards);
                    }
                    
                    $('#itc-results-grid').slideDown();
                    $btn.text('Scan Complete').css('background', '#0F172A');
                } else {
                    alert('Error scanning library.');
                }
            }
        });
    });

    // Bulk Optimizer Logic
    $('#itc-bulk-btn').on('click', function() {
        var $cards = $('.itc-card:not(.success-state)');
        if ($cards.length === 0) {
            alert('All images are already optimized!');
            return;
        }

        var confirmBulk = confirm('Are you sure you want to bulk optimize ' + $cards.length + ' images? This will consume your ImageTight quota.');
        if (!confirmBulk) return;

        var total = $cards.length;
        var current = 0;
        
        $(this).prop('disabled', true).text('Processing... 0%');
        $('#itc-bulk-progress').slideDown();

        function processNext() {
            if (current >= total) {
                $('#itc-bulk-btn').text('Bulk Optimization Complete! 🎉').css('background', '#22C55E');
                setTimeout(function(){ $('#itc-bulk-progress').slideUp(); }, 2000);
                return;
            }

            var $card = $($cards[current]);
            var idstr = $card.attr('id');
            var id = idstr.replace('itc-card-', '');
            
            // Trigger the auto compress for this card
            itc_auto_compress_promise(id).always(function() {
                current++;
                var percent = Math.round((current / total) * 100);
                $('.itc-progress-bar').css('width', percent + '%');
                $('#itc-bulk-btn').text('Processing... ' + percent + '%');
                
                // Add a small delay between requests to avoid overwhelmingly hitting the server
                setTimeout(processNext, 500);
            });
        }

        processNext();
    });

    window.itc_save_seo = function(id) {
        var altText = $('#itc-alt-' + id).val();
        var titleText = $('#itc-title-' + id).val();
        var $status = $('#itc-status-' + id);
        
        $status.text('Saving SEO...').css('color', '#64748B');
        
        var fd = new FormData();
        fd.append('action', 'itc_process_image');
        fd.append('nonce', itc_data.nonce);
        fd.append('attachment_id', id);
        fd.append('alt_text', altText);
        fd.append('title_text', titleText);

        $.ajax({
            url: itc_data.ajax_url,
            type: 'POST',
            data: fd, processData: false, contentType: false,
            success: function(res) {
                if(res.success) {
                    $status.text('SEO SAVED!').css('color', '#22C55E');
                }
            }
        });
    };

    window.itc_save_key = function() {
        var key = $('#itc-api-key').val();
        var quality = $('#itc-quality').val();
        var threshold = $('#itc-threshold').val();
        var auto = $('#itc-auto').is(':checked') ? 1 : 0;
        var backup = $('#itc-backup').is(':checked') ? 1 : 0;
        
        var data = { 
            action: 'itc_save_key', 
            nonce: itc_data.nonce, 
            api_key: key,
            quality: quality,
            threshold: threshold,
            auto: auto,
            backup: backup
        };
        
        $.post(itc_data.ajax_url, data, function(res) {
            alert(res.success ? 'All Settings Saved Successfully!' : 'Could not save.');
        });
    };

    window.itc_auto_compress_promise = function(id) {
        var $btn = $('#itc-card-' + id).find('.itc-btn-compress');
        var $status = $('#itc-status-' + id);
        
        $btn.text('Routing to API...').prop('disabled', true).css('opacity', '0.5');
        $status.text('Sending image to Vercel Cloud...').css('color', '#0F172A');
                
        var altText = $('#itc-alt-' + id).val();
        var titleText = $('#itc-title-' + id).val();
        
        var fd = new FormData();
        fd.append('action', 'itc_process_image');
        fd.append('nonce', itc_data.nonce);
        fd.append('attachment_id', id);
        fd.append('alt_text', altText);
        fd.append('title_text', titleText);

        var xhr = $.ajax({
            url: itc_data.ajax_url,
            type: 'POST',
            data: fd, processData: false, contentType: false,
            success: function(res) {
                if(res.success) {
                    var $card = $('#itc-card-' + id);
                    $card.addClass('success-state');
                    $card.find('.itc-size-badge').text(res.data.new_size + 'MB');
                    $btn.text('OPTIMIZED ✅').css('background', '#16A34A');
                    $status.text(res.data.message).css('color', '#16A34A');
                } else {
                    var errorMsg = res.data;
                    if (errorMsg.includes('Package') || errorMsg.includes('limit reached') || errorMsg.includes('Payment required')) {
                         $status.html('🛑 0 Credits Remaining. <a href="https://imagetight.tabaix.com/dashboard" target="_blank" style="color:#DC2626; text-decoration:underline;">Click Here to Top Up.</a>').css('color', '#0F172A');
                         $btn.text('Limits Exceeded').prop('disabled', true).css('opacity', '0.5');
                    } else {
                         $status.text('Error: ' + errorMsg).css('color', '#DC2626');
                         $btn.text('Failed / Retry').prop('disabled', false).css('opacity', '1');
                    }
                }
            }
        });

        return xhr;
    };
    
    // For manual clicks, we wrap normal function so we don't return promise directly to onclick
    window.itc_auto_compress = function(id) {
        itc_auto_compress_promise(id);
    };
});
