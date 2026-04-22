<?php
// --- Includes and Dependencies ---
require_once 'includes/config.php';
require_once 'includes/auth-check.php';
require_once 'includes/session-handler.php';
require_once 'includes/currency-helper.php';

// Ensure user is logged in
requireLogin();

// Get current currency config
$currency = getCurrencyConfig(); 

// Initialize feedback variables
$success = '';
$error = '';

// --- 1. Fetch Expense Categories ---
$stmt = $pdo->prepare("SELECT * FROM categories WHERE type = 'expense' ORDER BY name");
$stmt->execute();
$expense_categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

// --- 2. Fetch Income Categories ---
$stmt = $pdo->prepare("SELECT * FROM categories WHERE type = 'income' ORDER BY name");
$stmt->execute();
$income_categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

// --- Handle Form Submission ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $type = $_POST['type'] ?? 'expense';
    $amount = trim($_POST['amount']);
    $category = trim($_POST['category']);
    $description = trim($_POST['description']);
    $expense_date = trim($_POST['expense_date']);
    $location = trim($_POST['location']);
    
    // --- Image Handling Logic ---
    $receipt_image = null;
    if (isset($_FILES['receipt_image']) && $_FILES['receipt_image']['error'] === UPLOAD_ERR_OK) {
        $fileTmpPath = $_FILES['receipt_image']['tmp_name'];
        $fileName = $_FILES['receipt_image']['name'];
        $fileNameCmps = explode(".", $fileName);
        $fileExtension = strtolower(end($fileNameCmps));
        
        $allowedfileExtensions = array('jpg', 'gif', 'png', 'jpeg');
        if (in_array($fileExtension, $allowedfileExtensions)) {
             $checkImage = getimagesize($fileTmpPath);
             if($checkImage !== false) {
                $newFileName = md5(time() . $fileName) . '.' . $fileExtension;
                $uploadFileDir = './uploads/receipts/';
                // Ensure directory exists
                if (!is_dir($uploadFileDir)) { mkdir($uploadFileDir, 0755, true); }
                
                $dest_path = $uploadFileDir . $newFileName;
                
                if(move_uploaded_file($fileTmpPath, $dest_path)) {
                    $receipt_image = $newFileName;
                }
             }
        }
    }

    // --- Validation ---
    if (empty($amount) || empty($category) || empty($expense_date)) {
        $error = 'Please fill in all required fields';
    } elseif (!is_numeric($amount) || $amount <= 0) {
        $error = 'Please enter a valid amount';
    } else {
        try {
            $stmt = $pdo->prepare("INSERT INTO expenses (user_id, type, amount, category, description, expense_date, location, receipt_image) 
                                   VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                getUserId(),
                $type,
                $amount,
                $category,
                $description,
                $expense_date,
                $location,
                $receipt_image
            ]);
            
            $success = ucfirst($type) . ' added successfully!';
            
            $_POST = [];
            echo "<script>localStorage.removeItem('expenseDraft');</script>";
            
        } catch (PDOException $e) {
            $error = 'Error adding transaction: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Add Transaction</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin=""/>
    
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap');
        body { font-family: 'Poppins', sans-serif; }
        .mobile-container { max-width: 480px; margin: 0 auto; }
        input:focus, select:focus, textarea:focus { outline: none; }
        .category-option { transition: all 0.2s ease; }
        .category-option:hover { transform: scale(1.05); }
        .toggle-btn { transition: all 0.3s ease; }
        .toggle-btn.active { background-color: rgba(255, 255, 255, 0.2); font-weight: 700; border: 1px solid rgba(255,255,255,0.4); }
        
        #mapModal { z-index: 9999; }
        #map { height: 100%; width: 100%; border-radius: 0.75rem; }
    </style>
</head>
<body class="bg-gray-50 h-full">
    <div class="mobile-container min-h-screen flex flex-col relative">
        <header class="bg-gradient-to-r from-blue-600 to-purple-600 text-white p-6 rounded-b-3xl shadow-lg transition-colors duration-500" id="headerBg">
            <div class="flex items-center justify-between mb-4">
                <a href="dashboard.php" class="flex items-center text-white">
                    <i class="fas fa-arrow-left mr-2"></i> Back
                </a>
                <div class="bg-white/20 px-3 py-1 rounded-full text-xs font-bold flex items-center gap-1 backdrop-blur-sm">
                    <span><?php echo $currency['code']; ?></span>
                </div>
            </div>
            <div class="text-center">
                <h1 class="text-3xl font-bold">Add Transaction</h1>
                <div class="flex justify-center mt-4">
                    <div class="bg-black/20 p-1 rounded-xl flex w-48">
                        <button type="button" onclick="switchType('expense')" id="btn-expense" class="toggle-btn active w-1/2 py-1.5 rounded-lg text-sm text-white">Expense</button>
                        <button type="button" onclick="switchType('income')" id="btn-income" class="toggle-btn w-1/2 py-1.5 rounded-lg text-sm text-white/70 hover:text-white">Income</button>
                    </div>
                </div>
            </div>
        </header>

        <main class="flex-grow p-6 pb-32">
            <?php if ($success): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded-lg mb-4 flex items-center">
                <i class="fas fa-check-circle mr-2"></i> <?php echo htmlspecialchars($success); ?>
            </div>
            <?php endif; ?>
            
            <?php if ($error): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-lg mb-4 flex items-center">
                <i class="fas fa-exclamation-circle mr-2"></i> <?php echo htmlspecialchars($error); ?>
            </div>
            <?php endif; ?>

            <form id="expenseForm" method="POST" action="" enctype="multipart/form-data" class="space-y-6">
                <input type="hidden" name="type" id="typeInput" value="expense">
                
                <div class="bg-white rounded-xl shadow-md p-5">
                    <label class="block text-gray-700 mb-3 font-semibold" for="amount">
                        <i class="fas fa-money-bill-wave text-green-500 mr-2"></i> Amount *
                    </label>

                    <div class="flex items-center gap-2 mb-4 bg-blue-50 p-2 rounded-lg border border-blue-100">
                        <div class="flex-shrink-0">
                             <select id="convertCurrency" class="bg-white border border-blue-200 text-xs rounded-lg px-2 py-2 outline-none font-bold text-slate-700 shadow-sm">
                                <option value="MYR">MYR</option>
                                <option value="USD">USD</option>
                                <option value="SGD">SGD</option>
                                <option value="CNY">CNY</option>
                                <option value="EUR">EUR</option>
                                <option value="GBP">GBP</option>
                                <option value="JPY">JPY</option>
                            </select>
                        </div>
                        <div class="flex-grow">
                             <input type="number" id="foreignAmount" placeholder="Enter Foreign Amt" class="w-full bg-white border border-blue-200 rounded-lg px-3 py-2 text-sm outline-none shadow-sm transition-all focus:border-blue-400">
                        </div>
                        <div class="flex-shrink-0">
                             <button type="button" onclick="applyConversion()" class="bg-blue-600 hover:bg-blue-700 text-white text-xs px-3 py-2 rounded-lg font-bold shadow-md transition-all active:scale-95">
                                 Convert
                             </button>
                        </div>
                    </div>
                    <div class="relative">
                        <div class="absolute left-3 top-3 text-gray-500 font-bold text-lg"><?php echo $currency['code']; ?></div>
                        
                        <input type="number" id="amount" name="amount" step="0.01" min="0.01" 
                               class="w-full pl-16 pr-4 py-3 text-2xl font-bold border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent" 
                               placeholder="0.00" 
                               value="<?php echo htmlspecialchars($_POST['amount'] ?? ''); ?>" required>
                    </div>
                    
                    <div class="flex space-x-2 mt-3">
                        <button type="button" class="quick-amount bg-gray-100 text-gray-800 px-3 py-2 rounded-lg text-sm" data-amount="5">5</button>
                        <button type="button" class="quick-amount bg-gray-100 text-gray-800 px-3 py-2 rounded-lg text-sm" data-amount="10">10</button>
                        <button type="button" class="quick-amount bg-gray-100 text-gray-800 px-3 py-2 rounded-lg text-sm" data-amount="20">20</button>
                        <button type="button" class="quick-amount bg-gray-100 text-gray-800 px-3 py-2 rounded-lg text-sm" data-amount="50">50</button>
                    </div>
                </div>

                <div class="bg-white rounded-xl shadow-md p-5">
                    <label class="block text-gray-700 mb-3 font-semibold" for="category">
                        <i class="fas fa-tag text-blue-500 mr-2"></i> Category *
                    </label>
                    <input type="hidden" name="category" id="categoryInput" required>
                    <div id="expenseCategories" class="grid grid-cols-4 gap-3 category-grid">
                        <?php foreach ($expense_categories as $cat): ?>
                        <button type="button" class="category-option bg-gray-50 border border-gray-200 rounded-lg p-3 text-center w-full" onclick="selectCategory('<?php echo htmlspecialchars($cat['name'], ENT_QUOTES); ?>', this)">
                            <div class="text-xl mb-1"><?php echo htmlspecialchars($cat['icon']); ?></div>
                            <div class="text-xs text-gray-700 truncate"><?php echo htmlspecialchars($cat['name']); ?></div>
                        </button>
                        <?php endforeach; ?>
                    </div>
                    <div id="incomeCategories" class="grid grid-cols-4 gap-3 category-grid hidden">
                        <?php foreach ($income_categories as $cat): ?>
                        <button type="button" class="category-option bg-gray-50 border border-gray-200 rounded-lg p-3 text-center w-full" onclick="selectCategory('<?php echo htmlspecialchars($cat['name'], ENT_QUOTES); ?>', this)">
                            <div class="text-xl mb-1"><?php echo htmlspecialchars($cat['icon']); ?></div>
                            <div class="text-xs text-gray-700 truncate"><?php echo htmlspecialchars($cat['name']); ?></div>
                        </button>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="bg-white rounded-xl shadow-md p-5">
                    <label class="block text-gray-700 mb-3 font-semibold" for="description">
                        <i class="fas fa-edit text-purple-500 mr-2"></i> Description
                    </label>
                    <textarea id="description" name="description" rows="2" class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent" placeholder="What is this for?"><?php echo htmlspecialchars($_POST['description'] ?? ''); ?></textarea>
                </div>

                <div class="bg-white rounded-xl shadow-md p-5">
                    <label class="block text-gray-700 mb-3 font-semibold" for="expense_date">
                        <i class="fas fa-calendar-alt text-red-500 mr-2"></i> Date *
                    </label>
                    <input type="date" id="expense_date" name="expense_date" class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent" value="<?php echo htmlspecialchars($_POST['expense_date'] ?? date('Y-m-d')); ?>" required>
                </div>

                <div class="bg-white rounded-xl shadow-md p-5">
                    <label class="block text-gray-700 mb-3 font-semibold" for="location">
                        <i class="fas fa-map-marker-alt text-green-500 mr-2"></i> Location
                    </label>
                    <div class="flex w-full"> 
                        <input type="text" id="location" name="location" class="flex-grow min-w-0 px-4 py-3 border border-gray-300 rounded-l-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent" placeholder="Tap map button to select..." value="<?php echo htmlspecialchars($_POST['location'] ?? ''); ?>">
                        
                        <button type="button" onclick="openMapModal()" class="flex-shrink-0 bg-blue-100 text-blue-600 px-5 rounded-r-lg border border-l-0 border-gray-300 hover:bg-blue-200 transition">
                            <i class="fas fa-map-marked-alt text-xl"></i>
                        </button>
                    </div>
                    <p class="text-xs text-gray-400 mt-2">Tap the map icon to select a place</p>
                </div>

                <div class="bg-white rounded-xl shadow-md p-5">
                    <label class="block text-gray-700 mb-3 font-semibold">
                        <i class="fas fa-camera text-yellow-500 mr-2"></i> Receipt Photo
                    </label>
                    
                    <input type="file" id="receiptFile" name="receipt_image" accept="image/*" class="hidden" onchange="previewImage(this)">
                    
                    <div id="uploadPreview" class="hidden mb-3 relative">
                        <img id="imagePreview" src="#" alt="Receipt Preview" class="w-full h-48 object-cover rounded-lg border border-gray-200">
                        <button type="button" onclick="clearImage()" class="absolute top-2 right-2 bg-red-500 text-white rounded-full w-8 h-8 flex items-center justify-center shadow-lg">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>

                    <div id="uploadButtons" class="w-full">
                        <button type="button" onclick="triggerFileSelect()" class="w-full flex flex-col items-center justify-center p-6 border-2 border-dashed border-gray-300 rounded-lg hover:border-blue-500 hover:bg-blue-50 transition bg-gray-50">
                            <i class="fas fa-cloud-upload-alt text-3xl text-blue-500 mb-2"></i>
                            <span class="text-sm font-medium text-gray-600">Upload Receipt</span>
                            <span class="text-xs text-gray-400 mt-1">Tap to take photo or choose from gallery</span>
                        </button>
                    </div>
                </div>
            </form>
        </main>

        <div class="fixed bottom-20 w-full max-w-480 px-6">
            <button type="submit" form="expenseForm" id="submitBtn" class="w-full bg-gradient-to-r from-green-500 to-green-600 text-white py-4 rounded-xl font-bold text-lg shadow-lg hover:shadow-xl transition duration-300 flex items-center justify-center">
                <i class="fas fa-save mr-3"></i> Save Expense
            </button>
        </div>
    </div>

    <div id="mapModal" class="fixed inset-0 bg-black bg-opacity-75 hidden flex items-center justify-center p-4">
        <div class="bg-white w-full h-4/5 max-w-lg rounded-2xl flex flex-col overflow-hidden shadow-2xl relative">
            <div class="p-4 bg-gray-100 flex justify-between items-center border-b">
                <h3 class="font-bold text-gray-700"><i class="fas fa-map-marker-alt text-red-500"></i> Pick Location</h3>
                <button onclick="closeMapModal()" class="text-gray-500 hover:text-gray-800 text-2xl">&times;</button>
            </div>
            
            <div id="map" class="flex-grow relative"></div>

            <button onclick="getUserLocation()" class="absolute bottom-16 right-4 bg-white text-blue-600 p-3 rounded-full shadow-lg z-[2000] active:scale-95 transition-transform border border-gray-200">
                <i class="fas fa-crosshairs text-xl"></i>
            </button>
            
            <div id="mapLoading" class="hidden absolute inset-0 bg-white bg-opacity-80 flex items-center justify-center z-[10000]">
                <div class="animate-spin rounded-full h-10 w-10 border-b-2 border-blue-600"></div>
            </div>

            <div class="p-3 bg-white text-center text-xs text-gray-500 border-t">
                Tap anywhere to select location
            </div>
        </div>
    </div>
    <?php include 'includes/footer.php'; ?>

    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>

    <script>
        // --- Currency Converter ---
        async function applyConversion() {
            const currency = document.getElementById('convertCurrency').value;
            const foreignAmt = parseFloat(document.getElementById('foreignAmount').value);
            const baseCurrency = "<?php echo $currency['code']; ?>";

            if (!foreignAmt) {
                alert('Please enter an amount to convert.');
                return;
            }

            if (currency === baseCurrency) {
                 document.getElementById('amount').value = foreignAmt.toFixed(2);
                 return;
            }

            const btn = document.querySelector('button[onclick="applyConversion()"]');
            const originalText = btn.innerText;
            btn.innerText = '...';
            btn.disabled = true;

            try {
                const res = await fetch(`https://open.er-api.com/v6/latest/${currency}`);
                const data = await res.json();
                const rateToBase = data.rates[baseCurrency];
                
                if(!rateToBase) throw new Error("Rate not found");

                const converted = (foreignAmt * rateToBase).toFixed(2);
                document.getElementById('amount').value = converted;
                
                const descField = document.getElementById('description');
                const note = `[Converted: ${foreignAmt} ${currency} = ${converted} ${baseCurrency} @ ${rateToBase.toFixed(4)}]`;
                
                if(descField.value) {
                    if(!descField.value.includes('[Converted:')) {
                        descField.value += "\n" + note;
                    }
                } else {
                    descField.value = note;
                }
            } catch (e) {
                alert("Error fetching exchange rate. Please try again.");
                console.error(e);
            } finally {
                btn.innerText = originalText;
                btn.disabled = false;
            }
        }

        // --- Map Logic ---
        let map, marker;
        
        function openMapModal() {
            document.getElementById('mapModal').classList.remove('hidden');
            
            if (!map) {
                map = L.map('map').setView([4.2105, 101.9758], 6);
                
                // FIXED: Changed to OpenStreetMap France (HOT) style which shows building/POI names
                // This server is generally more lenient with WebViews than the main OSM server.
                L.tileLayer('https://{s}.tile.openstreetmap.fr/hot/{z}/{x}/{y}.png', {
                    attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors, Tiles style by <a href="https://www.hotosm.org/" target="_blank">HOT</a>',
                    maxZoom: 19
                }).addTo(map);

                if (navigator.geolocation) {
                    navigator.geolocation.getCurrentPosition(position => {
                        map.setView([position.coords.latitude, position.coords.longitude], 15);
                    });
                }
                map.on('click', function(e) { handleMapClick(e.latlng.lat, e.latlng.lng); });
            }
            setTimeout(() => { map.invalidateSize(); }, 200);
        }

        function closeMapModal() {
            document.getElementById('mapModal').classList.add('hidden');
        }

        async function handleMapClick(lat, lng) {
            if (marker) marker.setLatLng([lat, lng]);
            else marker = L.marker([lat, lng]).addTo(map);

            document.getElementById('mapLoading').classList.remove('hidden');

            try {
                // Using generic user-agent via email param to satisfy Nominatim requirements
                const response = await fetch(`https://nominatim.openstreetmap.org/reverse?format=jsonv2&lat=${lat}&lon=${lng}&zoom=18&addressdetails=1&email=student_project@example.com`, {
                    headers: { 'Accept-Language': 'en' }
                });
                
                if (!response.ok) throw new Error("Network error or Rate Limit");
                
                const data = await response.json();
                let placeName = '';
                
                // --- Name Detection Logic ---
                if (data.name) {
                    placeName = data.name;
                } 
                else if (data.address) {
                    const addr = data.address;
                    placeName = addr.building || addr.office || addr.amenity || addr.shop || addr.tourism || addr.leisure || addr.historic || addr.craft || addr.emergency || addr.military || '';
                    
                    if (!placeName && addr.road) {
                        placeName = addr.road;
                        if (addr.house_number) {
                            placeName = addr.house_number + ' ' + placeName;
                        }
                    }
                }

                if (!placeName && data.display_name) {
                    placeName = data.display_name.split(',')[0];
                }
                
                if (!placeName) {
                    placeName = "Selected Location";
                }

                if (data.address) {
                    const area = data.address.city || data.address.town || data.address.state || '';
                    if (area && !placeName.includes(area)) {
                        placeName += `, ${area}`;
                    }
                }

                document.getElementById('location').value = placeName;
                setTimeout(() => {
                    closeMapModal();
                    document.getElementById('mapLoading').classList.add('hidden');
                }, 500);

            } catch (error) {
                console.error("Geocoding failed", error);
                document.getElementById('location').value = `${lat.toFixed(5)}, ${lng.toFixed(5)}`;
                document.getElementById('mapLoading').classList.add('hidden');
                closeMapModal();
            }
        }

        // --- Image Logic ---
        const fileInput = document.getElementById('receiptFile');
        
        function triggerFileSelect() {
            fileInput.click();
        }
        
        function previewImage(input) {
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    document.getElementById('imagePreview').src = e.target.result;
                    document.getElementById('uploadPreview').classList.remove('hidden');
                    document.getElementById('uploadButtons').classList.add('hidden');
                }
                reader.readAsDataURL(input.files[0]);
            }
        }
        function clearImage() {
            fileInput.value = '';
            document.getElementById('uploadPreview').classList.add('hidden');
            document.getElementById('uploadButtons').classList.remove('hidden');
        }

        // --- UI Logic ---
        function switchType(type) {
            const expenseGrid = document.getElementById('expenseCategories');
            const incomeGrid = document.getElementById('incomeCategories');
            const btnExpense = document.getElementById('btn-expense');
            const btnIncome = document.getElementById('btn-income');
            const submitBtn = document.getElementById('submitBtn');
            const typeInput = document.getElementById('typeInput');
            
            typeInput.value = type;
            document.getElementById('categoryInput').value = '';
            
            document.querySelectorAll('.category-option').forEach(btn => {
                btn.classList.remove('bg-blue-100', 'border-blue-500', 'bg-green-100', 'border-green-500');
                btn.classList.add('bg-gray-50', 'border-gray-200');
            });

            if (type === 'income') {
                expenseGrid.classList.add('hidden');
                incomeGrid.classList.remove('hidden');
                btnExpense.classList.remove('active', 'text-white');
                btnExpense.classList.add('text-white/70');
                btnIncome.classList.add('active', 'text-white');
                btnIncome.classList.remove('text-white/70');
                submitBtn.innerHTML = '<i class="fas fa-save mr-3"></i> Save Income';
            } else {
                incomeGrid.classList.add('hidden');
                expenseGrid.classList.remove('hidden');
                btnIncome.classList.remove('active', 'text-white');
                btnIncome.classList.add('text-white/70');
                btnExpense.classList.add('active', 'text-white');
                btnExpense.classList.remove('text-white/70');
                submitBtn.innerHTML = '<i class="fas fa-save mr-3"></i> Save Expense';
            }
        }

        function selectCategory(name, el) {
            document.getElementById('categoryInput').value = name;
            const type = document.getElementById('typeInput').value;
            const activeBg = type === 'income' ? 'bg-green-100' : 'bg-blue-100';
            const activeBorder = type === 'income' ? 'border-green-500' : 'border-blue-500';
            
            document.querySelectorAll('.category-option').forEach(btn => {
                btn.classList.remove('bg-blue-100', 'border-blue-500', 'bg-green-100', 'border-green-500');
                btn.classList.add('bg-gray-50', 'border-gray-200');
            });
            el.classList.remove('bg-gray-50', 'border-gray-200');
            el.classList.add(activeBg, activeBorder);
        }

        document.querySelectorAll('.quick-amount').forEach(button => {
            button.addEventListener('click', function() {
                document.getElementById('amount').value = this.getAttribute('data-amount');
            });
        });

        document.getElementById('expense_date').value = new Date().toISOString().split('T')[0];

        // --- Auto-save ---
        const formInputs = document.querySelectorAll('#expenseForm input:not([type="file"]), #expenseForm select, #expenseForm textarea');
        formInputs.forEach(input => {
            input.addEventListener('input', function() {
                const formData = {
                    amount: document.getElementById('amount').value,
                    category: document.getElementById('categoryInput').value,
                    description: document.getElementById('description').value,
                    expense_date: document.getElementById('expense_date').value,
                    location: document.getElementById('location').value,
                    lastSaved: new Date().toISOString()
                };
                localStorage.setItem('expenseDraft', JSON.stringify(formData));
            });
        });

        window.addEventListener('load', function() {
            const draft = localStorage.getItem('expenseDraft');
            if (draft) {
                const formData = JSON.parse(draft);
                if(formData.amount) document.getElementById('amount').value = formData.amount;
                if(formData.category) document.getElementById('categoryInput').value = formData.category;
                if(formData.description) document.getElementById('description').value = formData.description;
                if(formData.expense_date) document.getElementById('expense_date').value = formData.expense_date;
                if(formData.location) document.getElementById('location').value = formData.location;
            }
        });

        document.getElementById('expenseForm').addEventListener('submit', function() {
            localStorage.removeItem('expenseDraft');
        });
        
        if (window.innerWidth <= 768) {
            document.querySelectorAll('input, select, textarea, button').forEach(el => {
                if(!el.classList.contains('text-xs') && !el.classList.contains('text-sm')) {
                     el.style.minHeight = '44px';
                }
            });
        }

        // --- Manual Geolocation Logic (Optimized for Indoor) ---
        function getUserLocation() {
            const btn = document.querySelector('button[onclick="getUserLocation()"] i');
            btn.classList.remove('fa-crosshairs');
            btn.classList.add('fa-spinner', 'fa-spin');

            if (!navigator.geolocation) {
                alert("Geolocation is not supported by your device.");
                resetIcon(btn);
                return;
            }

            navigator.geolocation.getCurrentPosition(
                (position) => {
                    const lat = position.coords.latitude;
                    const lng = position.coords.longitude;
                    
                    map.flyTo([lat, lng], 16);
                    
                    map.eachLayer((layer) => {
                        if (layer instanceof L.CircleMarker) {
                            map.removeLayer(layer);
                        }
                    });

                    L.circleMarker([lat, lng], {
                        radius: 8,
                        fillColor: "#3B82F6",
                        color: "#fff",
                        weight: 2,
                        opacity: 1,
                        fillOpacity: 0.8
                    }).addTo(map);

                    handleMapClick(lat, lng);
                    resetIcon(btn);
                },
                (error) => {
                    let msg = "Location error.";
                    switch(error.code) {
                        case error.PERMISSION_DENIED:
                            msg = "Permission denied. Please check App Info > Permissions.";
                            break;
                        case error.POSITION_UNAVAILABLE:
                            msg = "Location unavailable. Try moving near a window.";
                            break;
                        case error.TIMEOUT:
                            msg = "Location request timed out. Signal is weak.";
                            break;
                    }
                    alert(msg);
                    resetIcon(btn);
                },
                { 
                    enableHighAccuracy: false, 
                    timeout: 10000,            
                    maximumAge: 30000          
                }
            );
        }

        function resetIcon(btn) {
            btn.classList.remove('fa-spinner', 'fa-spin');
            btn.classList.add('fa-crosshairs');
        }
    </script>
</body>
</html>