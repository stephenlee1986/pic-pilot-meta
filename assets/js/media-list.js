document.addEventListener('click', function (e) {
    const btn = e.target.closest('.picpilot-generate-meta');
    if (!btn || !window.PicPilotStudio) return;

    const id = btn.getAttribute('data-id');
    const type = btn.getAttribute('data-type');
    const keywords = btn.closest('tr').querySelector('.picpilot-keywords')?.value || '';

    btn.textContent = 'Generating...';
    btn.disabled = true;

    // Debug log
    console.log('Sending metadata request:', {
        id, type, keywords,
        keywordsElement: btn.closest('tr').querySelector('.picpilot-keywords')
    });

    fetch(window.PicPilotStudio.ajax_url, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({
            action: 'picpilot_generate_metadata',
            nonce: window.PicPilotStudio.nonce,
            attachment_id: id,
            type: type,
            keywords: keywords
        })
    })
        .then(response => {
            console.log('PicPilot: Raw response status:', response.status);
            if (!response.ok) {
                throw new Error(`HTTP ${response.status}: ${response.statusText}`);
            }
            return response.text().then(text => {
                console.log('PicPilot: Raw response text:', text);
                try {
                    return JSON.parse(text);
                } catch (e) {
                    console.error('PicPilot: JSON Parse Error. Response text:', text);
                    throw new Error('Invalid JSON response from server');
                }
            });
        })
        .then(result => {
            console.log('PicPilot: Parsed response:', result);
            if (result.success) {
                // Check if we have valid result data
                const resultData = result.data && result.data.result ? result.data.result : null;
                
                if (!resultData) {
                    console.error('PicPilot: No result data found in response:', result.data);
                    showToast('⚠ Invalid response from server: No result data', true);
                    return;
                }
                
                let toastMessage = (type === 'alt' ? 'Alt' : 'Title') + ' generated ✔: ' + resultData;
                
                // Show fallback message if used
                if (result.data.used_fallback && result.data.fallback_message) {
                    toastMessage = result.data.fallback_message;
                }
                
                showToast(toastMessage);
                
                // Update button text (now it exists, so should show "Regenerate")
                if (type === 'alt') {
                    btn.textContent = 'Regenerate Alt Text';
                } else if (type === 'title') {
                    btn.textContent = 'Regenerate Title';
                }
            } else {
                const errorMessage = typeof result.data === 'object' && result.data.message ? result.data.message : (typeof result.data === 'string' ? result.data : 'Unknown error');
                showToast('⚠ Failed: ' + errorMessage, true);
                // Keep original button text on failure
                btn.textContent = btn.textContent.replace('Generating...', 
                    type === 'alt' ? 
                        (btn.textContent.includes('Regenerate') ? 'Regenerate Alt Text' : 'Generate Alt Text') : 
                        (btn.textContent.includes('Regenerate') ? 'Regenerate Title' : 'Generate Title')
                );
            }
        })
        .catch(err => {
            showToast('AJAX error: ' + err, true);
            // Restore original button text on error
            btn.textContent = type === 'alt' ? 
                (btn.textContent.includes('Regenerate') ? 'Regenerate Alt Text' : 'Generate Alt Text') : 
                (btn.textContent.includes('Regenerate') ? 'Regenerate Title' : 'Generate Title');
        })
        .finally(() => {
            btn.disabled = false;
        });
});



// Function to add the "Generate Metadata" button to media row actions
function showToast(message, isError = false) {
    const toast = document.createElement('div');
    toast.textContent = message;

    styleToast(toast);
    if (isError) {
        toast.style.background = '#dc3232'; // WordPress error red
    }

    document.body.appendChild(toast);

    requestAnimationFrame(() => {
        toast.style.opacity = '1';
    });

    setTimeout(() => {
        toast.style.opacity = '0';
        setTimeout(() => toast.remove(), 300);
    }, 4000);
}

function styleToast(toast) {
    toast.style.cssText = `
        position: fixed;
        bottom: 20px;
        right: 20px;
        background: #0073aa;
        color: #fff;
        padding: 10px 15px;
        border-radius: 6px;
        z-index: 9999;
        box-shadow: 0 4px 12px rgba(0,0,0,0.3);
        font-size: 14px;
        transition: opacity 0.3s ease;
        opacity: 0;
        pointer-events: none;
    `;
}

