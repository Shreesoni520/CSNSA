(function (window) {
  function initialsFromName(name) {
    name = (name || '').trim();
    if (!name) {
      return '?';
    }
    var parts = name.split(/\s+/);
    return parts[0].charAt(0).toUpperCase();
  }

  function renderPlaceholder(previewEl, name) {
    previewEl.innerHTML = '<div class="avatar-placeholder protected-avatar" oncontextmenu="return false;">' + initialsFromName(name) + '</div>';
  }

  function renderImage(previewEl, src, alt) {
    previewEl.innerHTML = '<img src="' + src + '" alt="' + (alt || '') + '" class="avatar-img rounded-circle protected-avatar" draggable="false" oncontextmenu="return false;" ondragstart="return false;">';
  }

  function initFuncionarioFotoPreview(options) {
    var previewEl = document.getElementById(options.previewId);
    var inputEl = document.getElementById(options.inputId);
    if (!previewEl || !inputEl) {
      return;
    }

    var nomeInput = options.nomeInput
      ? (typeof options.nomeInput === 'string'
        ? document.querySelector(options.nomeInput)
        : options.nomeInput)
      : null;

    var state = previewEl._fotoPreviewState;
    if (!state) {
      state = {
        hasSelectedFile: false,
        currentImageUrl: '',
        defaultName: ''
      };
      previewEl._fotoPreviewState = state;

      inputEl.addEventListener('change', function (event) {
        var file = event.target.files[0];
        if (!file) {
          state.hasSelectedFile = false;
          refresh();
          return;
        }
        state.hasSelectedFile = true;
        var reader = new FileReader();
        reader.onload = function (loadEvent) {
          renderImage(previewEl, loadEvent.target.result, nomeInput ? nomeInput.value : state.defaultName);
        };
        reader.readAsDataURL(file);
      });

      if (nomeInput) {
        nomeInput.addEventListener('input', refresh);
      }
    }

    function refresh() {
      if (state.hasSelectedFile) {
        return;
      }
      var name = nomeInput ? nomeInput.value : state.defaultName;
      if (state.currentImageUrl) {
        renderImage(previewEl, state.currentImageUrl, name);
        return;
      }
      renderPlaceholder(previewEl, name);
    }

    state.currentImageUrl = options.currentImageUrl || '';
    state.defaultName = options.defaultName || '';
    state.hasSelectedFile = false;
    inputEl.value = '';
    previewEl._fotoPreviewRefresh = refresh;
    refresh();
  }

  window.initFuncionarioFotoPreview = initFuncionarioFotoPreview;
  window.avatarInitialsFromName = initialsFromName;
})(window);
