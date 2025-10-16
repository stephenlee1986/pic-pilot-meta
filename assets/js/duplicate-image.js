document.addEventListener('DOMContentLoaded', function () {
  document.querySelectorAll('.pic-pilot-duplicate-image').forEach(function (button) {
    button.addEventListener('click', function (e) {
      e.preventDefault();
      const id = button.dataset.id;
      if (!id) return;

      // Smart generation is now always enabled
      if (true) {
        window.PicPilotFilenameModal?.open(id, (data, modal, statusEl) => {
          sendDuplicateRequest(id, data.title, data.filename, data.alt, button, data.keywords, modal, statusEl);
        });
      } else {
        sendDuplicateRequest(id, null, null, null, button);
      }
    });
  });

  function sendDuplicateRequest(attachmentId, newTitle = null, newFilename = null, newAlt = null, button, keywords = null, modal = null, statusEl = null) {
    const formData = new FormData();
    formData.append('action', 'pic_pilot_duplicate_image');
    formData.append('attachment_id', attachmentId);
    formData.append('nonce', PicPilotStudio.nonce);
    if (newTitle) formData.append('new_title', newTitle);
    if (newFilename) formData.append('new_filename', newFilename);
    if (newAlt) formData.append('new_alt', newAlt);
    if (keywords) formData.append('keywords', keywords);

    button.textContent = 'Duplicating...';
    
    // Update modal status if available
    if (statusEl) {
      statusEl.textContent = 'Duplicating image...';
      statusEl.style.color = '#0073aa';
    }

    fetch(PicPilotStudio.ajax_url, {
      method: 'POST',
      credentials: 'same-origin',
      body: formData
    })
      .then(res => res.json())
      .then(response => {
        if (response.success) {
          if (statusEl) {
            statusEl.textContent = '✅ Image duplicated successfully! Refreshing page...';
            statusEl.style.color = '#46b450';
          }
          showToast('✅ Image duplicated successfully!');
          
          // Close modal after a brief delay to show success message
          setTimeout(() => {
            if (modal) modal.remove();
            location.reload();
          }, 1500);
        } else {
          const errorMsg = response.data?.message || 'Duplication failed.';
          if (statusEl) {
            statusEl.textContent = '❌ ' + errorMsg;
            statusEl.style.color = '#dc3232';
          }
          showToast('❌ ' + errorMsg);
          button.textContent = 'Duplicate';
        }
      })
      .catch(() => {
        const errorMsg = 'Request failed. Check your connection.';
        if (statusEl) {
          statusEl.textContent = '❌ ' + errorMsg;
          statusEl.style.color = '#dc3232';
        }
        showToast('❌ ' + errorMsg);
        button.textContent = 'Duplicate';
      });
  }

  function showToast(message, isError = false) {
    const toast = document.createElement('div');
    toast.className = 'pic-pilot-toast';
    toast.textContent = message;
    if (isError) toast.classList.add('error');
    document.body.appendChild(toast);
    setTimeout(() => toast.classList.add('show'), 10);
    setTimeout(() => toast.remove(), 5000);
  }
});
