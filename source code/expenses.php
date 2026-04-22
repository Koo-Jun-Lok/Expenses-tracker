<?php
// --- Includes and Dependencies ---
require_once 'includes/config.php';
require_once 'includes/auth-check.php';
require_once 'includes/session-handler.php';
require_once 'includes/currency-helper.php';

// Ensure the user is logged in
requireLogin();
$user_id = getUserId();
$currencyConfig = getCurrencyConfig();

// --- 1. View State Handling (NEW) ---
// Determine which view to show: 'details' (default) or 'calendar'
$current_view = $_GET['view'] ?? 'details';

// --- 2. Delete Operation ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
    try {
        $stmt = $pdo->prepare("DELETE FROM expenses WHERE id = ? AND user_id = ?");
        $stmt->execute([$_POST['delete_id'], $user_id]);
        
        // Build redirect URL preserving current view and params
        $redirectUrl = "expenses.php?" . $_SERVER['QUERY_STRING'];
        if (strpos($redirectUrl, 'view=') === false) {
            $redirectUrl .= "&view=" . $current_view;
        }
        header("Location: " . $redirectUrl);
        exit;
    } catch (PDOException $e) {
        // error handling
    }
}

// --- 3. Filter Parameters ---
$range = $_GET['range'] ?? 'month'; 
$custom_date = $_GET['date'] ?? date('Y-m'); 
$search_query = $_GET['search'] ?? ''; 

// --- 4. Build Query ---
$where_clause = "user_id = ?";
$params = [$user_id];
$display_label = "";

// Handle Time Filter
if ($range === 'yesterday') {
    $where_clause .= " AND DATE(expense_date) = CURDATE() - INTERVAL 1 DAY";
    $display_label = "Yesterday";
} elseif ($range === 'week') {
    $where_clause .= " AND YEARWEEK(expense_date, 1) = YEARWEEK(CURDATE(), 1)";
    $display_label = "This Week";
} elseif ($range === 'last_week') {
    $where_clause .= " AND YEARWEEK(expense_date, 1) = YEARWEEK(CURDATE(), 1) - 1";
    $display_label = "Last Week";
} else {
    $where_clause .= " AND DATE_FORMAT(expense_date, '%Y-%m') = ?";
    $params[] = $custom_date;
    $display_label = date('M Y', strtotime($custom_date));
}

// Handle Search Query
if (!empty($search_query)) {
    $where_clause .= " AND (category LIKE ? OR description LIKE ? OR location LIKE ?)";
    $term = "%" . $search_query . "%";
    $params[] = $term;
    $params[] = $term;
    $params[] = $term;
}

$sql = "SELECT * FROM expenses WHERE $where_clause ORDER BY expense_date DESC, created_at DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$expenses = $stmt->fetchAll(PDO::FETCH_ASSOC);

// --- 5. Styles & Data Prep ---
$stmt = $pdo->query("SELECT name, icon, color FROM categories");
$category_map = [];
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $hex = $row['color'];
    $category_map[$row['name']] = [
        'icon' => $row['icon'],
        'bg'   => "bg-[{$hex}]/10", 
        'text' => "text-[{$hex}]"   
    ];
}

$sum_income = 0;
$sum_expense = 0;
$grouped_list = [];
$daily_data = [];
$js_calendar_data = []; 

