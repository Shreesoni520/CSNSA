<?php
$brandClass = $brandClass ?? 'brand-sm';
$logoSrc = $logoSrc ?? 'assets/logo-fatima.png';
$logoAlt = $logoAlt ?? 'Centro Social de Nossa Senhora Auxiliadora';
?>
<img
  class="navbar-brand-img <?php echo htmlspecialchars($brandClass); ?> csnsa-logo"
  src="<?php echo htmlspecialchars($logoSrc); ?>"
  alt="<?php echo htmlspecialchars($logoAlt); ?>"
  width="120"
  height="188"
>
