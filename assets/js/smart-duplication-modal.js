// Defines window.PicPilotFilenameModal.open(id, callback)
window.PicPilotFilenameModal = {
  async open(id, callback) {
    if (document.getElementById('picpilot-duplication-modal')) return;

    const modal = document.createElement('div');
    modal.id = 'picpilot-duplication-modal';
    modal.style.cssText = `
            position: fixed; bottom: 100px; right: 20px; background: #fff;
            border: 1px solid #ccc; padding: 15px; z-index: 9999; width: 340px;
            box-shadow: 0 0 10px rgba(0,0,0,0.3); border-radius: 6px; font-family: sans-serif;
        `;

    // Smart generation features are now always enabled
    const titleEnabled = true;
    const altEnabled = true;
    const filenameEnabled = true;
    
    // Build AI option HTML conditionally
    const buildSection = (name, label, enabled) => {
      const aiOption = enabled ? `<label><input type="radio" name="dup-${name}" value="generate"> Generate with AI</label><br>` : '';
      return `
        <div style="margin-bottom: 15px;">
          <strong>${label}</strong><br>
          <label><input type="radio" name="dup-${name}" value="auto" checked> Auto</label><br>
          ${aiOption}
          <label><input type="radio" name="dup-${name}" value="manual"> Enter manually:</label><br>
          <input type="text" id="dup-${name}-manual" style="width: 100%; display: none; margin-top: 5px;" />
        </div>
      `;
    };

    // Show keywords field only if at least one AI feature is enabled
    const anyAiEnabled = titleEnabled || altEnabled || filenameEnabled;
    const keywordsSection = anyAiEnabled ? `
      <div style="margin-bottom: 15px;">
        <strong>Keywords (optional)</strong><br>
        <input type="text" id="dup-keywords" style="width: 100%; margin-top: 5px;" 
               placeholder="Add context for AI generation" />
      </div>
    ` : '';

    modal.innerHTML = `
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
                <h3 style="margin: 0; font-size: 16px;">Customize duplication metadata</h3>
                <button id="picpilot-modal-close" style="background: none; border: none; font-size: 18px; cursor: pointer; color: #666; padding: 0; width: 20px; height: 20px;" title="Close">✕</button>
            </div>
            
            ${buildSection('title', 'Title', titleEnabled)}
            ${buildSection('alt', 'Alt Text', altEnabled)}
            ${buildSection('filename', 'Filename', filenameEnabled)}

            ${keywordsSection}

            <button id="picpilot-dup-confirm" style="width: 100%;">✅ Duplicate Image</button>
            <div id="picpilot-dup-status" style="margin-top: 10px; font-size: 13px; color: #666;"></div>
        `;

    document.body.appendChild(modal);

    // Show/hide manual input fields based on radio selection
    const updateFieldVisibility = (groupName, inputId) => {
      document.querySelectorAll(`input[name='${groupName}']`).forEach(input => {
        input.addEventListener('change', () => {
          const field = document.getElementById(inputId);
          if (!field) return;
          field.style.display = input.value === 'manual' && input.checked ? 'block' : 'none';
        });
      });
    };

    updateFieldVisibility('dup-title', 'dup-title-manual');
    updateFieldVisibility('dup-alt', 'dup-alt-manual');
    updateFieldVisibility('dup-filename', 'dup-filename-manual');

    // Add close button functionality
    document.getElementById('picpilot-modal-close').onclick = () => {
      modal.remove();
    };

    document.getElementById('picpilot-dup-confirm').onclick = async () => {
      const confirmBtn = document.getElementById('picpilot-dup-confirm');
      const statusEl = document.getElementById('picpilot-dup-status');
      confirmBtn.disabled = true;
      confirmBtn.textContent = 'Processing...';

      try {
        const keywordsField = document.getElementById('dup-keywords');
        const keywords = keywordsField ? keywordsField.value.trim() : '';
        console.log('🔑 Keywords retrieved from input field:', `"${keywords}"`);

        // Get user selections
        const titleVal = getValueFromRadio('dup-title', 'dup-title-manual');
        const altVal = getValueFromRadio('dup-alt', 'dup-alt-manual');
        const fileVal = getValueFromRadio('dup-filename', 'dup-filename-manual');

        console.log('📋 User selections:', { titleVal, altVal, fileVal, keywords });

        // Prepare data object
        const data = {
          title: titleVal,
          alt: altVal,
          filename: fileVal,
          keywords: keywords
        };

        // Generate metadata if requested
        if (titleVal === 'generate') {
          statusEl.textContent = 'Generating title...';
          console.log('🏷️ Generating title with keywords:', keywords);
          try {
            data.title = await generateMetadata(id, 'title', keywords);
            console.log('✅ Title generated:', data.title);
          } catch (error) {
            statusEl.textContent = 'Title generation failed, using fallback';
            statusEl.style.color = '#ff8800';
            console.warn('Title generation failed:', error);
            // Continue with null title for fallback handling
          }
        }

        if (altVal === 'generate') {
          statusEl.textContent = 'Generating alt text...';
          console.log('🏷️ Generating alt text with keywords:', keywords);
          try {
            data.alt = await generateMetadata(id, 'alt', keywords);
            console.log('✅ Alt text generated:', data.alt);
          } catch (error) {
            statusEl.textContent = 'Alt text generation failed, using fallback';
            statusEl.style.color = '#ff8800';
            console.warn('Alt text generation failed:', error);
            // Continue with null alt for fallback handling
          }
        }

        if (fileVal === 'generate') {
          statusEl.textContent = 'Generating filename...';
          console.log('🏷️ Generating filename with keywords:', keywords);
          try {
            data.filename = await generateFilename(id, keywords);
            console.log('✅ Filename generated:', data.filename);
          } catch (error) {
            statusEl.textContent = 'Filename generation failed, using fallback';
            statusEl.style.color = '#ff8800';
            console.warn('Filename generation failed:', error);
            // Continue with null filename for fallback handling
          }
        }

        // Don't remove modal yet - pass it along with the callback
        statusEl.textContent = 'Starting duplication...';
        statusEl.style.color = '#0073aa';
        callback(data, modal, statusEl);
      } catch (error) {
        statusEl.textContent = 'Error: ' + error.message;
        statusEl.style.color = '#dc3232';
        confirmBtn.disabled = false;
        confirmBtn.textContent = 'Try Again';
        console.error('Duplication error:', error);
      }
    };

    function getValueFromRadio(groupName, manualId) {
      const selected = document.querySelector(`input[name='${groupName}']:checked`).value;
      if (selected === 'manual') {
        const input = document.getElementById(manualId);
        const value = input ? input.value.trim() : '';
        return value || null;
      }
      return selected === 'generate' ? 'generate' : null; // 'auto' => null
    }

    async function generateMetadata(attachmentId, type, keywords) {
      console.log(`🔄 generateMetadata called: ID=${attachmentId}, type=${type}, keywords="${keywords}"`);

      const requestData = {
        action: 'picpilot_generate_metadata',
        nonce: window.picPilotStudio.nonce,
        attachment_id: attachmentId,
        type: type,
        keywords: keywords || ''
      };

      console.log('📤 Sending request data:', requestData);

      const response = await fetch(window.picPilotStudio.ajax_url, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams(requestData)
      });

      const result = await response.json();
      console.log('📥 Received response:', result);

      if (!result.success) {
        throw new Error(result.data?.message || result.data || 'Failed to generate metadata');
      }
      return result.data.result;
    }

    async function generateFilename(attachmentId, keywords) {
      const response = await fetch(window.picPilotStudio.ajax_url, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({
          action: 'picpilot_generate_filename',
          nonce: window.picPilotStudio.nonce,
          attachment_id: attachmentId,
          keywords: keywords || ''
        })
      });

      const result = await response.json();
      if (!result.success) {
        throw new Error(result.data?.message || result.data || 'Failed to generate filename');
      }
      return result.data.filename;
    }
  }
};