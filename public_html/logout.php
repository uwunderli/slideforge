<?php
require __DIR__ . '/../config.php';
Auth::logout();
redirect('login.php');
