            </div>
          </div>
        </div>
      </main>
    </div>
    <script src="js/jquery.min.js"></script>
    <script src="js/popper.min.js"></script>
    <script src="js/bootstrap.min.js"></script>
    <script src="js/simplebar.min.js"></script>
    <script src="js/jquery.stickOnScroll.js"></script>
    <script src="js/tinycolor-min.js"></script>
    <script src="js/config.js"></script>
    <script src="js/csnsa-upload.js"></script>
    <?php if (!empty($useDataTables)): ?>
    <script src="js/jquery.dataTables.min.js"></script>
    <script src="js/dataTables.bootstrap4.min.js"></script>
    <?php endif; ?>
    <script src="js/apps.js"></script>
    <?php
    if (!empty($pageScripts)) {
        echo $pageScripts;
    }
    ?>
  </body>
</html>
