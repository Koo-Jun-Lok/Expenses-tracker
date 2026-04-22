<?php
// --- Includes and Dependencies ---
require_once 'includes/config.php';
require_once 'includes/auth-check.php';
require_once 'includes/session-handler.php';
require_once 'includes/currency-helper.php';

// Ensure the user is logged in before accessing the dashboard
requireLogin();

// --- 1. User & Currency Setup ---
// Get current logged-in user details and currency settings (from cookies/helper)
$userInfo = getUserInfo($pdo, getUserId());
$currency = getCurrencyConfig(); 

// --- 2. Statistics Calculation ---
$current_month = date('Y-m');
$user_id = getUserId();

// Query to get Total Income and Total Expense for the CURRENT MONTH
$stmt = $pdo->prepare("SELECT 
    COALESCE(SUM(CASE WHEN type = 'income' THEN amount ELSE 0 END), 0) as total_income,
    COALESCE(SUM(CASE WHEN type = 'expense' THEN amount ELSE 0 END), 0) as total_expense,
    COUNT(*) as total_count
    FROM expenses 
    WHERE user_id = ? AND DATE_FORMAT(expense_date, '%Y-%m') = ?");
$stmt->execute([$user_id, $current_month]);
$stats = $stmt->fetch(PDO::FETCH_ASSOC);

$total_income = (float)$stats['total_income'];
$total_expense = (float)$stats['total_expense'];

// Core Logic: Calculate Net Balance (Income - Expense)
$balance = $total_income - $total_expense; 

// --- 3. Recent Transactions Fetching ---
// Fetch the 5 most recent transactions for the list
$stmt = $pdo->prepare("SELECT * FROM expenses 
    WHERE user_id = ? 
    ORDER BY expense_date DESC, created_at DESC 
    LIMIT 5");
$stmt->execute([$user_id]);
$recent_expenses = $stmt->fetchAll(PDO::FETCH_ASSOC);

// --- 4. Dynamic Category Styling (New Method) ---
// Fetch category names, icons, and colors directly from the database
// This replaces hardcoded arrays, allowing dynamic updates from the DB
$stmt = $pdo->query("SELECT name, icon, color FROM categories");
$category_map = [];
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    // Map category name to its icon and color for easy lookup later
    $category_map[$row['name']] = [
        'icon' => $row['icon'],
        'color' => $row['color'] // e.g., #3B82F6
    ];
}
?>
<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap');
        body { font-family: 'Plus Jakarta Sans', sans-serif; background-color: #F8FAFC; }
        .mobile-container { max-width: 480px; margin: 0 auto; }
        .hide-scrollbar::-webkit-scrollbar { display: none; }
        
        /* Custom Glassmorphism effect for the Balance Card */
        .glass-card {
            background: linear-gradient(135deg, #0F172A 0%, #1E293B 100%);
            box-shadow: 0 20px 40px -10px rgba(15, 23, 42, 0.4);
        }

        /* --- NEW: Page Entry Animation Styles --- */
        @keyframes enterAnimation {
            0% {
                opacity: 0;
                transform: translateY(30px); /* Start slightly lower (Off-canvas feel) */
            }
            100% {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .animate-enter {
            opacity: 0; /* Hidden initially to prevent flash */
            animation: enterAnimation 0.8s cubic-bezier(0.16, 1, 0.3, 1) forwards; /* Smooth "Premium" easing */
        }

        /* Stagger the animation for the main content */
        .animate-delay-100 {
            animation-delay: 0.1s;
        }
    </style>
</head>
<body class="text-slate-800 h-full">
    <div class="mobile-container min-h-screen flex flex-col relative bg-[#F8FAFC] overflow-x-hidden">
        
        <header class="px-6 pt-10 pb-4 flex justify-between items-center sticky top-0 z-30 bg-[#F8FAFC]/90 backdrop-blur-sm animate-enter">
            <div class="flex items-center gap-3">
                <div class="flex items-center gap-3">
                    <div class="w-11 h-11 rounded-full bg-gradient-to-tr from-blue-600 to-indigo-600 flex items-center justify-center text-white font-bold text-lg shadow-lg shadow-blue-500/30">
                        <?php echo strtoupper(substr($userInfo['username'], 0, 1)); ?>
                    </div>
                    <div>
                        <p class="text-xs text-slate-400 font-medium">Welcome back,</p>
                        <h1 class="text-base font-bold text-slate-900"><?php echo htmlspecialchars($userInfo['username'] ?: $userInfo['full_name']); ?></h1>
                    </div>
                </div>
            </div>
            
<div class="flex gap-2">
    <button id="locationTrigger" 
            class="flex items-center gap-3 px-4 py-3 rounded-xl shadow-lg shadow-blue-500/30 backdrop-blur-sm 
                   bg-gradient-to-tr from-blue-600 to-indigo-600 hover:from-blue-700 hover:to-indigo-700 
                   active:scale-95 transition-all duration-300 group">
        
        <!-- Currency Flag -->
        <div class="w-10 h-10 rounded-full bg-white/20 flex items-center justify-center">
            <span class="text-xl leading-none text-white"><?php echo $currency['flag']; ?></span>
        </div>
        
        <!-- Currency Code -->
        <div class="text-left">
            <p class="text-xs text-blue-100/80 font-medium">Currency</p>
            <h2 class="text-sm font-bold text-white"><?php echo $currency['code']; ?></h2>
        </div>
        
        <!-- Dropdown Icon -->
        <div class="ml-2">
            <i class="fas fa-chevron-down text-blue-100/80 text-xs group-hover:translate-y-0.5 transition-transform"></i>
        </div>
        
    </button>
</div>
        </header>

        <main class="flex-grow px-6 pb-32 overflow-y-auto hide-scrollbar animate-enter animate-delay-100">
            
            <div class="mt-4 relative w-full rounded-[2rem] overflow-hidden glass-card group transition-all duration-300">
                <div class="absolute top-[-50%] right-[-20%] w-64 h-64 bg-blue-500/20 rounded-full blur-[80px]"></div>
                <div class="absolute bottom-[-50%] left-[-20%] w-64 h-64 bg-indigo-500/20 rounded-full blur-[80px]"></div>

                <div class="relative z-10 p-6 flex flex-col h-full">
                    <div class="flex justify-between items-start mb-6">
                        <div>
                            <p class="text-slate-400 text-[10px] font-bold tracking-widest uppercase mb-1">Total Balance</p>
                            <h2 class="text-3xl font-extrabold text-white tracking-tight leading-none mb-3">
                                <?php echo formatCurrency($balance); ?>
                            </h2>
                            <div class="inline-flex items-center gap-2 px-3 py-1.5 rounded-lg bg-white/5 border border-white/10 backdrop-blur-md">
                                <i class="fas fa-globe-americas text-blue-400 text-xs"></i>
                                <span class="text-[10px] font-medium text-slate-300">
                                    <?php 
                                        if($currency['code'] !== 'MYR') {
                                            echo "1 MYR ≈ " . number_format($currency['rate'], 4) . " " . $currency['code'];
                                        } else {
                                            echo "Base Currency (MYR)";
                                        }
                                    ?>
                                </span>
                            </div>
                        </div>
                        <div class="w-10 h-10 rounded-2xl bg-white/5 border border-white/10 flex items-center justify-center backdrop-blur-sm shadow-inner">
                            <i class="fas fa-wallet text-white/80"></i>
                        </div>
                    </div>

<div class="flex gap-3">
    <div class="w-1/2 bg-white/5 border border-white/5 rounded-2xl p-3 flex items-center gap-3 backdrop-blur-sm hover:bg-white/10 transition-colors">
        <div class="w-8 h-8 rounded-full bg-emerald-500/20 flex items-center justify-center text-emerald-400">
            <i class="fas fa-arrow-down text-xs"></i>
        </div>
        <div>
            <p class="text-[9px] text-slate-400 font-bold uppercase">Income</p>
            <p class="text-xs font-bold text-white"><?php echo formatCurrency($total_income); ?></p>
        </div>
    </div>
    <div class="w-1/2 bg-white/5 border border-white/5 rounded-2xl p-3 flex items-center gap-3 backdrop-blur-sm hover:bg-white/10 transition-colors">
        <div class="w-8 h-8 rounded-full bg-rose-500/20 flex items-center justify-center text-rose-400">
            <i class="fas fa-arrow-up text-xs"></i>
        </div>
        <div>
            <p class="text-[9px] text-slate-400 font-bold uppercase">Expense</p>
            <p class="text-xs font-bold text-white"><?php echo formatCurrency($total_expense); ?></p>
        </div>
    </div>
</div>
                </div>
            </div>

            <div class="mt-8">
                <h3 class="font-bold text-slate-800 text-sm mb-4 px-1">Quick Actions</h3>
                <div class="grid grid-cols-4 gap-4">
                    <a href="add-expense.php" class="flex flex-col items-center gap-2 group cursor-pointer">
                        <div class="w-14 h-14 rounded-[1.2rem] bg-blue-600 text-white flex items-center justify-center shadow-lg shadow-blue-200 group-active:scale-90 transition-all duration-300">
                            <i class="fas fa-plus text-lg"></i>
                        </div>
                        <span class="text-[10px] font-bold text-slate-600 group-hover:text-blue-600 transition-colors">Add New</span>
                    </a>
                    <a href="expenses.php" class="flex flex-col items-center gap-2 group cursor-pointer">
                        <div class="w-14 h-14 rounded-[1.2rem] bg-white text-slate-600 flex items-center justify-center shadow-sm border border-slate-100 group-active:scale-90 transition-all duration-300">
                            <i class="fas fa-list-ul text-lg"></i>
                        </div>
                        <span class="text-[10px] font-bold text-slate-600">History</span>
                    </a>
                    <a href="reports.php" class="flex flex-col items-center gap-2 group cursor-pointer">
                        <div class="w-14 h-14 rounded-[1.2rem] bg-white text-slate-600 flex items-center justify-center shadow-sm border border-slate-100 group-active:scale-90 transition-all duration-300">
                            <i class="fas fa-chart-pie text-lg"></i>
                        </div>
                        <span class="text-[10px] font-bold text-slate-600">Report</span>
                    </a>
                    <a href="profile.php" class="flex flex-col items-center gap-2 group cursor-pointer">
                        <div class="w-14 h-14 rounded-[1.2rem] bg-white text-slate-600 flex items-center justify-center shadow-sm border border-slate-100 group-active:scale-90 transition-all duration-300">
                            <i class="fas fa-user text-lg"></i>
                        </div>
                        <span class="text-[10px] font-bold text-slate-600">Profile</span>
                    </a>
                </div>
            </div>

            <div class="mt-8">
                <div class="flex justify-between items-center mb-4 px-1">
                    <h3 class="font-bold text-slate-800 text-sm">Recent Transactions</h3>
                    <a href="expenses.php" class="text-[10px] font-bold text-blue-600 bg-blue-50 px-2.5 py-1 rounded-full hover:bg-blue-100 transition-colors">View All</a>
                </div>

                <div class="space-y-3">
                    <?php if (empty($recent_expenses)): ?>
                        <div class="bg-white rounded-3xl p-10 text-center shadow-sm border border-slate-100">
                            <div class="w-14 h-14 bg-slate-50 rounded-full flex items-center justify-center mx-auto mb-3 text-slate-300">
                                <i class="fas fa-receipt text-xl"></i>
                            </div>
                            <p class="text-xs font-semibold text-slate-400">No recent transactions.</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($recent_expenses as $t): 
                            // --- NEW LOGIC: Dynamic Styling ---
                            $catName = $t['category'];
                            $styleData = $category_map[$catName] ?? null; 

                            if ($styleData) {
                                $icon = $styleData['icon'];
                                $hexColor = $styleData['color'];
                                $iconClass = "bg-[{$hexColor}]/10 text-[{$hexColor}]"; 
                            } else {
                                $icon = '📝';
                                $iconClass = "bg-slate-50 text-slate-500";
                            }

                            $isIncome = ($t['type'] === 'income');
                            $amountColor = $isIncome ? 'text-emerald-600' : 'text-slate-900';
                            $sign = $isIncome ? '+' : '-';
                        ?>
                        <div class="group bg-white p-4 rounded-2xl shadow-sm border border-slate-100 flex items-center justify-between hover:shadow-md transition-all cursor-pointer active:scale-[0.98]" onclick="window.location.href='expenses.php'">
                            <div class="flex items-center space-x-4">
                                <div class="w-12 h-12 rounded-2xl <?php echo $iconClass; ?> flex items-center justify-center text-xl shadow-sm border border-black/5 group-hover:scale-105 transition-transform">
                                    <?php echo $icon; ?>
                                </div>
                                <div class="min-w-0">
                                    <h4 class="font-bold text-slate-800 text-sm leading-tight truncate"><?php echo htmlspecialchars($t['category']); ?></h4>
                                    <p class="text-[10px] text-slate-400 mt-1 truncate w-24 font-medium"><?php echo htmlspecialchars($t['description'] ?: 'No description'); ?></p>
                                </div>
                            </div>
                            <div class="text-right">
                                <span class="font-extrabold text-sm block <?php echo $amountColor; ?>">
                                    <?php echo $sign . formatCurrency($t['amount']); ?>
                                </span>
                                <span class="text-[10px] text-slate-400 font-medium"><?php echo date('d M', strtotime($t['expense_date'])); ?></span>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

        </main>

        <?php include 'includes/footer.php'; ?>

        <div class="p-6 border-t border-slate-100 hidden">
            <button onclick="document.getElementById('logoutModal').classList.remove('hidden')" class="flex items-center justify-center gap-3 w-full bg-rose-50 text-rose-600 font-bold py-3.5 rounded-2xl hover:bg-rose-100 transition-colors">
                <i class="fas fa-sign-out-alt"></i> Log Out
            </button>
        </div>
    </div>

    <div id="locationModal" class="fixed inset-0 z-[60] hidden">
        <div id="modalBackdrop" class="absolute inset-0 bg-slate-900/40 backdrop-blur-sm opacity-0 transition-opacity duration-300"></div>
        <div id="modalContent" class="absolute bottom-0 left-0 right-0 bg-white rounded-t-[2.5rem] p-6 pb-12 transform translate-y-full transition-transform duration-300">
            <div class="w-12 h-1.5 bg-slate-200 rounded-full mx-auto mb-8"></div>
            <h3 class="text-lg font-extrabold text-slate-900 mb-6 px-1">Switch Currency</h3>
            <div class="grid grid-cols-2 gap-3" id="countryGrid"></div>
        </div>
    </div>

    <div id="logoutModal" class="fixed inset-0 z-[70] hidden">
        <div class="absolute inset-0 bg-slate-900/50 backdrop-blur-sm" onclick="this.parentElement.classList.add('hidden')"></div>
        <div class="absolute top-1/2 left-1/2 transform -translate-x-1/2 -translate-y-1/2 bg-white w-[85%] max-w-sm rounded-[2rem] p-6 shadow-2xl">
            <div class="text-center">
                <div class="w-16 h-16 bg-rose-50 text-rose-500 rounded-full flex items-center justify-center mx-auto mb-4 text-2xl">
                    <i class="fas fa-power-off"></i>
                </div>
                <h3 class="text-xl font-bold text-slate-900 mb-2">Log Out</h3>
                <p class="text-sm text-slate-500 mb-8 font-medium">Are you sure you want to exit?</p>
                <div class="grid grid-cols-2 gap-4">
                    <button onclick="document.getElementById('logoutModal').classList.add('hidden')" class="py-3.5 rounded-2xl bg-slate-100 text-slate-700 text-sm font-bold hover:bg-slate-200 transition-colors">Cancel</button>
                    <a href="logout.php" class="py-3.5 rounded-2xl bg-rose-600 text-white text-sm font-bold shadow-lg shadow-rose-200 hover:bg-rose-700 transition-colors flex items-center justify-center">Yes, Logout</a>
                </div>
            </div>
        </div>
    </div>

    <div id="loadingOverlay" class="fixed inset-0 bg-white/90 z-[80] hidden flex items-center justify-center backdrop-blur-sm">
        <div class="flex flex-col items-center">
            <div class="w-10 h-10 border-4 border-blue-600 border-t-transparent rounded-full animate-spin mb-4"></div>
            <p class="text-xs font-bold text-slate-500 uppercase tracking-widest">Updating</p>
        </div>
    </div>

    <script src="js/main.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            // --- Currency Modal Logic ---
            const locTrigger = document.getElementById('locationTrigger');
            const locModal = document.getElementById('locationModal');
            const backdrop = document.getElementById('modalBackdrop');
            const content = document.getElementById('modalContent');
            const grid = document.getElementById('countryGrid');
            const loader = document.getElementById('loadingOverlay');

            const currentCode = "<?php echo $currency['code']; ?>";
            
            // List of supported currencies
            const countries = [
                { code: 'MYR', name: 'Malaysia', flag: '🇲🇾' },
                { code: 'USD', name: 'USA', flag: '🇺🇸' },
                { code: 'SGD', name: 'Singapore', flag: '🇸🇬' },
                { code: 'CNY', name: 'China', flag: '🇨🇳' },
                { code: 'EUR', name: 'Europe', flag: '🇪🇺' },
                { code: 'GBP', name: 'UK', flag: '🇬🇧' },
                { code: 'JPY', name: 'Japan', flag: '🇯🇵' },
            ];

            // Render Currency Buttons
            countries.forEach(c => {
                const isActive = c.code === currentCode;
                const btn = document.createElement('button');
                btn.className = `flex items-center p-3.5 rounded-2xl border transition-all active:scale-95 ${isActive ? 'bg-blue-50 border-blue-200 ring-1 ring-blue-500' : 'bg-white border-slate-100 hover:border-slate-300'}`;
                btn.innerHTML = `
                    <span class="text-3xl mr-3 shadow-sm rounded-full overflow-hidden">${c.flag}</span>
                    <div class="text-left">
                        <div class="font-bold text-sm ${isActive ? 'text-blue-700' : 'text-slate-800'}">${c.code}</div>
                        <div class="text-[10px] text-slate-400 font-bold tracking-wide uppercase">${c.name}</div>
                    </div>
                `;
                btn.onclick = () => switchCurrency(c.code, c.flag);
                grid.appendChild(btn);
            });

            // Functions to open/close currency modal with animation
            function openModal() {
                locModal.classList.remove('hidden');
                setTimeout(() => {
                    backdrop.classList.remove('opacity-0');
                    content.classList.remove('translate-y-full');
                }, 10);
            }

            function closeModal() {
                backdrop.classList.add('opacity-0');
                content.classList.add('translate-y-full');
                setTimeout(() => locModal.classList.add('hidden'), 300);
            }

            locTrigger.addEventListener('click', openModal);
            backdrop.addEventListener('click', closeModal);

            // Handle Currency Switch via API
            async function switchCurrency(code, flag) {
                closeModal();
                // If switching back to base currency (MYR), no API call needed
                if(code === 'MYR') {
                    setCookie('MYR', 1, '🇲🇾');
                    location.reload();
                    return;
                }
                
                // Show loading state and fetch rate
                loader.classList.remove('hidden');
                try {
                    const res = await fetch('https://open.er-api.com/v6/latest/MYR');
                    const data = await res.json();
                    if(data.result === 'success') {
                        setCookie(code, data.rates[code], flag);
                        location.reload();
                    }
                } catch(e) {
                    alert('Error updating rates');
                    loader.classList.add('hidden');
                }
            }

            // Save currency choice to cookie for 30 days
            function setCookie(code, rate, flag) {
                const d = new Date(); d.setTime(d.getTime() + (30*24*60*60*1000));
                document.cookie = `app_currency=${code};expires=${d.toUTCString()};path=/`;
                document.cookie = `app_rate=${rate};expires=${d.toUTCString()};path=/`;
                document.cookie = `app_flag=${flag};expires=${d.toUTCString()};path=/`;
            }

            // Logout Event Listener
            const logoutTrigger = document.getElementById('logoutTrigger');
            if(logoutTrigger) {
                logoutTrigger.addEventListener('click', (e) => {
                    e.preventDefault();
                    document.getElementById('logoutModal').classList.remove('hidden');
                });
            }
        });
    </script>
</body>
</html>