// Handle "Generate Both" button clicks
document.addEventListener('click', function (e) {
    const btn = e.target.closest('.picpilot-generate-both');
    if (!btn || !window.PicPilotStudio) return;

    const id = btn.getAttribute('data-id');
    const keywords = btn.closest('.picpilot-column-wrapper').querySelector('.picpilot-keywords')?.value || '';

    const originalText = btn.textContent;
    btn.textContent = 'Generating Both...';
    btn.disabled = true;

    console.log('Sending generate both request:', { id, keywords });

    fetch(window.PicPilotStudio.ajax_url, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({
            action: 'picpilot_generate_both',
            nonce: window.PicPilotStudio.nonce,
            attachment_id: id,
            keywords: keywords
        })
    })
        .then(response => response.json())
        .then(result => {
            if (result.success) {
                showToast('✔ Both alt text and title generated successfully!');
                // Refresh the page to update the UI
                setTimeout(() => {
                    window.location.reload();
                }, 1500);
            } else {
                showToast('⚠ Failed: ' + (result.data?.message || result.data), true);
                btn.textContent = originalText;
            }
        })
        .catch(err => {
            showToast('AJAX error: ' + err, true);
            btn.textContent = originalText;
        })
        .finally(() => {
            btn.disabled = false;
        });
});

// Handle "Rename Filename" button clicks
document.addEventListener('click', function (e) {
    const btn = e.target.closest('.picpilot-rename-filename');
    if (!btn || !window.PicPilotStudio) return;

    const id = btn.getAttribute('data-id');
    
    // Show rename options modal
    showRenameModal(id, btn);
});

// Show rename modal with manual and AI options
function showRenameModal(attachmentId, btn) {
    // Create modal overlay
    const overlay = document.createElement('div');
    overlay.style.cssText = `
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0,0,0,0.5);
        z-index: 10000;
        display: flex;
        align-items: center;
        justify-content: center;
    `;
    
    // Create modal content
    const modal = document.createElement('div');
    modal.style.cssText = `
        background: #fff;
        padding: 20px;
        border-radius: 8px;
        max-width: 500px;
        width: 90%;
        box-shadow: 0 4px 20px rgba(0,0,0,0.3);
    `;
    
    // Get keywords field for AI generation
    const keywordsElement = btn.closest('.picpilot-column-wrapper')?.querySelector('.picpilot-keywords') || 
                           btn.closest('tr')?.querySelector('.picpilot-keywords');
    const keywords = keywordsElement?.value || '';
    
    modal.innerHTML = `
        <h3 style="margin-top: 0; color: #d63638;">⚠️ Rename Filename</h3>
        <div style="background: #fef7f0; border: 1px solid #d63638; padding: 10px; border-radius: 4px; margin-bottom: 15px;">
            <strong>WARNING:</strong> Renaming may break existing references!
        </div>
        
        <div style="margin-bottom: 15px;">
            <label style="display: block; margin-bottom: 5px; font-weight: 600;">Keywords for AI (optional):</label>
            <input type="text" id="rename-keywords" value="${keywords}" placeholder="e.g., business manager, construction site" 
                   style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;" />
        </div>
        
        <div style="margin-bottom: 15px;">
            <label style="display: block; margin-bottom: 5px; font-weight: 600;">Manual filename (without extension):</label>
            <input type="text" id="manual-filename" placeholder="Enter new filename" 
                   style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;" />
        </div>
        
        <div style="display: flex; gap: 10px; justify-content: flex-end; margin-top: 20px;">
            <button type="button" id="cancel-rename" class="button">Cancel</button>
            <button type="button" id="ai-rename" class="button button-secondary">🤖 Generate with AI</button>
            <button type="button" id="manual-rename" class="button button-primary">✏️ Rename Manually</button>
        </div>
    `;
    
    overlay.appendChild(modal);
    document.body.appendChild(overlay);
    
    // Focus on manual input
    setTimeout(() => {
        document.getElementById('manual-filename').focus();
    }, 100);
    
    // Handle modal actions
    document.getElementById('cancel-rename').addEventListener('click', () => {
        overlay.remove();
    });
    
    document.getElementById('ai-rename').addEventListener('click', () => {
        const keywordsValue = document.getElementById('rename-keywords').value.trim();
        overlay.remove();
        performAIRename(attachmentId, keywordsValue, btn);
    });
    
    document.getElementById('manual-rename').addEventListener('click', () => {
        const manualFilename = document.getElementById('manual-filename').value.trim();
        if (!manualFilename) {
            showToast('Please enter a filename', true);
            return;
        }
        overlay.remove();
        performManualRename(attachmentId, manualFilename, btn);
    });
    
    // Close on overlay click
    overlay.addEventListener('click', (e) => {
        if (e.target === overlay) {
            overlay.remove();
        }
    });
    
    // Handle Enter key for manual input
    document.getElementById('manual-filename').addEventListener('keydown', (e) => {
        if (e.key === 'Enter') {
            document.getElementById('manual-rename').click();
        }
    });
}

