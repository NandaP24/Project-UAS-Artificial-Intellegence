<?php
include_once('koneksi.db.php');

class PineExpertSystem {
    private $koneksi;
    private $trainingData = [];
    
    public function __construct($koneksi) {
        $this->koneksi = $koneksi;
        $this->loadTrainingData();
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
    
    // Kombinasi KNN dan Expert System
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
        p {
            margin-bottom: 12px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Expert System - Klasifikasi Pohon Pinus</h1>
        
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
        
        <?php
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
                <li><strong>Hybrid Approach:</strong> Kombinasi KNN (60%) dan Expert System (40%) untuk hasil yang lebih akurat</li>
            </ul>
            <p><strong>Fitur yang tersedia:</strong></p>
            <ul>
                <li>Klasifikasi jenis pohon pinus berdasarkan diameter dan tinggi</li>
                <li>Evaluasi akurasi sistem secara otomatis</li>
                <li>Visualisasi tingkat kepercayaan hasil prediksi</li>
                <li>Perbandingan hasil dari berbagai metode</li>
            </ul>
        </div>
    </div>
</body>
</html>