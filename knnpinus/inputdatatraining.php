<!DOCTYPE html>
<html lang="en">
<head>
  <title>Input Data Training Pinus - KNN PINUS </title>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</head>
<body>

<div class="container mt-3">
  <h2>Input Data Training Pinus</h2>
  <form method="post">
  <div class="form-group row">
    <label for="Diameter" class="col-4 col-form-label">Diameter</label> 
    <div class="col-8">
      <input id="Diameter" name="Diameter" type="text" class="form-control" required="required">
    </div>
  </div>
    <div class="form-group row">
    <label for="Tinggi" class="col-4 col-form-label">Tinggi</label> 
    <div class="col-8">
      <input id="Tinggi" name="Tinggi" type="text" class="form-control" required="required">
   </div>
  </div>
    <div class="form-group row">
    <label for="Jenis" class="col-4 col-form-label">Jenis</label> 
    <div class="col-8">
      <input id="Jenis" name="Jenis" type="text" class="form-control" required="required">
    </div>
    </div> 
  <div class="form-group row">
    <div class="offset-4 col-8">
      <button name="submit" type="submit" class="btn btn-primary"> Simpan Data Baru</button>
      <button type="button" class="btn btn-info" data-bs-toggle="modal" data-bs-target="#myModal">  Cari Data Training </button>
    </div>
  </div>
  </div>

</form>


<!-- The Modal -->
<div class="modal" id="myModal">
  <div class="modal-dialog">
    <div class="modal-content">

      <!-- Modal Header -->
      <div class="modal-header">
        <h4 class="modal-title">Cari Data Training</h4>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>

      <!-- Modal body -->
      <div class="modal-body">
        <form method="post">
  <div class="form-group row">
    <label for="Diameter" class="col-4 col-form-label">Id Data</label> 
    <div class="col-8">
      <input id="IdData" name="IdData" type="text" class="form-control" required="required">
    </div>
  </div>
  <div class="form-group row">
    <div class="offset-4 col-8">
      <button name="ksubmit" type="submit" class="btn btn-primary" formaction="koreksirekordtraining.php">Koreksi</button>
      <button name="hsubmit" type="submit" class="btn btn-danger" formaction="hapusrekordtraining.php" onclick="return confirm('Apakah yakin akan menghapusnya ?')">Hapus</button>
      </div>
    </div>
   </form>
   </div>
      <!-- Modal footer -->
      <div class="modal-footer">
        <button type="button" class="btn btn-danger" data-bs-dismiss="modal">Close</button>
      </div>

    </div>
  </div>
</div>
<?php
include_once('koneksi.db.php');
if (isset($_POST['submit'])) {
  $Diameter=mysqli_real_escape_string($koneksi,$_POST['Diameter']);
  $Tinggi=mysqli_real_escape_string($koneksi,$_POST['Tinggi']);
  $Jenis=mysqli_real_escape_string($koneksi,$_POST['Jenis']);
  $sql="INSERT INTO `datatraining`(`Diameter`, `Tinggi`,  `Jenis`) VALUES ('".$Diameter."','".$Tinggi."','".$Jenis."')";
  $q=mysqli_query($koneksi,$sql);
  if ($q) {
    echo '<div class="alert alert-success alert-dismissible">
  <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
  <strong>Success!</strong> Data berhasil disimpan !
</div>';
  } else {
    echo '<div class="alert alert-danger alert-dismissible">
  <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
  <strong>Failed!</strong> Data gagal disimpan !
</div>';
  }
}
include('tabeldatatraining.php');
?>
</div>
</body>
</html>