(function (window, document) {
  'use strict';

  var EMPTY_LABEL = 'Nenhum ficheiro selecionado';

  function formatSize(bytes) {
    if (!bytes || bytes <= 0) {
      return '';
    }
    if (bytes < 1024) {
      return bytes + ' B';
    }
    if (bytes < 1024 * 1024) {
      return (bytes / 1024).toFixed(1) + ' KB';
    }
    return (bytes / (1024 * 1024)).toFixed(1) + ' MB';
  }

  function updateUploadState(wrap, file) {
    var filenameEl = wrap.querySelector('.csnsa-upload__filename');
    var zoneTitle = wrap.querySelector('.csnsa-upload__zone-title');
    var hasFile = !!file;

    wrap.classList.toggle('csnsa-upload--has-file', hasFile);

    if (filenameEl) {
      if (hasFile) {
        var size = formatSize(file.size);
        filenameEl.textContent = size ? file.name + ' (' + size + ')' : file.name;
      } else {
        filenameEl.textContent = wrap.dataset.emptyLabel || EMPTY_LABEL;
      }
    }

    if (zoneTitle) {
      zoneTitle.textContent = hasFile ? file.name : (wrap.dataset.zoneTitle || 'Arraste ou clique para enviar');
    }
  }

  function initUpload(wrap) {
    if (!wrap || wrap.dataset.uploadInit === '1') {
      return;
    }

    var input = wrap.querySelector('input[type="file"]');
    if (!input) {
      return;
    }

    wrap.dataset.uploadInit = '1';

    input.addEventListener('change', function () {
      updateUploadState(wrap, input.files && input.files[0] ? input.files[0] : null);
    });

    var zone = wrap.querySelector('.csnsa-upload__zone');
    if (zone) {
      ['dragenter', 'dragover'].forEach(function (eventName) {
        zone.addEventListener(eventName, function (event) {
          event.preventDefault();
          event.stopPropagation();
          wrap.classList.add('csnsa-upload--drag');
        });
      });

      ['dragleave', 'drop'].forEach(function (eventName) {
        zone.addEventListener(eventName, function (event) {
          event.preventDefault();
          event.stopPropagation();
          wrap.classList.remove('csnsa-upload--drag');
        });
      });

      zone.addEventListener('drop', function (event) {
        var files = event.dataTransfer && event.dataTransfer.files;
        if (!files || !files.length) {
          return;
        }
        try {
          input.files = files;
        } catch (err) {
          return;
        }
        input.dispatchEvent(new Event('change', { bubbles: true }));
      });
    }
  }

  function initAll(root) {
    (root || document).querySelectorAll('.csnsa-upload').forEach(initUpload);
  }

  window.initCsnsaUploads = initAll;

  document.addEventListener('DOMContentLoaded', function () {
    initAll(document);
  });

  if (window.jQuery) {
    window.jQuery(document).on('shown.bs.modal', function (event) {
      initAll(event.target);
    });
  }
}(window, document));
