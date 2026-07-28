<?php
/**
 * Sign out and return to the login screen.
 */

require __DIR__ . '/../includes/app.php';
require __DIR__ . '/includes/auth.php';

admin_logout();
flash('You have been signed out.', 'info');
redirect('admin/login.php');