// Perform AI-generated rename
function performAIRename(attachmentId, keywords, btn) {
    const originalText = btn.textContent;
    btn.textContent = 'Generating...';
    btn.disabled = true;
    
    // Generate filename with AI first
    fetch(window.PicPilotStudio.ajax_url, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({
            action: 'picpilot_generate_ai_filename',
            nonce: window.PicPilotStudio.nonce,
            attachment_id: attachmentId,
            keywords: keywords
        })
    })
        .then(response => response.json())
        .then(result => {
            if (result.success) {
                const generatedFilename = result.data.filename;
                showToast(`AI Generated: ${generatedFilename}`);
                // Now proceed with the rename using the generated filename
                performRename(attachmentId, generatedFilename, btn, originalText);
            } else {
                showToast('⚠ AI filename generation failed: ' + (result.data?.message || result.data), true);
                btn.textContent = originalText;
                btn.disabled = false;
            }
        })
        .catch(err => {
            showToast('AI generation error: ' + err, true);
            btn.textContent = originalText;
            btn.disabled = false;
        });
}

// Perform manual rename
function performManualRename(attachmentId, filename, btn) {
    const originalText = btn.textContent;
    btn.textContent = 'Renaming...';
    btn.disabled = true;
    
    performRename(attachmentId, filename, btn, originalText);
}

// Common rename function with usage check
function performRename(attachmentId, newFilename, btn, originalText) {
    // First check usage
    fetch(window.PicPilotStudio.ajax_url, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({
            action: 'picpilot_check_image_usage',
            nonce: window.PicPilotStudio.nonce,
            attachment_id: attachmentId
        })
    })
        .then(response => {
            // Log response for debugging
            console.log('Usage check response status:', response.status);
            return response.text().then(text => {
                console.log('Usage check raw response:', text);
                try {
                    return JSON.parse(text);
                } catch (e) {
                    console.error('Failed to parse JSON:', text);
                    throw new Error('Invalid JSON response: ' + text.substring(0, 100));
                }
            });
        })
        .then(result => {
            console.log('Usage check parsed result:', result);
            
            if (result.success) {
                const usageData = result.data;
                let proceed = true;
                
                if (!usageData.is_safe_to_rename) {
                    const warningMessage = `⚠️ DANGER: Image is used in ${usageData.usage_count} location(s):\n\n` +
                        usageData.usage.map(usage => `• ${usage.type}: ${usage.post_title}`).join('\n') +
                        '\n\nRenaming will BREAK these references!\n\nAre you absolutely sure you want to continue?';
                    
                    proceed = confirm(warningMessage);
                }
                
                if (proceed) {
                    // Proceed with rename
                    fetch(window.PicPilotStudio.ajax_url, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: new URLSearchParams({
                            action: 'picpilot_rename_filename',
                            nonce: window.PicPilotStudio.nonce,
                            attachment_id: attachmentId,
                            new_filename: newFilename.trim(),
                            force_rename: !usageData.is_safe_to_rename ? 'true' : 'false'
                        })
                    })
                        .then(response => response.json())
                        .then(renameResult => {
                            if (renameResult.success) {
                                showToast(`✔ Filename renamed to: ${renameResult.data.new_filename}. Note: Please manually update any content that references this image.`);
                                // Refresh the page to update the UI
                                setTimeout(() => {
                                    window.location.reload();
                                }, 2000);
                            } else {
                                showToast('⚠ Rename failed: ' + (renameResult.data?.message || renameResult.data), true);
                                btn.textContent = originalText;
                                btn.disabled = false;
                            }
                        })
                        .catch(err => {
                            showToast('Rename AJAX error: ' + err, true);
                            btn.textContent = originalText;
                            btn.disabled = false;
                        });
                } else {
                    btn.textContent = originalText;
                    btn.disabled = false;
                }
            } else {
                const errorMessage = result.data?.message || result.data || 'Unknown error';
                console.error('Usage check failed:', result);
                showToast('⚠ Usage check failed: ' + errorMessage, true);
                btn.textContent = originalText;
                btn.disabled = false;
            }
        })
        .catch(err => {
            showToast('Usage check AJAX error: ' + err, true);
            btn.textContent = originalText;
            btn.disabled = false;
        });
}
