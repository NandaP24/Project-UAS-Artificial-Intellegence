<?php
include_once('koneksi.db.php');

class PineExpertSystem {
    private $koneksi;
    private $trainingData = [];
    private $uploadDir = 'uploads/';
    
    public function __construct($koneksi) {
        $this->koneksi = $koneksi;
        $this->loadTrainingData();
        $this->createUploadDirectory();
    }
    
    // Buat direktori upload jika belum ada
    private function createUploadDirectory() {
        if (!file_exists($this->uploadDir)) {
            mkdir($this->uploadDir, 0755, true);
        }
    }
    
    // Load data training dari database
    private function loadTrainingData() {
        $query = "SELECT * FROM datatraining";
        $result = mysqli_query($this->koneksi, $query);
        
        while ($row = mysqli_fetch_assoc($result)) {
            $this->trainingData[] = [
                'diameter' => $row['Diameter'],
                'tinggi' => $row['Tinggi'],
                'jenis' => $row['Jenis']
            ];
        }
    }
    
    // Hitung jarak Euclidean untuk KNN
    private function calculateDistance($point1, $point2) {
        $diameterDiff = $point1['diameter'] - $point2['diameter'];
        $tinggiDiff = $point1['tinggi'] - $point2['tinggi'];
        return sqrt(($diameterDiff * $diameterDiff) + ($tinggiDiff * $tinggiDiff));
    }
    
    // Implementasi KNN
    public function knnClassification($diameter, $tinggi, $k = 5) {
        $inputPoint = ['diameter' => $diameter, 'tinggi' => $tinggi];
        $distances = [];
        
        // Hitung jarak ke semua data training
        foreach ($this->trainingData as $data) {
            $distance = $this->calculateDistance($inputPoint, $data);
            $distances[] = [
                'distance' => $distance,
                'jenis' => $data['jenis']
            ];
        }
        
        // Urutkan berdasarkan jarak
        usort($distances, function($a, $b) {
            return $a['distance'] <=> $b['distance'];
        });
        
        // Ambil K tetangga terdekat
        $neighbors = array_slice($distances, 0, $k);
        
        // Hitung voting
        $votes = [];
        foreach ($neighbors as $neighbor) {
            $jenis = $neighbor['jenis'];
            if (!isset($votes[$jenis])) {
                $votes[$jenis] = 0;
            }
            $votes[$jenis]++;
        }
        
        // Tentukan hasil klasifikasi
        arsort($votes);
        $predictedClass = array_keys($votes)[0];
        $confidence = $votes[$predictedClass] / $k;
        
        return [
            'predicted_class' => $predictedClass,
            'confidence' => $confidence,
            'votes' => $votes,
            'neighbors' => $neighbors
        ];
    }
    
    // Rules untuk Expert System dengan Certainty Factor
    private function douglasFirRules($diameter, $tinggi) {
        $cf = 0;
        
        // Rule 1: Diameter kecil (0.15-0.65) untuk Douglas Fir
        if ($diameter >= 0.15 && $diameter <= 0.65) {
            $cf += 0.7;
        }
        
        // Rule 2: Tinggi sedang (2.67-13.50) untuk Douglas Fir
        if ($tinggi >= 2.67 && $tinggi <= 13.50) {
            $cf += 0.8;
        }
        
        // Rule 3: Kombinasi diameter dan tinggi yang proporsional
        $ratio = $tinggi / $diameter;
        if ($ratio >= 10 && $ratio <= 30) {
            $cf += 0.6;
        }
        
        // Rule 4: Karakteristik khusus Douglas Fir
        if ($diameter < 0.5 && $tinggi < 15) {
            $cf += 0.5;
        }
        
        return min($cf, 1.0); // CF maksimal 1.0
    }
    
    private function whitePineRules($diameter, $tinggi) {
        $cf = 0;
        
        // Rule 1: Diameter relatif kecil (0.17-0.45) untuk White Pine
        if ($diameter >= 0.17 && $diameter <= 0.45) {
            $cf += 0.6;
        }
        
        // Rule 2: Tinggi tinggi (19.72-32.51) untuk White Pine
        if ($tinggi >= 19.72 && $tinggi <= 32.51) {
            $cf += 0.9;
        }
        
        // Rule 3: Rasio tinggi/diameter yang tinggi
        $ratio = $tinggi / $diameter;
        if ($ratio >= 50 && $ratio <= 120) {
            $cf += 0.8;
        }
        
        // Rule 4: Karakteristik khusus White Pine
        if ($diameter < 0.5 && $tinggi > 20) {
            $cf += 0.7;
        }
        
        return min($cf, 1.0); // CF maksimal 1.0
    }
    
