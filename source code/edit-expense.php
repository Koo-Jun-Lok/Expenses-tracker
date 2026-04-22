<?php
// --- Includes and Dependencies ---
require_once 'includes/config.php';
require_once 'includes/auth-check.php';
require_once 'includes/session-handler.php';

// Ensure user is logged in
requireLogin();

$user_id = getUserId();
$success = '';
$error = '';

// --- 1. Validate ID & Fetch Existing Data ---
if (!isset($_GET['id'])) {
    header('Location: expenses.php');
    exit();
}

$expense_id = $_GET['id'];

// Fetch the specific expense record
$stmt = $pdo->prepare("SELECT * FROM expenses WHERE id = ? AND user_id = ?");
$stmt->execute([$expense_id, $user_id]);
$expense = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$expense) {
    header('Location: expenses.php'); // Redirect if not found or unauthorized
    exit();
}

// --- 2. Fetch Categories (Same as Add Page) ---
$stmt = $pdo->prepare("SELECT * FROM categories WHERE type = 'expense' ORDER BY name");
$stmt->execute();
$expense_categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

$stmt = $pdo->prepare("SELECT * FROM categories WHERE type = 'income' ORDER BY name");
$stmt->execute();
$income_categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

// --- 3. Handle Form Submission (Update Logic) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Sanitize inputs
    $type = $_POST['type'];
    $amount = trim($_POST['amount']);
    $category = trim($_POST['category']);
    $description = trim($_POST['description']);
    $expense_date = trim($_POST['expense_date']);
    $location = trim($_POST['location']);
    
    // Logic for Receipt Image (Keep old, Replace, or Remove)
    $receipt_image = $_POST['existing_receipt_image'] ?? null; // Default to existing

    // Check if user cleared the image (frontend sets hidden input to empty)
    if (isset($_POST['image_cleared']) && $_POST['image_cleared'] == '1') {
        $receipt_image = null; 
    }

    // Check if NEW image is uploaded
    if (isset($_FILES['receipt_image']) && $_FILES['receipt_image']['error'] === UPLOAD_ERR_OK) {
        $fileTmpPath = $_FILES['receipt_image']['tmp_name'];
        $fileName = $_FILES['receipt_image']['name'];
        $fileNameCmps = explode(".", $fileName);
        $fileExtension = strtolower(end($fileNameCmps));
        
        $allowedfileExtensions = array('jpg', 'gif', 'png', 'jpeg');
        if (in_array($fileExtension, $allowedfileExtensions)) {
            $newFileName = md5(time() . $fileName) . '.' . $fileExtension;
            $uploadFileDir = './uploads/receipts/';
            // Ensure directory exists
            if (!is_dir($uploadFileDir)) { mkdir($uploadFileDir, 0755, true); }
            
            $dest_path = $uploadFileDir . $newFileName;
            
            if(move_uploaded_file($fileTmpPath, $dest_path)) {
                $receipt_image = $newFileName; // Update to new file
            }
        }
    }

    // Validation
    if (empty($amount) || empty($category) || empty($expense_date)) {
        $error = 'Please fill in all required fields';
    } elseif (!is_numeric($amount) || $amount <= 0) {
        $error = 'Please enter a valid amount';
    } else {
        try {
            // Update SQL
            $sql = "UPDATE expenses 
                    SET type = ?, amount = ?, category = ?, description = ?, expense_date = ?, location = ?, receipt_image = ?
                    WHERE id = ? AND user_id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                $type,
                $amount,
                $category,
                $description,
                $expense_date,
                $location,
                $receipt_image,
                $expense_id,
                $user_id
            ]);
            
            // Redirect to expenses list with success message
            header("Location: expenses.php?msg=updated");
            exit;
            
        } catch (PDOException $e) {
            $error = 'Error updating transaction: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Edit Transaction</title>
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
                <a href="expenses.php" class="flex items-center text-white">
                    <i class="fas fa-arrow-left mr-2"></i> Cancel
                </a>
            </div>
            <div class="text-center">
                <h1 class="text-3xl font-bold">Edit Transaction</h1>
                <div class="flex justify-center mt-4">
                    <div class="bg-black/20 p-1 rounded-xl flex w-48">
                        <button type="button" onclick="switchType('expense')" id="btn-expense" class="toggle-btn w-1/2 py-1.5 rounded-lg text-sm text-white">Expense</button>
                        <button type="button" onclick="switchType('income')" id="btn-income" class="toggle-btn w-1/2 py-1.5 rounded-lg text-sm text-white/70 hover:text-white">Income</button>
                    </div>
                </div>
            </div>
        </header>

        <main class="flex-grow p-6 pb-32">
            <?php if ($error): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-lg mb-4 flex items-center">
                <i class="fas fa-exclamation-circle mr-2"></i> <?php echo htmlspecialchars($error); ?>
            </div>
            <?php endif; ?>

            <form id="editForm" method="POST" action="" enctype="multipart/form-data" class="space-y-6">
                <input type="hidden" name="type" id="typeInput" value="<?php echo htmlspecialchars($expense['type']); ?>">
                
                <div class="bg-white rounded-xl shadow-md p-5">
                    <label class="block text-gray-700 mb-3 font-semibold" for="amount">
                        <i class="fas fa-money-bill-wave text-green-500 mr-2"></i> Amount *
                    </label>
                    <div class="relative">
                        <div class="absolute left-3 top-3 text-gray-500 font-bold">RM</div>
                        <input type="number" id="amount" name="amount" step="0.01" min="0.01" 
                               class="w-full pl-12 pr-4 py-3 text-2xl font-bold border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent" 
                               placeholder="0.00" 
                               value="<?php echo htmlspecialchars($expense['amount']); ?>" required>
                    </div>
                    <div class="flex space-x-2 mt-3">
                        <button type="button" class="quick-amount bg-gray-100 text-gray-800 px-3 py-2 rounded-lg text-sm" data-amount="5">RM 5</button>
                        <button type="button" class="quick-amount bg-gray-100 text-gray-800 px-3 py-2 rounded-lg text-sm" data-amount="10">RM 10</button>
                        <button type="button" class="quick-amount bg-gray-100 text-gray-800 px-3 py-2 rounded-lg text-sm" data-amount="20">RM 20</button>
                        <button type="button" class="quick-amount bg-gray-100 text-gray-800 px-3 py-2 rounded-lg text-sm" data-amount="50">RM 50</button>
                    </div>
                </div>

                <div class="bg-white rounded-xl shadow-md p-5">
                    <label class="block text-gray-700 mb-3 font-semibold" for="category">
                        <i class="fas fa-tag text-blue-500 mr-2"></i> Category *
                    </label>
                    <input type="hidden" name="category" id="categoryInput" value="<?php echo htmlspecialchars($expense['category']); ?>" required>
                    
                    <div id="expenseCategories" class="grid grid-cols-4 gap-3 category-grid">
                        <?php foreach ($expense_categories as $cat): ?>
                        <button type="button" class="category-option bg-gray-50 border border-gray-200 rounded-lg p-3 text-center w-full" 
                                data-name="<?php echo htmlspecialchars($cat['name']); ?>"
                                onclick="selectCategory('<?php echo htmlspecialchars($cat['name']); ?>', this)">
                            <div class="text-xl mb-1"><?php echo htmlspecialchars($cat['icon']); ?></div>
                            <div class="text-xs text-gray-700 truncate"><?php echo htmlspecialchars($cat['name']); ?></div>
                        </button>
                        <?php endforeach; ?>
                    </div>
                    
                    <div id="incomeCategories" class="grid grid-cols-4 gap-3 category-grid hidden">
                        <?php foreach ($income_categories as $cat): ?>
                        <button type="button" class="category-option bg-gray-50 border border-gray-200 rounded-lg p-3 text-center w-full" 
                                data-name="<?php echo htmlspecialchars($cat['name']); ?>"
                                onclick="selectCategory('<?php echo htmlspecialchars($cat['name']); ?>', this)">
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
                    <textarea id="description" name="description" rows="2" 
                              class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent" 
                              placeholder="What is this for?"><?php echo htmlspecialchars($expense['description']); ?></textarea>
                </div>

                <div class="bg-white rounded-xl shadow-md p-5">
                    <label class="block text-gray-700 mb-3 font-semibold" for="expense_date">
                        <i class="fas fa-calendar-alt text-red-500 mr-2"></i> Date *
                    </label>
                    <input type="date" id="expense_date" name="expense_date" 
                           class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent" 
                           value="<?php echo htmlspecialchars($expense['expense_date']); ?>" required>
                </div>

                <div class="bg-white rounded-xl shadow-md p-5">
                    <label class="block text-gray-700 mb-3 font-semibold" for="location">
                        <i class="fas fa-map-marker-alt text-green-500 mr-2"></i> Location
                    </label>
                    <div class="flex w-full"> <input type="text" id="location" name="location" 
                               class="flex-grow min-w-0 px-4 py-3 border border-gray-300 rounded-l-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent" 
                               placeholder="Tap map button to select..." 
                               value="<?php echo htmlspecialchars($expense['location']); ?>">
                        
                        <button type="button" onclick="openMapModal()" class="flex-shrink-0 bg-blue-100 text-blue-600 px-5 rounded-r-lg border border-l-0 border-gray-300 hover:bg-blue-200 transition">
                            <i class="fas fa-map-marked-alt text-xl"></i>
                        </button>
                    </div>
                </div>

                <div class="bg-white rounded-xl shadow-md p-5">
                    <label class="block text-gray-700 mb-3 font-semibold">
                        <i class="fas fa-camera text-yellow-500 mr-2"></i> Receipt Photo
                    </label>
                    
                    <input type="file" id="receiptFile" name="receipt_image" accept="image/*" class="hidden" onchange="previewImage(this)">
                    <input type="hidden" name="existing_receipt_image" value="<?php echo htmlspecialchars($expense['receipt_image'] ?? ''); ?>">
                    <input type="hidden" name="image_cleared" id="imageCleared" value="0">
                    
                    <div id="uploadPreview" class="<?php echo !empty($expense['receipt_image']) ? '' : 'hidden'; ?> mb-3 relative">
                        <img id="imagePreview" 
                             src="<?php 
                                if (!empty($expense['receipt_image'])) {
                                    echo (strpos($expense['receipt_image'], 'data:image') === 0) ? $expense['receipt_image'] : 'uploads/receipts/' . $expense['receipt_image'];
                                } else {
                                    echo '#';
                                }
                             ?>" 
                             alt="Receipt Preview" class="w-full h-48 object-cover rounded-lg border border-gray-200">
                        
                        <button type="button" onclick="clearImage()" class="absolute top-2 right-2 bg-red-500 text-white rounded-full w-8 h-8 flex items-center justify-center shadow-lg">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>

                    <div id="uploadButtons" class="w-full <?php echo !empty($expense['receipt_image']) ? 'hidden' : ''; ?>">
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
            <button type="submit" form="editForm" id="submitBtn" class="w-full bg-gradient-to-r from-green-500 to-green-600 text-white py-4 rounded-xl font-bold text-lg shadow-lg hover:shadow-xl transition duration-300 flex items-center justify-center">
                <i class="fas fa-save mr-3"></i> Update Transaction
            </button>
        </div>
        
        <?php include 'includes/footer.php'; ?>
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
            <div class="p-3 bg-white text-center text-xs text-gray-500 border-t">Tap anywhere to select location</div>
        </div>
    </div>

    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>

    <script>
        // --- 1. Initialization Logic (Pre-fill Data) ---
        const savedType = "<?php echo $expense['type']; ?>";
        const savedCategory = "<?php echo $expense['category']; ?>";

        document.addEventListener('DOMContentLoaded', () => {
            // 1. Switch to the correct type (Expense/Income)
            switchType(savedType);
            
            // 2. Highlight the saved category
            const activeGrid = savedType === 'income' ? document.getElementById('incomeCategories') : document.getElementById('expenseCategories');
            const targetBtn = activeGrid.querySelector(`button[data-name="${savedCategory}"]`);
            
            if (targetBtn) {
                selectCategory(savedCategory, targetBtn);
            }
        });

        // --- 2. Switch Type Logic ---
        function switchType(type) {
            const expenseGrid = document.getElementById('expenseCategories');
            const incomeGrid = document.getElementById('incomeCategories');
            const btnExpense = document.getElementById('btn-expense');
            const btnIncome = document.getElementById('btn-income');
            const submitBtn = document.getElementById('submitBtn');
            const typeInput = document.getElementById('typeInput');
            
            typeInput.value = type;
            
            if (type === 'income') {
                expenseGrid.classList.add('hidden');
                incomeGrid.classList.remove('hidden');
                
                btnExpense.classList.remove('active', 'text-white');
                btnExpense.classList.add('text-white/70');
                btnIncome.classList.add('active', 'text-white');
                btnIncome.classList.remove('text-white/70');
                
                submitBtn.innerHTML = '<i class="fas fa-save mr-3"></i> Update Income';
            } else {
                incomeGrid.classList.add('hidden');
                expenseGrid.classList.remove('hidden');
                
                btnIncome.classList.remove('active', 'text-white');
                btnIncome.classList.add('text-white/70');
                btnExpense.classList.add('active', 'text-white');
                btnExpense.classList.remove('text-white/70');
                
                submitBtn.innerHTML = '<i class="fas fa-save mr-3"></i> Update Expense';
            }
        }

        // --- 3. Category Selection Logic ---
        function selectCategory(name, el) {
            document.getElementById('categoryInput').value = name;
            const type = document.getElementById('typeInput').value;
            
            const activeBg = type === 'income' ? 'bg-green-100' : 'bg-blue-100';
            const activeBorder = type === 'income' ? 'border-green-500' : 'border-blue-500';
            
            // Clear styles from ALL buttons in both grids
            document.querySelectorAll('.category-option').forEach(btn => {
                btn.classList.remove('bg-blue-100', 'border-blue-500', 'bg-green-100', 'border-green-500');
                btn.classList.add('bg-gray-50', 'border-gray-200');
            });
            
            // Add style to selected
            el.classList.remove('bg-gray-50', 'border-gray-200');
            el.classList.add(activeBg, activeBorder);
        }

        // --- 4. Amount Buttons ---
        document.querySelectorAll('.quick-amount').forEach(button => {
            button.addEventListener('click', function() {
                document.getElementById('amount').value = this.getAttribute('data-amount');
            });
        });

        // --- 5. Image Handling ---
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
                    
                    // Reset "cleared" flag because user selected a new image
                    document.getElementById('imageCleared').value = "0";
                }
                reader.readAsDataURL(input.files[0]);
            }
        }

        function clearImage() {
            fileInput.value = ''; // Clear input
            document.getElementById('uploadPreview').classList.add('hidden');
            document.getElementById('uploadButtons').classList.remove('hidden');
            
            // Mark as cleared so backend knows to remove the old image
            document.getElementById('imageCleared').value = "1";
        }

        // --- 6. Map Logic ---
        let map, marker;
        function openMapModal() {
            document.getElementById('mapModal').classList.remove('hidden');
            if (!map) {
                map = L.map('map').setView([4.2105, 101.9758], 6);
                
                // Using HOT style for better POI visibility
                L.tileLayer('https://{s}.tile.openstreetmap.fr/hot/{z}/{x}/{y}.png', {
                    attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
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
                const response = await fetch(`https://nominatim.openstreetmap.org/reverse?format=json&lat=${lat}&lon=${lng}&zoom=18&addressdetails=1`, {
                    headers: { 'Accept-Language': 'en' }
                });
                const data = await response.json();
                
                let placeName = '';
                if (data.name) placeName = data.name;
                else if (data.address) {
                    const addr = data.address;
                    const specific = addr.amenity || addr.shop || addr.tourism || addr.historic || addr.leisure || addr.building || addr.office;
                    if (specific) placeName = specific;
                    else if (addr.road) {
                        placeName = addr.road;
                        if (addr.house_number) placeName += ` ${addr.house_number}`;
                    }
                    else if (addr.suburb) placeName = addr.suburb;
                }
                if (!placeName && data.display_name) placeName = data.display_name.split(',')[0];
                if (!placeName) placeName = "Selected Location";

                document.getElementById('location').value = placeName;
                setTimeout(() => {
                    closeMapModal();
                    document.getElementById('mapLoading').classList.add('hidden');
                }, 500);
            } catch (error) {
                document.getElementById('location').value = `${lat.toFixed(5)}, ${lng.toFixed(5)}`;
                document.getElementById('mapLoading').classList.add('hidden');
                closeMapModal();
            }
        }

        // --- Manual Geolocation Logic ---
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
                    
                    // Remove old marker
                    map.eachLayer((layer) => {
                        if (layer instanceof L.CircleMarker) {
                            map.removeLayer(layer);
                        }
                    });

                    L.circleMarker([lat, lng], {
                        radius: 8, fillColor: "#3B82F6", color: "#fff", weight: 2, opacity: 1, fillOpacity: 0.8
                    }).addTo(map);

                    handleMapClick(lat, lng);
                    resetIcon(btn);
                },
                (error) => {
                    let msg = "Location error.";
                    switch(error.code) {
                        case error.PERMISSION_DENIED: msg = "Permission denied."; break;
                        case error.POSITION_UNAVAILABLE: msg = "Location unavailable."; break;
                        case error.TIMEOUT: msg = "Timeout."; break;
                    }
                    alert(msg);
                    resetIcon(btn);
                },
                { enableHighAccuracy: false, timeout: 10000, maximumAge: 30000 }
            );
        }

        function resetIcon(btn) {
            btn.classList.remove('fa-spinner', 'fa-spin');
            btn.classList.add('fa-crosshairs');
        }

        // Mobile UI fix
        if (window.innerWidth <= 768) {
            document.querySelectorAll('input, select, textarea, button').forEach(el => {
                if(!el.classList.contains('text-xs') && !el.classList.contains('text-sm')) {
                     el.style.minHeight = '44px';
                }
            });
        }
    </script>
</body>
</html>