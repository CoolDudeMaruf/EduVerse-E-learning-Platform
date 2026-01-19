    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="<?php echo $base_url; ?>public/js/main.js"></script>
    <?php if($is_logged_in): ?>
    <script src="<?php echo $base_url; ?>public/js/notifications.js"></script>
    <?php endif; ?>
</body>
</html>