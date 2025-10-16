/**
 * Universal Modal for Page Builders
 * Works with Elementor, Beaver Builder, Visual Composer, Divi, etc.
 */

(function() {
    'use strict';

    // Prevent multiple initializations
    if (window.picPilotUniversalModal) {
        return;
    }
    window.picPilotUniversalModal = true;

    // Configuration
    const CONFIG = {
        buttonText: 'Pic Pilot',
        buttonClass: 'pic-pilot-launch-modal-btn',
        modalId: 'pic-pilot-universal-modal',
        checkInterval: 1000,
        maxChecks: 30
    };

    let checkCount = 0;
    let observer = null;

    // Initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

    function init() {
        
        // Set up click handler for existing attachment field buttons
        setupAttachmentFieldsHandler();
        
        // Start monitoring for media modals
        startMediaModalMonitoring();
        
        // Set up MutationObserver for dynamic content
        setupMutationObserver();
    }

    function setupAttachmentFieldsHandler() {
        // Handle clicks on the original attachment fields buttons
        document.addEventListener('click', function(e) {
            if (e.target.matches('.pic-pilot-launch-modal-btn') || e.target.closest('.pic-pilot-launch-modal-btn')) {
                e.preventDefault();
                e.stopPropagation();
                
                const button = e.target.matches('.pic-pilot-launch-modal-btn') ? e.target : e.target.closest('.pic-pilot-launch-modal-btn');
                const attachmentId = button.getAttribute('data-attachment-id');
                
                if (!attachmentId) {
                    return;
                }
                
                openUniversalModal(attachmentId);
            }
        });
    }

    function startMediaModalMonitoring() {
        const checkForModals = () => {
            checkCount++;
            
            // Check for various media modal patterns
            const modalSelectors = [
                // WordPress native
                '.media-modal-content',
                '.media-frame-content',
                '.attachments-browser',
                
                // Elementor
                '.elementor-modal-content',
                '.elementor-finder',
                '.dialog-widget-content',
                
                // Visual Composer
                '.vc_media-xs',
                '.vc_ui-panel-content',
                
                // Divi
                '.et-fb-modal',
                '.et-core-modal-content'
            ];
            
            modalSelectors.forEach(selector => {
                const modals = document.querySelectorAll(selector);
                if (modals.length > 0) {
                    modals.forEach(modal => {
                        enhanceExistingButtons(modal);
                    });
                }
            });
            
            // Continue checking for a while
            if (checkCount < CONFIG.maxChecks) {
                setTimeout(checkForModals, CONFIG.checkInterval);
            }
        };
        
        // Start checking immediately and then periodically
        checkForModals();
    }

    function setupMutationObserver() {
        // Disconnect existing observer
        if (observer) {
            observer.disconnect();
        }
        
        observer = new MutationObserver((mutations) => {
            let shouldCheck = false;
            
            mutations.forEach((mutation) => {
                if (mutation.type === 'childList' && mutation.addedNodes.length > 0) {
                    mutation.addedNodes.forEach((node) => {
                        if (node.nodeType === 1) { // Element node
                            // Check if this could be a modal or contains modal content
                            if (node.classList && (
                                node.classList.contains('modal') ||
                                node.classList.contains('dialog') ||
                                node.classList.contains('lightbox') ||
                                node.querySelector && (
                                    node.querySelector('.media-modal') ||
                                    node.querySelector('.elementor-modal') ||
                                    node.querySelector('.vc_media') ||
                                    node.querySelector('.et-fb-modal')
                                )
                            )) {
                                shouldCheck = true;
                            }
                        }
                    });
                }
            });
            
            if (shouldCheck) {
                setTimeout(() => {
                    const modals = document.querySelectorAll('.media-modal-content, .elementor-modal-content, .vc_media-xs, .et-fb-modal');
                    modals.forEach(modal => enhanceExistingButtons(modal));
                }, 100);
            }
        });
        
        observer.observe(document.body, {
            childList: true,
            subtree: true
        });
    }

    function enhanceExistingButtons(modalContainer) {
        // Look for existing pic-pilot-launch-modal-btn buttons
        const existingButtons = modalContainer.querySelectorAll(`.${CONFIG.buttonClass}`);
        
        if (existingButtons.length > 0) {
            existingButtons.forEach(button => {
                // Ensure the button has the universal modal handler
                if (!button.hasAttribute('data-universal-enhanced')) {
                    button.setAttribute('data-universal-enhanced', 'true');
                }
            });
            
            return true;
        }
        
        return false;
    }


    function openUniversalModal(attachmentId = null) {
        // Remove existing modal
        const existingModal = document.getElementById(CONFIG.modalId);
        if (existingModal) {
            existingModal.remove();
        }
        
        // Get current image data
        const currentTitle = getImageTitle(attachmentId);
        const currentAlt = getImageAlt(attachmentId);
        const imageUrl = getImageUrl(attachmentId);
        
        // Get settings from global object
        const settings = window.picPilotAttachment?.settings || {};
        const autoGenerateBothEnabled = settings.auto_generate_both_enabled || false;
        const dangerousRenameEnabled = settings.dangerous_rename_enabled || false;
        const showKeywords = settings.show_keywords || false;
        
        // Determine if "Generate Both" should be shown
        const isMissingAlt = !currentAlt;
        const isMissingTitle = !currentTitle;
        const isMissingBoth = isMissingAlt && isMissingTitle;
        const showGenerateBoth = autoGenerateBothEnabled && isMissingBoth;
        
        // Create keywords section conditionally
        const keywordsSection = showKeywords ? `
            <div style="margin-bottom: 20px; padding: 12px; background: #f8f9fa; border-radius: 6px;">
                <label style="display: block; margin-bottom: 6px; font-weight: 600; font-size: 13px;">🎯 Keywords (optional):</label>
                <input type="text" id="pic-pilot-universal-keywords" placeholder="e.g., business person, outdoor scene, product photo" style="width: 100%; padding: 8px 12px; border: 1px solid #ddd; border-radius: 4px; font-size: 13px; box-sizing: border-box;">
                <p style="margin: 6px 0 0 0; font-size: 11px; color: #666;">Provide context for better AI results</p>
            </div>
        ` : '';
        
        // Create "Generate Both" section conditionally
        const generateBothSection = showGenerateBoth ? `
            <div style="margin-bottom: 15px; padding: 12px; background: #fff8e1; border: 1px solid #ffcc02; border-radius: 6px; text-align: center;">
                <div style="display: flex; align-items: center; justify-content: center; gap: 10px;">
                    <button type="button" id="pic-pilot-generate-both" data-attachment-id="${attachmentId}" style="background: #ffcc02; color: #333; border: none; padding: 8px 16px; border-radius: 4px; cursor: pointer; font-size: 13px; font-weight: 600;">
                        ⚡ Generate Both
                    </button>
                    <span style="font-size: 12px; color: #666;">Title + Alt Text</span>
                </div>
                <div id="pic-pilot-both-status" style="margin-top: 8px; padding: 6px; border-radius: 4px; font-size: 11px; display: none;"></div>
            </div>
        ` : '';
        
        // Create combined advanced section
        const advancedSection = dangerousRenameEnabled ? `
            <div style="margin-top: 15px; padding: 12px; background: #fdf2f2; border: 1px solid #fecaca; border-radius: 6px;">
                <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 8px;">
                    <span style="font-size: 13px; font-weight: 600; color: #991b1b;">📝 Advanced</span>
                    <span style="font-size: 11px; color: #991b1b;">⚠️ Use with caution</span>
                </div>
                <div style="display: flex; gap: 10px; align-items: center;">
                    <button type="button" id="pic-pilot-rename-file" data-attachment-id="${attachmentId}" style="background: #dc2626; color: #fff; border: none; padding: 6px 12px; border-radius: 4px; cursor: pointer; font-size: 12px;">
                        Generate Filename
                    </button>
                    <div id="pic-pilot-rename-status" style="flex: 1; padding: 4px; border-radius: 4px; font-size: 11px; display: none;"></div>
                </div>
            </div>
        ` : '';
        
        // Create comprehensive modal HTML
        const modalHtml = `
            <div id="${CONFIG.modalId}" style="position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.7); z-index: 999999; display: flex; align-items: center; justify-content: center; padding: 20px; box-sizing: border-box;">
                <div style="background: #fff; border-radius: 8px; max-width: 700px; width: 100%; max-height: 90vh; overflow-y: auto; box-shadow: 0 4px 20px rgba(0,0,0,0.3); position: relative;">
                    <div style="display: flex; justify-content: space-between; align-items: center; padding: 20px; border-bottom: 1px solid #ddd; background: #2271b1; color: #fff; border-radius: 8px 8px 0 0;">
                        <h2 style="margin: 0; font-size: 18px;">🤖 AI Tools & Metadata</h2>
                        <button type="button" onclick="document.getElementById('${CONFIG.modalId}').remove()" style="background: none; border: none; color: #fff; font-size: 24px; cursor: pointer; padding: 0; width: 30px; height: 30px; border-radius: 50%; display: flex; align-items: center; justify-content: center;">×</button>
                    </div>
                    
                    <div style="padding: 20px;">
                        <div style="text-align: center; margin-bottom: 20px; padding: 15px; background: #f9f9f9; border-radius: 6px;">
                            ${imageUrl ? `<img src="${imageUrl}" alt="Preview" style="max-width: 100%; max-height: 200px; border-radius: 4px; margin-bottom: 10px;" />` : '<div style="padding: 40px; background: #eee; border-radius: 4px; margin-bottom: 10px;">Image preview not available</div>'}
                            <div style="font-size: 13px; color: #666;">
                                <strong>Attachment ID:</strong> ${attachmentId || 'Unknown'}<br>
                                <strong>Current Title:</strong> ${currentTitle || 'No title'}<br>
                                <strong>Current Alt Text:</strong> ${currentAlt || 'No alt text'}
                            </div>
                        </div>

                        ${keywordsSection}
                        ${generateBothSection}

                        <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 12px; margin-bottom: 15px;">
                            <div style="border: 1px solid #e5e7eb; border-radius: 6px; padding: 12px; text-align: center; background: #fafafa;">
                                <div style="margin-bottom: 8px;">
                                    <span style="font-size: 14px; font-weight: 600; color: #374151;">📝 Title</span>
                                </div>
                                <button type="button" id="pic-pilot-generate-title" data-attachment-id="${attachmentId}" style="background: #2563eb; color: #fff; border: none; padding: 8px 12px; border-radius: 4px; cursor: pointer; font-size: 12px; width: 100%;">
                                    ${currentTitle ? 'Regenerate' : 'Generate'}
                                </button>
                                <div id="pic-pilot-title-status" style="margin-top: 8px; padding: 6px; border-radius: 4px; font-size: 11px; display: none;"></div>
                            </div>

                            <div style="border: 1px solid #e5e7eb; border-radius: 6px; padding: 12px; text-align: center; background: #fafafa;">
                                <div style="margin-bottom: 8px;">
                                    <span style="font-size: 14px; font-weight: 600; color: #374151;">🏷️ Alt Text</span>
                                </div>
                                <button type="button" id="pic-pilot-generate-alt" data-attachment-id="${attachmentId}" style="background: #2563eb; color: #fff; border: none; padding: 8px 12px; border-radius: 4px; cursor: pointer; font-size: 12px; width: 100%;">
                                    ${currentAlt ? 'Regenerate' : 'Generate'}
                                </button>
                                <div id="pic-pilot-alt-status" style="margin-top: 8px; padding: 6px; border-radius: 4px; font-size: 11px; display: none;"></div>
                            </div>
                            
                            <div style="border: 1px solid #e5e7eb; border-radius: 6px; padding: 12px; text-align: center; background: #f0fdf4;">
                                <div style="margin-bottom: 8px;">
                                    <span style="font-size: 14px; font-weight: 600; color: #166534;">🔄 Duplicate</span>
                                </div>
                                <button type="button" id="pic-pilot-duplicate" data-attachment-id="${attachmentId}" style="background: #16a34a; color: #fff; border: none; padding: 8px 12px; border-radius: 4px; cursor: pointer; font-size: 12px; width: 100%;">
                                    Create Copy
                                </button>
                                <div id="pic-pilot-duplicate-status" style="margin-top: 8px; padding: 6px; border-radius: 4px; font-size: 11px; display: none;"></div>
                            </div>
                        </div>
                        
                        ${advancedSection}
                    </div>
                </div>
            </div>
        `;

        // Add modal to page
        document.body.insertAdjacentHTML('beforeend', modalHtml);
        
        // Set up modal event handlers
        setupModalEventHandlers(attachmentId);
        
        // Focus keywords input
        const keywordsInput = document.getElementById('pic-pilot-universal-keywords');
        if (keywordsInput) {
            keywordsInput.focus();
        }
        
        // Close on overlay click
        document.getElementById(CONFIG.modalId).addEventListener('click', function(e) {
            if (e.target === this) {
                this.remove();
            }
        });
        
        // Close on escape key
        const escapeHandler = (e) => {
            if (e.key === 'Escape') {
                const modal = document.getElementById(CONFIG.modalId);
                if (modal) {
                    modal.remove();
                }
                document.removeEventListener('keydown', escapeHandler);
            }
        };
        document.addEventListener('keydown', escapeHandler);
    }
    
    function setupModalEventHandlers(attachmentId) {
        // Generate Title button
        const titleBtn = document.getElementById('pic-pilot-generate-title');
        if (titleBtn) {
            titleBtn.addEventListener('click', () => generateUniversalMetadata('title', attachmentId));
        }
        
        // Generate Alt Text button
        const altBtn = document.getElementById('pic-pilot-generate-alt');
        if (altBtn) {
            altBtn.addEventListener('click', () => generateUniversalMetadata('alt', attachmentId));
        }
        
        // Generate Both button
        const bothBtn = document.getElementById('pic-pilot-generate-both');
        if (bothBtn) {
            bothBtn.addEventListener('click', () => generateUniversalMetadata('both', attachmentId));
        }
        
        // Rename File button
        const renameBtn = document.getElementById('pic-pilot-rename-file');
        if (renameBtn) {
            renameBtn.addEventListener('click', () => renameUniversalFile(attachmentId));
        }
        
        // Duplicate Image button
        const duplicateBtn = document.getElementById('pic-pilot-duplicate');
        if (duplicateBtn) {
            duplicateBtn.addEventListener('click', () => duplicateUniversalImage(attachmentId));
        }
    }
    
    // Helper functions for modal
    function getImageTitle(attachmentId) {
        if (!attachmentId) return '';
        
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
            const field = document.querySelector(selector);
            if (field && field.value) {
                return field.value;
            }
        }
        
        // Try to get from attachment details display
        const titleDisplay = document.querySelector('.attachment-details .title');
        if (titleDisplay) {
            return titleDisplay.textContent.trim();
        }
        
        return '';
    }

    function getImageAlt(attachmentId) {
        if (!attachmentId) return '';
        
        const altSelectors = [
            `#attachment_${attachmentId}_alt`,
            'input[name*="[image_alt]"]',
            '#attachment-details-alt-text',
            'input[data-setting="alt"]',
            '.setting[data-setting="alt"] input',
            'input.attachment-alt'
        ];

        for (const selector of altSelectors) {
            const field = document.querySelector(selector);
            if (field && field.value) {
                return field.value;
            }
        }

        return '';
    }

    function getImageUrl(attachmentId) {
        // Try to get image URL from various sources
        const imgSelectors = [
            `.attachment-preview img`,
            `.details-image img`,
            `.media-modal img[data-attachment-id="${attachmentId}"]`,
            `.attachment img`
        ];
        
        for (const selector of imgSelectors) {
            const img = document.querySelector(selector);
            if (img && img.src) {
                return img.src;
            }
        }

        // Fallback: try to construct URL if we have attachment data
        const urlField = document.querySelector('input[name*="[url]"], #attachment-details-copy-link');
        if (urlField && urlField.value && urlField.value.includes('/uploads/')) {
            return urlField.value;
        }

        return '';
    }
    
    // AI Generation functions - these will call the actual AJAX endpoints
    async function generateUniversalMetadata(type, attachmentId) {
        const button = document.getElementById(`pic-pilot-generate-${type}`);
        const statusEl = document.getElementById(`pic-pilot-${type}-status`);
        const keywordsInput = document.getElementById('pic-pilot-universal-keywords');
        
        if (!button || !statusEl) {
            return;
        }
        
        const keywords = keywordsInput ? keywordsInput.value.trim() : '';
        const originalText = button.textContent;
        
        // Update UI
        button.disabled = true;
        button.textContent = 'Generating...';
        showUniversalStatus(statusEl, `Generating ${type}...`, 'info');
        
        try {
            // Use different action for 'both' type
            const action = type === 'both' ? 'picpilot_generate_both' : 'picpilot_generate_metadata';
            const response = await fetch(window.picPilotAttachment.ajax_url, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({
                    action: action,
                    nonce: window.picPilotAttachment.nonce,
                    attachment_id: attachmentId,
                    type: type,
                    keywords: keywords
                })
            });
            
            if (!response.ok) {
                throw new Error(`HTTP ${response.status}: ${response.statusText}`);
            }
            
            const text = await response.text();
            
            let result;
            try {
                result = JSON.parse(text);
            } catch (e) {
                throw new Error('Invalid JSON response from server');
            }
            
            if (result.success) {
                let generatedText;
                
                if (type === 'both') {
                    // Handle 'both' response format: {alt_result: ..., title_result: ...}
                    generatedText = {
                        title: result.data?.title_result,
                        alt: result.data?.alt_result
                    };
                    
                    if (!generatedText.title || !generatedText.alt) {
                        showUniversalStatus(statusEl, '⚠ Invalid response from server', 'error');
                        return;
                    }
                } else {
                    // Handle regular response format: {result: ...}
                    generatedText = result.data && result.data.result ? result.data.result : null;
                    
                    if (!generatedText) {
                        showUniversalStatus(statusEl, '⚠ Invalid response from server', 'error');
                        return;
                    }
                }
                
                // Update the corresponding WordPress field
                updateWordPressField(type, generatedText, attachmentId);
                
                if (type === 'both') {
                    showUniversalStatus(statusEl, `✅ Title and Alt Text generated successfully!`, 'success');
                    
                    // Update both button texts
                    const titleBtn = document.getElementById('pic-pilot-generate-title');
                    const altBtn = document.getElementById('pic-pilot-generate-alt');
                    if (titleBtn && generatedText.title) {
                        titleBtn.textContent = 'Regenerate Title';
                    }
                    if (altBtn && generatedText.alt) {
                        altBtn.textContent = 'Regenerate Alt Text';
                    }
                } else {
                    showUniversalStatus(statusEl, `✅ ${type.charAt(0).toUpperCase() + type.slice(1)} generated successfully!`, 'success');
                    
                    // Update button text
                    button.textContent = `Regenerate ${type.charAt(0).toUpperCase() + type.slice(1)}`;
                }
                
            } else {
                const errorMessage = typeof result.data === 'object' && result.data.message 
                    ? result.data.message 
                    : (typeof result.data === 'string' ? result.data : 'Unknown error');
                showUniversalStatus(statusEl, `⚠ Failed: ${errorMessage}`, 'error');
            }
            
        } catch (error) {
            showUniversalStatus(statusEl, `⚠ Error: ${error.message}`, 'error');
        } finally {
            button.disabled = false;
            if (button.textContent === 'Generating...') {
                button.textContent = originalText;
            }
        }
    }
    
    async function duplicateUniversalImage(attachmentId) {
        const button = document.getElementById('pic-pilot-duplicate');
        const statusEl = document.getElementById('pic-pilot-duplicate-status');
        const keywordsInput = document.getElementById('pic-pilot-universal-keywords');
        
        if (!button || !statusEl) {
            return;
        }
        
        const keywords = keywordsInput ? keywordsInput.value.trim() : '';
        const originalText = button.textContent;
        
        // Update UI
        button.disabled = true;
        button.textContent = 'Duplicating...';
        showUniversalStatus(statusEl, 'Creating duplicate image...', 'info');
        
        try {
            const response = await fetch(window.picPilotUniversal.ajax_url, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({
                    action: 'pic_pilot_duplicate_image',
                    nonce: window.picPilotUniversal.nonce,
                    attachment_id: attachmentId,
                    new_title: 'generate',
                    new_alt: 'generate',
                    keywords: keywords
                })
            });
            
            if (!response.ok) {
                throw new Error(`HTTP ${response.status}: ${response.statusText}`);
            }
            
            const result = await response.json();
            
            if (result.success) {
                showUniversalStatus(statusEl, `✅ Image duplicated! New ID: ${result.data.id}`, 'success');
                
                // Show link to new image if possible
                setTimeout(() => {
                    const editUrl = `post.php?post=${result.data.id}&action=edit`;
                    showUniversalStatus(statusEl, `✅ Duplicate created! <a href="${editUrl}" target="_blank" style="color: #0073aa;">View new image →</a>`, 'success');
                }, 1000);
                
            } else {
                const errorMessage = result.data?.message || result.data || 'Unknown error';
                showUniversalStatus(statusEl, `⚠ Duplication failed: ${errorMessage}`, 'error');
            }
            
        } catch (error) {
            showUniversalStatus(statusEl, `⚠ Error: ${error.message}`, 'error');
        } finally {
            button.disabled = false;
            button.textContent = originalText;
        }
    }
    
    async function renameUniversalFile(attachmentId) {
        const button = document.getElementById('pic-pilot-rename-file');
        const statusEl = document.getElementById('pic-pilot-rename-status');
        const keywordsInput = document.getElementById('pic-pilot-universal-keywords');
        
        if (!button || !statusEl) {
            return;
        }
        
        const keywords = keywordsInput ? keywordsInput.value.trim() : '';
        const originalText = button.textContent;
        
        // Update UI
        button.disabled = true;
        button.textContent = 'Generating...';
        showUniversalStatus(statusEl, 'Generating new filename...', 'info');
        
        try {
            // First, generate a new filename
            const response = await fetch(window.picPilotAttachment.ajax_url, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({
                    action: 'picpilot_generate_ai_filename',
                    nonce: window.picPilotAttachment.nonce,
                    attachment_id: attachmentId,
                    keywords: keywords
                })
            });
            
            if (!response.ok) {
                throw new Error(`HTTP ${response.status}: ${response.statusText}`);
            }
            
            const text = await response.text();
            
            let result;
            try {
                result = JSON.parse(text);
            } catch (e) {
                throw new Error('Invalid JSON response from server');
            }
            
            if (result.success) {
                const newFilename = result.data?.filename || 'Unknown filename';
                // Show generated filename with option to proceed
                showUniversalStatus(statusEl, `
                    ✅ Generated filename: <strong>${newFilename}</strong><br>
                    <button id="confirm-rename-${attachmentId}" data-filename="${newFilename}" style="margin-top: 8px; background: #dc2626; color: #fff; border: none; padding: 4px 8px; border-radius: 3px; cursor: pointer; font-size: 11px;">
                        ⚠️ Proceed with Rename
                    </button>
                    <button id="cancel-rename-${attachmentId}" style="margin: 8px 0 0 8px; background: #6b7280; color: #fff; border: none; padding: 4px 8px; border-radius: 3px; cursor: pointer; font-size: 11px;">
                        Cancel
                    </button>
                `, 'success');
                
                // Add event listeners for confirm/cancel buttons
                setTimeout(() => {
                    const confirmBtn = document.getElementById(`confirm-rename-${attachmentId}`);
                    const cancelBtn = document.getElementById(`cancel-rename-${attachmentId}`);
                    
                    if (confirmBtn) {
                        confirmBtn.addEventListener('click', () => {
                            proceedWithRename(attachmentId, newFilename, statusEl);
                        });
                    }
                    
                    if (cancelBtn) {
                        cancelBtn.addEventListener('click', () => {
                            showUniversalStatus(statusEl, `Generated filename: <strong>${newFilename}</strong> (rename cancelled)`, 'info');
                        });
                    }
                }, 100);
                
            } else {
                const errorMessage = typeof result.data === 'object' && result.data.message 
                    ? result.data.message 
                    : (typeof result.data === 'string' ? result.data : 'Unknown error');
                showUniversalStatus(statusEl, `⚠ Generation failed: ${errorMessage}`, 'error');
            }
            
        } catch (error) {
            showUniversalStatus(statusEl, `⚠ Error: ${error.message}`, 'error');
        } finally {
            button.disabled = false;
            button.textContent = originalText;
        }
    }
    
    // Function to proceed with rename after user confirmation
    async function proceedWithRename(attachmentId, newFilename, statusEl) {
        showUniversalStatus(statusEl, 'Checking image usage...', 'info');
        
        try {
            // First check if image is in use
            const usageResponse = await fetch(window.picPilotAttachment.ajax_url, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({
                    action: 'picpilot_check_image_usage',
                    nonce: window.picPilotAttachment.nonce,
                    attachment_id: attachmentId
                })
            });
            
            if (!usageResponse.ok) {
                throw new Error(`HTTP ${usageResponse.status}: ${usageResponse.statusText}`);
            }
            
            const usageResult = await usageResponse.json();
            
            if (!usageResult.success) {
                const errorMessage = usageResult.data?.message || usageResult.data || 'Usage check failed';
                showUniversalStatus(statusEl, `⚠ Usage check failed: ${errorMessage}`, 'error');
                return;
            }
            
            const usageData = usageResult.data;
            let proceed = true;
            
            // Warn if image is in use
            if (!usageData.is_safe_to_rename) {
                const warningMessage = `⚠️ DANGER: Image is used in ${usageData.usage_count} location(s):\n\n` +
                    usageData.usage.map(usage => `• ${usage.type}: ${usage.post_title}`).join('\n') +
                    '\n\nRenaming will BREAK these references!\n\nAre you absolutely sure you want to continue?';
                
                proceed = confirm(warningMessage);
            }
            
            if (proceed) {
                showUniversalStatus(statusEl, 'Renaming file...', 'info');
                
                // Proceed with rename
                const renameResponse = await fetch(window.picPilotAttachment.ajax_url, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: new URLSearchParams({
                        action: 'picpilot_rename_filename',
                        nonce: window.picPilotAttachment.nonce,
                        attachment_id: attachmentId,
                        new_filename: newFilename.trim(),
                        force_rename: !usageData.is_safe_to_rename ? 'true' : 'false'
                    })
                });
                
                if (!renameResponse.ok) {
                    throw new Error(`HTTP ${renameResponse.status}: ${renameResponse.statusText}`);
                }
                
                const renameResult = await renameResponse.json();
                
                if (renameResult.success) {
                    showUniversalStatus(statusEl, `✅ File renamed successfully to: <strong>${renameResult.data.new_filename}</strong>`, 'success');
                    
                    // Show additional info after 2 seconds
                    setTimeout(() => {
                        showUniversalStatus(statusEl, `✅ File renamed to: <strong>${renameResult.data.new_filename}</strong><br><small style="color: #d97706;">⚠️ The plugin does not automatically replace image references in your content. Please manually update any posts, pages, or widgets that reference this image.</small>`, 'success');
                    }, 2000);
                } else {
                    const errorMessage = renameResult.data?.message || renameResult.data || 'Unknown error';
                    showUniversalStatus(statusEl, `⚠ Rename failed: ${errorMessage}`, 'error');
                }
            } else {
                showUniversalStatus(statusEl, `Rename cancelled by user`, 'info');
            }
            
        } catch (error) {
            showUniversalStatus(statusEl, `⚠ Error: ${error.message}`, 'error');
        }
    }
    
    function updateWordPressField(type, value, attachmentId) {
        if (type === 'alt') {
            const altSelectors = [
                `#attachment_${attachmentId}_alt`,
                'input[name*="[image_alt]"]',
                '#attachment-details-alt-text',
                'input[data-setting="alt"]',
                '.setting[data-setting="alt"] input',
                'input.attachment-alt'
            ];

            for (const selector of altSelectors) {
                const field = document.querySelector(selector);
                if (field) {
                    field.value = value;
                    field.dispatchEvent(new Event('change', { bubbles: true }));
                    break;
                }
            }
            
        } else if (type === 'title') {
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
                const field = document.querySelector(selector);
                if (field) {
                    field.value = value;
                    field.dispatchEvent(new Event('change', { bubbles: true }));
                    break;
                }
            }
        } else if (type === 'both' && typeof value === 'object') {
            // Handle "Generate Both" response with both title and alt text
            if (value.title) {
                updateWordPressField('title', value.title, attachmentId);
            }
            if (value.alt) {
                updateWordPressField('alt', value.alt, attachmentId);
            }
        }
    }
    
    function showUniversalStatus(element, message, type) {
        element.innerHTML = message;
        element.className = `pic-pilot-status-${type}`;
        element.style.display = 'block';
        
        // Apply styles based on type
        if (type === 'success') {
            element.style.background = '#d4edda';
            element.style.color = '#155724';
            element.style.border = '1px solid #c3e6cb';
        } else if (type === 'error') {
            element.style.background = '#f8d7da';
            element.style.color = '#721c24';
            element.style.border = '1px solid #f5c6cb';
        } else if (type === 'info') {
            element.style.background = '#cce7ff';
            element.style.color = '#055160';
            element.style.border = '1px solid #b3d7ff';
        }
        
        // Auto-hide success messages after 5 seconds
        if (type === 'success') {
            setTimeout(() => {
                element.style.display = 'none';
            }, 5000);
        }
    }

    // Global cleanup
    window.addEventListener('beforeunload', () => {
        if (observer) {
            observer.disconnect();
        }
    });


})();