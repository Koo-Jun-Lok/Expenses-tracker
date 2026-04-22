<?php
// --- Includes and Dependencies ---
require_once 'includes/config.php';
require_once 'includes/auth-check.php';
require_once 'includes/session-handler.php';
require_once 'includes/currency-helper.php';

// Ensure the user is logged in
requireLogin();
$user_id = getUserId();
$currency = getCurrencyConfig(); 

// --- 1. Month Filter Handling ---
// Get selected month from URL, default to current month
$selected_month = $_GET['month'] ?? date('Y-m');

// --- 2. Generate Month Dropdown Options ---
// Create a list of the last 12 months for the filter dropdown
$month_options = [];
for ($i = 0; $i > -12; $i--) {
    $val = date('Y-m', strtotime("$i months"));
    $text = date('F Y', strtotime("$i months")); // e.g. January 2026
    $month_options[] = ['value' => $val, 'label' => $text];
}
// Label for the currently selected month
$current_month_label = date('F Y', strtotime($selected_month));

// --- 3. Data Query: Expenses ---
// Fetch expenses grouped by category for the selected month
$exp_sql = "SELECT category, SUM(amount) as total 
            FROM expenses 
            WHERE user_id = ? AND type = 'expense' AND DATE_FORMAT(expense_date, '%Y-%m') = ?
            GROUP BY category ORDER BY total DESC";
$stmt = $pdo->prepare($exp_sql);
$stmt->execute([$user_id, $selected_month]);
$expense_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

// --- 3. Data Query: Income ---
// Fetch income grouped by category for the selected month
$inc_sql = "SELECT category, SUM(amount) as total 
            FROM expenses 
            WHERE user_id = ? AND type = 'income' AND DATE_FORMAT(expense_date, '%Y-%m') = ?
            GROUP BY category ORDER BY total DESC";
$stmt = $pdo->prepare($inc_sql);
$stmt->execute([$user_id, $selected_month]);
$income_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

// --- Total Calculations ---
$total_expense = array_sum(array_column($expense_data, 'total'));
$total_income = array_sum(array_column($income_data, 'total'));

// Prepare data for the "Total" view (Net Balance view)
$total_data = [
    ['category' => 'Income', 'total' => $total_income, 'type' => 'income'],
    ['category' => 'Expense', 'total' => $total_expense, 'type' => 'expense']
];

// Chart Colors Array
$colors = ['#EF4444', '#F59E0B', '#3B82F6', '#10B981', '#8B5CF6', '#EC4899', '#6366F1'];