foreach ($expenses as $ex) {
    $amt = (float)$ex['amount'];
    $date = $ex['expense_date'];
    
    if (!isset($daily_data[$date])) $daily_data[$date] = 0;

    if ($ex['type'] === 'income') {
        $sum_income += $amt;
        $daily_data[$date] -= $amt; 
    } else {
        $sum_expense += $amt;
        $daily_data[$date] += $amt; 
    }
    
    if (!isset($grouped_list[$date])) $grouped_list[$date] = [];
    $grouped_list[$date][] = $ex;

    $timePart = date('H:i:s', strtotime($ex['created_at']));
    $properDateTime = $ex['expense_date'] . ' ' . $timePart;
    $formattedDate = date('F j, Y h:i A', strtotime($properDateTime));

    $finalReceiptPath = '';
    if (!empty($ex['receipt_image'])) {
        if (strpos($ex['receipt_image'], 'data:image') === 0) {
            $finalReceiptPath = $ex['receipt_image'];
        } else {
            $finalReceiptPath = 'uploads/receipts/' . basename($ex['receipt_image']);
        }
    }

    $catName = $ex['category'];
    $style = $category_map[$catName] ?? ['bg' => 'bg-slate-50', 'text' => 'text-slate-500', 'icon' => '📝'];

    $isIncome = ($ex['type'] === 'income');
    $sign = $isIncome ? '+' : '-';
    
    if (!isset($js_calendar_data[$date])) $js_calendar_data[$date] = [];
    $js_calendar_data[$date][] = [
        'id' => $ex['id'],
        'category' => $ex['category'],
        'desc' => $ex['description'] ?: 'No Description',
        'amount_fmt' => $sign . formatCurrency($ex['amount']),
        'type' => $ex['type'],
        'date_fmt' => $formattedDate,
        'location' => $ex['location'] ?: 'Unknown Location',
        'icon' => $style['icon'],
        'bg' => $style['bg'],
        'text' => $style['text'],
        'amount_color' => $isIncome ? 'text-emerald-600' : 'text-gray-900',
        'receipt' => $finalReceiptPath
    ];
}
$net_total = $sum_income - $sum_expense;

$cal_view_month = ($range === 'month') ? $custom_date : date('Y-m');
$first_day = strtotime($cal_view_month . '-01');
$days_in_month = date('t', $first_day);
$start_weekday = date('w', $first_day); 

$prev_month = date('Y-m', strtotime($cal_view_month . ' -1 month'));
$next_month = date('Y-m', strtotime($cal_view_month . ' +1 month'));

