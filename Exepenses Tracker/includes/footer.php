<?php
// =======================
// Current Page Logic
// =======================
$current_page = basename($_SERVER['PHP_SELF']);

// Active helper function
function active($pages, $current) {
    return in_array($current, (array)$pages);
}
?>

<nav class="fixed bottom-0 w-full max-w-480 bg-white border-t border-gray-200 shadow-xl z-50">
    <div class="flex items-center justify-between px-2 py-2 relative h-[70px]">

        <div class="flex-1 flex justify-center gap-2">

            <a href="dashboard.php" 
               class="tab <?php echo active('dashboard.php', $current_page) ? 'tab-active' : ''; ?>">
                <i class="fas fa-home tab-icon"></i>
                <span class="tab-label">Home</span>
                <?php if (active('dashboard.php', $current_page)): ?>
                    <span class="tab-dot"></span>
                <?php endif; ?>
            </a>

            <a href="expenses.php" 
               class="tab <?php echo active(['expenses.php','edit-expense.php'], $current_page) ? 'tab-active' : ''; ?>">
                <i class="fas fa-list tab-icon"></i>
                <span class="tab-label">Expenses</span>
                <?php if (active(['expenses.php','edit-expense.php'], $current_page)): ?>
                    <span class="tab-dot"></span>
                <?php endif; ?>
            </a>

        </div>

        <div class="relative z-50 -top-6 mx-1">
            <a href="add-expense.php" class="fab">
                <i class="fas fa-plus text-xl"></i>
            </a>
        </div>

        <div class="flex-1 flex justify-center gap-2">

            <a href="reports.php" 
               class="tab <?php echo active('reports.php', $current_page) ? 'tab-active' : ''; ?>">
                <i class="fas fa-chart-pie tab-icon"></i>
                <span class="tab-label">Reports</span>
                <?php if (active('reports.php', $current_page)): ?>
                    <span class="tab-dot"></span>
                <?php endif; ?>
            </a>

            <a href="profile.php" 
               class="tab <?php echo active(['profile.php','settings.php'], $current_page) ? 'tab-active' : ''; ?>">
                <i class="fas fa-user tab-icon"></i>
                <span class="tab-label">Profile</span>
                <?php if (active(['profile.php','settings.php'], $current_page)): ?>
                    <span class="tab-dot"></span>
                <?php endif; ?>
            </a>

        </div>

    </div>
</nav>

<div class="h-24"></div>

<style>
@import url('https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap');
/* ---------- UNIFORM TAB STYLE ---------- */
.tab {
    /* 1. FIXED DIMENSIONS */
    width: 68px;          
    height: 56px;         
    
    /* 2. LAYOUT */
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    border-radius: 12px;  
    padding: 0;

    /* 3. COLORS */
    background: transparent;
    color: #94a3b8;       /* Inactive Gray */
    transition: all 0.2s ease-in-out;
    position: relative;
    text-decoration: none;
    
    /* 4. PREVENT RESIZING */
    flex-shrink: 0;
    flex-grow: 0;
}

/* Hover Effect */
.tab:hover {
    color: #3b82f6;
    background-color: #f8fafc;
}

/* ---------- ACTIVE STATE ---------- */
.tab-active {
    background: #eff6ff;  /* blue-50 */
    color: #2563eb;       /* blue-600 */
    /* REMOVED: font-weight: 600; -> Removed to keep name style consistent */
}

/* ---------- ICON ---------- */
.tab-icon {
    font-size: 1.25rem;   /* 20px */
    margin-bottom: 4px;   
}

/* ---------- LABEL ---------- */
.tab-label {
    font-size: 0.7rem;    /* 11px */
    line-height: 1;
    font-weight: bold; 
     font-family: 'Poppins', sans-serif;   /* Fixed font weight for BOTH active and inactive */
}

/* ---------- DOT ---------- */
.tab-dot {
    position: absolute;
    top: 5px;
    width: 5px;
    height: 5px;
    background: #2563eb;
    border-radius: 9999px;
    animation: pulse 2s infinite;
}

/* ---------- FAB BUTTON ---------- */
.fab {
    width: 56px;
    height: 56px;
    border-radius: 9999px;
    background: linear-gradient(135deg, #2563eb, #1d4ed8);
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    box-shadow: 0 8px 20px rgba(37, 99, 235, 0.4);
    transition: all 0.2s ease;
    border: 4px solid #fff;
}

.fab:active {
    transform: scale(0.95);
}

/* ---------- MOBILE FIXES ---------- */
@media (max-width: 480px) {
    nav a {
        -webkit-tap-highlight-color: transparent;
        user-select: none;
    }
}

@keyframes pulse {
    0%, 100% { opacity: 1; transform: scale(1); }
    50% { opacity: 0.6; transform: scale(1.2); }
}
</style>