    // Expert System dengan Certainty Factor
    public function expertSystemClassification($diameter, $tinggi) {
        $douglasCF = $this->douglasFirRules($diameter, $tinggi);
        $whitePineCF = $this->whitePineRules($diameter, $tinggi);
        
        $results = [
            'Douglas Fir' => $douglasCF,
            'White Pine' => $whitePineCF
        ];
        
        arsort($results);
        $predictedClass = array_keys($results)[0];
        $confidence = $results[$predictedClass];
        
        return [
            'predicted_class' => $predictedClass,
            'confidence' => $confidence,
            'all_cf' => $results
        ];
    }
    
    // Upload dan validasi gambar
    public function uploadImage($file) {
        $allowedTypes = ['image/jpeg', 'image/png', 'image/jpg'];
        $maxSize = 5 * 1024 * 1024; // 5MB
        
        if (!in_array($file['type'], $allowedTypes)) {
            return ['success' => false, 'message' => 'Format file tidak didukung. Gunakan JPG, JPEG, atau PNG.'];
        }
        
        if ($file['size'] > $maxSize) {
            return ['success' => false, 'message' => 'Ukuran file terlalu besar. Maksimal 5MB.'];
        }
        
        $fileName = uniqid() . '_' . basename($file['name']);
        $filePath = $this->uploadDir . $fileName;
        
        if (move_uploaded_file($file['tmp_name'], $filePath)) {
            return ['success' => true, 'path' => $filePath, 'filename' => $fileName];
        }
        
        return ['success' => false, 'message' => 'Gagal mengupload file.'];
    }
    
    // Simulasi Computer Vision - Ekstraksi fitur dari gambar
    public function extractImageFeatures($imagePath) {
        // Placeholder untuk computer vision processing
        // Dalam implementasi nyata, ini akan menggunakan OpenCV, TensorFlow, atau library CV lainnya
        
        if (!file_exists($imagePath)) {
            return ['success' => false, 'message' => 'File gambar tidak ditemukan.'];
        }
        
        // Simulasi ekstraksi fitur menggunakan analisis pixel dasar
        $imageInfo = getimagesize($imagePath);
        $width = $imageInfo[0];
        $height = $imageInfo[1];
        
        // Simulasi deteksi objek pohon (placeholder)
        $mockFeatures = $this->simulateTreeDetection($imagePath, $width, $height);
        
        return [
            'success' => true,
            'features' => $mockFeatures,
            'image_info' => [
                'width' => $width,
                'height' => $height,
                'type' => $imageInfo['mime']
            ]
        ];
    }
    
    // Simulasi deteksi pohon dari gambar
    private function simulateTreeDetection($imagePath, $width, $height) {
        // Simulasi computer vision processing
        // Dalam implementasi nyata, ini akan menggunakan:
        // - Object detection untuk mendeteksi pohon
        // - Image segmentation untuk mengukur dimensi
        // - Feature extraction untuk karakteristik visual
        
        // Generate mock data berdasarkan analisis gambar sederhana
        $mockDiameter = rand(15, 65) / 100; // 0.15 - 0.65
        $mockHeight = rand(300, 3500) / 100; // 3.00 - 35.00
        
        // Simulasi confidence berdasarkan kualitas gambar
        $mockConfidence = rand(70, 95) / 100; // 0.70 - 0.95
        
        // Simulasi deteksi karakteristik visual
        $visualFeatures = [
            'bark_texture' => rand(1, 10) / 10,
            'leaf_density' => rand(1, 10) / 10,
            'branch_pattern' => rand(1, 10) / 10,
            'tree_shape' => rand(1, 10) / 10
        ];
        
        return [
            'detected_objects' => [
                [
                    'object' => 'tree',
                    'confidence' => $mockConfidence,
                    'bounding_box' => [
                        'x' => rand(0, $width/4),
                        'y' => rand(0, $height/4),
                        'width' => rand($width/2, $width),
                        'height' => rand($height/2, $height)
                    ]
                ]
            ],
            'estimated_diameter' => $mockDiameter,
            'estimated_height' => $mockHeight,
            'visual_features' => $visualFeatures,
            'processing_status' => 'simulated'
        ];
    }
    
