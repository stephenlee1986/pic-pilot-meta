document.addEventListener('DOMContentLoaded', function() {
    // Check if we need to show the bulk modal
    if (window.picPilotStudio && window.picPilotStudio.bulk && window.picPilotStudio.bulk.key) {
        showBulkModal(window.picPilotStudio.bulk.key, window.picPilotStudio.bulk.count);
    }

    // Add "Generate for Missing Alt Text" button to the page
    addMissingAltButton();
});

function showBulkModal(bulkKey, count) {
    if (document.getElementById('picpilot-bulk-modal')) return;

    const modal = document.createElement('div');
    modal.id = 'picpilot-bulk-modal';
    modal.style.cssText = `
        position: fixed; top: 0; left: 0; width: 100%; height: 100%;
        background: rgba(0,0,0,0.7); z-index: 10000; display: flex;
        align-items: center; justify-content: center;
    `;

    const modalContent = document.createElement('div');
    modalContent.style.cssText = `
        background: #fff; padding: 20px; border-radius: 8px; max-width: 500px;
        width: 90%; box-shadow: 0 4px 20px rgba(0,0,0,0.3);
    `;

    modalContent.innerHTML = `
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
            <h2 style="margin: 0; font-size: 18px;">Bulk AI Metadata Generation</h2>
            <button id="picpilot-bulk-close" style="background: none; border: none; font-size: 20px; cursor: pointer; color: #666;" title="Close">✕</button>
        </div>
        
        <p style="margin-bottom: 20px; color: #666;">
            Generate AI metadata for ${count} selected images. Choose which types of metadata to generate:
        </p>

        <div style="margin-bottom: 20px;">
            <label style="display: block; margin-bottom: 8px;">
                <input type="checkbox" id="bulk-generate-title" checked style="margin-right: 8px;">
                <strong>Title</strong> - Generate descriptive titles for images
            </label>
            <label style="display: block; margin-bottom: 8px;">
                <input type="checkbox" id="bulk-generate-alt" checked style="margin-right: 8px;">
                <strong>Alt Text</strong> - Generate accessibility descriptions
            </label>
            <p style="margin: 10px 0; padding: 10px; background: #fff3cd; border: 1px solid #ffeaa7; border-radius: 4px; font-size: 13px;">
                <strong>Note:</strong> Filename generation is not available in bulk operations to prevent disconnecting images from their references.
            </p>
        </div>

        <div style="display: flex; gap: 10px; justify-content: flex-end;">
            <button id="picpilot-bulk-cancel" style="padding: 10px 20px; border: 1px solid #ddd; background: #fff; border-radius: 4px; cursor: pointer;">
                Cancel
            </button>
            <button id="picpilot-bulk-start" style="padding: 10px 20px; background: #0073aa; color: #fff; border: none; border-radius: 4px; cursor: pointer;">
                🤖 Start Generation
            </button>
        </div>

        <div id="picpilot-bulk-progress" style="display: none; margin-top: 20px;">
            <div style="background: #f0f0f0; border-radius: 10px; overflow: hidden; margin-bottom: 10px;">
                <div id="picpilot-bulk-progress-bar" style="height: 20px; background: #0073aa; width: 0%; transition: width 0.3s ease;"></div>
            </div>
            <div id="picpilot-bulk-status" style="font-size: 14px; color: #666; text-align: center;">Preparing...</div>
            <div id="picpilot-bulk-details" style="font-size: 12px; color: #999; margin-top: 10px; max-height: 200px; overflow-y: auto;"></div>
        </div>
    `;

    modal.appendChild(modalContent);
    document.body.appendChild(modal);

    // Event listeners
    document.getElementById('picpilot-bulk-close').onclick = closeBulkModal;
    document.getElementById('picpilot-bulk-cancel').onclick = closeBulkModal;
    document.getElementById('picpilot-bulk-start').onclick = () => startBulkGeneration(bulkKey);
}

function closeBulkModal() {
    const modal = document.getElementById('picpilot-bulk-modal');
    if (modal) {
        modal.remove();
        // Clean up URL parameters
        const url = new URL(window.location);
        url.searchParams.delete('picpilot_bulk_action');
        url.searchParams.delete('picpilot_bulk_key');
        url.searchParams.delete('picpilot_bulk_count');
        window.history.replaceState({}, '', url);
    }
}

