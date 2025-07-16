
<?php
if (isset($_POST['hsubmit'])) {
  include_once('koneksi.db.php');
  $IdData=mysqli_real_escape_string($koneksi,$_POST['IdData']);
  $sql="DELETE FROM `datauji` WHERE `IdData`='".$IdData."'";
  $q=mysqli_query($koneksi,$sql);
  if ($q) {
    echo '<div class="alert alert-success alert-dismissible">
  <strong>Success!</strong> Rekord berhasil dihapus !
</div>';
  } else {
    echo '<div class="alert alert-danger alert-dismissible">
  <strong>Failed!</strong> Rekord gagal dihapus !
</div>';
  }
}
?>