    // Klasifikasi hybrid dengan data dari computer vision
    public function hybridClassificationWithCV($diameter, $tinggi, $cvFeatures = null, $k = 5) {
        $result = $this->hybridClassification($diameter, $tinggi, $k);
        
        if ($cvFeatures) {
            // Tambahkan bobot berdasarkan computer vision
            $cvWeight = 0.2;
            $originalWeight = 0.8;
            
            // Simulasi CV-based classification
            $cvClassification = $this->classifyFromVisualFeatures($cvFeatures['visual_features']);
            
            // Kombinasi hasil
            $finalScores = [];
            foreach ($result['hybrid_scores'] as $class => $score) {
                $cvScore = isset($cvClassification[$class]) ? $cvClassification[$class] : 0;
                $finalScores[$class] = ($score * $originalWeight) + ($cvScore * $cvWeight);
            }
            
            arsort($finalScores);
            $finalPrediction = array_keys($finalScores)[0];
            
            $result['cv_enhanced'] = true;
            $result['cv_classification'] = $cvClassification;
            $result['final_prediction'] = $finalPrediction;
            $result['final_confidence'] = $finalScores[$finalPrediction];
            $result['enhanced_scores'] = $finalScores;
        }
        
        return $result;
    }
    
    // Klasifikasi berdasarkan fitur visual
    private function classifyFromVisualFeatures($visualFeatures) {
        // Simulasi klasifikasi berdasarkan fitur visual
        $douglasScore = 0;
        $pineScore = 0;
        
        // Rule berdasarkan karakteristik visual (simulasi)
        if ($visualFeatures['bark_texture'] > 0.6) {
            $douglasScore += 0.3;
        } else {
            $pineScore += 0.3;
        }
        
        if ($visualFeatures['leaf_density'] > 0.7) {
            $pineScore += 0.4;
        } else {
            $douglasScore += 0.4;
        }
        
        if ($visualFeatures['branch_pattern'] > 0.5) {
            $douglasScore += 0.3;
        } else {
            $pineScore += 0.3;
        }
        
        return [
            'Douglas Fir' => min($douglasScore, 1.0),
            'White Pine' => min($pineScore, 1.0)
        ];
    }
    
    // Simpan hasil analisis gambar ke database
    public function saveImageAnalysis($imagePath, $features, $classification) {
        $query = "INSERT INTO image_analysis (image_path, features, classification_result, created_at) VALUES (?, ?, ?, NOW())";
        
        // Buat tabel jika belum ada
        $createTable = "CREATE TABLE IF NOT EXISTS image_analysis (
            id INT AUTO_INCREMENT PRIMARY KEY,
            image_path VARCHAR(255) NOT NULL,
            features TEXT,
            classification_result TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )";
        
        mysqli_query($this->koneksi, $createTable);
        
        $stmt = mysqli_prepare($this->koneksi, $query);
        $featuresJson = json_encode($features);
        $classificationJson = json_encode($classification);
        
