<?php
if (!isset($pageTitle)) {
    $pageTitle = 'CSNSA';
}
$extraHead = $extraHead ?? '';
?>
<!doctype html>
<html lang="pt">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <meta name="description" content="">
    <meta name="author" content="">
    <link rel="icon" href="favicon.ico">
    <title>CSNSA - <?php echo e($pageTitle); ?></title>
    <link rel="stylesheet" href="css/simplebar.css">
    <link href="https://fonts.googleapis.com/css2?family=Overpass:ital,wght@0,100;0,200;0,300;0,400;0,600;0,700;0,800;0,900;1,100;1,200;1,300;1,400;1,600;1,700;1,800;1,900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/feather.css">
    <?php if (!empty($useDataTables)): ?>
    <link rel="stylesheet" href="css/dataTables.bootstrap4.css">
    <?php endif; ?>
    <link rel="stylesheet" href="css/app-light.css" id="lightTheme">
    <link rel="stylesheet" href="css/app-dark.css" id="darkTheme" disabled>
    <link rel="stylesheet" href="css/table-actions.css">
    <link rel="stylesheet" href="css/csnsa-light-fixes.css" id="lightFixes">
    <link rel="stylesheet" href="css/csnsa-alerts.css">
    <link rel="stylesheet" href="css/csnsa-upload.css">
    <style>
      .page-header .breadcrumbs { display: none !important; }
    </style>
    <?php echo $extraHead; ?>
  </head>
  <body class="vertical  light  ">
    <div class="wrapper">
      <aside class="left-sidebar bg-sidebar">
        <div id="sidebar" class="sidebar sidebar-with-footer">
          <?php include __DIR__ . '/../menu.php'; ?>
        </div>
      </aside>
      <main role="main" class="main-content">
        <div class="container-fluid">
          <div class="row justify-content-center">
            <div class="col-12">
