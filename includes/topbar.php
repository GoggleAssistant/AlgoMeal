<?php
// Default to Overview if title isn't properly set by including page
$page_title = $page_title ?? 'Overview';
?>    
    <!-- Main Content wrapper opened outside but managed contextually -->
    <main class="main-content">
        <!-- Top App Bar -->
        <header class="topbar">
            <h2 class="page-title"><?php echo htmlspecialchars($page_title); ?></h2>
            <div class="user-profile">
                <div class="user-info">
                    <div class="user-name"><?php echo htmlspecialchars($username); ?></div>
                    <div class="user-role"><?php echo htmlspecialchars($role); ?></div>
                </div>
                <!-- First letter of username for avatar -->
                <div class="user-avatar"><?php echo strtoupper(substr($username, 0, 1)); ?></div>
            </div>
        </header>