        mysqli_stmt_bind_param($stmt, 'sss', $imagePath, $featuresJson, $classificationJson);
        return mysqli_stmt_execute($stmt);
    }
    public function hybridClassification($diameter, $tinggi, $k = 5) {
        $knnResult = $this->knnClassification($diameter, $tinggi, $k);
        $expertResult = $this->expertSystemClassification($diameter, $tinggi);
        
        // Bobot kombinasi
        $knnWeight = 0.6;
        $expertWeight = 0.4;
        
        // Kombinasi hasil
        $hybridScores = [];
        $classes = ['Douglas Fir', 'White Pine'];
        
        foreach ($classes as $class) {
            $knnScore = 0;
            $expertScore = 0;
            
            // Skor KNN
            if ($knnResult['predicted_class'] == $class) {
                $knnScore = $knnResult['confidence'];
            }
            
            // Skor Expert System
            $expertScore = $expertResult['all_cf'][$class];
            
            // Kombinasi skor
            $hybridScores[$class] = ($knnScore * $knnWeight) + ($expertScore * $expertWeight);
        }
        
        arsort($hybridScores);
        $finalPrediction = array_keys($hybridScores)[0];
        $finalConfidence = $hybridScores[$finalPrediction];
        
        return [
            'final_prediction' => $finalPrediction,
            'final_confidence' => $finalConfidence,
            'hybrid_scores' => $hybridScores,
            'knn_result' => $knnResult,
            'expert_result' => $expertResult
        ];
    }
    
    // Evaluasi akurasi sistem
    public function evaluateSystem() {
        $correct = 0;
        $total = count($this->trainingData);
        
        foreach ($this->trainingData as $data) {
            $result = $this->hybridClassification($data['diameter'], $data['tinggi']);
            if ($result['final_prediction'] == $data['jenis']) {
                $correct++;
            }
        }
        
        return [
            'accuracy' => ($correct / $total) * 100,
            'correct' => $correct,
            'total' => $total
        ];
    }
}

// Penggunaan sistem
$expertSystem = new PineExpertSystem($koneksi);

