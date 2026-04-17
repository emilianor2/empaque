<?php
// edit_header.php
session_start();
include("conexion.php");

/* Seguridad */
if (empty($_SESSION['editor_ok'])) {
  header("Location: editor_registros.php");
  exit;
}
$EDITOR_USER = $_SESSION['editor_user'] ?? '';

function h($s){ return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }
function fetch_header_row($con, $hid){
  $hid = (int)$hid;
  $q = mysqli_query($con, "SELECT id, fecha, hora, operario, turno, op, created_at FROM op_header WHERE id=$hid");
  return $q ? mysqli_fetch_assoc($q) : null;
}
function audit_log($con, $entity, $entity_id, $action, $by, $beforeArr, $afterArr){
  $entity    = mysqli_real_escape_string($con, $entity);
  $entity_id = (int)$entity_id;
  $action    = mysqli_real_escape_string($con, $action);
  $by        = mysqli_real_escape_string($con, $by ?: 'SIN_NOMBRE');
  $before = $beforeArr ? json_encode($beforeArr, JSON_UNESCAPED_UNICODE) : null;
  $after  = $afterArr  ? json_encode($afterArr,  JSON_UNESCAPED_UNICODE) : null;
  $before_sql = is_null($before) ? "NULL" : "'".mysqli_real_escape_string($con, $before)."'";
  $after_sql  = is_null($after)  ? "NULL" : "'".mysqli_real_escape_string($con, $after)."'";
  $sql = "INSERT INTO audit_log(entity, entity_id, action, changed_by, before_state, after_state)
          VALUES('$entity', $entity_id, '$action', '$by', $before_sql, $after_sql)";
  mysqli_query($con, $sql);
}

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$return = isset($_GET['return']) ? $_GET['return'] : 'editor_registros.php';

if ($id<=0) {
  $_SESSION['flash_err'] = "ID de cabecera inválido.";
  header("Location: $return");
  exit;
}

$header = fetch_header_row($con, $id);
if (!$header) {
  $_SESSION['flash_err'] = "Cabecera no encontrada.";
  header("Location: $return");
  exit;
}

$err = $ok = '';
if ($_SERVER['REQUEST_METHOD']==='POST') {
  if ($EDITOR_USER==='') {
    $err = "Guardá primero el Responsable en el listado.";
  } else {
    $fecha    = mysqli_real_escape_string($con, trim($_POST['fecha'] ?? ''));
    $turno    = (int)($_POST['turno'] ?? 0);
    $operario = (int)($_POST['operario'] ?? 0);
    $op       = mysqli_real_escape_string($con, trim($_POST['op'] ?? ''));

    if ($fecha==='' || !in_array($turno,[1,2,3]) || $operario<=0 || $op==='') {
      $err = "Completá Fecha, Turno, Operario y OP.";
    } else {
      $before = $header;
      $sql = "UPDATE op_header
              SET fecha=STR_TO_DATE('$fecha','%Y-%m-%d'),
                  turno=$turno,
                  operario=$operario,
                  op='$op'
              WHERE id=$id";
      if (mysqli_query($con, $sql)) {
        $after = ['id'=>$id,'fecha'=>$fecha,'turno'=>$turno,'operario'=>$operario,'op'=>$op];
        audit_log($con, 'header', $id, 'update', $EDITOR_USER, $before, $after);
        $_SESSION['flash_ok'] = "Cabecera #$id actualizada correctamente.";
        header("Location: $return");
        exit;
      } else {
        $err = "Error: ".mysqli_error($con);
      }
    }
  }
}

// recargar por si hubo cambios parciales
$header = fetch_header_row($con, $id);
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title>Editar cabecera #<?=$id?></title>
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container py-4">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h5 m-0">Editar cabecera #<?=$id?></h1>
    <span class="small text-muted">Responsable: <b><?=h($_SESSION['editor_user'] ?? '')?></b></span>
    <div class="d-flex gap-2">
      <a href="<?=h($return)?>" class="btn btn-outline-secondary">← Volver</a>
    </div>
  </div>

  <?php if($err): ?><div class="alert alert-danger"><?=$err?></div><?php endif; ?>
  <?php if($ok): ?><div class="alert alert-success"><?=$ok?></div><?php endif; ?>

  <form method="post" class="card p-3">
    <div class="row g-2">
      <div class="col-12 col-md-3">
        <label class="form-label">Fecha</label>
        <input type="date" name="fecha" class="form-control" value="<?=h($header['fecha'])?>" required>
      </div>
      <div class="col-12 col-md-3">
        <label class="form-label">Turno</label>
        <select name="turno" class="form-select" required>
          <option value="1" <?=$header['turno']==1?'selected':''?>>Mañana</option>
          <option value="2" <?=$header['turno']==2?'selected':''?>>Tarde</option>
          <option value="3" <?=$header['turno']==3?'selected':''?>>Noche</option>
        </select>
      </div>
      <div class="col-12 col-md-3">
        <label class="form-label">Operario</label>
        <input type="number" min="0" name="operario" class="form-control" value="<?=h($header['operario'])?>" required>
      </div>
      <div class="col-12 col-md-3">
        <label class="form-label">OP</label>
        <input type="text" name="op" class="form-control" value="<?=h($header['op'])?>" required>
      </div>
    </div>

    <div class="mt-3 d-flex gap-2">
      <button class="btn btn-primary">💾 Guardar cambios</button>
      <a href="<?=h($return)?>" class="btn btn-outline-secondary">Cancelar</a>
    </div>
  </form>
</div>
</body>
</html>
