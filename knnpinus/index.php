<!DOCTYPE html>
<html lang="en">
<head>
  <title>Iris</title>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</head>
<body>

<nav class="navbar navbar-expand-sm navbar-dark bg-dark">
  <div class="container-fluid">
    <a class="navbar-brand" href="./">KNN PINUS</a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mynavbar">
      <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse" id="mynavbar">
      <ul class="navbar-nav me-auto">
        <li class="nav-item">
          <a class="nav-link" href="inputdatatraining.php" target="mainframe">Input Data Training</a>
        </li>
        <li class="nav-item">
          <a class="nav-link" href="inputdatauji.php" target="mainframe">Input Data Uji</a>
        </li>
        <li class="nav-item">
          <a class="nav-link" href="daftarhasiluji.php"  target="mainframe">Hasil Prediksi </a>
        </li>
      </ul>
    </div>
  </div>
</nav>

<div class="container-fluid mt-3">
  <!--h3>Navbar Forms</h3>
  <p>You can also include forms inside the navigation bar.</p-->
  <iframe name="mainframe" width="100%" height="400px"></iframe>
</div>

</body>
</html>