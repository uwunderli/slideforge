<?php
require __DIR__ . '/../config.php';
Auth::requireAdmin();
redirect('admin_settings.php?tab=users');