async function startBulkGeneration(bulkKey) {
    const titleEnabled = document.getElementById('bulk-generate-title').checked;
    const altEnabled = document.getElementById('bulk-generate-alt').checked;

    if (!titleEnabled && !altEnabled) {
        alert('Please select at least one type of metadata to generate.');
        return;
    }

    // Show progress section
    document.getElementById('picpilot-bulk-progress').style.display = 'block';
    document.getElementById('picpilot-bulk-start').disabled = true;
    document.getElementById('picpilot-bulk-cancel').disabled = true;

    try {
        // Get the image IDs from the server
        const response = await fetch(window.picPilotStudio.ajax_url, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams({
                action: 'picpilot_bulk_process',
                nonce: window.picPilotStudio.nonce,
                bulk_key: bulkKey,
                generate_title: titleEnabled ? '1' : '0',
                generate_alt: altEnabled ? '1' : '0'
            })
        });

        const result = await response.json();
        
        if (!result.success) {
            throw new Error(result.data?.message || 'Failed to start bulk processing');
        }

        // Process images one by one
        await processBulkImages(result.data.image_ids, titleEnabled, altEnabled);
        
    } catch (error) {
        console.error('Bulk generation error:', error);
        document.getElementById('picpilot-bulk-status').textContent = 'Error: ' + error.message;
        document.getElementById('picpilot-bulk-status').style.color = '#dc3232';
        document.getElementById('picpilot-bulk-start').disabled = false;
        document.getElementById('picpilot-bulk-cancel').disabled = false;
    }
}

async function processBulkImages(imageIds, generateTitle, generateAlt) {
    const total = imageIds.length;
    let completed = 0;
    let errors = 0;
    const statusEl = document.getElementById('picpilot-bulk-status');
    const progressBar = document.getElementById('picpilot-bulk-progress-bar');
    const detailsEl = document.getElementById('picpilot-bulk-details');

    for (const imageId of imageIds) {
        try {
            statusEl.textContent = `Processing image ${completed + 1} of ${total}...`;
            statusEl.style.color = '#666';

            // Process title if enabled
            if (generateTitle) {
                await processImageMetadata(imageId, 'title');
                detailsEl.innerHTML += `<div style="color: #46b450;">✓ Generated title for image ID ${imageId}</div>`;
            }

            // Process alt text if enabled
            if (generateAlt) {
                await processImageMetadata(imageId, 'alt');
                detailsEl.innerHTML += `<div style="color: #46b450;">✓ Generated alt text for image ID ${imageId}</div>`;
            }

            completed++;
            const progress = (completed / total) * 100;
            progressBar.style.width = progress + '%';

        } catch (error) {
            errors++;
            console.error(`Error processing image ${imageId}:`, error);
            detailsEl.innerHTML += `<div style="color: #dc3232;">✗ Failed to process image ID ${imageId}: ${error.message}</div>`;
        }

        // Scroll details to bottom
        detailsEl.scrollTop = detailsEl.scrollHeight;
    }

    // Final status
    if (errors === 0) {
        statusEl.textContent = `✅ Successfully processed all ${total} images!`;
        statusEl.style.color = '#46b450';
        
        // Auto-close and redirect after success
        setTimeout(() => {
            closeBulkModal();
            // Redirect with success message
            const url = new URL(window.location);
            url.searchParams.set('picpilot_bulk_success', total);
            window.location.href = url.toString();
        }, 2000);
    } else {
        statusEl.textContent = `⚠ Completed with ${errors} errors. ${completed} of ${total} images processed successfully.`;
        statusEl.style.color = '#f56e28';
        document.getElementById('picpilot-bulk-cancel').disabled = false;
        document.getElementById('picpilot-bulk-cancel').textContent = 'Close';
    }
}

async function processImageMetadata(imageId, type) {
    const response = await fetch(window.picPilotStudio.ajax_url, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({
            action: 'picpilot_generate_metadata',
            nonce: window.picPilotStudio.nonce,
            attachment_id: imageId,
            type: type,
            keywords: ''
        })
    });

    const result = await response.json();
    
    if (!result.success) {
        throw new Error(result.data?.message || result.data || `Failed to generate ${type}`);
    }

    return result.data;
}

function addMissingAltButton() {
    // Add buttons next to the alt text filter
    const altFilter = document.getElementById('picpilot-alt-filter');
    if (!altFilter) return;

    // Missing Alt Text button
    const altButton = document.createElement('button');
    altButton.type = 'button';
    altButton.className = 'button button-secondary';
    altButton.textContent = '🤖 Generate for Missing Alt Text';
    altButton.style.marginLeft = '10px';
    altButton.onclick = generateForMissingAlt;

    // Missing Title button
    const titleButton = document.createElement('button');
    titleButton.type = 'button';
    titleButton.className = 'button button-secondary';
    titleButton.textContent = '🤖 Generate for Missing Titles';
    titleButton.style.marginLeft = '10px';
    titleButton.onclick = generateForMissingTitles;

    altFilter.parentNode.insertBefore(altButton, altFilter.nextSibling);
    altFilter.parentNode.insertBefore(titleButton, altButton.nextSibling);
}

