document.addEventListener('DOMContentLoaded', function() {
    // Get elements
    const titleBtn = document.getElementById('picpilot-generate-title-edit');
    const altBtn = document.getElementById('picpilot-generate-alt-edit');
    const keywordsInput = document.getElementById('picpilot-edit-keywords');
    const statusDiv = document.getElementById('picpilot-edit-status');

    if (!titleBtn || !altBtn || !keywordsInput || !statusDiv) {
        return; // Elements not found, exit
    }

    // Title generation
    titleBtn.addEventListener('click', function() {
        const keywords = keywordsInput.value.trim();
        generateMetadata('title', keywords, titleBtn);
    });

    // Alt text generation
    altBtn.addEventListener('click', function() {
        const keywords = keywordsInput.value.trim();
        generateMetadata('alt', keywords, altBtn);
    });

    async function generateMetadata(type, keywords, button) {
        const originalText = button.textContent;
        
        // Update UI
        button.disabled = true;
        button.textContent = 'Generating...';
        showStatus('Generating ' + (type === 'alt' ? 'alt text' : 'title') + '...', 'info');

        try {
            const response = await fetch(PicPilotStudioEdit.ajax_url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: new URLSearchParams({
                    action: 'picpilot_generate_metadata',
                    nonce: PicPilotStudioEdit.nonce,
                    attachment_id: PicPilotStudioEdit.attachment_id,
                    type: type,
                    keywords: keywords
                })
            });

            const result = await response.json();

            if (result.success) {
                // Success - update the form field
                if (type === 'title') {
                    const titleField = document.getElementById('title');
                    if (titleField) {
                        titleField.value = result.data.result;
                        titleField.focus();
                    }
                } else if (type === 'alt') {
                    const altField = document.querySelector('input[name="_wp_attachment_image_alt"], textarea[name="_wp_attachment_image_alt"]');
                    if (altField) {
                        altField.value = result.data.result;
                        altField.focus();
                        
                        // Update button text to "Regenerate" since alt text now exists
                        button.innerHTML = '<span class="dashicons dashicons-universal-access-alt" style="margin-right: 5px;"></span>Regenerate Alt Text';
                    }
                }

                // Show success message
                let successMessage = (type === 'alt' ? 'Alt text' : 'Title') + ' generated successfully!';
                
                // Show fallback message if used
                if (result.data.used_fallback && result.data.fallback_message) {
                    successMessage = result.data.fallback_message;
                }
                
                showStatus(successMessage, 'success');

            } else {
                // Error
                const errorMessage = result.data?.message || result.data || 'Generation failed';
                showStatus('Error: ' + errorMessage, 'error');
            }

        } catch (error) {
            console.error('Generation error:', error);
            showStatus('Network error occurred. Please try again.', 'error');
        } finally {
            // Restore button
            button.disabled = false;
            button.textContent = originalText;
        }
    }

    function showStatus(message, type) {
        statusDiv.style.display = 'block';
        statusDiv.textContent = message;
        
        // Reset classes
        statusDiv.className = '';
        
        // Add appropriate class
        if (type === 'success') {
            statusDiv.style.backgroundColor = '#d4edda';
            statusDiv.style.color = '#155724';
            statusDiv.style.border = '1px solid #c3e6cb';
        } else if (type === 'error') {
            statusDiv.style.backgroundColor = '#f8d7da';
            statusDiv.style.color = '#721c24';
            statusDiv.style.border = '1px solid #f5c6cb';
        } else {
            statusDiv.style.backgroundColor = '#d1ecf1';
            statusDiv.style.color = '#0c5460';
            statusDiv.style.border = '1px solid #bee5eb';
        }

        // Auto-hide success messages after 4 seconds
        if (type === 'success') {
            setTimeout(() => {
                statusDiv.style.display = 'none';
            }, 4000);
        }
    }
});