// Interface web sederhana
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Expert System - Klasifikasi Pohon Pinus</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
            background-color: #f8f9fa;
            color: #333;
            line-height: 1.6;
        }
        .container {
            background-color: white;
            padding: 40px;
            border-radius: 8px;
            box-shadow: 0 2px 20px rgba(0,0,0,0.08);
            border: 1px solid #e9ecef;
        }
        h1 {
            color: #2c3e50;
            text-align: center;
            margin-bottom: 40px;
            font-weight: 300;
            font-size: 2.2em;
        }
        h3 {
            color: #34495e;
            margin-bottom: 20px;
            font-weight: 400;
            border-bottom: 2px solid #3498db;
            padding-bottom: 8px;
        }
        h4 {
            color: #5a6c7d;
            margin-bottom: 15px;
            font-weight: 500;
        }
        .form-group {
            margin-bottom: 25px;
        }
        label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: #495057;
        }
        input[type="number"] {
            width: 100%;
            padding: 12px 16px;
            border: 2px solid #e9ecef;
            border-radius: 6px;
            font-size: 16px;
            transition: border-color 0.3s ease;
            background-color: #fff;
        }
        input[type="number"]:focus {
            outline: none;
            border-color: #3498db;
        }
        .btn {
            background-color: #3498db;
            color: white;
            padding: 12px 24px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 16px;
            margin-right: 10px;
            transition: background-color 0.3s ease;
            font-weight: 500;
        }
        .btn:hover {
            background-color: #2980b9;
        }
        .result {
            margin-top: 40px;
            padding: 25px;
            background-color: #f8f9fa;
            border-radius: 8px;
            border-left: 4px solid #3498db;
        }
        .accuracy {
            background-color: #e8f5e8;
            border-left-color: #28a745;
        }
        .method-result {
            margin: 20px 0;
            padding: 20px;
            background-color: white;
            border-radius: 6px;
            border: 1px solid #e9ecef;
        }
        .confidence-bar {
            width: 100%;
            height: 8px;
            background-color: #e9ecef;
            border-radius: 4px;
            overflow: hidden;
            margin-top: 10px;
        }
        .confidence-fill {
            height: 100%;
            background-color: #3498db;
            transition: width 0.5s ease;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
            background-color: white;
        }
        th, td {
            border: 1px solid #dee2e6;
            padding: 12px;
            text-align: left;
        }
        th {
            background-color: #f8f9fa;
            color: #495057;
            font-weight: 500;
        }
        tr:nth-child(even) {
            background-color: #f8f9fa;
        }
        ul {
            margin: 10px 0;
            padding-left: 20px;
        }
        li {
            margin-bottom: 5px;
        }
        .upload-section {
            margin: 30px 0;
            padding: 25px;
            background-color: #f8f9fa;
            border-radius: 8px;
            border: 2px dashed #dee2e6;
            text-align: center;
        }
        .upload-section.dragover {
            border-color: #3498db;
            background-color: #e3f2fd;
        }
        .file-input {
            margin: 15px 0;
        }
        .file-input input[type="file"] {
            display: none;
        }
        .file-input label {
            background-color: #6c757d;
            color: white;
            padding: 10px 20px;
            border-radius: 6px;
            cursor: pointer;
            display: inline-block;
            transition: background-color 0.3s ease;
        }
        .file-input label:hover {
            background-color: #5a6268;
        }
        .image-preview {
            margin: 20px 0;
            max-width: 100%;
            text-align: center;
        }
        .image-preview img {
            max-width: 300px;
            max-height: 300px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .cv-results {
            background-color: #e8f4f8;
            border-left-color: #17a2b8;
        }
        .feature-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-top: 15px;
        }
        .feature-item {
            background-color: white;
            padding: 15px;
            border-radius: 6px;
            border: 1px solid #dee2e6;
        }
        .feature-value {
            font-size: 1.2em;
            font-weight: bold;
            color: #3498db;
        }
        .tabs {
            display: flex;
            margin-bottom: 20px;
            border-bottom: 2px solid #dee2e6;
        }
        .tab {
            padding: 12px 24px;
            background-color: #f8f9fa;
            border: none;
            border-bottom: 2px solid transparent;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        .tab.active {
            background-color: white;
            border-bottom-color: #3498db;
            color: #3498db;
        }
        .tab-content {
            display: none;
        }
        .tab-content.active {
            display: block;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Expert System - Klasifikasi Pohon Pinus</h1>
        
        <div class="tabs">
            <button class="tab active" onclick="showTab('manual')">Input Manual</button>
            <button class="tab" onclick="showTab('vision')">Computer Vision</button>
        </div>
    
    <script>
        function showTab(tabName) {
            // Hide all tab contents
            const contents = document.querySelectorAll('.tab-content');
            contents.forEach(content => content.classList.remove('active'));
            
            // Remove active class from all tabs
            const tabs = document.querySelectorAll('.tab');
            tabs.forEach(tab => tab.classList.remove('active'));
            
            // Show selected tab content
            document.getElementById(tabName + '-tab').classList.add('active');
            
            // Add active class to selected tab
            event.target.classList.add('active');
        }
        
        function previewImage(input) {
            const preview = document.getElementById('image-preview');
            const previewImg = document.getElementById('preview-img');
            
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                
                reader.onload = function(e) {
                    previewImg.src = e.target.result;
                    preview.style.display = 'block';
                }
                
                reader.readAsDataURL(input.files[0]);
            }
        }
        
        // Drag and drop functionality
        const uploadArea = document.getElementById('upload-area');
        const fileInput = document.getElementById('tree-image');
        
        uploadArea.addEventListener('dragover', function(e) {
            e.preventDefault();
            uploadArea.classList.add('dragover');
        });
        
        uploadArea.addEventListener('dragleave', function(e) {
            e.preventDefault();
            uploadArea.classList.remove('dragover');
        });
        
        uploadArea.addEventListener('drop', function(e) {
            e.preventDefault();
            uploadArea.classList.remove('dragover');
            
            const files = e.dataTransfer.files;
            if (files.length > 0) {
                fileInput.files = files;
                previewImage(fileInput);
            }
        });
        
        uploadArea.addEventListener('click', function() {
            fileInput.click();
        });
    </script>
        
        <div id="manual-tab" class="tab-content active">
            <form method="POST">
                <div class="form-group">
                    <label for="diameter">Diameter (meter):</label>
                    <input type="number" id="diameter" name="diameter" step="0.01" min="0" 
                           value="<?php echo isset($_POST['diameter']) ? $_POST['diameter'] : ''; ?>" required>
                </div>
                
                <div class="form-group">
                    <label for="tinggi">Tinggi (meter):</label>
                    <input type="number" id="tinggi" name="tinggi" step="0.01" min="0" 
                           value="<?php echo isset($_POST['tinggi']) ? $_POST['tinggi'] : ''; ?>" required>
                </div>
                
                <button type="submit" name="classify" class="btn">Klasifikasi</button>
                <button type="submit" name="evaluate" class="btn">Evaluasi Sistem</button>
            </form>
        </div>
        
        <div id="vision-tab" class="tab-content">
            <form method="POST" enctype="multipart/form-data">
                <div class="upload-section" id="upload-area">
                    <h4>Upload Gambar Pohon</h4>
                    <p>Seret dan lepas gambar di sini atau klik untuk memilih file</p>
                    <div class="file-input">
                        <input type="file" id="tree-image" name="tree_image" accept="image/*" onchange="previewImage(this)">
                        <label for="tree-image">Pilih Gambar</label>
                    </div>
                    <p style="font-size: 14px; color: #6c757d;">Format: JPG, PNG, JPEG (Max: 5MB)</p>
                </div>
                
                <div id="image-preview" class="image-preview" style="display: none;">
                    <img id="preview-img" src="" alt="Preview">
                </div>
                
                <div class="form-group">
                    <label>Atau masukkan dimensi manual (opsional):</label>
                    <div style="display: flex; gap: 15px;">
                        <input type="number" name="manual_diameter" placeholder="Diameter (m)" step="0.01" min="0" style="flex: 1;">
                        <input type="number" name="manual_tinggi" placeholder="Tinggi (m)" step="0.01" min="0" style="flex: 1;">
                    </div>
                </div>
                
                <button type="submit" name="analyze_image" class="btn">Analisis Gambar</button>
            </form>
        </div>
        
        <?php
        if (isset($_POST['analyze_image'])) {
            if (isset($_FILES['tree_image']) && $_FILES['tree_image']['error'] == 0) {
                $uploadResult = $expertSystem->uploadImage($_FILES['tree_image']);
                
                if ($uploadResult['success']) {
                    $cvResult = $expertSystem->extractImageFeatures($uploadResult['path']);
                    
                    if ($cvResult['success']) {
                        $features = $cvResult['features'];
                        
                        // Gunakan dimensi manual jika tersedia, atau dari CV
                        $diameter = !empty($_POST['manual_diameter']) ? floatval($_POST['manual_diameter']) : $features['estimated_diameter'];
                        $tinggi = !empty($_POST['manual_tinggi']) ? floatval($_POST['manual_tinggi']) : $features['estimated_height'];
                        
                        $result = $expertSystem->hybridClassificationWithCV($diameter, $tinggi, $features);
                        
                        // Simpan hasil ke database
                        $expertSystem->saveImageAnalysis($uploadResult['path'], $features, $result);
                        
                        echo '<div class="result cv-results">';
                        echo '<h3>Hasil Analisis Computer Vision</h3>';
                        
                        echo '<div class="image-preview">';
                        echo '<img src="' . $uploadResult['path'] . '" alt="Analyzed Image" style="max-width: 250px;">';
                        echo '</div>';
                        
                        echo '<div class="feature-grid">';
                        echo '<div class="feature-item">';
                        echo '<h4>Dimensi Terdeteksi</h4>';
                        echo '<p><strong>Diameter:</strong> <span class="feature-value">' . number_format($features['estimated_diameter'], 2) . ' m</span></p>';
                        echo '<p><strong>Tinggi:</strong> <span class="feature-value">' . number_format($features['estimated_height'], 2) . ' m</span></p>';
                        echo '</div>';
                        
                        echo '<div class="feature-item">';
                        echo '<h4>Deteksi Objek</h4>';
                        foreach ($features['detected_objects'] as $obj) {
                            echo '<p><strong>' . ucfirst($obj['object']) . ':</strong> <span class="feature-value">' . number_format($obj['confidence'] * 100, 1) . '%</span></p>';
                        }
                        echo '</div>';
                        
                        echo '<div class="feature-item">';
                        echo '<h4>Fitur Visual</h4>';
                        echo '<p><strong>Tekstur Kulit:</strong> <span class="feature-value">' . number_format($features['visual_features']['bark_texture'] * 100, 1) . '%</span></p>';
                        echo '<p><strong>Kepadatan Daun:</strong> <span class="feature-value">' . number_format($features['visual_features']['leaf_density'] * 100, 1) . '%</span></p>';
                        echo '<p><strong>Pola Cabang:</strong> <span class="feature-value">' . number_format($features['visual_features']['branch_pattern'] * 100, 1) . '%</span></p>';
                        echo '</div>';
                        
                        echo '<div class="feature-item">';
                        echo '<h4>Info Gambar</h4>';
                        echo '<p><strong>Ukuran:</strong> ' . $cvResult['image_info']['width'] . ' x ' . $cvResult['image_info']['height'] . ' px</p>';
                        echo '<p><strong>Format:</strong> ' . $cvResult['image_info']['type'] . '</p>';
                        echo '<p><strong>Status:</strong> <span style="color: #f39c12;">Simulasi</span></p>';
                        echo '</div>';
                        echo '</div>';
                        
                        echo '<h4>Klasifikasi Hasil</h4>';
                        echo '<p><strong>Prediksi Akhir:</strong> ' . $result['final_prediction'] . '</p>';
                        echo '<p><strong>Tingkat Kepercayaan:</strong> ' . number_format($result['final_confidence'] * 100, 2) . '%</p>';
                        
                        echo '<div class="confidence-bar">';
                        echo '<div class="confidence-fill" style="width: ' . ($result['final_confidence'] * 100) . '%"></div>';
                        echo '</div>';
                        
                        if (isset($result['cv_enhanced'])) {
                            echo '<div class="method-result">';
                            echo '<h4>Klasifikasi Berbasis Computer Vision</h4>';
                            echo '<table>';
                            echo '<tr><th>Jenis</th><th>Skor CV</th><th>Skor Enhanced</th></tr>';
                            foreach ($result['cv_classification'] as $class => $cvScore) {
                                $enhancedScore = $result['enhanced_scores'][$class];
                                echo '<tr>';
                                echo '<td>' . $class . '</td>';
                                echo '<td>' . number_format($cvScore * 100, 2) . '%</td>';
                                echo '<td>' . number_format($enhancedScore * 100, 2) . '%</td>';
                                echo '</tr>';
                            }
                            echo '</table>';
                            echo '</div>';
                        }
                        
                        echo '</div>';
                    } else {
                        echo '<div class="result" style="border-left-color: #e74c3c;">';
                        echo '<p style="color: #e74c3c;">Error: ' . $cvResult['message'] . '</p>';
                        echo '</div>';
                    }
                } else {
                    echo '<div class="result" style="border-left-color: #e74c3c;">';
                    echo '<p style="color: #e74c3c;">Error: ' . $uploadResult['message'] . '</p>';
                    echo '</div>';
                }
            } else {
                echo '<div class="result" style="border-left-color: #e74c3c;">';
                echo '<p style="color: #e74c3c;">Error: Silakan pilih gambar untuk dianalisis.</p>';
                echo '</div>';
            }
        }
        
        if (isset($_POST['classify'])) {
            $diameter = floatval($_POST['diameter']);
            $tinggi = floatval($_POST['tinggi']);
            
            $result = $expertSystem->hybridClassification($diameter, $tinggi);
            
            echo '<div class="result">';
            echo '<h3>Hasil Klasifikasi Hybrid</h3>';
            echo '<p><strong>Prediksi Akhir:</strong> ' . $result['final_prediction'] . '</p>';
            echo '<p><strong>Tingkat Kepercayaan:</strong> ' . number_format($result['final_confidence'] * 100, 2) . '%</p>';
            
            echo '<div class="confidence-bar">';
            echo '<div class="confidence-fill" style="width: ' . ($result['final_confidence'] * 100) . '%"></div>';
            echo '</div>';
            
            echo '<div class="method-result">';
            echo '<h4>KNN Classification (K=5)</h4>';
            echo '<p><strong>Prediksi:</strong> ' . $result['knn_result']['predicted_class'] . '</p>';
            echo '<p><strong>Confidence:</strong> ' . number_format($result['knn_result']['confidence'] * 100, 2) . '%</p>';
            echo '<p><strong>Voting:</strong> ';
            foreach ($result['knn_result']['votes'] as $class => $votes) {
                echo $class . ' (' . $votes . ') ';
            }
            echo '</p>';
            echo '</div>';
            
            echo '<div class="method-result">';
            echo '<h4>Expert System (Certainty Factor)</h4>';
            echo '<p><strong>Prediksi:</strong> ' . $result['expert_result']['predicted_class'] . '</p>';
            echo '<p><strong>Confidence:</strong> ' . number_format($result['expert_result']['confidence'] * 100, 2) . '%</p>';
            echo '<p><strong>Certainty Factors:</strong></p>';
            echo '<ul>';
            foreach ($result['expert_result']['all_cf'] as $class => $cf) {
                echo '<li>' . $class . ': ' . number_format($cf * 100, 2) . '%</li>';
            }
            echo '</ul>';
            echo '</div>';
            
            echo '<div class="method-result">';
            echo '<h4>Hybrid Scores</h4>';
            echo '<table>';
            echo '<tr><th>Jenis</th><th>Skor Hybrid</th></tr>';
            foreach ($result['hybrid_scores'] as $class => $score) {
                echo '<tr><td>' . $class . '</td><td>' . number_format($score * 100, 2) . '%</td></tr>';
            }
            echo '</table>';
            echo '</div>';
            
            echo '</div>';
        }
        
        if (isset($_POST['evaluate'])) {
            $evaluation = $expertSystem->evaluateSystem();
            
            echo '<div class="result accuracy">';
            echo '<h3>Evaluasi Akurasi Sistem</h3>';
            echo '<p><strong>Akurasi:</strong> ' . number_format($evaluation['accuracy'], 2) . '%</p>';
            echo '<p><strong>Prediksi Benar:</strong> ' . $evaluation['correct'] . ' dari ' . $evaluation['total'] . ' data</p>';
            echo '<div class="confidence-bar">';
            echo '<div class="confidence-fill" style="width: ' . $evaluation['accuracy'] . '%; background-color: #28a745;"></div>';
            echo '</div>';
            echo '</div>';
        }
        ?>
        
        <div class="result">
            <h3>Data Training</h3>
            <table>
                <tr>
                    <th>ID</th>
                    <th>Diameter (m)</th>
                    <th>Tinggi (m)</th>
                    <th>Jenis</th>
                </tr>
                <?php
                $query = "SELECT * FROM datatraining ORDER BY IdData";
                $result = mysqli_query($koneksi, $query);
                while ($row = mysqli_fetch_assoc($result)) {
                    echo '<tr>';
                    echo '<td>' . $row['IdData'] . '</td>';
                    echo '<td>' . $row['Diameter'] . '</td>';
                    echo '<td>' . $row['Tinggi'] . '</td>';
                    echo '<td>' . $row['Jenis'] . '</td>';
                    echo '</tr>';
                }
                ?>
            </table>
        </div>
        
        <div class="result">
            <h3>Informasi Sistem</h3>
            <p><strong>Metode yang digunakan:</strong></p>
            <ul>
                <li><strong>K-Nearest Neighbors (KNN):</strong> Algoritma supervised learning yang mengklasifikasikan berdasarkan k tetangga terdekat</li>
                <li><strong>Expert System dengan Certainty Factor:</strong> Sistem pakar yang menggunakan rules dan factor kepastian</li>
                <li><strong>Computer Vision:</strong> Analisis gambar untuk ekstraksi fitur visual dan estimasi dimensi</li>
                <li><strong>Hybrid Approach:</strong> Kombinasi semua metode untuk hasil yang lebih akurat</li>
            </ul>
            <p><strong>Fitur yang tersedia:</strong></p>
            <ul>
                <li>Klasifikasi manual berdasarkan input diameter dan tinggi</li>
                <li>Analisis gambar dengan computer vision (simulasi)</li>
                <li>Deteksi objek pohon dari gambar</li>
                <li>Ekstraksi fitur visual (tekstur kulit, kepadatan daun, pola cabang)</li>
                <li>Estimasi dimensi pohon dari gambar</li>
                <li>Evaluasi akurasi sistem secara otomatis</li>
                <li>Visualisasi tingkat kepercayaan hasil prediksi</li>
                <li>Perbandingan hasil dari berbagai metode</li>
            </ul>
            <p><strong>Catatan Computer Vision:</strong></p>
            <p style="color: #f39c12;">Modul computer vision saat ini menggunakan simulasi untuk demonstrasi. Dalam implementasi nyata, akan menggunakan library seperti OpenCV, TensorFlow, atau PyTorch untuk pemrosesan gambar yang sesungguhnya.</p>
        </div>
    </div>
</body>
</html>