(function () {
  function isProtectedEl(el) {
    if (!el) {
      return false;
    }
    if (el.classList && el.classList.contains('avatar-placeholder')) {
      return true;
    }
    if (el.tagName !== 'IMG') {
      return false;
    }
    if (el.classList.contains('avatar-img') || el.classList.contains('protected-avatar')) {
      return true;
    }
    return !!el.closest('.profile-avatar-box, .funcionario-foto-preview, .topnav .avatar');
  }

  function blockEvent(event) {
    if (isProtectedEl(event.target)) {
      event.preventDefault();
      return false;
    }
  }

  document.addEventListener('contextmenu', blockEvent, true);
  document.addEventListener('dragstart', blockEvent, true);
  document.addEventListener('selectstart', blockEvent, true);
  document.addEventListener('copy', blockEvent, true);
})();
