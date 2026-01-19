<?php
// Get current page for active nav link
$current_page = basename($_SERVER['PHP_SELF'], '.php');
?>
<nav class="navbar" id="navbar">
    <div class="container">
        <div class="nav-wrapper">
            <a href="<?php echo $base_url; ?>" class="logo">
                <span class="material-icons">school</span>
                <span class="logo-text">EduVerse</span>
            </a>
            
            <div class="nav-menu" id="navMenu">
                <a href="<?php echo $base_url; ?>" class="nav-link <?php echo ($current_page == 'index') ? 'active' : ''; ?>">Home</a>
                <a href="<?php echo $base_url; ?>courses" class="nav-link <?php echo ($current_page == 'courses') ? 'active' : ''; ?>">Courses</a>
                <a href="<?php echo $base_url; ?>categories" class="nav-link <?php echo ($current_page == 'categories') ? 'active' : ''; ?>">Categories</a>
                <a href="<?php echo $base_url; ?>instructors" class="nav-link <?php echo ($current_page == 'instructors') ? 'active' : ''; ?>">Instructors</a>
                <a href="<?php echo $base_url; ?>about" class="nav-link <?php echo ($current_page == 'about') ? 'active' : ''; ?>">About</a>
            </div>
            
            <div class="nav-actions">
                <button class="btn-icon wishlist-icon" data-action="wishlist-view" title="Wishlist" style="position: relative;">
                    <span class="material-icons">favorite_border</span>
                    <span class="wishlist-badge" style="position: absolute; top: -4px; right: -4px; background: #ef4444; color: white; font-size: 0.625rem; font-weight: 700; padding: 2px 6px; border-radius: 10px; display: none;">0</span>
                </button>
                <button class="btn-icon cart-icon" data-action="cart" title="Cart" style="position: relative;">
                    <span class="material-icons">shopping_cart</span>
                    <span class="cart-badge" style="position: absolute; top: -4px; right: -4px; background: #6366f1; color: white; font-size: 0.625rem; font-weight: 700; padding: 2px 6px; border-radius: 10px; display: none;">0</span>
                </button>
                <?php if($is_logged_in): ?>
                <div class="navbar-icons" style="display: inline-block;"></div>
                <?php endif; ?>
                
                <?php if($is_logged_in): ?>
                    <?php
                        // Get user's full profile data
                        $user_data = get_user_by_id($con, $current_user_id);
                        $user_profile_image = $user_data['profile_image_url'] ?? '';
                        $user_first_name = $user_data['first_name'] ?? '';
                        $user_last_name = $user_data['last_name'] ?? '';
                        $user_initials = strtoupper(substr($user_first_name, 0, 1) . substr($user_last_name, 0, 1));
                        if(empty($user_initials)) {
                            $user_initials = strtoupper(substr($current_user['username'] ?? 'U', 0, 2));
                        }
                    ?>
                    <div class="nav-user" style="display: flex; align-items: center; gap: 12px; position: relative;">
                        <button class="btn-icon user-menu-toggle" id="userMenuToggle" title="User Menu" style="border-radius: 50%; padding: 0; overflow: hidden; width: 40px; height: 40px; border: 2px solid #e5e7eb; transition: all 0.3s ease;">
                            <?php if(!empty($user_profile_image)): ?>
                                <img src="<?php echo htmlspecialchars($base_url . $user_profile_image); ?>" alt="Profile" style="width: 100%; height: 100%; object-fit: cover;">
                            <?php else: ?>
                                <div style="width: 100%; height: 100%; display: flex; align-items: center; justify-content: center; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; font-weight: 600; font-size: 0.875rem;">
                                    <?php echo $user_initials; ?>
                                </div>
                            <?php endif; ?>
                        </button>
                        <div class="user-dropdown" id="userDropdown" style="display: none; position: absolute; top: 50px; right: 0; background: white; border: 1px solid #e5e7eb; border-radius: 12px; box-shadow: 0 10px 25px rgba(0,0,0,0.1); z-index: 1000; min-width: 240px; overflow: hidden;">
                            <div style="padding: 16px; border-bottom: 1px solid #e5e7eb; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                                <div style="display: flex; align-items: center; gap: 12px;">
                                    <div style="width: 50px; height: 50px; border-radius: 50%; overflow: hidden; border: 3px solid white;">
                                        <?php if(!empty($user_profile_image)): ?>
                                            <img src="<?php echo htmlspecialchars($base_url . $user_profile_image); ?>" alt="Profile" style="width: 100%; height: 100%; object-fit: cover;">
                                        <?php else: ?>
                                            <div style="width: 100%; height: 100%; display: flex; align-items: center; justify-content: center; background: rgba(255,255,255,0.2); color: white; font-weight: 700; font-size: 1.125rem;">
                                                <?php echo $user_initials; ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    <div style="flex: 1;">
                                        <p style="font-weight: 700; margin: 0; color: white; font-size: 1rem;"><?php echo htmlspecialchars($user_first_name . ' ' . $user_last_name) ?: htmlspecialchars($current_user['username'] ?? 'User'); ?></p>
                                        <p style="font-size: 0.8125rem; color: rgba(255,255,255,0.9); margin: 4px 0 0 0; overflow: hidden; text-overflow: ellipsis;"><?php echo htmlspecialchars($current_user['email'] ?? ''); ?></p>
                                    </div>
                                </div>
                            </div>
                            <div style="padding: 8px 0;">
                                <?php if($current_user['role'] === 'instructor'): ?>
                                    <a href="<?php echo $base_url; ?>dashboard/instructor" style="display: flex; align-items: center; gap: 12px; padding: 12px 16px; color: #1f2937; text-decoration: none; transition: background 0.2s; border-bottom: 1px solid #f3f4f6;">
                                        <span class="material-icons" style="font-size: 20px; color: #667eea;">school</span>
                                        <span style="font-weight: 500;">Instructor Dashboard</span>
                                    </a>
                                <?php endif; ?>
                                <?php if(in_array($current_user['role'], ['admin'])): ?>
                                    <a href="<?php echo $base_url; ?>dashboard/admin" style="display: flex; align-items: center; gap: 12px; padding: 12px 16px; color: #1f2937; text-decoration: none; transition: background 0.2s; border-bottom: 1px solid #f3f4f6;">
                                        <span class="material-icons" style="font-size: 20px; color: #f59e0b;">admin_panel_settings</span>
                                        <span style="font-weight: 500;">Admin Panel</span>
                                    </a>
                                <?php endif; ?>
                                <?php if(in_array($current_user['role'], ['student'])): ?>
                                    <a href="<?php echo $base_url; ?>dashboard/student" style="display: flex; align-items: center; gap: 12px; padding: 12px 16px; color: #1f2937; text-decoration: none; transition: background 0.2s; border-bottom: 1px solid #f3f4f6;">
                                        <span class="material-icons" style="font-size: 20px; color: #10b981;">dashboard</span>
                                        <span style="font-weight: 500;">My Dashboard</span>
                                    </a>
                                <?php endif; ?>
                                <a href="<?php echo $base_url; ?>logout" id="logoutBtn" class="logout-btn" style="display: flex; align-items: center; gap: 12px; padding: 12px 16px; color: #ef4444; text-decoration: none; transition: background 0.2s;">
                                    <span class="material-icons" style="font-size: 20px;">logout</span>
                                    <span style="font-weight: 500;">Logout</span>
                                </a>
                            </div>
                        </div>
                    </div>
                <?php else: ?>
                    <a href="<?php echo $base_url; ?>login" class="btn btn-secondary">Login</a>
                    <a href="<?php echo $base_url; ?>signup" class="btn btn-primary">Sign Up</a>
                <?php endif; ?>
                
                <button class="nav-toggle" id="navToggle">
                    <span></span>
                    <span></span>
                    <span></span>
                </button>
            </div>
        </div>
    </div>
</nav>
