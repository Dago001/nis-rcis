<?php
/**
 * NIS Residence Card Issuance System (NIS-RCIS)
 * Footer Component & JavaScript Loader
 */
?>
    </div><!-- /.content-container -->

    <footer class="app-footer">
        <p style="margin: 0;">&copy; <?php echo date('Y'); ?> Nigeria Immigration Service (NIS). All rights reserved.</p>
    </footer>
</div><!-- /.main-wrapper -->

<!-- Scripts -->
<script src="assets/js/main.js"></script>
<?php if (isset($extraScripts) && is_array($extraScripts)): ?>
    <?php foreach ($extraScripts as $script): ?>
        <script src="<?php echo h($script); ?>"></script>
    <?php endforeach; ?>
<?php endif; ?>

</body>
</html>
