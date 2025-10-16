/**
 * Attachment Fields AI Tools JavaScript
 * Handles AI generation in WordPress image edit screens
 */

// Immediate test to verify script loading
const PICPILOT_JS_VERSION = '2.2.0-performance-optimized';
const PICPILOT_DEBUG = true; // Enable for debugging modal issues

// Optimized logging
function debugLog(...args) {
    if (PICPILOT_DEBUG && console?.log) {
        console.log('🖼️', ...args);
    }
}

debugLog('PicPilot attachment-fields.js loading - Version:', PICPILOT_JS_VERSION);
console.log('🖼️ MODAL DEBUG: attachment-fields.js loaded, looking for pic-pilot-launch-modal-btn buttons');

(function($) {
    'use strict';

    // Add error handling
    window.onerror = function(msg, url, lineNo, columnNo, error) {
        if (msg.includes('pic-pilot') || msg.includes('🖼️')) {
            console.error('🖼️ PicPilot Error:', msg, 'at', url, ':', lineNo);
        }
        return false;
    };

    // Global state for request deduplication and performance optimization
    window.picPilotActiveRequests = window.picPilotActiveRequests || {};
    let initAIToolsThrottled = null;
    let mutationObserver = null;
    
    $(document).ready(function() {
        try {
            debugLog('Pic Pilot Attachment Fields initialized - Version:', PICPILOT_JS_VERSION);
            initializePerformantAITools();
        } catch (error) {
            console.error('🖼️ Error during initialization:', error);
        }
    });

    // Throttled initAITools to prevent excessive calls
    function throttle(func, limit) {
        let inThrottle;
        return function() {
            const args = arguments;
            const context = this;
            if (!inThrottle) {
                func.apply(context, args);
                inThrottle = true;
                setTimeout(() => inThrottle = false, limit);
            }
        }
    }

    // Performance-optimized initialization
    function initializePerformantAITools() {
        // Create throttled version of initAITools (max once per 250ms)
        initAIToolsThrottled = throttle(initAITools, 250);
        
        // Initialize immediately
        initAIToolsThrottled();
        
        // Set up modern MutationObserver instead of deprecated DOMNodeInserted
        setupMutationObserver();
        
        // Set up optimized event listeners
        setupOptimizedEventListeners();
    }

    function setupMutationObserver() {
        if (mutationObserver) {
            mutationObserver.disconnect();
        }

        mutationObserver = new MutationObserver(function(mutations) {
            let shouldInit = false;
            
            mutations.forEach(function(mutation) {
                // Only check added nodes
                if (mutation.type === 'childList' && mutation.addedNodes.length > 0) {
                    for (let node of mutation.addedNodes) {
                        if (node.nodeType === 1) { // Element node
                            // Check if AI tools container was added
                            if (node.classList?.contains('pic-pilot-attachment-ai-tools') ||
                                node.querySelector?.('.pic-pilot-attachment-ai-tools')) {
                                shouldInit = true;
                                break;
                            }
                        }
                    }
                }
            });
            
            if (shouldInit) {
                debugLog('AI Tools detected via MutationObserver');
                initAIToolsThrottled();
            }
        });

        // Observe changes to body and media modal containers
        mutationObserver.observe(document.body, {
            childList: true,
            subtree: true
        });
    }

    function setupOptimizedEventListeners() {
        // Single consolidated click handler with event delegation
        $(document).off('click.picpilot').on('click.picpilot', '.attachment, .media-modal, .add_media', function(e) {
            // Only init if clicking on attachment or media modal areas
            if ($(e.target).closest('.attachment, .media-modal').length > 0) {
                debugLog('Media interaction detected, initializing AI Tools');
                initAIToolsThrottled();
            }
        });
    }

    // Optimized WordPress media events integration
    function setupWordPressMediaHooks() {
        if (typeof wp === 'undefined' || !wp.media) return;

        // Hook into media frame events if available (throttled)
        if (wp.media.view.Modal) {
            const originalOpen = wp.media.view.Modal.prototype.open;
            wp.media.view.Modal.prototype.open = function() {
                const result = originalOpen.apply(this, arguments);
                // Use throttled version with minimal delay
                setTimeout(initAIToolsThrottled, 100);
                return result;
            };
        }

        // Hook into attachment details rendering (throttled)
        if (wp.media.view.Attachment && wp.media.view.Attachment.Details) {
            const originalRender = wp.media.view.Attachment.Details.prototype.render;
            wp.media.view.Attachment.Details.prototype.render = function() {
                const result = originalRender.apply(this, arguments);
                // Use throttled version immediately after render
                initAIToolsThrottled();
                return result;
            };
        }
    }

    // Initialize WordPress hooks after DOM ready
    $(document).ready(function() {
        setupWordPressMediaHooks();
    });

    // Cache DOM queries to improve performance
    let $cachedElements = {
        buttons: null,
        containers: null,
        lastUpdate: 0
    };
    
    const CACHE_DURATION = 1000; // Cache for 1 second

    function initAITools() {
        try {
            // Check if we need to refresh cache
            const now = Date.now();
            if (!$cachedElements.buttons || (now - $cachedElements.lastUpdate) > CACHE_DURATION) {
                $cachedElements.buttons = $('.pic-pilot-launch-modal-btn:not(.bound)');
                $cachedElements.containers = $('.pic-pilot-attachment-ai-tools');
                $cachedElements.lastUpdate = now;
            }
            
            const $buttons = $cachedElements.buttons;
            console.log('🖼️ MODAL DEBUG: Button search results:', {
                selector: '.pic-pilot-launch-modal-btn:not(.bound)',
                foundButtons: $buttons.length,
                allButtons: $('.pic-pilot-launch-modal-btn').length,
                containers: $('.pic-pilot-attachment-ai-tools').length
            });
            debugLog('Found', $buttons.length, 'unbound AI Tools buttons');
            
            if ($buttons.length === 0) {
                debugLog('No unbound AI Tools buttons found');
                return;
            }
            
            // Bind click events to modal launch buttons (one-time binding)
            $buttons.addClass('bound').off('click.picpilot-modal').on('click.picpilot-modal', function(e) {
                try {
                    e.preventDefault();
                    e.stopPropagation();
                    
                    const $button = $(this);
                    const attachmentId = $button.data('attachment-id');
                    
                    console.log('🖼️ MODAL DEBUG: Button clicked!', {
                        button: $button[0],
                        attachmentId: attachmentId,
                        buttonData: $button.data()
                    });
                    
                    if (!attachmentId) {
                        console.error('🖼️ No attachment ID found on button');
                        return;
                    }
                    
                    debugLog('AI Tools button clicked for attachment:', attachmentId);
                    console.log('🖼️ MODAL DEBUG: About to open modal for attachment:', attachmentId);
                    openAIToolsModal(attachmentId);
                } catch (clickError) {
                    console.error('🖼️ Error in button click handler:', clickError);
                }
            });
            
            // Invalidate cache after binding
            $cachedElements.buttons = null;
            
        } catch (error) {
            console.error('🖼️ Error in initAITools:', error);
        }
    }

    function openAIToolsModal(attachmentId) {
        debugLog('Opening AI Tools modal for attachment:', attachmentId);
        
        // Remove existing modal if any
        $('#pic-pilot-ai-modal').remove();
        
        // Remove any existing modal styles to prevent conflicts
        $('#pic-pilot-modal-styles').remove();

        // Get current image data
        const currentTitle = getImageTitle(attachmentId);
        const currentAlt = getImageAlt(attachmentId);
        const imageUrl = getImageUrl(attachmentId);
        
        debugLog('Modal data:', { currentTitle, currentAlt, imageUrl });
        
        // If we can't get image data, show a warning but continue
        if (!imageUrl) {
            console.warn('🖼️ Could not retrieve image URL, using placeholder');
        }

        // Create modal HTML
        const modalHtml = `
            <div id="pic-pilot-ai-modal" class="pic-pilot-modal-overlay">
                <div class="pic-pilot-modal-content">
                    <div class="pic-pilot-modal-header">
                        <h2>🤖 AI Tools & Metadata</h2>
                        <button type="button" class="pic-pilot-modal-close">×</button>
                    </div>
                    
                    <div class="pic-pilot-modal-body">
                        <div class="pic-pilot-image-preview">
                            <img src="${imageUrl}" alt="Preview" style="max-width: 100%; max-height: 200px; border-radius: 4px;">
                            <div class="pic-pilot-image-info">
                                <strong>Current Title:</strong> ${currentTitle || 'No title'}<br>
                                <strong>Current Alt Text:</strong> ${currentAlt || 'No alt text'}
                            </div>
                        </div>

                        <div class="pic-pilot-keywords-section">
                            <label for="pic-pilot-modal-keywords">🎯 Keywords (optional):</label>
                            <input type="text" id="pic-pilot-modal-keywords" placeholder="e.g., business person, outdoor scene, product photo">
                            <p class="pic-pilot-help-text">Provide context for better AI results</p>
                        </div>

                        <div class="pic-pilot-tools-grid">
                            <div class="pic-pilot-tool-card">
                                <h3>📝 Generate Title</h3>
                                <p>Create an SEO-friendly title</p>
                                <button type="button" class="button button-primary pic-pilot-modal-generate" data-type="title" data-attachment-id="${attachmentId}">
                                    Generate Title
                                </button>
                                <div class="pic-pilot-modal-status pic-pilot-title-status"></div>
                            </div>

                            <div class="pic-pilot-tool-card">
                                <h3>🏷️ Generate Alt Text</h3>
                                <p>Create accessible descriptions</p>
                                <button type="button" class="button button-primary pic-pilot-modal-generate" data-type="alt" data-attachment-id="${attachmentId}">
                                    ${currentAlt ? 'Regenerate Alt Text' : 'Generate Alt Text'}
                                </button>
                                <div class="pic-pilot-modal-status pic-pilot-alt-status"></div>
                            </div>

                            <div class="pic-pilot-tool-card pic-pilot-duplicate-card">
                                <h3>🔄 Duplicate Image</h3>
                                <p>Create a copy with AI metadata</p>
                                <button type="button" class="button button-secondary pic-pilot-modal-duplicate" data-attachment-id="${attachmentId}">
                                    🔄 Duplicate with AI
                                </button>
                                <div class="pic-pilot-modal-status pic-pilot-duplicate-status"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        `;

        // Add modal to page
        $('body').append(modalHtml);

        // Add modal styles
        addModalStyles();

        // Bind modal events
        bindModalEvents();

        // Focus keywords input
        $('#pic-pilot-modal-keywords').focus();
    }

    function bindModalEvents() {
        // Close modal events
        $('.pic-pilot-modal-close, .pic-pilot-modal-overlay').on('click', function(e) {
            if (e.target === this) {
                closeAIToolsModal();
            }
        });

        // Prevent modal content clicks from closing modal
        $('.pic-pilot-modal-content').on('click', function(e) {
            e.stopPropagation();
        });

        // Generate buttons
        $('.pic-pilot-modal-generate').on('click', function(e) {
            e.preventDefault();
            const $button = $(this);
            const type = $button.data('type');
            const attachmentId = $button.data('attachment-id');
            generateModalMetadata($button, type, attachmentId);
        });

        // Duplicate button
        $('.pic-pilot-modal-duplicate').on('click', function(e) {
            e.preventDefault();
            const $button = $(this);
            const attachmentId = $button.data('attachment-id');
            duplicateModalImage($button, attachmentId);
        });

        // Escape key to close
        $(document).on('keyup.pic-pilot-modal', function(e) {
            if (e.keyCode === 27) { // Escape key
                closeAIToolsModal();
            }
        });
    }

    function closeAIToolsModal() {
        $('#pic-pilot-ai-modal').fadeOut(200, function() {
            $(this).remove();
        });
        $(document).off('keyup.pic-pilot-modal');
    }

    function addModalStyles() {
        if ($('#pic-pilot-modal-styles').length) return;

        $('head').append(`
            <style id="pic-pilot-modal-styles">
                .pic-pilot-modal-overlay {
                    position: fixed;
                    top: 0;
                    left: 0;
                    width: 100%;
                    height: 100%;
                    background: rgba(0, 0, 0, 0.7);
                    z-index: 999999;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    padding: 20px;
                    box-sizing: border-box;
                }

                .pic-pilot-modal-content {
                    background: #fff;
                    border-radius: 8px;
                    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.3);
                    max-width: 700px;
                    width: 100%;
                    max-height: 90vh;
                    overflow-y: auto;
                    position: relative;
                }

                .pic-pilot-modal-header {
                    display: flex;
                    justify-content: space-between;
                    align-items: center;
                    padding: 20px;
                    border-bottom: 1px solid #ddd;
                    background: #2271b1;
                    color: #fff;
                    border-radius: 8px 8px 0 0;
                }

                .pic-pilot-modal-header h2 {
                    margin: 0;
                    font-size: 18px;
                }

                .pic-pilot-modal-close {
                    background: none;
                    border: none;
                    color: #fff;
                    font-size: 24px;
                    cursor: pointer;
                    padding: 0;
                    width: 30px;
                    height: 30px;
                    border-radius: 50%;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                }

                .pic-pilot-modal-close:hover {
                    background: rgba(255, 255, 255, 0.2);
                }

                .pic-pilot-modal-body {
                    padding: 20px;
                }

                .pic-pilot-image-preview {
                    text-align: center;
                    margin-bottom: 20px;
                    padding: 15px;
                    background: #f9f9f9;
                    border-radius: 6px;
                }

                .pic-pilot-image-info {
                    margin-top: 10px;
                    font-size: 13px;
                    color: #666;
                }

                .pic-pilot-keywords-section {
                    margin-bottom: 25px;
                    padding: 15px;
                    background: #f8f9fa;
                    border-radius: 6px;
                }

                .pic-pilot-keywords-section label {
                    display: block;
                    margin-bottom: 8px;
                    font-weight: 600;
                }

                .pic-pilot-keywords-section input {
                    width: 100%;
                    padding: 8px 12px;
                    border: 1px solid #ddd;
                    border-radius: 4px;
                    font-size: 14px;
                }

                .pic-pilot-help-text {
                    margin: 8px 0 0 0;
                    font-size: 12px;
                    color: #666;
                }

                .pic-pilot-tools-grid {
                    display: grid;
                    grid-template-columns: 1fr 1fr;
                    gap: 15px;
                }

                .pic-pilot-tool-card {
                    border: 1px solid #ddd;
                    border-radius: 6px;
                    padding: 15px;
                    text-align: center;
                }

                .pic-pilot-duplicate-card {
                    grid-column: 1 / -1;
                    background: #f0f8f0;
                    border-color: #00a32a;
                }

                .pic-pilot-tool-card h3 {
                    margin: 0 0 8px 0;
                    font-size: 16px;
                    color: #333;
                }

                .pic-pilot-tool-card p {
                    margin: 0 0 15px 0;
                    font-size: 13px;
                    color: #666;
                }

                .pic-pilot-modal-status {
                    margin-top: 10px;
                    padding: 8px;
                    border-radius: 4px;
                    font-size: 12px;
                    display: none;
                }

                .pic-pilot-modal-status.success {
                    background: #d4edda;
                    color: #155724;
                    border: 1px solid #c3e6cb;
                }

                .pic-pilot-modal-status.error {
                    background: #f8d7da;
                    color: #721c24;
                    border: 1px solid #f5c6cb;
                }

                .pic-pilot-modal-status.info {
                    background: #cce7ff;
                    color: #055160;
                    border: 1px solid #b3d7ff;
                }

                @media (max-width: 768px) {
                    .pic-pilot-tools-grid {
                        grid-template-columns: 1fr;
                    }
                    
                    .pic-pilot-duplicate-card {
                        grid-column: 1;
                    }
                }
            </style>
        `);
    }

    async function generateMetadata($button, type, attachmentId) {
        const $container = $button.closest('.pic-pilot-attachment-ai-tools');
        const $keywordsInput = $container.find('.pic-pilot-keywords-input');
        const $status = $container.find(`.pic-pilot-${type}-status`);
        const keywords = $keywordsInput.val().trim();
        const originalText = $button.text();
        
        // Create unique request key for deduplication
        const requestKey = `${attachmentId}-${type}-${keywords}`;
        
        // Check if there's already an active request for this combination
        if (window.picPilotActiveRequests[requestKey]) {
            debugLog(`[${PICPILOT_JS_VERSION}] Request already in progress for ${requestKey}, skipping duplicate`);
            showStatus($status, `Generation already in progress...`, 'info');
            return;
        }

        // Mark request as active
        window.picPilotActiveRequests[requestKey] = true;
        debugLog(`[${PICPILOT_JS_VERSION}] Starting generation request: ${requestKey}`);

        // Update UI
        $button.prop('disabled', true).text('Generating...');
        showStatus($status, `Generating ${type}...`, 'info');

        try {
            const response = await $.ajax({
                url: picPilotAttachment.ajax_url,
                method: 'POST',
                data: {
                    action: 'picpilot_generate_metadata',
                    nonce: picPilotAttachment.nonce,
                    attachment_id: attachmentId,
                    type: type,
                    keywords: keywords,
                    js_version: PICPILOT_JS_VERSION  // Add version to track deployment
                }
            });

            debugLog(`[${PICPILOT_JS_VERSION}] Received response for ${requestKey}:`, response);

            if (response.success) {
                // Check if this was a duplicate request that was blocked
                if (response.data.duplicate_blocked) {
                    debugLog(`[${PICPILOT_JS_VERSION}] Duplicate request handled silently for ${requestKey}`);
                    showStatus($status, `Generation in progress, please wait...`, 'info');
                    return; // Don't update anything for duplicate requests
                }
                
                // Update the corresponding WordPress field
                updateWordPressField(type, response.data.result, attachmentId);
                
                showStatus($status, `✅ ${capitalizeFirst(type)} generated successfully!`, 'success');
                
                // Show fallback message if applicable
                if (response.data.used_fallback && response.data.fallback_message) {
                    setTimeout(() => {
                        showStatus($status, `⚠️ ${response.data.fallback_message}`, 'info');
                    }, 2000);
                }

                // Update button text for alt text
                if (type === 'alt') {
                    $button.text('Regenerate Alt Text');
                }
            } else {
                console.error(`🖼️ [${PICPILOT_JS_VERSION}] Generation failed for ${requestKey}:`, response.data);
                const errorMessage = typeof response.data === 'object' && response.data.message ? response.data.message : (typeof response.data === 'string' ? response.data : 'Unknown error');
                showStatus($status, `❌ Failed to generate ${type}: ${errorMessage}`, 'error');
            }
        } catch (error) {
            console.error(`🖼️ [${PICPILOT_JS_VERSION}] Network error for ${requestKey}:`, error);
            showStatus($status, `❌ Generation failed: ${error.statusText || 'Unknown error'}`, 'error');
        } finally {
            // Clear the active request flag
            delete window.picPilotActiveRequests[requestKey];
            debugLog(`[${PICPILOT_JS_VERSION}] Completed request: ${requestKey}`);
            
            $button.prop('disabled', false);
            if ($button.text() === 'Generating...') {
                $button.text(originalText);
            }
        }
    }

    async function duplicateImage($button, attachmentId) {
        const $container = $button.closest('.pic-pilot-attachment-ai-tools');
        const $keywordsInput = $container.find('.pic-pilot-keywords-input');
        const $status = $container.find('.pic-pilot-duplicate-status');
        const keywords = $keywordsInput.val().trim();
        
        const originalText = $button.text();

        // Update UI
        $button.prop('disabled', true).text('Duplicating...');
        showStatus($status, 'Creating duplicate image...', 'info');

        try {
            const response = await $.ajax({
                url: picPilotAttachment.ajax_url,
                method: 'POST',
                data: {
                    action: 'pic_pilot_duplicate_image',
                    nonce: picPilotAttachment.nonce,
                    attachment_id: attachmentId,
                    keywords: keywords
                }
            });

            if (response.success) {
                showStatus($status, `✅ Image duplicated successfully! New image ID: ${response.data.id}`, 'success');
                
                // Show a link to the new image if we can
                setTimeout(() => {
                    const editUrl = `post.php?post=${response.data.id}&action=edit`;
                    showStatus($status, `✅ Duplicate created! <a href="${editUrl}" target="_blank">View new image →</a>`, 'success');
                }, 1000);
            } else {
                showStatus($status, `❌ Duplication failed: ${response.data?.message || 'Unknown error'}`, 'error');
            }
        } catch (error) {
            console.error('Duplication error:', error);
            showStatus($status, `❌ Duplication failed: ${error.statusText || 'Unknown error'}`, 'error');
        } finally {
            $button.prop('disabled', false).text(originalText);
        }
    }

    function updateWordPressField(type, value, attachmentId) {
        // Prevent multiple rapid updates by debouncing
        if (window.picPilotUpdateInProgress) {
            console.log(`🖼️ Update already in progress, skipping duplicate update for ${type}`);
            return;
        }
        
        window.picPilotUpdateInProgress = true;
        
        setTimeout(() => {
            window.picPilotUpdateInProgress = false;
        }, 1000);
        
        if (type === 'alt') {
            // Look for alt text field - try multiple selectors for different contexts
            const altSelectors = [
                `#attachment_${attachmentId}_alt`,
                'input[name="attachments[' + attachmentId + '][image_alt]"]',
                'input[name="attachments[' + attachmentId + '][post_excerpt]"]', // Some contexts use excerpt
                '#attachment-details-alt-text',
                'input[data-setting="alt"]',
                '.setting[data-setting="alt"] input',
                'input.attachment-alt'
            ];

            let $altField = null;
            for (const selector of altSelectors) {
                $altField = $(selector);
                if ($altField.length) {
                    debugLog(`Found alt field with selector: ${selector}`);
                    break;
                }
            }

            if ($altField && $altField.length) {
                // Temporarily disable any change event listeners to prevent cascades
                const currentVal = $altField.val();
                if (currentVal !== value) {
                    $altField.off('change.picpilot').val(value);
                    
                    // Trigger change after a delay to ensure it's the final value
                    setTimeout(() => {
                        $altField.trigger('change');
                    }, 100);
                    
                    debugLog(`Updated alt text: ${value}`);
                }
            } else {
                debugLog('Could not find alt text field to update');
            }

        } else if (type === 'title') {
            // Look for title field - try multiple selectors
            const titleSelectors = [
                `#attachment_${attachmentId}_title`,
                'input[name="attachments[' + attachmentId + '][post_title]"]',
                '#attachment-details-title',
                'input[data-setting="title"]',
                '.setting[data-setting="title"] input',
                'input.attachment-title',
                '#title' // Full page edit
            ];

            let $titleField = null;
            for (const selector of titleSelectors) {
                $titleField = $(selector);
                if ($titleField.length) {
                    console.log(`🖼️ Found title field with selector: ${selector}`);
                    break;
                }
            }

            if ($titleField && $titleField.length) {
                // Temporarily disable any change event listeners to prevent cascades
                const currentVal = $titleField.val();
                if (currentVal !== value) {
                    $titleField.off('change.picpilot').val(value);
                    
                    // Trigger change after a delay to ensure it's the final value
                    setTimeout(() => {
                        $titleField.trigger('change');
                    }, 100);
                    
                    console.log(`🖼️ Updated title: ${value}`);
                }
            } else {
                console.warn('🖼️ Could not find title field to update');
            }
        }
    }

    function showStatus($element, message, type) {
        $element.html(message)
                .removeClass('success error info')
                .addClass(type)
                .show();
        
        // Auto-hide success messages after 5 seconds
        if (type === 'success') {
            setTimeout(() => {
                $element.fadeOut();
            }, 5000);
        }
    }

    function capitalizeFirst(str) {
        return str.charAt(0).toUpperCase() + str.slice(1);
    }

    // Helper functions for modal
    function getImageTitle(attachmentId) {
        // Try multiple selectors to get current title
        const titleSelectors = [
            `#attachment_${attachmentId}_title`,
            'input[name*="[post_title]"]',
            '#attachment-details-title',
            'input[data-setting="title"]',
            '.setting[data-setting="title"] input',
            'input.attachment-title',
            '#title'
        ];

        for (const selector of titleSelectors) {
            const $field = $(selector);
            if ($field.length) {
                return $field.val() || '';
            }
        }

        // Try to get from attachment details display
        const $titleDisplay = $('.attachment-details .title');
        if ($titleDisplay.length) {
            return $titleDisplay.text().trim();
        }

        return '';
    }

    function getImageAlt(attachmentId) {
        // Try multiple selectors to get current alt text
        const altSelectors = [
            `#attachment_${attachmentId}_alt`,
            'input[name*="[image_alt]"]',
            '#attachment-details-alt-text',
            'input[data-setting="alt"]',
            '.setting[data-setting="alt"] input',
            'input.attachment-alt'
        ];

        for (const selector of altSelectors) {
            const $field = $(selector);
            if ($field.length) {
                return $field.val() || '';
            }
        }

        return '';
    }

    function getImageUrl(attachmentId) {
        // Try to get image URL from various sources
        const $img = $(`.attachment-preview img, .details-image img, .media-modal img[data-attachment-id="${attachmentId}"]`).first();
        if ($img.length) {
            return $img.attr('src') || $img.data('full-src') || '';
        }

        // Fallback: try to construct URL if we have attachment data
        const $urlField = $('input[name*="[url]"], #attachment-details-copy-link');
        if ($urlField.length) {
            const url = $urlField.val();
            if (url && url.includes('/uploads/')) {
                return url;
            }
        }

        return '';
    }

    async function generateModalMetadata($button, type, attachmentId) {
        const keywords = $('#pic-pilot-modal-keywords').val().trim();
        const $status = $(`.pic-pilot-${type}-status`);
        const originalText = $button.text();

        // Create unique request key for deduplication
        const requestKey = `${attachmentId}-${type}-${keywords}`;
        
        // Check if there's already an active request for this combination
        if (window.picPilotActiveRequests[requestKey]) {
            debugLog(`[${PICPILOT_JS_VERSION}] Request already in progress for ${requestKey}, skipping duplicate`);
            showModalStatus($status, `Generation already in progress...`, 'info');
            return;
        }

        // Mark request as active
        window.picPilotActiveRequests[requestKey] = true;
        debugLog(`[${PICPILOT_JS_VERSION}] Starting generation request: ${requestKey}`);

        // Update UI
        $button.prop('disabled', true).text('Generating...');
        showModalStatus($status, `Generating ${type}...`, 'info');

        try {
            const requestData = {
                action: 'picpilot_generate_metadata',
                nonce: picPilotAttachment.nonce,
                attachment_id: attachmentId,
                type: type,
                keywords: keywords,
                js_version: PICPILOT_JS_VERSION  // Add version to track deployment
            };
            
            console.log(`🖼️ [${PICPILOT_JS_VERSION}] Making AJAX request for ${requestKey}:`, requestData);
            console.log(`🖼️ [${PICPILOT_JS_VERSION}] URL:`, picPilotAttachment.ajax_url);
            console.log(`🖼️ [${PICPILOT_JS_VERSION}] picPilotAttachment object:`, window.picPilotAttachment);
            
            const response = await $.ajax({
                url: picPilotAttachment.ajax_url,
                method: 'POST',
                data: requestData
            });

            console.log(`🖼️ [${PICPILOT_JS_VERSION}] Received response for ${requestKey}:`, response);

            if (response.success) {
                // Check if this was a duplicate request that was blocked
                if (response.data.duplicate_blocked) {
                    console.log(`🖼️ [${PICPILOT_JS_VERSION}] Duplicate request handled silently for ${requestKey}`);
                    showModalStatus($status, `Generation in progress, please wait...`, 'info');
                    return; // Don't update anything for duplicate requests
                }
                
                // Only update once we have the final result
                const finalResult = response.data && response.data.result ? response.data.result : null;
                console.log(`🖼️ [${PICPILOT_JS_VERSION}] Final result for ${requestKey}: ${finalResult}`);
                
                if (finalResult) {
                    // Update the corresponding WordPress field with final result only
                    updateWordPressField(type, finalResult, attachmentId);
                    
                    // Update the modal preview with final result only
                    updateModalPreview(type, finalResult);
                } else {
                    console.error(`🖼️ [${PICPILOT_JS_VERSION}] No result data found in response for ${requestKey}:`, response.data);
                    showModalStatus($status, `❌ Invalid response from server: No result data`, 'error');
                    return;
                }
                
                showModalStatus($status, `✅ ${capitalizeFirst(type)} generated successfully!`, 'success');
                
                // Show fallback message if applicable
                if (response.data.used_fallback && response.data.fallback_message) {
                    setTimeout(() => {
                        showModalStatus($status, `⚠️ ${response.data.fallback_message}`, 'info');
                    }, 2000);
                }

                // Update button text for alt text
                if (type === 'alt') {
                    $button.text('Regenerate Alt Text');
                }
            } else {
                console.error(`🖼️ [${PICPILOT_JS_VERSION}] Generation failed for ${requestKey}:`, response.data);
                const errorMessage = typeof response.data === 'object' && response.data.message ? response.data.message : (typeof response.data === 'string' ? response.data : 'Unknown error');
                showModalStatus($status, `❌ Failed to generate ${type}: ${errorMessage}`, 'error');
            }
        } catch (error) {
            console.error(`🖼️ [${PICPILOT_JS_VERSION}] Network error for ${requestKey}:`, error);
            showModalStatus($status, `❌ Generation failed: ${error.statusText || 'Unknown error'}`, 'error');
        } finally {
            // Clear the active request flag
            delete window.picPilotActiveRequests[requestKey];
            debugLog(`[${PICPILOT_JS_VERSION}] Completed request: ${requestKey}`);
            
            $button.prop('disabled', false);
            if ($button.text() === 'Generating...') {
                $button.text(originalText);
            }
        }
    }

    async function duplicateModalImage($button, attachmentId) {
        const keywords = $('#pic-pilot-modal-keywords').val().trim();
        const $status = $('.pic-pilot-duplicate-status');
        
        const originalText = $button.text();

        // Update UI
        $button.prop('disabled', true).text('Duplicating...');
        showModalStatus($status, 'Creating duplicate image...', 'info');

        try {
            const response = await $.ajax({
                url: picPilotAttachment.ajax_url,
                method: 'POST',
                data: {
                    action: 'pic_pilot_duplicate_image',
                    nonce: picPilotAttachment.nonce,
                    attachment_id: attachmentId,
                    new_title: 'generate',
                    new_alt: 'generate',
                    keywords: keywords
                }
            });

            if (response.success) {
                showModalStatus($status, `✅ Image duplicated successfully! New image ID: ${response.data.id}`, 'success');
                
                // Show a link to the new image if we can
                setTimeout(() => {
                    const editUrl = `post.php?post=${response.data.id}&action=edit`;
                    showModalStatus($status, `✅ Duplicate created! <a href="${editUrl}" target="_blank">View new image →</a>`, 'success');
                }, 1000);
            } else {
                showModalStatus($status, `❌ Duplication failed: ${response.data?.message || 'Unknown error'}`, 'error');
            }
        } catch (error) {
            console.error('Duplication error:', error);
            showModalStatus($status, `❌ Duplication failed: ${error.statusText || 'Unknown error'}`, 'error');
        } finally {
            $button.prop('disabled', false).text(originalText);
        }
    }

    function updateModalPreview(type, value) {
        const $imageInfo = $('.pic-pilot-image-info');
        if (!$imageInfo.length) return;
        
        if (type === 'title') {
            // Get current HTML and update title portion
            let currentHtml = $imageInfo.html();
            const titleRegex = /(<strong>Current Title:<\/strong>)\s*([^<]*?)(<br>)/;
            if (titleRegex.test(currentHtml)) {
                const newHtml = currentHtml.replace(titleRegex, `$1 ${value}$3`);
                $imageInfo.html(newHtml);
            }
        } else if (type === 'alt') {
            // Get current HTML and update alt text portion
            let currentHtml = $imageInfo.html();
            const altRegex = /(<strong>Current Alt Text:<\/strong>)\s*(.*)$/;
            if (altRegex.test(currentHtml)) {
                const newHtml = currentHtml.replace(altRegex, `$1 ${value}`);
                $imageInfo.html(newHtml);
            }
        }
    }

    function showModalStatus($element, message, type) {
        $element.html(message)
                .removeClass('success error info')
                .addClass(type)
                .show();
        
        // Auto-hide success messages after 5 seconds
        if (type === 'success') {
            setTimeout(() => {
                $element.fadeOut();
            }, 5000);
        }
    }

    // Cleanup function to prevent memory leaks
    function cleanup() {
        // Disconnect MutationObserver
        if (mutationObserver) {
            mutationObserver.disconnect();
            mutationObserver = null;
        }
        
        // Clear cached elements
        $cachedElements = {
            buttons: null,
            containers: null,
            lastUpdate: 0
        };
        
        // Clear active requests
        window.picPilotActiveRequests = {};
        
        // Remove event listeners
        $(document).off('click.picpilot');
        
        debugLog('PicPilot cleanup completed');
    }
    
    // Clean up when page is unloaded
    $(window).on('beforeunload', cleanup);
    
    // Global functions for debugging (only expose in debug mode)
    if (PICPILOT_DEBUG) {
        window.picPilotDebug = {
            initAITools: initAITools,
            openModal: openAIToolsModal,
            cleanup: cleanup,
            toggleDebug: function() {
                window.PICPILOT_DEBUG = !PICPILOT_DEBUG;
                console.log('🖼️ Debug mode:', PICPILOT_DEBUG ? 'ON' : 'OFF');
            },
            checkElements: function() {
                console.log('🖼️ === DEBUG INFO ===');
                console.log('🖼️ AI Tools buttons:', $('.pic-pilot-launch-modal-btn').length);
                console.log('🖼️ AI Tools containers:', $('.pic-pilot-attachment-ai-tools').length);
                console.log('🖼️ Media sidebar:', $('.media-sidebar').length);
                console.log('🖼️ Attachment details:', $('.attachment-details').length);
                console.log('🖼️ Active requests:', Object.keys(window.picPilotActiveRequests).length);
                console.log('🖼️ MutationObserver active:', !!mutationObserver);
                
                // Show all attachment IDs found
                $('.pic-pilot-launch-modal-btn').each(function(i, btn) {
                    console.log('🖼️ Button', i, 'has attachment ID:', $(btn).data('attachment-id'));
                });
            }
        };
    }

})(jQuery);