async function generateForMissingAlt() {
    try {
        // Get images without alt text
        const response = await fetch(window.picPilotStudio.ajax_url, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams({
                action: 'picpilot_get_images_without_alt',
                nonce: window.picPilotStudio.nonce
            })
        });

        const result = await response.json();
        
        if (!result.success) {
            throw new Error(result.data?.message || 'Failed to get images without alt text');
        }

        const imageIds = result.data.image_ids;
        
        if (imageIds.length === 0) {
            alert('No images found without alt text!');
            return;
        }

        // Create a temporary bulk key for these images
        const tempKey = 'missing_alt_' + Date.now();
        
        // Show bulk modal with these specific images
        showBulkModalForMissingAlt(imageIds, tempKey);
        
    } catch (error) {
        console.error('Error getting images without alt text:', error);
        alert('Error: ' + error.message);
    }
}

function showBulkModalForMissingAlt(imageIds, tempKey) {
    if (document.getElementById('picpilot-bulk-modal')) return;

    const modal = document.createElement('div');
    modal.id = 'picpilot-bulk-modal';
    modal.style.cssText = `
        position: fixed; top: 0; left: 0; width: 100%; height: 100%;
        background: rgba(0,0,0,0.7); z-index: 10000; display: flex;
        align-items: center; justify-content: center;
    `;

    const modalContent = document.createElement('div');
    modalContent.style.cssText = `
        background: #fff; padding: 20px; border-radius: 8px; max-width: 500px;
        width: 90%; box-shadow: 0 4px 20px rgba(0,0,0,0.3);
    `;

    modalContent.innerHTML = `
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
            <h2 style="margin: 0; font-size: 18px;">Generate Alt Text for Missing Images</h2>
            <button id="picpilot-bulk-close" style="background: none; border: none; font-size: 20px; cursor: pointer; color: #666;" title="Close">✕</button>
        </div>
        
        <p style="margin-bottom: 20px; color: #666;">
            Found ${imageIds.length} images without alt text. Generate AI descriptions for accessibility.
        </p>

        <div style="display: flex; gap: 10px; justify-content: flex-end;">
            <button id="picpilot-bulk-cancel" style="padding: 10px 20px; border: 1px solid #ddd; background: #fff; border-radius: 4px; cursor: pointer;">
                Cancel
            </button>
            <button id="picpilot-bulk-start" style="padding: 10px 20px; background: #0073aa; color: #fff; border: none; border-radius: 4px; cursor: pointer;">
                🤖 Generate Alt Text
            </button>
        </div>

        <div id="picpilot-bulk-progress" style="display: none; margin-top: 20px;">
            <div style="background: #f0f0f0; border-radius: 10px; overflow: hidden; margin-bottom: 10px;">
                <div id="picpilot-bulk-progress-bar" style="height: 20px; background: #0073aa; width: 0%; transition: width 0.3s ease;"></div>
            </div>
            <div id="picpilot-bulk-status" style="font-size: 14px; color: #666; text-align: center;">Preparing...</div>
            <div id="picpilot-bulk-details" style="font-size: 12px; color: #999; margin-top: 10px; max-height: 200px; overflow-y: auto;"></div>
        </div>
    `;

    modal.appendChild(modalContent);
    document.body.appendChild(modal);

    // Event listeners
    document.getElementById('picpilot-bulk-close').onclick = closeBulkModal;
    document.getElementById('picpilot-bulk-cancel').onclick = closeBulkModal;
    document.getElementById('picpilot-bulk-start').onclick = () => {
        startMissingAltGeneration(imageIds);
    };
}

async function startMissingAltGeneration(imageIds) {
    // Show progress section
    document.getElementById('picpilot-bulk-progress').style.display = 'block';
    document.getElementById('picpilot-bulk-start').disabled = true;
    document.getElementById('picpilot-bulk-cancel').disabled = true;

    try {
        // Process images for alt text only
        await processBulkImages(imageIds, false, true);
    } catch (error) {
        console.error('Missing alt generation error:', error);
        document.getElementById('picpilot-bulk-status').textContent = 'Error: ' + error.message;
        document.getElementById('picpilot-bulk-status').style.color = '#dc3232';
        document.getElementById('picpilot-bulk-start').disabled = false;
        document.getElementById('picpilot-bulk-cancel').disabled = false;
    }
}

