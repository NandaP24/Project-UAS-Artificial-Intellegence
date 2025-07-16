<?php
session_start();
$data = $_SESSION['hasil'] ?? [];

function hitungAkurasi($data) {
    $benar = 0;
    foreach ($data as $d) {
        if ($d['Correct'] === '✅') {
            $benar++;
        }
    }
    $total = count($data);
    return $total > 0 ? round(($benar / $total) * 100, 2) : 0;
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Hasil Prediksi KNN + Certainty Factor</title>
    <style>
        body { font-family: Arial, sans-serif; padding: 20px; }
        table { border-collapse: collapse; width: 100%; margin-top: 20px; }
        th, td { border: 1px solid #999; padding: 8px; text-align: center; }
        th { background-color: #f2f2f2; }
        h2 { margin-bottom: 10px; }
        .summary { margin-top: 20px; font-weight: bold; }
    </style>
</head>
<body>

<h2>Hasil Prediksi KNN + Certainty Factor</h2>

<table>
    <tr>
        <th>ID</th>
        <th>Prediksi KNN</th>
        <th>Prediksi CF</th>
        <th>Nilai CF</th>
        <th>Final Confidence</th>
        <th>Prediksi Akhir</th>
        <th>Label Asli</th>
        <th>Benar?</th>
    </tr>

    <?php foreach ($data as $row): ?>
    <tr>
        <td><?= $row['IdData'] ?></td>
        <td><?= $row['KNN'] ?></td>
        <td><?= $row['CF'] ?></td>
        <td><?= $row['CF_Value'] ?></td>
        <td><?= $row['Final_Confidence'] ?></td>
        <td><?= $row['Final_Pred'] ?></td>
        <td><?= $row['True_Label'] ?></td>
        <td><?= $row['Correct'] ?></td>
    </tr>
    <?php endforeach; ?>
</table>

<div class="summary">
    Akurasi: <?= hitungAkurasi($data) ?>%
    (<?= count(array_filter($data, fn($d) => $d['Correct'] === '✅')) ?> benar dari <?= count($data) ?> data)
</div>

</body>
</html>
