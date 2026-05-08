(function($) {
    if (typeof uam_seo_data === 'undefined') return;
    
    var ajaxUrl = uam_seo_data.ajaxUrl;
    var nonce = uam_seo_data.nonce;
    var postId = uam_seo_data.postId;
    var postTitle = uam_seo_data.postTitle;

    // Tabs
    $('.uam-mb-tab').on('click', function() {
        var tab = $(this).data('tab');
        $('.uam-mb-tab, .uam-mb-content').removeClass('active');
        $(this).addClass('active');
        $('#uam-tab-' + tab).addClass('active');
    });

    function showLoader(msg) {
        $('#uam-mb-loader span').text(msg || 'Thinking...');
        $('#uam-mb-loader').css('display', 'flex');
    }
    function hideLoader() { $('#uam-mb-loader').hide(); }

    // SEO Meta
    $('#uam-btn-gen-meta').on('click', function() {
        showLoader('Generating SEO meta...');
        $.post(ajaxUrl, {
            action: 'uam_generate_meta',
            nonce: nonce,
            title: postTitle,
            keyword: $('#uam_focus_keyword').val()
        }, function(res) {
            hideLoader();
            if(res.success) {
                if(res.data.seo_title) $('#uam_seo_title').val(res.data.seo_title);
                if(res.data.meta_description) $('#uam_meta_description').val(res.data.meta_description);
            }
        });
    });

    // AI Image
    $('#uam-btn-gen-img').on('click', function() {
        var prompt = $('#uam-img-prompt').val();
        if(!prompt) return alert('Enter a prompt');
        showLoader('Generating image...');
        $('#uam-img-preview-area').html('');
        $('#uam-img-actions').hide();
        $.post(ajaxUrl, {
            action: 'uam_generate_image',
            nonce: nonce,
            post_title: postTitle,
            post_excerpt: prompt,
            style: $('#uam-img-style').val(),
            save_to_library: 1
        }, function(res) {
            hideLoader();
            if(res.success) {
                $('#uam-img-preview-area').html('<img src="'+res.data.image_url+'" style="width:100%; border-radius:10px">');
                if(res.data.attach_id) {
                    $('#uam-btn-set-feat').data('id', res.data.attach_id).parent().css('display', 'flex');
                }
            }
        });
    });

    $('#uam-btn-set-feat').on('click', function() {
        var id = $(this).data('id');
        showLoader('Setting featured image...');
        $.post(ajaxUrl, { action: 'uam_set_featured_image', nonce: nonce, post_id: postId, attach_id: id }, function(res) {
            hideLoader();
            alert(res.success ? 'Featured image set!' : 'Error: ' + res.data.message);
        });
    });

    // Vision
    $('#uam-btn-select-vision').on('click', function(e) {
        e.preventDefault();
        var frame = wp.media({ title: 'Select Image', button: { text: 'Use this image' }, multiple: false });
        frame.on('select', function() {
            var attachment = frame.state().get('selection').first().toJSON();
            $('#uam-vision-attach-id').val(attachment.id);
            $('#uam-vision-img-preview').html('<img src="'+attachment.url+'" style="width:80px;height:80px;object-fit:cover;border-radius:5px">');
        });
        frame.open();
    });

    $('#uam-btn-analyze-vision').on('click', function() {
        var id = $('#uam-vision-attach-id').val();
        if(!id) return alert('Select an image');
        showLoader('Analyzing image...');
        $('#uam-vision-results').html('');
        $.post(ajaxUrl, { action: 'uam_analyze_vision', nonce: nonce, attachment_id: id }, function(res) {
            hideLoader();
            if(res.success) {
                var d = res.data;
                $('#uam-vision-results').html('<div style="background:#f8fafc;padding:12px;border-radius:8px;font-size:13px"><p><strong>Title:</strong> <span id="uam-vis-title">'+d.title+'</span></p><p><strong>Alt:</strong> <span id="uam-vis-alt">'+d.alt+'</span></p><button type="button" class="uam-mb-btn uam-mb-btn-secondary" id="uam-btn-vis-apply">✅ Apply to SEO Meta</button></div>');
                
                $('#uam-btn-vis-apply').on('click', function() {
                    $('#uam_seo_title').val($('#uam-vis-title').text());
                    // Switch to Meta tab to show the change
                    $('.uam-mb-tab[data-tab="meta"]').trigger('click');
                });
            }
        });
    });

})(jQuery);