// --- [New Method] 4. Fetch Dynamic Category Styles from DB ---
// Fetch icons and colors from the database instead of hardcoding
$stmt = $pdo->query("SELECT name, icon, color FROM categories");
$category_map = [];
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    // Generate Tailwind classes for background (10% opacity) and text color
    $hex = $row['color'];
    $category_map[$row['name']] = [
        'icon' => $row['icon'],
        'bg'   => "bg-[{$hex}]/10", 
        'text' => "text-[{$hex}]"   
    ];
}
?>
<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Analytics</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap');
        body { font-family: 'Plus Jakarta Sans', sans-serif; background-color: #F8F9FD; }
        
        /* Layout: Lock page height to prevent full body scroll, use inner scrolling */
        .mobile-container { 
            max-width: 480px; 
            margin: 0 auto; 
            background-color: #F8F9FD; 
            height: 100vh; /* Force full viewport height */
            display: flex; 
            flex-direction: column; 
            overflow: hidden; /* Prevent container overflow */
        }
        
        .hide-scrollbar::-webkit-scrollbar { display: none; }
        
        /* Animations */
        .page-enter { animation: slideUp 0.6s cubic-bezier(0.16, 1, 0.3, 1) forwards; }
        @keyframes slideUp { 0% { transform: translateY(20px); opacity: 0; } 100% { transform: translateY(0); opacity: 1; } }

        .dropdown-enter { animation: dropIn 0.2s ease-out forwards; transform-origin: top right; }
        @keyframes dropIn { from { opacity: 0; transform: scale(0.95); } to { opacity: 1; transform: scale(1); } }
        
        /* Tab Button Styles */
        .tab-btn { transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1); }
        .tab-btn.active { background-color: #1a1b2e; color: white; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1); }
        .tab-btn.inactive { background-color: transparent; color: #6B7280; }
    </style>
</head>
<body class="bg-gray-50 text-gray-800" onclick="closeDropdownOutside(event)">
    <div class="mobile-container">
        
        <div class="flex-none bg-[#F8F9FD] z-20 shadow-sm relative">
            <header class="px-6 pt-8 pb-2">
                <div class="flex justify-between items-center mb-4">
                    <div class="flex items-center">
                        <a href="dashboard.php" class="mr-3 w-8 h-8 flex items-center justify-center rounded-full bg-white border border-gray-200 text-gray-600 hover:bg-gray-50 transition-colors">
                            <i class="fas fa-arrow-left text-xs"></i>
                        </a>
                        <h1 class="text-xl font-bold text-gray-900">Analytics</h1>
                    </div>
                    
                    <div class="relative">
                        <button id="monthDropdownBtn" onclick="toggleDropdown(event)" class="flex items-center bg-white border border-gray-200 px-3 py-2 rounded-full shadow-sm hover:border-blue-300 transition-all active:scale-95">
                            <div class="w-6 h-6 rounded-full bg-blue-50 text-blue-600 flex items-center justify-center mr-2">
                                <i class="fas fa-calendar-alt text-[10px]"></i>
                            </div>
                            <span class="text-xs font-bold text-gray-700 mr-2 min-w-[80px] text-left" id="currentMonthLabel">
                                <?php echo $current_month_label; ?>
                            </span>
                            <i class="fas fa-chevron-down text-[10px] text-gray-400"></i>
                        </button>

                        <div id="monthDropdownMenu" class="hidden absolute right-0 top-full mt-2 w-48 bg-white rounded-2xl shadow-xl border border-gray-100 overflow-hidden z-50 dropdown-enter origin-top-right">
                            <div class="max-h-64 overflow-y-auto py-1 hide-scrollbar">
                                <?php foreach ($month_options as $opt): 
                                    $isActive = ($opt['value'] == $selected_month);
                                    $bgClass = $isActive ? 'bg-blue-50 text-blue-600 font-bold' : 'text-gray-600 hover:bg-gray-50';
                                    $iconVisibility = $isActive ? '' : 'invisible';
                                ?>
                                <a href="?month=<?php echo $opt['value']; ?>" 
                                   class="block px-4 py-3 text-sm flex items-center justify-between transition-colors <?php echo $bgClass; ?>">
                                    <span><?php echo $opt['label']; ?></span>
                                    <i class="fas fa-check text-xs <?php echo $iconVisibility; ?>"></i>
                                </a>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="flex justify-between items-center bg-white p-1.5 rounded-2xl shadow-sm border border-gray-200/60 mb-4">
                    <button onclick="switchTab('expenses')" id="tab-expenses" class="tab-btn active flex-1 py-2.5 rounded-xl text-xs font-bold flex items-center justify-center gap-2">
                        <i class="fas fa-shopping-cart text-[10px]"></i><span>Expense</span>
                    </button>
                    <button onclick="switchTab('income')" id="tab-income" class="tab-btn inactive flex-1 py-2.5 rounded-xl text-xs font-bold flex items-center justify-center gap-2">
                        <i class="fas fa-wallet text-[10px]"></i><span>Income</span>
                    </button>
                    <button onclick="switchTab('total')" id="tab-total" class="tab-btn inactive flex-1 py-2.5 rounded-xl text-xs font-bold flex items-center justify-center gap-2">
                        <i class="fas fa-chart-pie text-[10px]"></i><span>Total</span>
                    </button>
                </div>
            </header>

            <div class="px-6 pb-2">
                <div class="bg-white rounded-[2rem] shadow-[0_8px_30px_rgb(0,0,0,0.04)] border border-gray-100 p-4 relative">
                    <div class="h-56 w-56 mx-auto relative flex items-center justify-center">
                        <canvas id="mainChart"></canvas>
                        <div class="chart-center-text absolute text-center pointer-events-none">
                            <div class="text-[10px] text-gray-400 font-bold uppercase tracking-widest mb-1" id="chart-title">Total Expenses</div>
                            <div class="text-xl font-bold text-gray-900 tracking-tight" id="chart-total"><?php echo $currency['code']; ?> 0.00</div>
                        </div>
                    </div>
                </div>
                
                <div class="flex justify-between items-end mt-4 mb-2 px-1">
                    <h3 class="font-bold text-gray-800 text-base">Breakdown</h3>
                    <span class="text-[10px] font-semibold text-gray-500 bg-white border border-gray-200 px-2 py-1 rounded-lg">Category</span>
                </div>
            </div>
        </div>

        <div id="scrollable-list" class="flex-grow overflow-y-auto px-6 pb-24 hide-scrollbar page-enter">
            <div id="list-container" class="space-y-3">
                </div>
        </div>
        
        <?php include 'includes/footer.php'; ?>
    </div>

    <script>
        // --- Global Currency Data ---
        const currencyCode = "<?php echo $currency['code']; ?>";
        const currencyRate = <?php echo $currency['rate']; ?>;

        // --- Dropdown Logic ---
        function toggleDropdown(e) {
            e.stopPropagation(); 
            const menu = document.getElementById('monthDropdownMenu');
            menu.classList.toggle('hidden');
        }

        function closeDropdownOutside(e) {
            const menu = document.getElementById('monthDropdownMenu');
            const btn = document.getElementById('monthDropdownBtn');
            if (!menu.classList.contains('hidden') && !menu.contains(e.target) && !btn.contains(e.target)) {
                menu.classList.add('hidden');
            }
        }

        // --- Data Injection from PHP ---
        const expensesData = <?php echo json_encode($expense_data); ?>;
        const incomeData = <?php echo json_encode($income_data); ?>;
        const totalData = <?php echo json_encode($total_data); ?>;
        const colors = <?php echo json_encode($colors); ?>;
        
        // --- Pass DB Category Styles to JS ---
        // 'category_map' contains icons and colors for each category name
        const categoryMap = <?php echo json_encode($category_map); ?>;

        // Function to get icon from the map
        const getIcon = (cat) => {
            // Hardcoded icons for 'Total' view summaries
            if (cat === 'Income') return '💵';
            if (cat === 'Expense') return '💸';
            
            // Lookup icon from DB map, fallback to box icon
            if (categoryMap[cat] && categoryMap[cat].icon) {
                return categoryMap[cat].icon;
            }
            return '📦';
        };

        let currentChart = null;

        // Initialize view on load
        document.addEventListener('DOMContentLoaded', () => {
            switchTab('expenses');
        });

        // --- Tab Switching Logic ---
        function switchTab(type) {
            // Update Tab UI
            document.querySelectorAll('.tab-btn').forEach(btn => {
                btn.classList.remove('active');
                btn.classList.add('inactive');
            });
            document.getElementById('tab-' + type).classList.replace('inactive', 'active');

            // Select Dataset based on Tab
            let data = [], label = "";
            if(type === 'expenses') { data = expensesData; label = "Total Expenses"; }
            else if(type === 'income') { data = incomeData; label = "Total Income"; }
            else { data = totalData; label = "Net Balance"; }

            renderView(data, label, type);
        }

        // --- Main Render Function (Chart + List) ---
        function renderView(data, labelTitle, type) {
            let total = 0, displayTotal = 0;

            // Calculate Totals based on Type
            if (type === 'total') {
                const inc = data.find(i => i.type === 'income')?.total || 0;
                const exp = data.find(i => i.type === 'expense')?.total || 0;
                displayTotal = parseFloat(inc) - parseFloat(exp);
                total = parseFloat(inc) + parseFloat(exp); // For percentage calculation base
            } else {
                total = data.reduce((acc, item) => acc + parseFloat(item.total), 0);
                displayTotal = total;
            }
            
            // Update Chart Center Text
            document.getElementById('chart-title').innerText = labelTitle;
            const totalEl = document.getElementById('chart-total');
            totalEl.innerText = formatCurrency(displayTotal);
            
            if(type === 'total') {
                totalEl.className = displayTotal >= 0 ? "text-xl font-bold text-gray-900 tracking-tight" : "text-xl font-bold text-red-500 tracking-tight";
            } else {
                totalEl.className = "text-xl font-bold text-gray-900 tracking-tight";
            }

            // Render List Items
            const listDiv = document.getElementById('list-container');
            listDiv.innerHTML = '';

            if (data.length === 0) {
                listDiv.innerHTML = `
                    <div class="text-center py-12 flex flex-col items-center opacity-50">
                        <i class="fas fa-chart-pie text-4xl text-gray-300 mb-2"></i>
                        <p class="text-xs text-gray-500">No data available</p>
                    </div>`;
            }

            data.forEach((item, index) => {
                const baseTotal = (type === 'total') ? total : displayTotal;
                const pct = baseTotal > 0 ? Math.round((item.total / baseTotal) * 100) : 0;
                const color = colors[index % colors.length];
                // Use dynamic icon logic
                const icon = getIcon(item.category);

                let amountClass = "text-gray-900";
                let sign = "";
                if(type === 'total') {
                    if(item.type === 'income') { amountClass = "text-green-600"; sign = "+"; }
                    if(item.type === 'expense') { amountClass = "text-red-500"; sign = "-"; }
                }

                const html = `
                    <div class="bg-white p-4 rounded-2xl shadow-sm border border-gray-100 flex items-center justify-between group hover:shadow-md transition-all">
                        <div class="flex items-center space-x-4">
                            <div class="w-12 h-12 rounded-2xl flex items-center justify-center text-xl bg-gray-50 border border-gray-100 shadow-sm">
                                ${icon}
                            </div>
                            <div>
                                <div class="flex items-center gap-2 mb-1">
                                    <h4 class="text-sm font-bold text-gray-800">${item.category}</h4>
                                    <span class="text-[10px] px-2 py-0.5 rounded-md bg-gray-100 text-gray-500 font-bold">${pct}%</span>
                                </div>
                                <div class="w-28 h-1.5 bg-gray-100 rounded-full overflow-hidden">
                                    <div class="h-full rounded-full transition-all duration-1000 ease-out" style="width: ${pct}%; background-color: ${color}"></div>
                                </div>
                            </div>
                        </div>
                        <div class="text-right">
                            <div class="text-sm font-bold ${amountClass}">${sign}${formatCurrency(item.total)}</div>
                        </div>
                    </div>
                `;
                listDiv.innerHTML += html;
            });

            updateChart(data); 
        }

        // --- Chart.js Rendering ---
        function updateChart(data) {
            const ctx = document.getElementById('mainChart').getContext('2d');
            const labels = data.map(d => d.category);
            const values = data.map(d => d.total);
            const bgColors = data.map((_, i) => colors[i % colors.length]);

            // Handle empty chart visual
            if(values.length === 0) {
                values.push(1); bgColors.push('#F3F4F6'); labels.push('No Data');
            }

            if(currentChart) currentChart.destroy();

            currentChart = new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: labels,
                    datasets: [{
                        data: values,
                        backgroundColor: bgColors,
                        borderWidth: 0,
                        hoverOffset: 10,
                        borderRadius: 5,
                        cutout: '80%'
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { display: false }, tooltip: { enabled: values.length > 0 && data.length > 0 } },
                    animation: { animateScale: true, animateRotate: true, duration: 800, easing: 'easeOutQuart' }
                }
            });
        }

        // --- Currency Formatter ---
        function formatCurrency(val) {
            const converted = parseFloat(val) * currencyRate;
            return currencyCode + ' ' + converted.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});
        }
    </script>
</body>
</html>