    </main>

    <!-- Bootstrap JS -->
    <script src="<?php echo ASSETS_URL; ?>/js/bootstrap.bundle.min.js"></script>
    
    <!-- jQuery -->
    <script src="<?php echo ASSETS_URL; ?>/js/jquery-3.6.0.min.js"></script>
    
    <!-- DataTables JS -->
    <script src="<?php echo ASSETS_URL; ?>/js/jquery.dataTables.min.js"></script>
    <script src="<?php echo ASSETS_URL; ?>/js/dataTables.bootstrap5.min.js"></script>
    
    <!-- SweetAlert2 JS -->
    <script src="<?php echo ASSETS_URL; ?>/js/sweetalert2@11.js"></script>
    
    <!-- Custom JS -->
    <script src="<?php echo ASSETS_URL; ?>/js/script.js"></script>
    
    <!-- Page-specific scripts -->
    <?php if (isset($page_scripts)): ?>
        <?php foreach ($page_scripts as $script): ?>
            <script src="<?php echo ASSETS_URL . '/js/' . $script; ?>"></script>
        <?php endforeach; ?>
    <?php endif; ?>
</body>
</html>