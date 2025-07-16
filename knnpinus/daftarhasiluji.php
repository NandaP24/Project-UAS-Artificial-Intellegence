<?php
// Aktifkan error reporting untuk debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

include 'koneksi.db.php';
$error = '';
$k = null;
$knn_results = [];
$training_count = 0;

function euclideanDistance(array $a, array $b): float {
    $sum = 0;
    $features = ['Diameter', 'Tinggi'];
    foreach ($features as $feature) {
        if (isset($a[$feature]) && isset($b[$feature])) {
            $sum += pow(floatval($a[$feature]) - floatval($b[$feature]), 2);
        }
    }
    return sqrt($sum);
}

function calculateK(int $training_count): int {
    if ($training_count <= 0) {
        return 1;
    }
    
    $k = intval($training_count / 2);
    
    // Pastikan K adalah bilangan ganjil dan minimal 1
    if ($k % 2 == 0) {
        $k += 1;
    }
    
    // Pastikan K tidak lebih dari jumlah data training
    if ($k > $training_count) {
        $k = $training_count;
    }
    
    return max(1, $k);
}

function predictClass(array $neighbors): array {
    if (empty($neighbors)) {
        return [
            'predicted_class' => 'Unknown',
            'confidence' => 0,
            'class_votes' => []
        ];
    }
    
    $class_votes = [];
    foreach ($neighbors as $neighbor_info) {
        if (isset($neighbor_info['train_point']['Jenis'])) {
            $class = $neighbor_info['train_point']['Jenis'];
            if (isset($class_votes[$class])) {
                $class_votes[$class]++;
            } else {
                $class_votes[$class] = 1;
            }
        }
    }
    
    if (empty($class_votes)) {
        return [
            'predicted_class' => 'Unknown',
            'confidence' => 0,
            'class_votes' => []
        ];
    }
    
    // Cari kelas dengan vote terbanyak
    $max_votes = max($class_votes);
    $predicted_class = array_search($max_votes, $class_votes);
    $confidence = $max_votes / count($neighbors);
    
    return [
        'predicted_class' => $predicted_class,
        'confidence' => $confidence,
        'class_votes' => $class_votes
    ];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Cek koneksi database
        if (!$koneksi) {
            throw new Exception("Koneksi database gagal");
        }
        
        // data training
        $sql_training = "SELECT * FROM datatraining";
        $training_result = mysqli_query($koneksi, $sql_training);
        
        if (!$training_result) {
            throw new Exception("Error query data training: " . mysqli_error($koneksi));
        }
        
        // data uji
        $sql_test = "SELECT * FROM datauji";
        $test_result = mysqli_query($koneksi, $sql_test);
        
        if (!$test_result) {
            throw new Exception("Error query data uji: " . mysqli_error($koneksi));
        }
        
        $training_dataset = [];
        while ($row = mysqli_fetch_assoc($training_result)) {
            $training_dataset[] = $row;
        }
        
        $test_dataset = [];
        while ($row = mysqli_fetch_assoc($test_result)) {
            $test_dataset[] = $row;
        }
        
        // Cek apakah ada data
        if (empty($training_dataset)) {
            throw new Exception("Tidak ada data training");
        }
        
        if (empty($test_dataset)) {
            throw new Exception("Tidak ada data uji");
        }
        
        // Hitung jumlah data training
        $training_count = count($training_dataset);
        
        // Hitung K secara otomatis
        $k = calculateK($training_count);
        
        foreach ($test_dataset as $test_point) {
            $distances = [];
            foreach ($training_dataset as $train_point) {
                $dist = euclideanDistance($test_point, $train_point);
                $distances[] = [
                    'train_point' => $train_point,
                    'distance' => $dist
                ];
            }
           
            usort($distances, function($a, $b) { 
                return $a['distance'] <=> $b['distance']; 
            });
            
            $nearest = array_slice($distances, 0, $k);
            
            // Prediksi kelas berdasarkan voting
            $prediction = predictClass($nearest);
            
            $test_id = isset($test_point['IdData']) ? $test_point['IdData'] : 'IdData';
            
            $knn_results[$test_id] = [
                'test_point' => $test_point,
                'neighbors' => $nearest,
                'prediction' => $prediction
            ];
        }
        
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <title>KNN dengan Data Training dan Data Uji</title>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" />
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</head>
<body>

<div class="container mt-3">
  <h2>KNN dengan Perhitungan K Otomatis</h2>
  
  <?php if ($training_count > 0): ?>
    <div class="alert alert-info">
      <strong>Informasi:</strong> 
      Jumlah data training: <?php echo $training_count; ?> | 
      Nilai K yang digunakan: <?php echo $k; ?> 
    </div>
  <?php endif; ?>
  
  <form method="post">
    <div class="form-group row">
      <div class="col-12">
        <button type="submit" class="btn btn-primary">Jalankan KNN</button>
      </div>
    </div>
  </form>

  <?php if ($error): ?>
    <div class="alert alert-danger mt-3">
      <strong>Error:</strong> <?php echo htmlspecialchars($error); ?>
    </div>
  <?php endif; ?>

  <?php if ($k && !empty($knn_results)): ?>
    <h2 class="mt-5">K-Nearest Neighbors Results</h2>
    <div class="alert alert-success">
      <strong>Hasil KNN:</strong> 
      K = <?php echo htmlspecialchars($k); ?> | 
      Jumlah data training: <?php echo $training_count; ?> | 
      Jumlah data uji: <?php echo count($knn_results); ?>
    </div>
    
    <!-- Tabel Ringkasan Prediksi -->
    <h4 class="mt-4">Ringkasan Prediksi</h4>
    <table class="table table-bordered">
      <thead class="table-dark">
        <tr>
          <th>Test Data ID</th>
          <th>Diameter</th>
          <th>Tinggi</th>
          <th>Prediksi Jenis</th>
          <th>Confidence</th>
          <th>Detail Voting</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($knn_results as $test_id => $data): 
            $test_point = $data['test_point'];
            $prediction = $data['prediction'];
        ?>
        <tr>
          <td><?php echo htmlspecialchars($test_id); ?></td>
          <td><?php echo htmlspecialchars($test_point['Diameter'] ?? 'N/A'); ?></td>
          <td><?php echo htmlspecialchars($test_point['Tinggi'] ?? 'N/A'); ?></td>
          <td>
            <span class="badge bg-primary"><?php echo htmlspecialchars($prediction['predicted_class']); ?></span>
          </td>
          <td><?php echo number_format($prediction['confidence'] * 100, 2); ?>%</td>
          <td>
            <?php if (!empty($prediction['class_votes'])): ?>
              <?php foreach ($prediction['class_votes'] as $class => $votes): ?>
                <span class="badge bg-secondary me-1"><?php echo htmlspecialchars($class); ?>: <?php echo $votes; ?></span>
              <?php endforeach; ?>
            <?php endif; ?>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>

    <!-- Detail Tetangga Terdekat -->
    <?php foreach ($knn_results as $test_id => $data): 
        $test_point = $data['test_point'];
        $neighbors = $data['neighbors'];
        $prediction = $data['prediction'];
    ?>
      <h5 class="mt-4">
        Test Data ID: <?php echo htmlspecialchars($test_id); ?> 
        (Diameter: <?php echo htmlspecialchars($test_point['Diameter']); ?>, 
         Tinggi: <?php echo htmlspecialchars($test_point['Tinggi']); ?>) 
        - Prediksi: <span class="badge bg-success"><?php echo htmlspecialchars($prediction['predicted_class']); ?></span>
      </h5>
      <table class="table table-bordered table-sm">
        <thead class="table-light">
          <tr>
            <th>Neighbor ID</th>
            <th>Diameter</th>
            <th>Tinggi</th>
            <th>Jenis</th>
            <th>Distance</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($neighbors as $neighbor_info): 
            $n = $neighbor_info['train_point'];
            $d = $neighbor_info['distance'];
          ?>
          <tr>
            <td><?php echo htmlspecialchars($n['IdData']); ?></td>
            <td><?php echo htmlspecialchars($n['Diameter']); ?></td>
            <td><?php echo htmlspecialchars($n['Tinggi']); ?></td>
            <td>
              <span class="badge bg-info"><?php echo htmlspecialchars($n['Jenis']); ?></span>
            </td>
            <td><?php echo number_format($d, 4); ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php endforeach; ?>

    <!-- Statistik Prediksi -->
    <h4 class="mt-4">Statistik Prediksi</h4>
    <div class="row">
      <div class="col-md-6">
        <div class="card">
          <div class="card-body">
            <h5 class="card-title">Distribusi Prediksi</h5>
            <?php 
            $prediction_stats = [];
            foreach ($knn_results as $data) {
                $predicted_class = $data['prediction']['predicted_class'];
                if (isset($prediction_stats[$predicted_class])) {
                    $prediction_stats[$predicted_class]++;
                } else {
                    $prediction_stats[$predicted_class] = 1;
                }
            }
            
            foreach ($prediction_stats as $class => $count): ?>
              <div class="mb-2">
                <span class="badge bg-primary"><?php echo htmlspecialchars($class); ?></span>: 
                <?php echo $count; ?> data (<?php echo number_format($count / count($knn_results) * 100, 2); ?>%)
              </div>
            <?php endforeach; ?>
          </div>
        </div>
      </div>
      <div class="col-md-6">
        <div class="card">
          <div class="card-body">
            <h5 class="card-title">Confidence Rata-rata</h5>
            <?php 
            $total_confidence = 0;
            $count_results = count($knn_results);
            if ($count_results > 0) {
                foreach ($knn_results as $data) {
                    $total_confidence += $data['prediction']['confidence'];
                }
                $avg_confidence = $total_confidence / $count_results;
            } else {
                $avg_confidence = 0;
            }
            ?>
            <h3><?php echo number_format($avg_confidence * 100, 2); ?>%</h3>
            <p class="card-text">Tingkat kepercayaan rata-rata prediksi</p>
          </div>
        </div>
      </div>
    </div>
  <?php endif; ?>

</div>

</body>
</html>