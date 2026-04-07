jQuery(document).ready(function ($) {
    const selectedPosts = [];
    let bannerUrl = '';
    let layoutType = 'custom';

    // Render Sendy lists fetched automatically by the server.
    const knownLists = sssb_ajax.known_lists || [];
    const rememberedLists = (sssb_ajax.remembered_lists || []).map(String);
    const $listInput = $('#sssb-list-id');

    const escapeHtml = (str) => String(str).replace(/[&<>"']/g, (c) => ({
        '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
    }[c]));

    const formatCount = (n) => {
        if (n === null || n === undefined) return '';
        return Number(n).toLocaleString();
    };

    if (knownLists.length > 0) {
        let listHtml = '<div class="sssb-list-checkboxes" style="max-height: 200px; overflow-y: auto; border: 1px solid #ddd; padding: 10px; border-radius: 4px; background: #fff; margin-top:5px;">';
        knownLists.forEach(list => {
            const isChecked = rememberedLists.indexOf(String(list.id)) !== -1;
            const countLabel = (list.count !== null && list.count !== undefined)
                ? ` <span style="color:#646970;">(${formatCount(list.count)} subscribers)</span>`
                : '';
            listHtml += `
                <label style="display:block; margin-bottom: 5px;">
                    <input type="checkbox" name="sssb_target_lists" value="${escapeHtml(list.id)}"${isChecked ? ' checked' : ''}> ${escapeHtml(list.name)}${countLabel}
                </label>
            `;
        });
        listHtml += '</div>';
        $listInput.after(listHtml);
    } else {
        $('#sssb-list-empty-notice').show();
    }

    // Banner Image Upload
    $('#sssb-upload-banner').on('click', function (e) {
        e.preventDefault();
        const image_frame = wp.media({
            title: 'Select Banner Image',
            multiple: false,
            library: { type: 'image' },
            button: { text: 'Use Banner' }
        });

        image_frame.on('select', function () {
            const uploaded_image = image_frame.state().get('selection').first().toJSON();
            bannerUrl = uploaded_image.url;
            $('#sssb-banner-url').val(bannerUrl);
            $('#sssb-banner-preview').html(`<img src="${bannerUrl}" style="max-width:100%; height:auto;">`);
            $('#sssb-remove-banner').show();
            $('#sssb-upload-banner').text('Change Banner');
            updatePreview();
        });

        image_frame.open();
    });

    $('#sssb-remove-banner').on('click', function (e) {
        e.preventDefault();
        bannerUrl = '';
        $('#sssb-banner-url').val('');
        $('#sssb-banner-preview').empty();
        $(this).hide();
        $('#sssb-upload-banner').text('Select Banner');
        updatePreview();
    });

    // Layout / Format Change
    $('input[name="sssb_layout"]').on('change', function () {
        layoutType = $(this).val();
        updatePreview();
    });
    $('#sssb-format').on('change', function () {
        layoutType = $(this).val();
        updatePreview();
    });

    // Search Posts
    let currentQuery = '';
    let currentPage = 1;
    let hasMorePosts = true;
    let isLoadingPosts = false;

    $('#sssb-search').on('input', function () {
        const query = $(this).val();
        // Allow empty query to reset to recent posts, otherwise wait for 3 chars
        if (query.length > 0 && query.length < 3) return;
        currentQuery = query;
        currentPage = 1;
        hasMorePosts = true;
        loadPosts(query, 1, false);
    });

    // Infinite scroll inside the results container.
    // Scroll events don't bubble, so bind directly (not via delegation).
    $('#sssb-post-results').on('scroll', function () {
        if (isLoadingPosts || !hasMorePosts) return;
        const el = this;
        if (el.scrollTop + el.clientHeight >= el.scrollHeight - 80) {
            loadPosts(currentQuery, currentPage + 1, true);
        }
    });

    // Add Post to selection
    $(document).on('click', '.sssb-add-post', function (e) {
        e.preventDefault();
        const postId = $(this).data('id');
        const title = $(this).data('title');
        const thumbnail = $(this).data('thumbnail');
        const excerpt = $(this).data('excerpt');
        const link = $(this).data('link');
        const content = $(this).data('content');

        if (selectedPosts.some(p => p.id === postId)) return;

        selectedPosts.push({ id: postId, title, thumbnail, excerpt, link, content });
        renderSelectedPosts();
        updatePreview();

        // Change clicked button to "Remove"
        $(this).removeClass('sssb-add-post button-small')
            .addClass('sssb-remove-post-from-search button-link-delete')
            .text('Remove');
    });

    // Remove Post
    $(document).on('click', '.sssb-remove-post', function (e) {
        e.preventDefault();
        const postId = $(this).data('id');
        const index = selectedPosts.findIndex(p => p.id === postId);
        if (index > -1) {
            selectedPosts.splice(index, 1);
            renderSelectedPosts();
            updatePreview();

            // Re-render search list to update button state if the item is visible there
            // Or simpler: find the button in search results and reset it
            const $searchBtn = $(`.sssb-remove-post-from-search[data-id="${postId}"]`);
            if ($searchBtn.length) {
                const post = $searchBtn.data('post-obj');
                // We need to restore the "Add" button. 
                // Since we don't have the full post object easily here unless we store it, 
                // we can just reload the search or manually swap classes/text if we kept the data.
                // A simpler way: just trigger a re-render of the current search results if we have them?
                // Let's just swap the button appearance.
                $searchBtn.removeClass('sssb-remove-post-from-search button-link-delete')
                    .addClass('sssb-add-post button-small')
                    .text('Add');
            }
        }
    });

    // Remove Post via Search Result Button (New Handler)
    $(document).on('click', '.sssb-remove-post-from-search', function (e) {
        e.preventDefault();
        const postId = $(this).data('id');
        const index = selectedPosts.findIndex(p => p.id === postId);
        if (index > -1) {
            selectedPosts.splice(index, 1);
            renderSelectedPosts();
            updatePreview();

            // Swap this button back to Add
            $(this).removeClass('sssb-remove-post-from-search button-link-delete')
                .addClass('sssb-add-post button-small')
                .text('Add');
        }
    });

    // Toggle Schedule Options
    $('input[name="sssb_send_type"]').on('change', function () {
        if ($(this).val() === 'schedule') {
            $('#sssb-schedule-options').slideDown();
        } else {
            $('#sssb-schedule-options').slideUp();
        }
    });

    // Update datetime when time selectors change
    $('#sssb-schedule-hour, #sssb-schedule-minute').on('change', function () {
        updateScheduledDateTime();
    });

    // Function to combine date and time into hidden input
    function updateScheduledDateTime() {
        const selectedDate = $('#sssb-datepicker-inline').datepicker('getDate');
        if (selectedDate) {
            const hour = $('#sssb-schedule-hour').val();
            const minute = $('#sssb-schedule-minute').val();

            // Format: YYYY-MM-DD HH:MM:SS
            const year = selectedDate.getFullYear();
            const month = String(selectedDate.getMonth() + 1).padStart(2, '0');
            const day = String(selectedDate.getDate()).padStart(2, '0');

            const datetime = `${year}-${month}-${day} ${hour}:${minute}:00`;
            $('#sssb-schedule-datetime').val(datetime);
        }
    }


    // Create Campaign
    $('#sssb-create-campaign').on('click', function (e) {
        e.preventDefault();
        const $btn = $(this);
        $btn.prop('disabled', true).text('Processing...');

        const campaignData = {
            subject: $('#sssb-subject').val(),
            from_name: $('#sssb-from-name').val(),
            from_email: $('#sssb-from-email').val(),
            html_text: $('#sssb-preview-content').html(),
            list_id: (function () {
                const $selectedLists = $('input[name="sssb_target_lists"]:checked');
                if ($selectedLists.length > 0) {
                    const ids = [];
                    $selectedLists.each(function () { ids.push($(this).val()); });
                    return ids.join(',');
                }
                // Fallback: parse pipe-separated format (List Name|List ID)
                const rawValue = $('#sssb-list-id').val();
                if (rawValue && rawValue.includes('|')) {
                    const ids = rawValue.split('\n')
                        .map(line => line.trim())
                        .filter(line => line.includes('|'))
                        .map(line => line.split('|')[1].trim())
                        .filter(id => id.length > 0);
                    return ids.join(',');
                }
                return rawValue;
            })(),
            send_type: $('input[name="sssb_send_type"]:checked').val(),
            schedule_date: $('#sssb-schedule-datetime').val()
        };

        campaignData.plain_text = $(campaignData.html_text).text();

        // Basic validation for schedule
        if (campaignData.send_type === 'schedule' && !campaignData.schedule_date) {
            alert('Please select a date and time for scheduling.');
            $btn.prop('disabled', false).text('Create Campaign');
            return;
        }

        $.ajax({
            url: sssb_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'sssb_create_campaign',
                nonce: sssb_ajax.nonce,
                campaign: campaignData
            },
            success: function (response) {
                $btn.prop('disabled', false).text('Create Campaign');
                if (response.success) {
                    alert(response.data.message);
                    if (window.confirm('Reload page to create another?')) {
                        window.location.reload();
                    }
                } else {
                    alert('Error: ' + response.data.message);
                }
            },
            error: function () {
                $btn.prop('disabled', false).text('Create Campaign');
                alert('Connection error');
            }
        });
    });

    function postItemHtml(post) {
        const isSelected = selectedPosts.some(p => p.id === post.id);
        const btnHtml = isSelected
            ? `<button class="button button-link-delete sssb-remove-post-from-search" data-id="${post.id}">Remove</button>`
            : `<button class="button button-small sssb-add-post"
                                data-id="${post.id}"
                                data-title="${post.title}"
                                data-thumbnail="${post.thumbnail}"
                                data-excerpt="${post.excerpt}"
                                data-link="${post.link}">Add</button>`;
        return `
            <div class="sssb-post-item" style="display:flex; align-items:center; margin-bottom:10px; border-bottom:1px solid #eee; padding-bottom:10px;">
                <img src="${post.thumbnail}" alt="" style="width:50px; height:50px; object-fit:cover; margin-right:10px; border-radius:4px;">
                <div style="flex-grow:1;">
                    <strong>${post.title}</strong>
                    <div style="margin-top:5px;">${btnHtml}</div>
                </div>
            </div>`;
    }

    function appendPostList(posts) {
        if (!posts || posts.length === 0) return;
        const html = posts.map(postItemHtml).join('');
        $('#sssb-post-results').append(html);
    }

    function renderPostList(posts) {
        let html = '';
        if (posts.length === 0) {
            html = '<p>No posts found.</p>';
        } else {
            posts.forEach(post => {
                const isSelected = selectedPosts.some(p => p.id === post.id);
                let btnHtml = '';

                if (isSelected) {
                    btnHtml = `<button class="button button-link-delete sssb-remove-post-from-search" data-id="${post.id}">Remove</button>`;
                } else {
                    btnHtml = `<button class="button button-small sssb-add-post"
                                data-id="${post.id}"
                                data-title="${post.title}"
                                data-thumbnail="${post.thumbnail}"
                                data-excerpt="${post.excerpt}"
                                data-link="${post.link}">Add</button>`;
                }

                html += `
                    <div class="sssb-post-item" style="display:flex; align-items:center; margin-bottom:10px; border-bottom:1px solid #eee; padding-bottom:10px;">
                        <img src="${post.thumbnail}" alt="" style="width:50px; height:50px; object-fit:cover; margin-right:10px; border-radius:4px;">
                        <div style="flex-grow:1;">
                            <strong>${post.title}</strong>
                            <div style="margin-top:5px;">${btnHtml}</div>
                        </div>
                    </div>
                `;
            });
        }
        $('#sssb-post-results').html(html);
    }

    function renderSelectedPosts() {
        let html = '';
        selectedPosts.forEach(post => {
            html += `
                <div class="sssb-selected-item" style="display:flex; justify-content:space-between; align-items:center; padding:8px; background:#f0f0f1; margin-bottom:5px; border-radius:4px;">
                    <span style="font-weight:500;">${post.title}</span>
                    <a href="#" class="sssb-remove-post" data-id="${post.id}" style="color:#d63638; text-decoration:none; font-size:13px;">Remove</a>
                </div>
            `;
        });
        $('#sssb-selected-list').html(html);
    }

    function updatePreview() {
        let html = '';
        const settings = sssb_ajax.settings || {};

        // 1. Template Wrapper & Styles
        html += `
        <!DOCTYPE html>
        <html lang="en">
        <head><meta charset="UTF-8" /><meta name="viewport" content="width=device-width, initial-scale=1.0" />
            <title>Newsletter</title>
            <style type="text/css">
            body { margin: 0; padding: 0; background-color: #f1f5f9; font-family: 'Poppins', sans-serif; }
            table, td { border-collapse: collapse; }
            .container { width: 100%; max-width: 680px; margin: auto; background-color: #ffffff; border-radius: 18px; overflow: hidden; box-shadow: 0 10px 34px rgba(0, 0, 0, 0.10); }
            a { text-decoration: underline; color: #2271b1; }
            
            /* Mobile Responsive */
            @media screen and (max-width: 600px) {
                .responsive-td {
                    display: block !important;
                    width: 100% !important;
                    box-sizing: border-box !important;
                    padding-left: 0 !important;
                    padding-right: 0 !important;
                    padding-bottom: 20px !important;
                }
                .sssb-card-table {
                    height: auto !important;
                }
            }
            </style>
        </head>
        <body style="margin: 0; padding: 0; background-color: #f1f5f9;">
        <div class="container">
        `;

        // 2. Banner (Integrated into Hero)
        const currentBanner = bannerUrl || '';

        // BRANCH: editorial format renders its own body
        if (layoutType === 'editorial') {
            html += renderEditorialBody(currentBanner, settings);
            html += renderFooter(settings, 'editorial');
            $('#sssb-preview-content').html(html);
            return;
        }

        // 3. Hero Section
        if (selectedPosts.length > 0) {
            const heroPost = selectedPosts[0];
            html += `
            <div style="padding: 26px 30px;">
                <div style="background-color: #ffffff; border-radius: 12px; border: 1px solid #e5e7eb; text-align: center;">
                    ${(currentBanner || heroPost.thumbnail) ? `<a href="${heroPost.link}" style="text-decoration:none;"><img src="${currentBanner || heroPost.thumbnail}" style="width: 100%; height: auto; display: block; border-top-left-radius: 12px; border-top-right-radius: 12px;" /></a>` : ''}
                    <div style="padding: 22px;">
                        <h2 style="margin-top: 0; margin-bottom: ${settings.show_article_excerpt == '1' ? '12px' : '20px'}; color: #0f172a; text-align: center;">${heroPost.title}</h2>
                        ${settings.show_article_excerpt == '1' && heroPost.excerpt ? `<p style="color: #475569; font-size: 15px; line-height: 1.6; text-align: center; margin-top: 0; margin-bottom: 22px;">${heroPost.excerpt}...</p>` : ''}

                        <table border="0" cellpadding="0" cellspacing="0" style="margin: auto;">
                            <tr>
                                <td style="background: #0f172a; border-radius: 10px; padding: 12px 22px; text-align: center;">
                                    <a href="${heroPost.link}" style="color: #ffffff !important; font-size: 14px; text-decoration: none; display: block; font-weight: 600;">Read More</a>
                                </td>
                            </tr>
                        </table>
                    </div>
                </div>
            </div>
            `;
        }

        // 4. Grid Section
        const gridPosts = selectedPosts.slice(1);
        if (gridPosts.length > 0) {
            html += `<div style="padding: 0 30px;"><table border="0" cellpadding="0" cellspacing="0" width="100%">`;

            for (let i = 0; i < gridPosts.length; i += 2) {
                html += `<tr>`;

                // Column 1
                const post1 = gridPosts[i];
                html += renderGridItem(post1);

                // Column 2
                if (i + 1 < gridPosts.length) {
                    const post2 = gridPosts[i + 1];
                    html += renderGridItem(post2);
                } else {
                    html += `<td width="50%"></td>`;
                }

                html += `</tr>`;
            }
            html += `</table></div>`;
        }

        // 5. Read More
        if (settings.more_articles_link) {
            html += `
           <div style="text-align: center; margin: 14px 0;">
                <table border="0" cellpadding="0" cellspacing="0" style="margin: auto;">
                    <tr>
                        <td style="background: #0f172a; border-radius: 10px; padding: 12px 22px;">
                            <a href="${settings.more_articles_link}" style="color: #ffffff !important; font-size: 14px; text-decoration: none; font-weight: 600;">Read More Articles</a>
                        </td>
                    </tr>
                </table>
           </div>`;
        }

        html += renderFooter(settings, 'custom');
        $('#sssb-preview-content').html(html);
    }

    function renderFooter(settings, format) {
        let html = '';

        // Highlighted box above the dark footer.
        // - Default formats: shows "Custom Footer Text" (centered, as before).
        // - Editorial format: shows the About Us heading + body in the same box,
        //   but left-aligned (heading not centered) per request.
        if (format === 'editorial') {
            const aboutHeading = settings.editorial_about_heading || '';
            const aboutBody    = settings.editorial_about_body || '';
            if (aboutHeading || aboutBody) {
                html += `
                 <div style="padding: 20px 30px; text-align: center; background-color: #f8fafc; border-top: 1px solid #e2e8f0;">
                     ${aboutHeading ? `<h3 style="margin:0 0 8px; color:#0f172a; font-size:16px; text-align:center;">${aboutHeading}</h3>` : ''}
                     <div style="font-size: 14px; color: #475569; line-height: 1.6; text-align:center;">
                         ${aboutBody}
                     </div>
                 </div>`;
            }
        } else if (settings.footer_custom_text) {
            html += `
             <div style="padding: 20px 30px; text-align: center; background-color: #f8fafc; border-top: 1px solid #e2e8f0;">
                 <div style="font-size: 14px; color: #475569; line-height: 1.6;">
                     ${settings.footer_custom_text}
                 </div>
             </div>`;
        }

        const footerLogo = settings.footer_logo || '';
        const copyright = (settings.footer_copyright || 'TheYouthTalks').replace(/{year}/g, new Date().getFullYear());

        html += `
        <div style="background-color: #0f172a; padding: 32px 20px 40px; text-align: center; color: #cbd5e1; border-top: 1px solid #1e293b;">
            ${footerLogo ? `<a href="${sssb_ajax.site_url}" style="text-decoration:none;"><img src="${footerLogo}" width="110" style="width: 110px; margin-bottom: 20px;" /></a>` : ''}
            <div style="margin-bottom: 20px;">
                ${settings.social_instagram ? `<a href="${settings.social_instagram}" style="text-decoration:none; margin:0 8px;"><img src="https://cdn-icons-png.flaticon.com/512/2111/2111463.png" width="30" height="30" style="width:30px; height:30px; vertical-align:middle;" /></a>` : ''}
                ${settings.social_linkedin ? `<a href="${settings.social_linkedin}" style="text-decoration:none; margin:0 8px;"><img src="https://upload.wikimedia.org/wikipedia/commons/thumb/c/ca/LinkedIn_logo_initials.png/960px-LinkedIn_logo_initials.png" width="30" height="30" style="width:30px; height:30px; vertical-align:middle;" /></a>` : ''}
                ${settings.social_twitter ? `<a href="${settings.social_twitter}" style="text-decoration:none; margin:0 8px;"><img src="https://img.freepik.com/free-vector/twitter-new-2023-x-logo-white-background-vector_1017-45422.jpg" width="30" height="30" style="width:30px; height:30px; vertical-align:middle;" /></a>` : ''}
                ${settings.social_youtube ? `<a href="${settings.social_youtube}" style="text-decoration:none; margin:0 8px;"><img src="https://cdn-icons-png.flaticon.com/512/1384/1384060.png" width="30" height="30" style="width:30px; height:30px; vertical-align:middle;" /></a>` : ''}
            </div>
            <div>
                <p style="margin: 0 0 10px; font-size: 14px; color: #94a3b8;">${copyright}</p>
                <p style="margin: 0; font-size: 12px; color: #64748b;">You're receiving this email because you are subscribed.</p>
                <div style="margin-top: 15px;">
                    <a href="[unsubscribe]" style="color: #ffffff !important; text-decoration: underline; font-size: 13px;">Unsubscribe</a>
                </div>
            </div>
        </div>
        </div>
        </body>
        </html>`;

        return html;
    }

    function renderEditorialBody(currentBanner, settings) {
        let html = '';
        const heroPost = selectedPosts[0];
        const otherPosts = selectedPosts.slice(1);

        // Optional banner at the top of the card
        if (currentBanner) {
            html += `
            <div style="padding: 26px 30px 0;">
                <img src="${currentBanner}" style="width: 100%; height: auto; display: block; border-radius: 12px;" />
            </div>`;
        }

        html += `<div style="padding: 26px 30px; color: #0f172a; font-size: 15px; line-height: 1.7;">`;

        // Greeting
        if (settings.editorial_greeting) {
            html += `<p style="margin: 0 0 16px; font-weight: 600;">${settings.editorial_greeting}</p>`;
        }

        // Intro
        if (settings.editorial_intro) {
            html += `<div style="margin: 0 0 22px; color: #334155;">${settings.editorial_intro}</div>`;
        }

        // Hero story (centered, with featured image)
        if (heroPost) {
            const heroLabel = settings.editorial_hero_label
                ? `<div style="font-size: 12px; text-transform: uppercase; letter-spacing: 1px; color: #64748b; margin-bottom: 10px; text-align:center;">${settings.editorial_hero_label}</div>`
                : '';
            const heroImage = heroPost.thumbnail
                ? `<a href="${heroPost.link}" style="text-decoration:none; display:block;"><img src="${heroPost.thumbnail}" alt="" style="width:100%; height:auto; display:block; border-radius:10px; margin-bottom:16px;" /></a>`
                : '';
            html += `
            <div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:12px; padding:20px; margin-bottom:24px; text-align:center;">
                ${heroLabel}
                ${heroImage}
                <h2 style="margin:0 0 10px; color:#0f172a; font-size:20px; text-align:center;"><a href="${heroPost.link}" style="color:#0f172a; text-decoration:none;">${heroPost.title}</a></h2>
                ${heroPost.excerpt ? `<p style="margin:0 0 16px; color:#475569; font-size:14px; text-align:center;">${heroPost.excerpt}...</p>` : ''}
                <table border="0" cellpadding="0" cellspacing="0" style="margin:auto;">
                    <tr><td style="background:#0f172a; border-radius:8px; padding:10px 22px; text-align:center;">
                        <a href="${heroPost.link}" style="color:#ffffff !important; font-size:13px; text-decoration:none; display:block; font-weight:600;">Read More</a>
                    </td></tr>
                </table>
            </div>`;
        }

        // "What else" / other stories — same 2-column grid as The Roundup
        if (otherPosts.length > 0) {
            if (settings.editorial_grid_heading) {
                html += `<h3 style="margin:24px 0 16px; color:#0f172a; font-size:18px; text-align:center;">${settings.editorial_grid_heading}</h3>`;
            }
            html += `<table border="0" cellpadding="0" cellspacing="0" width="100%" style="margin-bottom:8px;">`;
            for (let i = 0; i < otherPosts.length; i += 2) {
                html += `<tr>`;
                html += renderGridItem(otherPosts[i]);
                if (i + 1 < otherPosts.length) {
                    html += renderGridItem(otherPosts[i + 1]);
                } else {
                    html += `<td width="50%"></td>`;
                }
                html += `</tr>`;
            }
            html += `</table>`;
        }

        // Why this matters
        if (settings.editorial_why_heading || settings.editorial_why_body) {
            html += `<div style="margin: 24px 0; padding: 18px 20px; background:#fef9c3; border-left:4px solid #eab308; border-radius:8px;">`;
            if (settings.editorial_why_heading) {
                html += `<h3 style="margin:0 0 8px; color:#713f12; font-size:16px;">${settings.editorial_why_heading}</h3>`;
            }
            if (settings.editorial_why_body) {
                html += `<div style="color:#713f12; font-size:14px; line-height:1.6;">${settings.editorial_why_body}</div>`;
            }
            html += `</div>`;
        }

        // Collaboration / CTA
        if (settings.editorial_collab_heading || settings.editorial_collab_body) {
            html += `<div style="margin: 24px 0; padding: 18px 20px; background:#ecfeff; border-left:4px solid #06b6d4; border-radius:8px;">`;
            if (settings.editorial_collab_heading) {
                html += `<h3 style="margin:0 0 8px; color:#155e75; font-size:16px;">${settings.editorial_collab_heading}</h3>`;
            }
            if (settings.editorial_collab_body) {
                html += `<div style="color:#155e75; font-size:14px; line-height:1.6;">${settings.editorial_collab_body}</div>`;
            }
            html += `</div>`;
        }

        // (About Us is now rendered inside renderFooter() for the editorial format,
        //  in the same highlighted box that "Custom Footer Text" uses for The Roundup.)

        html += `</div>`;
        return html;
    }

    function renderGridItem(post) {
        const settings = sssb_ajax.settings || {};
        return `
        <td class="responsive-td" style="padding:8px; vertical-align: top;" valign="top" width="50%">
            <table class="sssb-card-table" cellpadding="0" cellspacing="0" style="border:1px solid #e2e8f0; border-radius:12px; width: 100%; table-layout: fixed; background-color: #ffffff;">
                <tbody>
                    <tr>
                        <td align="center" valign="middle" style="height: 180px; overflow: hidden; vertical-align: middle; padding: 0; background-color: #f1f5f9; border-top-left-radius:12px; border-top-right-radius:12px;">
                            ${post.thumbnail ?
                `<a href="${post.link}" style="text-decoration:none; display:block;"><img alt="" src="${post.thumbnail}" width="280" style="display:block; width: 100%; height: 180px; object-fit: cover; margin: 0 auto; border-top-left-radius:12px; border-top-right-radius:12px;" /></a>`
                : `<div style="height: 180px; display: flex; align-items: center; justify-content: center; color: #94a3b8;">No Image</div>`
            }
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:12px; text-align:center; vertical-align: top;" valign="top">
                            <h3 style="font-size:15px; margin:0 0 ${settings.show_article_excerpt == '1' ? '6px' : '10px'}; color:#0f172a; line-height: 1.3; min-height: 40px;"><a href="${post.link}" style="color:#0f172a; text-decoration:none;">${post.title}</a></h3>
                            ${settings.show_article_excerpt == '1' && post.excerpt ? `<p style="font-size:12px; color:#475569; line-height:1.4; margin:0 0 10px;">${post.excerpt}...</p>` : ''}

                            <table border="0" cellpadding="0" cellspacing="0" role="presentation" style="margin:auto;">
                                <tbody>
                                    <tr>
                                        <td style="background:#0f172a; border-radius:6px; padding:8px 18px; text-align:center;">
                                            <a href="${post.link}" style="color:#ffffff !important; font-size:13px; text-decoration:none; display:block; font-weight:600;">Read More</a>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </td>
                    </tr>
                </tbody>
            </table>
        </td>`;
    }



    // Initial load of posts
    loadPosts('', 1, false);

    function loadPosts(query, page, append) {
        if (isLoadingPosts) return;
        isLoadingPosts = true;

        if (!append) {
            $('#sssb-post-results').html('<p style="padding:10px; color:#64748b;">Loading…</p>');
        } else {
            $('#sssb-post-results').append('<p class="sssb-loading-more" style="padding:10px; text-align:center; color:#64748b;">Loading more…</p>');
        }

        $.ajax({
            url: sssb_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'sssb_search_posts',
                nonce: sssb_ajax.nonce,
                query: query,
                page: page
            },
            success: function (response) {
                $('.sssb-loading-more').remove();
                if (response.success) {
                    const payload = response.data || {};
                    const posts = payload.posts || [];
                    hasMorePosts = !!payload.has_more;
                    currentPage = payload.page || page;
                    if (append) {
                        appendPostList(posts);
                    } else {
                        renderPostList(posts);
                    }
                    if (!hasMorePosts && append) {
                        $('#sssb-post-results').append('<p style="padding:10px; text-align:center; color:#94a3b8; font-size:12px;">No more posts</p>');
                    }
                }
            },
            complete: function () {
                isLoadingPosts = false;
            }
        });
    }
});
