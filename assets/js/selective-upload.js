/**
 * Pic Pilot Meta - Dual Upload Areas JavaScript
 */

(function($) {
    'use strict';

    let automaticFiles = [];
    let selectiveFiles = [];
    let automaticUploader;
    let selectiveUploader;

    // Initialize when document is ready
    $(document).ready(function() {
        initializeUploaders();
        bindEvents();
    });

    function initializeUploaders() {
        // Initialize automatic uploader
        automaticUploader = new plupload.Uploader({
            runtimes: 'html5,flash,silverlight,html4',
            browse_button: 'automatic-select-files-btn',
            drop_element: 'automatic-upload-zone',
            url: picpilotSelectiveUpload.ajaxUrl,
            max_file_size: '10mb',
            filters: {
                mime_types: [
                    { title: "Image files", extensions: "jpg,jpeg,png,gif,webp" }
                ]
            },
            multi_selection: true,
            multipart_params: {
                action: 'picpilot_automatic_upload',
                nonce: picpilotSelectiveUpload.nonce
            }
        });

        // Initialize selective uploader
        selectiveUploader = new plupload.Uploader({
            runtimes: 'html5,flash,silverlight,html4',
            browse_button: 'selective-select-files-btn',
            drop_element: 'selective-upload-zone',
            url: picpilotSelectiveUpload.ajaxUrl,
            max_file_size: '10mb',
            filters: {
                mime_types: [
                    { title: "Image files", extensions: "jpg,jpeg,png,gif,webp" }
                ]
            },
            multi_selection: true,
            multipart_params: {
                action: 'picpilot_selective_upload',
                nonce: picpilotSelectiveUpload.nonce
            }
        });

        automaticUploader.init();
        selectiveUploader.init();

        setupUploaderEvents(automaticUploader, 'automatic');
        setupUploaderEvents(selectiveUploader, 'selective');
    }

    function setupUploaderEvents(uploader, mode) {
        const uploadZone = mode === 'automatic' ? $('#automatic-upload-zone') : $('#selective-upload-zone');

        // Handle drag and drop visual feedback
        uploader.bind('DragEnter', function() {
            uploadZone.addClass('dragover');
        });

        uploader.bind('DragLeave', function() {
            uploadZone.removeClass('dragover');
        });

        uploader.bind('Drop', function() {
            uploadZone.removeClass('dragover');
        });

        // Handle file addition
        uploader.bind('FilesAdded', function(up, files) {
            uploadZone.removeClass('dragover');
            showUploadProgress(mode);
            uploader.start();
        });

        // Handle upload progress
        uploader.bind('UploadProgress', function(up, file) {
            updateUploadProgress(mode, file.name, file.percent);
        });

        // Handle successful upload
        uploader.bind('FileUploaded', function(up, file, response) {
            const result = JSON.parse(response.response);
            
            if (result.success) {
                addUploadedFile(result.data, mode);
            } else {
                showError('Upload failed: ' + (result.data.message || 'Unknown error'));
            }
        });

        // Handle upload completion
        uploader.bind('UploadComplete', function() {
            hideUploadProgress(mode);
            updateFilesDisplay();
        });

        // Handle upload errors
        uploader.bind('Error', function(up, error) {
            showError('Upload error: ' + error.message);
            hideUploadProgress(mode);
        });
    }

    function bindEvents() {
        // Bind AI generation buttons (delegated events for dynamic content)
        $(document).on('click', '.ai-generate-btn', function(e) {
            e.preventDefault();
            
            const btn = $(this);
            const fileId = btn.data('file-id');
            const type = btn.data('type');
            const keywords = btn.closest('.file-item').find('.keywords-input').val() || '';
            
            generateAIContent(btn, fileId, type, keywords);
        });

        // Handle manual metadata changes
        $(document).on('input', '.metadata-input', function() {
            const input = $(this);
            const fileId = input.data('file-id');
            const type = input.data('type');
            const value = input.val();
            
            // Debounce the update
            clearTimeout(input.data('timeout'));
            input.data('timeout', setTimeout(function() {
                updateAttachmentMetadata(fileId, type, value);
            }, 1000));
        });
    }

    function showUploadProgress(mode) {
        const uploadArea = mode === 'automatic' ? $('#automatic-upload-area') : $('#selective-upload-area');
        const progressHtml = `
            <div class="upload-progress">
                <div class="spinner is-active"></div>
                <span class="progress-text">${picpilotSelectiveUpload.strings.processing}</span>
            </div>
        `;
        
        uploadArea.find('.upload-zone').hide();
        uploadArea.append(progressHtml);
    }

    function updateUploadProgress(mode, filename, percent) {
        const uploadArea = mode === 'automatic' ? $('#automatic-upload-area') : $('#selective-upload-area');
        uploadArea.find('.progress-text').text(`Uploading ${filename}... ${percent}%`);
    }

    function hideUploadProgress(mode) {
        const uploadArea = mode === 'automatic' ? $('#automatic-upload-area') : $('#selective-upload-area');
        uploadArea.find('.upload-progress').remove();
        uploadArea.find('.upload-zone').show();
    }

    function addUploadedFile(fileData, mode) {
        if (mode === 'automatic') {
            automaticFiles.push(fileData);
        } else {
            selectiveFiles.push(fileData);
        }
    }

    function updateFilesDisplay() {
        // Update automatic files display
        if (automaticFiles.length > 0) {
            $('#automatic-uploaded-files').show();
            const automaticGrid = $('#automatic-files-grid');
            automaticGrid.empty();
            
            automaticFiles.forEach(function(file) {
                const fileHtml = createAutomaticFileHtml(file);
                automaticGrid.append(fileHtml);
            });
        }

        // Update selective files display
        if (selectiveFiles.length > 0) {
            $('#selective-uploaded-files').show();
            const selectiveGrid = $('#selective-files-grid');
            selectiveGrid.empty();
            
            selectiveFiles.forEach(function(file) {
                const fileHtml = createSelectiveFileHtml(file);
                selectiveGrid.append(fileHtml);
            });
        }
    }

    function createAutomaticFileHtml(file) {
        let aiResultsHtml = '';
        if (file.ai_results && Object.keys(file.ai_results).length > 0) {
            aiResultsHtml = '<div class="ai-results-summary">';
            aiResultsHtml += '<strong>✅ AI Generated Content:</strong><br>';
            
            if (file.ai_results.alt) {
                aiResultsHtml += `<div class="success-item">📝 Alt Text: ${file.ai_results.alt}</div>`;
            }
            if (file.ai_results.title) {
                aiResultsHtml += `<div class="success-item">📋 Title: ${file.ai_results.title}</div>`;
            }
            if (file.ai_results.filename) {
                aiResultsHtml += `<div class="success-item">📁 Filename: ${file.ai_results.filename.original} → ${file.ai_results.filename.new}</div>`;
            }
            
            aiResultsHtml += '</div>';
        }

        // Automatic uploads always attempt filename generation - no dependency check needed

        // Show filename change if it occurred
        let filenameDisplayHtml = '';
        if (file.original_filename && file.original_filename !== file.filename) {
            filenameDisplayHtml = `
                <div class="file-info">
                    <strong>ID:</strong> ${file.id}<br>
                    <strong>Original:</strong> <span class="original-filename">${file.original_filename}</span><br>
                    <strong>New Filename:</strong> <span class="new-filename">${file.filename}</span><br>
                    <strong>URL:</strong> <a href="${file.url}" target="_blank">View</a>
                </div>
            `;
        } else {
            filenameDisplayHtml = `
                <div class="file-info">
                    <strong>ID:</strong> ${file.id}<br>
                    <strong>Filename:</strong> ${file.filename}<br>
                    <strong>URL:</strong> <a href="${file.url}" target="_blank">View</a>
                </div>
            `;
        }

        return `
            <div class="file-item automatic-item" data-file-id="${file.id}">
                <div class="file-preview">
                    <img src="${file.url}" alt="Preview">
                </div>
                
                ${filenameDisplayHtml}
                
                ${aiResultsHtml}
                
                <div class="metadata-display">
                    <div class="metadata-section">
                        <h4>Current Alt Text</h4>
                        <div class="current-value">${file.alt || 'Not set'}</div>
                    </div>
                    
                    <div class="metadata-section">
                        <h4>Current Title</h4>
                        <div class="current-value">${file.title || 'Not set'}</div>
                    </div>
                </div>
            </div>
        `;
    }

    function createSelectiveFileHtml(file) {
        return `
            <div class="file-item selective-item" data-file-id="${file.id}">
                <div class="file-preview">
                    <img src="${file.url}" alt="Preview">
                </div>
                
                <div class="file-info">
                    <strong>ID:</strong> ${file.id}<br>
                    <strong>Filename:</strong> ${file.filename}<br>
                    <strong>URL:</strong> <a href="${file.url}" target="_blank">View</a>
                </div>
                
                <div class="keywords-section">
                    <label>${picpilotSelectiveUpload.strings.keywords}</label>
                    <input type="text" class="keywords-input" 
                           placeholder="e.g., business meeting, office, teamwork">
                </div>
                
                <div class="metadata-section">
                    <h4>Alt Text</h4>
                    <div class="current-value ${file.alt ? '' : 'empty'}">
                        Current: ${file.alt || 'Not set'}
                    </div>
                    <div class="metadata-field">
                        <textarea class="metadata-input" 
                                data-file-id="${file.id}" 
                                data-type="alt" 
                                placeholder="Alt text will appear here...">${file.alt || ''}</textarea>
                        <button class="ai-generate-btn" 
                                data-file-id="${file.id}" 
                                data-type="alt">
                            🤖 ${picpilotSelectiveUpload.strings.generateAlt}
                        </button>
                    </div>
                    <div class="success-message" id="alt-success-${file.id}"></div>
                    <div class="error-message" id="alt-error-${file.id}"></div>
                </div>
                
                <div class="metadata-section">
                    <h4>Title</h4>
                    <div class="current-value ${file.title ? '' : 'empty'}">
                        Current: ${file.title || 'Not set'}
                    </div>
                    <div class="metadata-field">
                        <input type="text" 
                               class="metadata-input" 
                               data-file-id="${file.id}" 
                               data-type="title" 
                               placeholder="Title will appear here..." 
                               value="${file.title || ''}">
                        <button class="ai-generate-btn" 
                                data-file-id="${file.id}" 
                                data-type="title">
                            🤖 ${picpilotSelectiveUpload.strings.generateTitle}
                        </button>
                    </div>
                    <div class="success-message" id="title-success-${file.id}"></div>
                    <div class="error-message" id="title-error-${file.id}"></div>
                </div>
                
                <div class="metadata-section">
                    <h4>Filename</h4>
                    <div class="current-value">
                        Current: ${file.filename}
                    </div>
                    <div class="metadata-field">
                        <input type="text" 
                               class="filename-suggestion" 
                               data-file-id="${file.id}" 
                               placeholder="Filename suggestion will appear here..." 
                               readonly>
                        <button class="ai-generate-btn" 
                                data-file-id="${file.id}" 
                                data-type="filename">
                            🤖 ${picpilotSelectiveUpload.strings.generateFilename}
                        </button>
                    </div>
                    <div class="filename-warning" style="display: none;">
                        ⚠️ Filename changes require careful consideration as they may break existing references.
                    </div>
                    <div class="success-message" id="filename-success-${file.id}"></div>
                    <div class="error-message" id="filename-error-${file.id}"></div>
                </div>
            </div>
        `;
    }

    function generateAIContent(btn, fileId, type, keywords) {
        // Visual feedback
        btn.prop('disabled', true).addClass('generating');
        btn.text(picpilotSelectiveUpload.strings.generating);
        
        // Clear previous messages
        $(`#${type}-success-${fileId}, #${type}-error-${fileId}`).removeClass('show');
        
        // AJAX request
        $.ajax({
            url: picpilotSelectiveUpload.ajaxUrl,
            method: 'POST',
            data: {
                action: 'picpilot_process_selective_ai',
                nonce: picpilotSelectiveUpload.nonce,
                attachment_id: fileId,
                type: type,
                keywords: keywords
            },
            success: function(response) {
                if (response.success) {
                    handleAISuccess(btn, fileId, type, response.data.generated_content);
                } else {
                    handleAIError(btn, fileId, type, response.data.message || 'Generation failed');
                }
            },
            error: function(xhr, status, error) {
                handleAIError(btn, fileId, type, 'Network error: ' + error);
            }
        });
    }

    function handleAISuccess(btn, fileId, type, generatedContent) {
        // Reset button
        resetAIButton(btn, type);
        
        // Update the input field
        if (type === 'filename') {
            // For filename, show as suggestion only
            btn.closest('.metadata-field').find('.filename-suggestion').val(generatedContent);
            btn.closest('.metadata-section').find('.filename-warning').show();
        } else {
            // For alt and title, update the input and the attachment
            const input = btn.closest('.metadata-field').find('.metadata-input');
            input.val(generatedContent);
            
            // Update current value display
            const currentValueDiv = btn.closest('.metadata-section').find('.current-value');
            currentValueDiv.text('Current: ' + generatedContent).removeClass('empty');
        }
        
        // Show success message
        const successMsg = $(`#${type}-success-${fileId}`);
        successMsg.text(picpilotSelectiveUpload.strings.success).addClass('show');
        
        // Hide success message after 3 seconds
        setTimeout(() => {
            successMsg.removeClass('show');
        }, 3000);
    }

    function handleAIError(btn, fileId, type, errorMessage) {
        // Reset button
        resetAIButton(btn, type);
        
        // Show error message
        const errorMsg = $(`#${type}-error-${fileId}`);
        errorMsg.text(errorMessage).addClass('show');
        
        // Hide error message after 5 seconds
        setTimeout(() => {
            errorMsg.removeClass('show');
        }, 5000);
    }

    function resetAIButton(btn, type) {
        btn.prop('disabled', false).removeClass('generating');
        
        // Reset button text based on type
        const buttonTexts = {
            alt: picpilotSelectiveUpload.strings.generateAlt,
            title: picpilotSelectiveUpload.strings.generateTitle,
            filename: picpilotSelectiveUpload.strings.generateFilename
        };
        
        btn.text('🤖 ' + buttonTexts[type]);
    }

    function updateAttachmentMetadata(fileId, type, value) {
        // Update metadata via AJAX (for manual edits)
        $.ajax({
            url: picpilotSelectiveUpload.ajaxUrl,
            method: 'POST',
            data: {
                action: 'picpilot_update_attachment_metadata',
                nonce: picpilotSelectiveUpload.nonce,
                attachment_id: fileId,
                type: type,
                value: value
            },
            success: function(response) {
                if (response.success) {
                    console.log(`Updated ${type} for attachment ${fileId}`);
                } else {
                    console.error('Failed to update metadata:', response.data.message);
                }
            },
            error: function() {
                console.error('Network error updating metadata');
            }
        });
    }

    function showError(message) {
        // Simple error display - could be enhanced with a better notification system
        alert(message);
    }

})(jQuery);