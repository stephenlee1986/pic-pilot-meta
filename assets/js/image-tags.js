/**
 * Image Tags Management
 * Handles adding/removing tags and tag interactions in the media library
 */

(function() {
    'use strict';

    let tagModalOverlay = null;
    let availableTags = [];

    // Initialize when DOM is ready
    document.addEventListener('DOMContentLoaded', function() {
        initializeTagsFeature();
        loadAvailableTags();
    });

    function initializeTagsFeature() {
        // Handle add tag button clicks
        document.addEventListener('click', function(e) {
            if (e.target.classList.contains('picpilot-add-tag-btn')) {
                e.preventDefault();
                const attachmentId = e.target.getAttribute('data-id');
                showTagModal(attachmentId);
            }
        });

        // Handle remove tag clicks
        document.addEventListener('click', function(e) {
            if (e.target.classList.contains('picpilot-remove-tag')) {
                e.preventDefault();
                e.stopPropagation();
                const tagSpan = e.target.closest('.picpilot-tag');
                const tagName = tagSpan.getAttribute('data-tag');
                const attachmentId = tagSpan.getAttribute('data-id');
                removeTag(attachmentId, tagName, tagSpan);
            }
        });
    }

    function showTagModal(attachmentId) {
        if (tagModalOverlay) {
            tagModalOverlay.remove();
        }

        tagModalOverlay = document.createElement('div');
        tagModalOverlay.className = 'picpilot-tag-modal-overlay';
        tagModalOverlay.style.cssText = `
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 9999;
            display: flex;
            align-items: center;
            justify-content: center;
        `;

        const modal = document.createElement('div');
        modal.className = 'picpilot-tag-modal';
        modal.style.cssText = `
            background: white;
            padding: 20px;
            border-radius: 5px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.3);
            max-width: 400px;
            width: 90%;
            max-height: 80vh;
            overflow-y: auto;
        `;

        // Create available tags list
        const availableTagsHtml = availableTags.length > 0 
            ? availableTags.map(tag => 
                `<button type="button" class="picpilot-existing-tag" data-tag="${escapeHtml(tag.name)}" style="
                    background: #f0f0f0;
                    border: 1px solid #ddd;
                    padding: 4px 8px;
                    margin: 2px;
                    border-radius: 3px;
                    cursor: pointer;
                    font-size: 12px;
                ">${escapeHtml(tag.name)} (${tag.count})</button>`
              ).join('')
            : '<p style="color: #666; font-style: italic;">No existing tags found</p>';

        modal.innerHTML = `
            <h3 style="margin-top: 0;">Add Tag</h3>
            
            <div style="margin-bottom: 15px;">
                <label for="picpilot-new-tag-input" style="display: block; margin-bottom: 5px; font-weight: 600;">
                    Create New Tag:
                </label>
                <input type="text" id="picpilot-new-tag-input" placeholder="Enter tag name..." style="
                    width: 100%;
                    padding: 8px;
                    border: 1px solid #ddd;
                    border-radius: 3px;
                    box-sizing: border-box;
                " />
            </div>

            <div style="margin-bottom: 15px;">
                <h4 style="margin-bottom: 8px;">Or Choose Existing Tag:</h4>
                <div class="picpilot-existing-tags" style="
                    max-height: 150px;
                    overflow-y: auto;
                    border: 1px solid #ddd;
                    padding: 10px;
                    background: #fafafa;
                    border-radius: 3px;
                ">
                    ${availableTagsHtml}
                </div>
            </div>

            <div style="text-align: right; margin-top: 20px;">
                <button type="button" id="picpilot-cancel-tag" class="button" style="margin-right: 10px;">
                    Cancel
                </button>
                <button type="button" id="picpilot-add-tag" class="button button-primary">
                    Add Tag
                </button>
            </div>
        `;

        tagModalOverlay.appendChild(modal);
        document.body.appendChild(tagModalOverlay);

        // Focus the input
        const input = modal.querySelector('#picpilot-new-tag-input');
        input.focus();

        // Handle modal events
        modal.querySelector('#picpilot-cancel-tag').addEventListener('click', closeTagModal);
        modal.querySelector('#picpilot-add-tag').addEventListener('click', function() {
            const tagName = input.value.trim();
            if (tagName) {
                addTag(attachmentId, tagName);
            }
        });

        // Handle existing tag clicks
        modal.addEventListener('click', function(e) {
            if (e.target.classList.contains('picpilot-existing-tag')) {
                const tagName = e.target.getAttribute('data-tag');
                addTag(attachmentId, tagName);
            }
        });

        // Handle Enter key in input
        input.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                const tagName = input.value.trim();
                if (tagName) {
                    addTag(attachmentId, tagName);
                }
            }
        });

        // Close modal when clicking overlay
        tagModalOverlay.addEventListener('click', function(e) {
            if (e.target === tagModalOverlay) {
                closeTagModal();
            }
        });
    }

    function closeTagModal() {
        if (tagModalOverlay) {
            tagModalOverlay.remove();
            tagModalOverlay = null;
        }
    }

    function addTag(attachmentId, tagName) {
        showLoadingState(true);

        const formData = new FormData();
        formData.append('action', 'picpilot_add_image_tag');
        formData.append('nonce', window.picPilotStudio.nonce);
        formData.append('attachment_id', attachmentId);
        formData.append('tag_name', tagName);

        fetch(window.picPilotStudio.ajax_url, {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            showLoadingState(false);
            
            if (data.success) {
                closeTagModal();
                refreshTagsColumn(attachmentId);
                loadAvailableTags(); // Refresh available tags
                showNotice('Tag added successfully', 'success');
            } else {
                showNotice(data.data.message || 'Failed to add tag', 'error');
            }
        })
        .catch(error => {
            showLoadingState(false);
            console.error('Error adding tag:', error);
            showNotice('Error adding tag', 'error');
        });
    }

    function removeTag(attachmentId, tagName, tagElement) {
        if (!confirm(`Remove tag "${tagName}"?`)) {
            return;
        }

        const formData = new FormData();
        formData.append('action', 'picpilot_remove_image_tag');
        formData.append('nonce', window.picPilotStudio.nonce);
        formData.append('attachment_id', attachmentId);
        formData.append('tag_name', tagName);

        fetch(window.picPilotStudio.ajax_url, {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                tagElement.remove();
                loadAvailableTags(); // Refresh available tags
                showNotice('Tag removed successfully', 'success');
            } else {
                showNotice(data.data.message || 'Failed to remove tag', 'error');
            }
        })
        .catch(error => {
            console.error('Error removing tag:', error);
            showNotice('Error removing tag', 'error');
        });
    }

    function loadAvailableTags() {
        const formData = new FormData();
        formData.append('action', 'picpilot_get_all_tags');
        formData.append('nonce', window.picPilotStudio.nonce);

        fetch(window.picPilotStudio.ajax_url, {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                availableTags = data.data.tags;
            }
        })
        .catch(error => {
            console.error('Error loading available tags:', error);
        });
    }

    function refreshTagsColumn(attachmentId) {
        // Find the row containing this attachment
        const row = document.querySelector(`tr#post-${attachmentId}`);
        if (!row) return;

        const tagsCell = row.querySelector('.column-picpilot_tags');
        if (!tagsCell) return;

        // Simple refresh - reload the page to show updated tags
        // In a more sophisticated implementation, you could fetch and update just this cell
        window.location.reload();
    }

    function showLoadingState(loading) {
        if (tagModalOverlay) {
            const addButton = tagModalOverlay.querySelector('#picpilot-add-tag');
            if (addButton) {
                addButton.disabled = loading;
                addButton.textContent = loading ? 'Adding...' : 'Add Tag';
            }
        }
    }

    function showNotice(message, type = 'info') {
        const notice = document.createElement('div');
        notice.className = `notice notice-${type} is-dismissible`;
        notice.style.cssText = `
            position: fixed;
            top: 32px;
            right: 20px;
            z-index: 10000;
            max-width: 300px;
            padding: 12px;
            background: ${type === 'success' ? '#d4edda' : type === 'error' ? '#f8d7da' : '#d1ecf1'};
            border: 1px solid ${type === 'success' ? '#c3e6cb' : type === 'error' ? '#f5c6cb' : '#bee5eb'};
            border-radius: 3px;
            color: ${type === 'success' ? '#155724' : type === 'error' ? '#721c24' : '#0c5460'};
        `;

        notice.innerHTML = `
            <p style="margin: 0;">${escapeHtml(message)}</p>
            <button type="button" class="notice-dismiss" style="
                position: absolute;
                top: 0;
                right: 0;
                padding: 9px;
                background: none;
                border: none;
                cursor: pointer;
                font-size: 18px;
            ">
                <span class="screen-reader-text">Dismiss this notice.</span>
                <span aria-hidden="true">&times;</span>
            </button>
        `;

        document.body.appendChild(notice);

        // Auto dismiss after 3 seconds
        setTimeout(() => {
            if (notice.parentNode) {
                notice.remove();
            }
        }, 3000);

        // Handle dismiss button
        notice.querySelector('.notice-dismiss').addEventListener('click', () => {
            notice.remove();
        });
    }

    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

})();