async function generateForMissingTitles() {
    try {
        // Get images with default/missing titles
        const response = await fetch(window.picPilotStudio.ajax_url, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams({
                action: 'picpilot_get_images_without_titles',
                nonce: window.picPilotStudio.nonce
            })
        });

        const result = await response.json();
        
        if (!result.success) {
            throw new Error(result.data?.message || 'Failed to get images without titles');
        }

        const imageIds = result.data.image_ids;
        
        if (imageIds.length === 0) {
            alert('No images found with missing or default titles!');
            return;
        }

        // Create a temporary bulk key for these images
        const tempKey = 'missing_titles_' + Date.now();
        
        // Show bulk modal with these specific images
        showBulkModalForMissingTitles(imageIds, tempKey);
        
    } catch (error) {
        console.error('Error getting images without titles:', error);
        alert('Error: ' + error.message);
    }
}

function showBulkModalForMissingTitles(imageIds, tempKey) {
    if (document.getElementById('picpilot-bulk-modal')) return;

    const modal = document.createElement('div');
    modal.id = 'picpilot-bulk-modal';
    modal.style.cssText = `
        position: fixed; top: 0; left: 0; width: 100%; height: 100%;
        background: rgba(0,0,0,0.7); z-index: 10000; display: flex;
        align-items: center; justify-content: center;
    `;

    const modalContent = document.createElement('div');
    modalContent.style.cssText = `
        background: #fff; padding: 20px; border-radius: 8px; max-width: 500px;
        width: 90%; box-shadow: 0 4px 20px rgba(0,0,0,0.3);
    `;

    modalContent.innerHTML = `
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
            <h2 style="margin: 0; font-size: 18px;">Generate Titles for Images</h2>
            <button id="picpilot-bulk-close" style="background: none; border: none; font-size: 20px; cursor: pointer; color: #666;" title="Close">✕</button>
        </div>
        
        <p style="margin-bottom: 20px; color: #666;">
            Found ${imageIds.length} images with missing or default titles. Generate descriptive titles for better SEO.
        </p>

        <div style="display: flex; gap: 10px; justify-content: flex-end;">
            <button id="picpilot-bulk-cancel" style="padding: 10px 20px; border: 1px solid #ddd; background: #fff; border-radius: 4px; cursor: pointer;">
                Cancel
            </button>
            <button id="picpilot-bulk-start" style="padding: 10px 20px; background: #0073aa; color: #fff; border: none; border-radius: 4px; cursor: pointer;">
                🤖 Generate Titles
            </button>
        </div>

        <div id="picpilot-bulk-progress" style="display: none; margin-top: 20px;">
            <div style="background: #f0f0f0; border-radius: 10px; overflow: hidden; margin-bottom: 10px;">
                <div id="picpilot-bulk-progress-bar" style="height: 20px; background: #0073aa; width: 0%; transition: width 0.3s ease;"></div>
            </div>
            <div id="picpilot-bulk-status" style="font-size: 14px; color: #666; text-align: center;">Preparing...</div>
            <div id="picpilot-bulk-details" style="font-size: 12px; color: #999; margin-top: 10px; max-height: 200px; overflow-y: auto;"></div>
        </div>
    `;

    modal.appendChild(modalContent);
    document.body.appendChild(modal);

    // Event listeners
    document.getElementById('picpilot-bulk-close').onclick = closeBulkModal;
    document.getElementById('picpilot-bulk-cancel').onclick = closeBulkModal;
    document.getElementById('picpilot-bulk-start').onclick = () => {
        startMissingTitlesGeneration(imageIds);
    };
}

async function startMissingTitlesGeneration(imageIds) {
    // Show progress section
    document.getElementById('picpilot-bulk-progress').style.display = 'block';
    document.getElementById('picpilot-bulk-start').disabled = true;
    document.getElementById('picpilot-bulk-cancel').disabled = true;

    try {
        // Process images for titles only
        await processBulkImages(imageIds, true, false);
    } catch (error) {
        console.error('Missing titles generation error:', error);
        document.getElementById('picpilot-bulk-status').textContent = 'Error: ' + error.message;
        document.getElementById('picpilot-bulk-status').style.color = '#dc3232';
        document.getElementById('picpilot-bulk-start').disabled = false;
        document.getElementById('picpilot-bulk-cancel').disabled = false;
    }
}