// --- Helper Vars for View Logic ---
$isDetails = ($current_view === 'details');
$activeBtnClass = "w-9 h-9 rounded-full bg-white text-slate-900 shadow-sm flex items-center justify-center transition-all";
$inactiveBtnClass = "w-9 h-9 rounded-full text-slate-400 flex items-center justify-center transition-all hover:text-slate-600";
?>
<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Transactions</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap');
        body { font-family: 'Plus Jakarta Sans', sans-serif; background-color: #F8FAFC; }
        .mobile-container { max-width: 480px; margin: 0 auto; }
        .hide-scrollbar::-webkit-scrollbar { display: none; }
        .modal-enter { animation: slideUp 0.35s cubic-bezier(0.16, 1, 0.3, 1) forwards; }
        @keyframes slideUp { from { transform: translateY(100%); } to { transform: translateY(0); } }
        #detailModal { z-index: 100; }
        #imagePreviewModal { z-index: 200; }
        .selected-day { background-color: #2563EB !important; color: white !important; box-shadow: 0 4px 10px -2px rgba(37, 99, 235, 0.4); }
        @keyframes enterAnimation { 0% { opacity: 0; transform: translateY(30px); } 100% { opacity: 1; transform: translateY(0); } }
        .animate-enter { opacity: 0; animation: enterAnimation 0.6s cubic-bezier(0.16, 1, 0.3, 1) forwards; }
        .animate-delay-100 { animation-delay: 0.1s; }
    </style>
</head>
<body class="text-slate-800 h-full">
    <div class="mobile-container min-h-screen flex flex-col relative bg-[#F8FAFC]">
        
        <header class="bg-white sticky top-0 z-20 shadow-[0_2px_15px_-3px_rgba(0,0,0,0.07)] rounded-b-[2rem] animate-enter">
            <div class="px-6 pt-10 pb-6">
                <div class="flex justify-between items-center mb-4">
                    <div>
                        <h1 class="text-2xl font-extrabold text-slate-900 tracking-tight">Transactions</h1>
                    </div>
                    <div class="flex bg-slate-100 p-1 rounded-full">
                        <button onclick="toggleView('details')" id="btn-details" class="<?php echo $isDetails ? $activeBtnClass : $inactiveBtnClass; ?>">
                            <i class="fas fa-list-ul text-sm"></i>
                        </button>
                        <button onclick="toggleView('calendar')" id="btn-calendar" class="<?php echo !$isDetails ? $activeBtnClass : $inactiveBtnClass; ?>">
                            <i class="fas fa-calendar-alt text-sm"></i>
                        </button>
                    </div>
                </div>

                <form method="GET" id="searchSection" class="mb-6 relative <?php echo $isDetails ? '' : 'hidden'; ?>">
                    <input type="hidden" name="range" value="<?php echo htmlspecialchars($range); ?>">
                    <input type="hidden" name="date" value="<?php echo htmlspecialchars($custom_date); ?>">
                    <input type="hidden" name="view" value="details"> <div class="relative group">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <i class="fas fa-search text-slate-400 group-focus-within:text-blue-500 transition-colors"></i>
                        </div>
                        <input type="text" name="search" 
                               value="<?php echo htmlspecialchars($search_query); ?>" 
                               placeholder="Search category, notes..." 
                               class="block w-full pl-10 pr-10 py-3 rounded-2xl bg-slate-50 border-none text-sm font-semibold text-slate-700 placeholder-slate-400 focus:ring-2 focus:ring-blue-500/20 focus:bg-white transition-all shadow-sm">
                        
                        <?php if(!empty($search_query)): ?>
                            <a href="expenses.php?range=<?php echo $range; ?>&date=<?php echo $custom_date; ?>&view=details" 
                               class="absolute inset-y-0 right-0 pr-3 flex items-center cursor-pointer text-slate-400 hover:text-slate-600">
                                <i class="fas fa-times-circle"></i>
                            </a>
                        <?php endif; ?>
                    </div>
                </form>

                <div class="bg-[#1E293B] rounded-[1.75rem] p-6 text-white shadow-xl relative overflow-hidden ring-1 ring-white/10">
                    
                    <div id="filterSection" class="flex justify-between items-center mb-6 relative z-10 transition-all duration-300 <?php echo $isDetails ? '' : 'hidden'; ?>">
                        <form method="GET" class="flex items-center">
                            <input type="hidden" name="date" value="<?php echo $custom_date; ?>">
                            <input type="hidden" name="view" value="details">
                            <?php if(!empty($search_query)): ?>
                                <input type="hidden" name="search" value="<?php echo htmlspecialchars($search_query); ?>">
                            <?php endif; ?>
                            
                            <div class="relative group">
                                <select name="range" onchange="this.form.submit()" class="appearance-none bg-white/10 text-xs font-bold text-white py-2 pl-3 pr-8 rounded-xl border border-white/10 outline-none cursor-pointer hover:bg-white/20 transition-all backdrop-blur-md">
                                    <option value="month" class="text-slate-900" <?php echo $range=='month'?'selected':''; ?>>This Month</option>
                                    <option value="week" class="text-slate-900" <?php echo $range=='week'?'selected':''; ?>>This Week</option>
                                    <option value="last_week" class="text-slate-900" <?php echo $range=='last_week'?'selected':''; ?>>Last Week</option>
                                    <option value="yesterday" class="text-slate-900" <?php echo $range=='yesterday'?'selected':''; ?>>Yesterday</option>
                                </select>
                                <i class="fas fa-chevron-down text-[10px] text-white/50 absolute right-3 top-1/2 transform -translate-y-1/2 pointer-events-none"></i>
                            </div>
                        </form>

                        <?php if($range == 'month'): ?>
                        <div class="flex items-center bg-white/10 rounded-xl p-1 backdrop-blur-md border border-white/5">
                            <a href="?range=month&date=<?php echo $prev_month; ?>&view=details&search=<?php echo urlencode($search_query); ?>" class="w-7 h-7 flex items-center justify-center text-white/70 hover:text-white hover:bg-white/10 rounded-lg transition-all"><i class="fas fa-chevron-left text-[10px]"></i></a>
                            <span class="text-xs font-bold px-2"><?php echo date('M', strtotime($custom_date)); ?></span>
                            <a href="?range=month&date=<?php echo $next_month; ?>&view=details&search=<?php echo urlencode($search_query); ?>" class="w-7 h-7 flex items-center justify-center text-white/70 hover:text-white hover:bg-white/10 rounded-lg transition-all"><i class="fas fa-chevron-right text-[10px]"></i></a>
                        </div>
                        <?php endif; ?>
                    </div>

                    <div id="monthNav" class="<?php echo !$isDetails ? 'flex' : 'hidden'; ?> justify-center items-center mb-6 relative z-10">
                        <div class="flex items-center bg-white/10 rounded-xl p-1 backdrop-blur-md border border-white/5">
                            <a href="?range=month&date=<?php echo $prev_month; ?>&view=calendar" class="w-8 h-8 flex items-center justify-center text-white/70 hover:text-white hover:bg-white/10 rounded-lg transition-all"><i class="fas fa-chevron-left text-xs"></i></a>
                            <span class="text-sm font-bold px-4 tracking-wide"><?php echo date('F Y', strtotime($custom_date)); ?></span>
                            <a href="?range=month&date=<?php echo $next_month; ?>&view=calendar" class="w-8 h-8 flex items-center justify-center text-white/70 hover:text-white hover:bg-white/10 rounded-lg transition-all"><i class="fas fa-chevron-right text-xs"></i></a>
                        </div>
                    </div>

                    <div class="grid grid-cols-3 gap-4 text-center relative z-10">
                        <div class="flex flex-col items-center">
                            <span class="text-[10px] text-slate-400 font-bold uppercase tracking-wider mb-1">Total</span>
                            <span class="text-sm font-bold truncate w-full <?php echo $net_total >= 0 ? 'text-white' : 'text-rose-400'; ?>">
                                <?php echo formatCurrency($net_total); ?>
                            </span>
                        </div>
                        <div class="flex flex-col items-center relative after:content-[''] after:absolute after:-left-2 after:top-1/2 after:-translate-y-1/2 after:h-8 after:w-px after:bg-white/10">
                            <span class="text-[10px] text-emerald-400 font-bold uppercase tracking-wider mb-1">Income</span>
                            <span class="text-sm font-bold text-emerald-400 truncate w-full">
                                <?php echo formatCurrency($sum_income); ?>
                            </span>
                        </div>
                        <div class="flex flex-col items-center relative after:content-[''] after:absolute after:-left-2 after:top-1/2 after:-translate-y-1/2 after:h-8 after:w-px after:bg-white/10">
                            <span class="text-[10px] text-rose-400 font-bold uppercase tracking-wider mb-1">Expense</span>
                            <span class="text-sm font-bold text-rose-400 truncate w-full">
                                <?php echo formatCurrency($sum_expense); ?>
                            </span>
                        </div>
                    </div>
                    
                    <div class="absolute -top-12 -right-12 w-48 h-48 bg-blue-500 opacity-10 rounded-full blur-3xl pointer-events-none"></div>
                    <div class="absolute -bottom-12 -left-12 w-32 h-32 bg-purple-500 opacity-10 rounded-full blur-3xl pointer-events-none"></div>
                </div>
            </div>
        </header>

        <main class="flex-grow px-5 pt-6 pb-32 overflow-y-auto hide-scrollbar animate-enter animate-delay-100">
            
            <div id="view-details" class="space-y-6 <?php echo $isDetails ? '' : 'hidden'; ?>">
                <?php if (empty($grouped_list)): ?>
                    <div class="flex flex-col items-center justify-center py-20 opacity-50">
                        <div class="w-20 h-20 bg-slate-100 rounded-full flex items-center justify-center mb-4 text-slate-300">
                            <i class="fas fa-search text-3xl"></i>
                        </div>
                        <p class="text-sm font-semibold text-slate-400">No transactions found.</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($grouped_list as $date => $items): 
                        $dateObj = new DateTime($date);
                        $isToday = ($date == date('Y-m-d'));
                        $dateLabel = $isToday ? 'Today' : (($date == date('Y-m-d', strtotime('-1 day'))) ? 'Yesterday' : $dateObj->format('D, d M'));
                        
                        $dayNet = 0;
                        foreach($items as $i) {
                            if($i['type']=='expense') $dayNet += $i['amount'];
                            else $dayNet -= $i['amount'];
                        }
                    ?>
                        <div class="bg-white rounded-3xl overflow-hidden shadow-sm border border-slate-100">
                            <div class="bg-slate-50/80 px-5 py-3 flex justify-between items-center border-b border-slate-100 backdrop-blur-sm">
                                <span class="text-[11px] font-bold text-slate-500 uppercase tracking-wider"><?php echo $dateLabel; ?></span>
                                <?php if($dayNet != 0): ?>
                                    <?php if($dayNet > 0): ?>
                                        <span class="text-[10px] font-bold text-slate-400 bg-slate-200/50 px-2 py-0.5 rounded-md">-<?php echo formatCurrency($dayNet); ?></span>
                                    <?php else: ?>
                                        <span class="text-[10px] font-bold text-emerald-600 bg-emerald-50 px-2 py-0.5 rounded-md">+<?php echo formatCurrency(abs($dayNet)); ?></span>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </div>
                            
                            <div class="divide-y divide-slate-50">
                                <?php foreach ($items as $ex): 
                                    $style = $category_map[$ex['category']] ?? ['bg' => 'bg-slate-50', 'text' => 'text-slate-500', 'icon' => '📝'];
                                    $isIncome = ($ex['type'] === 'income');
                                    $amountColor = $isIncome ? 'text-emerald-600' : 'text-slate-900';
                                    $sign = $isIncome ? '+' : '-';
                                    $receiptPath = (!empty($ex['receipt_image'])) ? (strpos($ex['receipt_image'], 'data:image') === 0 ? $ex['receipt_image'] : 'uploads/receipts/' . basename($ex['receipt_image'])) : '';
                                    $formattedDate = date('F j, Y h:i A', strtotime($ex['expense_date'] . ' ' . date('H:i:s', strtotime($ex['created_at']))));

                                    $jsObj = [
                                        'id' => $ex['id'],
                                        'category' => $ex['category'],
                                        'amount' => $sign . formatCurrency($ex['amount']),
                                        'type' => $ex['type'],
                                        'date' => $formattedDate,
                                        'desc' => $ex['description'] ?: 'No Description',
                                        'location' => $ex['location'] ?: 'Unknown Location',
                                        'icon' => $style['icon'],
                                        'bg' => $style['bg'],
                                        'text' => $style['text'],
                                        'receipt' => $receiptPath
                                    ];
                                    $modalData = htmlspecialchars(json_encode($jsObj), ENT_QUOTES, 'UTF-8');
                                ?>
                                    <div onclick='openModal(<?php echo $modalData; ?>)' class="group relative flex items-center justify-between p-4 cursor-pointer hover:bg-slate-50 transition-all active:bg-slate-100">
                                        <div class="flex items-center space-x-4">
                                            <div class="w-12 h-12 rounded-2xl <?php echo $style['bg']; ?> <?php echo $style['text']; ?> flex items-center justify-center text-xl shadow-sm border border-black/5 group-hover:scale-105 transition-transform">
                                                <?php echo $style['icon']; ?>
                                            </div>
                                            <div class="min-w-0">
                                                <h4 class="text-sm font-bold text-slate-800 truncate"><?php echo htmlspecialchars($ex['category']); ?></h4>
                                                <p class="text-[11px] text-slate-400 truncate w-32 font-medium mt-0.5">
                                                    <?php echo htmlspecialchars($ex['description'] ?: 'No description'); ?>
                                                </p>
                                            </div>
                                        </div>
                                        <div class="text-right">
                                            <div class="text-sm font-extrabold <?php echo $amountColor; ?>">
                                                <?php echo $sign . formatCurrency($ex['amount']); ?>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <div id="view-calendar" class="<?php echo !$isDetails ? '' : 'hidden'; ?> pb-12">
                <div class="bg-white rounded-[2rem] shadow-sm border border-slate-100 p-6 mb-6">
                    <div class="flex justify-between items-center mb-6">
                        <h3 class="font-bold text-slate-800 text-lg"><?php echo date('F Y', strtotime($cal_view_month)); ?></h3>
                        <div class="flex gap-1">
                            <span class="w-2 h-2 rounded-full bg-blue-600"></span>
                            <span class="w-2 h-2 rounded-full bg-red-500"></span>
                        </div>
                    </div>

                    <div class="grid grid-cols-7 mb-4">
                        <?php foreach(['Su','Mo','Tu','We','Th','Fr','Sa'] as $d): ?>
                            <div class="text-center text-[10px] font-bold text-slate-400 uppercase tracking-widest"><?php echo $d; ?></div>
                        <?php endforeach; ?>
                    </div>

                    <div class="grid grid-cols-7 gap-y-3">
                        <?php for($i=0; $i<$start_weekday; $i++): ?><div></div><?php endfor; ?>

                        <?php for($d=1; $d<=$days_in_month; $d++): 
                            $dateStr = $cal_view_month . '-' . str_pad($d, 2, '0', STR_PAD_LEFT);
                            $dayVal = $daily_data[$dateStr] ?? 0;
                            $isToday = ($dateStr == date('Y-m-d'));
                        ?>
                            <div onclick="selectDate('<?php echo $dateStr; ?>', this)" 
                                 class="day-cell flex flex-col items-center h-11 justify-start relative cursor-pointer group">
                                <div class="w-8 h-8 flex items-center justify-center text-xs font-semibold rounded-full transition-all duration-300
                                    <?php echo $isToday ? 'bg-slate-900 text-white shadow-lg' : 'text-slate-600 hover:bg-slate-100'; ?>">
                                    <?php echo $d; ?>
                                </div>
                                <?php if($dayVal != 0): ?>
                                    <?php if($dayVal > 0): ?>
                                        <span class="absolute -bottom-1 bg-white text-rose-500 border border-rose-100 text-[8px] px-1.5 py-0 rounded-full font-bold shadow-sm z-10 pointer-events-none transform scale-90">
                                            -<?php echo (int)$dayVal; ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="absolute -bottom-1 bg-white text-emerald-500 border border-emerald-100 text-[8px] px-1.5 py-0 rounded-full font-bold shadow-sm z-10 pointer-events-none transform scale-90">
                                            +<?php echo abs((int)$dayVal); ?>
                                        </span>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </div>
                        <?php endfor; ?>
                    </div>
                </div>

                <div id="calendar-transactions-container" class="hidden animate-fade-in-up">
                    <div class="flex items-center justify-between mb-4 px-2">
                        <h3 class="text-xs font-bold text-slate-400 uppercase tracking-widest" id="cal-selected-date-label">Selected Date</h3>
                        <div class="h-px bg-slate-200 flex-grow ml-4"></div>
                    </div>
                    <div id="calendar-transactions-list" class="space-y-4"></div>
                </div>
            </div>

        </main>
        
        <div id="detailModal" class="fixed inset-0 z-[100] hidden">
            <div class="absolute inset-0 bg-slate-900/40 backdrop-blur-md transition-opacity duration-300" onclick="closeModal()"></div>
            <div class="absolute bottom-0 inset-x-0 bg-white rounded-t-[2.5rem] p-6 pb-12 shadow-[0_-10px_40px_-15px_rgba(0,0,0,0.1)] modal-enter md:relative md:max-w-sm md:mx-auto md:rounded-[2.5rem] md:top-24 max-h-[90vh] overflow-y-auto">
                <div class="w-12 h-1.5 bg-slate-200 rounded-full mx-auto mb-8"></div>
                <div class="flex justify-between items-start mb-8">
                    <div class="flex items-center space-x-5">
                        <div id="m-icon-bg" class="w-16 h-16 rounded-3xl flex items-center justify-center text-4xl shadow-sm border border-slate-100"><span id="m-icon"></span></div>
                        <div><h3 id="m-category" class="text-2xl font-bold text-slate-900 leading-tight">Category</h3><p id="m-date" class="text-xs font-semibold text-slate-400 mt-1">Date</p></div>
                    </div>
                    <button onclick="closeModal()" class="w-10 h-10 rounded-full bg-slate-50 flex items-center justify-center text-slate-400 hover:bg-slate-100 hover:text-slate-600 transition-colors"><i class="fas fa-times text-lg"></i></button>
                </div>
                <div class="space-y-6 mb-8">
                    <div class="bg-slate-50 p-6 rounded-3xl border border-slate-100 text-center"><p class="text-[10px] text-slate-400 font-bold uppercase tracking-widest mb-1">Amount</p><p id="m-amount" class="text-4xl font-extrabold text-slate-900 tracking-tight">RM 0.00</p></div>
                    <div class="space-y-4">
                        <div class="flex items-start p-4 rounded-2xl border border-slate-100 hover:bg-slate-50 transition-colors">
                            <div class="w-8 flex-shrink-0 text-center"><i class="fas fa-align-left text-slate-300 text-lg"></i></div>
                            <div><p class="text-[10px] text-slate-400 font-bold uppercase mb-0.5">Note</p><p id="m-desc" class="text-sm font-medium text-slate-700 break-words leading-relaxed">--</p></div>
                        </div>
                        <div class="flex items-start p-4 rounded-2xl border border-slate-100 hover:bg-slate-50 transition-colors">
                            <div class="w-8 flex-shrink-0 text-center"><i class="fas fa-map-marker-alt text-slate-300 text-lg"></i></div>
                            <div><p class="text-[10px] text-slate-400 font-bold uppercase mb-0.5">Location</p><p id="m-loc" class="text-sm font-medium text-slate-700 break-words leading-relaxed">--</p></div>
                        </div>
                    </div>
                    <div id="m-receipt-container" class="hidden">
                        <p class="text-[10px] text-slate-400 font-bold uppercase mb-3 px-1">Receipt</p>
                        <div class="bg-slate-50 p-2 rounded-2xl border border-slate-100 cursor-pointer overflow-hidden relative group" onclick="openImagePreview()">
                            <div class="absolute inset-0 bg-black/0 group-hover:bg-black/10 transition-colors z-10 flex items-center justify-center"><i class="fas fa-expand text-white opacity-0 group-hover:opacity-100 transition-opacity drop-shadow-md"></i></div>
                            <img id="m-receipt-img" src="" alt="Receipt" class="w-full h-48 object-cover rounded-xl shadow-sm">
                        </div>
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <a id="m-edit-btn" href="#" class="flex items-center justify-center bg-slate-100 text-slate-700 font-bold py-4 rounded-2xl text-sm hover:bg-slate-200 active:scale-95 transition-all"><i class="fas fa-pen mr-2"></i> Edit</a>
                    <form method="POST" onsubmit="return confirm('Delete this record?');" class="w-full"><input type="hidden" name="delete_id" id="m-delete-id"><button type="submit" class="w-full flex items-center justify-center bg-rose-50 text-rose-600 font-bold py-4 rounded-2xl text-sm hover:bg-rose-100 active:scale-95 transition-all border border-rose-100"><i class="fas fa-trash-alt mr-2"></i> Delete</button></form>
                </div>
            </div>
        </div>

        <div id="imagePreviewModal" class="fixed inset-0 z-[200] hidden bg-black/95 flex items-center justify-center p-4 backdrop-blur-md transition-opacity duration-300" onclick="closeImagePreview()">
            <div class="relative max-w-full max-h-full" onclick="event.stopPropagation()">
                <img id="full-image" src="" class="max-w-full max-h-[90vh] object-contain rounded-2xl shadow-2xl">
                <button onclick="closeImagePreview()" class="absolute top-4 right-4 bg-black/60 hover:bg-black/80 text-white rounded-full w-10 h-10 flex items-center justify-center backdrop-blur-sm transition-all shadow-lg border border-white/10"><i class="fas fa-times text-lg"></i></button>
            </div>
        </div>

        <?php include 'includes/footer.php'; ?>
    </div>

    <script>
        const calendarData = <?php echo json_encode($js_calendar_data); ?>;

        function toggleView(view) {
            const btnDetails = document.getElementById('btn-details');
            const btnCalendar = document.getElementById('btn-calendar');
            const divDetails = document.getElementById('view-details');
            const divCalendar = document.getElementById('view-calendar');
            
            const searchSection = document.getElementById('searchSection');
            const filterSection = document.getElementById('filterSection');
            const monthNav = document.getElementById('monthNav');

            const activeClass = "w-9 h-9 rounded-full bg-white text-slate-900 shadow-sm flex items-center justify-center transition-all";
            const inactiveClass = "w-9 h-9 rounded-full text-slate-400 flex items-center justify-center transition-all hover:text-slate-600";

            if(view === 'details') {
                btnDetails.className = activeClass;
                btnCalendar.className = inactiveClass;
                divDetails.classList.remove('hidden');
                divCalendar.classList.add('hidden');
                
                searchSection.classList.remove('hidden');
                filterSection.classList.remove('hidden');
                monthNav.classList.add('hidden');
                monthNav.classList.remove('flex');
                
                // Update URL to view=details without reload
                const url = new URL(window.location);
                url.searchParams.set('view', 'details');
                window.history.replaceState({}, '', url);
            } else {
                btnCalendar.className = activeClass;
                btnDetails.className = inactiveClass;
                divCalendar.classList.remove('hidden');
                divDetails.classList.add('hidden');
                
                searchSection.classList.add('hidden');
                filterSection.classList.add('hidden');
                monthNav.classList.remove('hidden');
                monthNav.classList.add('flex');
                
                // Update URL to view=calendar without reload
                const url = new URL(window.location);
                url.searchParams.set('view', 'calendar');
                window.history.replaceState({}, '', url);
            }
        }

        function selectDate(dateStr, el) {
            document.querySelectorAll('.day-cell div').forEach(div => {
                div.classList.remove('selected-day');
                if(!div.classList.contains('bg-slate-900')) { div.classList.add('text-slate-600'); }
            });
            const circle = el.querySelector('div');
            circle.classList.remove('text-slate-600', 'hover:bg-slate-100');
            circle.classList.add('selected-day');

            const container = document.getElementById('calendar-transactions-container');
            const list = document.getElementById('calendar-transactions-list');
            const label = document.getElementById('cal-selected-date-label');
            
            list.innerHTML = '';
            container.classList.remove('hidden');
            
            const dateObj = new Date(dateStr);
            label.innerText = dateObj.toLocaleDateString('en-US', { weekday: 'long', day: 'numeric', month: 'long' });

            const transactions = calendarData[dateStr];

            if (transactions && transactions.length > 0) {
                transactions.forEach(tx => {
                    const modalDataStr = JSON.stringify({
                        id: tx.id, category: tx.category, amount: tx.amount_fmt, type: tx.type, date: tx.date_fmt,
                        desc: tx.desc, location: tx.location, icon: tx.icon, bg: tx.bg, text: tx.text, receipt: tx.receipt
                    }).replace(/"/g, '&quot;');

                    const html = `
                        <div onclick='openModal(${modalDataStr})' class="bg-white rounded-2xl p-4 shadow-sm border border-slate-100 flex items-center justify-between cursor-pointer active:bg-slate-50 transition-all hover:shadow-md">
                            <div class="flex items-center space-x-4">
                                <div class="w-10 h-10 rounded-xl ${tx.bg} ${tx.text} flex items-center justify-center text-lg shadow-sm">${tx.icon}</div>
                                <div class="min-w-0">
                                    <h4 class="text-sm font-bold text-slate-800 truncate">${tx.category}</h4>
                                    <p class="text-[11px] text-slate-400 truncate w-32 font-medium">${tx.desc}</p>
                                </div>
                            </div>
                            <div class="text-sm font-bold ${tx.amount_color}">${tx.amount_fmt}</div>
                        </div>`;
                    list.insertAdjacentHTML('beforeend', html);
                });
            } else {
                list.innerHTML = `<div class="text-center py-8 opacity-60"><p class="text-xs text-slate-400 font-medium">No transactions on this day</p></div>`;
            }
        }

        function openModal(data) {
            if(typeof data === 'string') data = JSON.parse(data);
            document.getElementById('m-icon').innerText = data.icon;
            document.getElementById('m-icon-bg').className = `w-16 h-16 rounded-3xl flex items-center justify-center text-4xl shadow-sm border border-slate-100 ${data.bg} ${data.text}`;
            document.getElementById('m-category').innerText = data.category;
            document.getElementById('m-date').innerText = data.date;
            const amt = document.getElementById('m-amount');
            amt.innerText = data.amount;
            amt.className = data.type === 'income' ? "text-4xl font-extrabold text-emerald-600 tracking-tight" : "text-4xl font-extrabold text-slate-900 tracking-tight";
            document.getElementById('m-desc').innerText = data.desc;
            document.getElementById('m-loc').innerText = data.location;
            const rDiv = document.getElementById('m-receipt-container');
            const rImg = document.getElementById('m-receipt-img');
            if(data.receipt){ rImg.src=data.receipt; rDiv.classList.remove('hidden'); } else { rDiv.classList.add('hidden'); }
            document.getElementById('m-edit-btn').href = 'edit-expense.php?id=' + data.id;
            document.getElementById('m-delete-id').value = data.id;
            document.getElementById('detailModal').classList.remove('hidden');
        }
        function closeModal() { document.getElementById('detailModal').classList.add('hidden'); }
        function openImagePreview() { const s=document.getElementById('m-receipt-img').src; if(s){document.getElementById('full-image').src=s;document.getElementById('imagePreviewModal').classList.remove('hidden');} }
        function closeImagePreview() { document.getElementById('imagePreviewModal').classList.add('hidden'); }
    </script>
</body>
</html>