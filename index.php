<?php
require_once __DIR__ . '/bootstrap.php';

redirect(current_user_id() !== null ? 'chat.php' : 'login.php');
