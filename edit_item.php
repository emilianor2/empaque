<?php
// edit_item.php
session_start();
include("conexion.php");

/* Seguridad */
if (empty($_SESSION['editor_ok'])) {
  header("Location: editor_registros.php");
  exit;
}
$EDITOR_USER = $_SESSION['editor_user'] ?? '';

function h($s){ return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }
function fetch_item_row($con, $iid){
  $iid = (int)$iid;
  $q = mysqli_query($con, "SELECT id, header_id, tipo_codigo, codigo, maquina, cantidad_total, buenas, malas, excedente, desc_trabajo, obs, caja_excedente, created_at FROM op_item WHERE id=$iid");
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
  $_SESSION['flash_err'] = "ID de línea inválido.";
  header("Location: $return");
  exit;
}

$item = fetch_item_row($con, $id);
if (!$item) {
  $_SESSION['flash_err'] = "Línea no encontrada.";
  header("Location: $return");
  exit;
}

$err = $ok = '';
if ($_SERVER['REQUEST_METHOD']==='POST') {
  if ($EDITOR_USER==='') {
    $err = "Guardá primero el Responsable en el listado.";
  } else {
    $tipo        = ($_POST['tipo'] ?? 'LS')==='F' ? 'F':'LS';
    $codigo      = mysqli_real_escape_string($con, trim($_POST['codigo'] ?? ''));
    $maquina     = mysqli_real_escape_string($con, trim($_POST['maquina'] ?? ''));
    $cant_total  = (int)($_POST['cantidad_total'] ?? 0);
    $buenas      = (int)($_POST['buenas'] ?? 0);
    $malas       = (int)($_POST['malas'] ?? 0);
    $excedente   = (int)($_POST['excedente'] ?? 0);
    $desc        = mysqli_real_escape_string($con, trim($_POST['desc_trabajo'] ?? ''));
    $obs_raw     = trim($_POST['obs'] ?? '');
    $obs_sql     = $obs_raw!=='' ? "'".mysqli_real_escape_string($con, $obs_raw)."'" : "NULL";
    $caja_raw    = trim($_POST['caja_excedente'] ?? '');
    $caja_sql    = $excedente>0 ? "'".mysqli_real_escape_string($con, $caja_raw)."'" : "NULL";

    if ($codigo==='' || $maquina==='') {
      $err = "Completá Código y Máquina.";
    } elseif ($excedente>0 && $caja_raw==='') {
      $err = "Falta Caja excedente (excedente > 0).";
    } else {
      $before = $item;
      $sql = "UPDATE op_item
              SET tipo_codigo='$tipo',
                  codigo='$codigo',
                  maquina='$maquina',
                  cantidad_total=$cant_total,
                  buenas=$buenas,
                  malas=$malas,
                  excedente=$excedente,
                  desc_trabajo='$desc',
                  obs=$obs_sql,
                  caja_excedente=$caja_sql
              WHERE id=$id";
      if (mysqli_query($con, $sql)) {
        $after = [
          'id'=>$id,'tipo_codigo'=>$tipo,'codigo'=>$codigo,'maquina'=>$maquina,
          'cantidad_total'=>$cant_total,'buenas'=>$buenas,'malas'=>$malas,'excedente'=>$excedente,
          'desc_trabajo'=>$desc,'obs'=>$obs_raw!==''?$obs_raw:null,'caja_excedente'=>$excedente>0?$caja_raw:null
        ];
        audit_log($con, 'item', $id, 'update', $EDITOR_USER, $before, $after);
        $_SESSION['flash_ok'] = "Línea #$id actualizada correctamente.";
        header("Location: $return");
        exit;
      } else {
        $err = "Error: ".mysqli_error($con);
      }
    }
  }
}

// recargar por si hubo cambios parciales
$item = fetch_item_row($con, $id);
$mqs = ['SRI-1','SRI-2','SRI-3','SRI-4'];
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title>Editar línea #<?=$id?></title>
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container py-4">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h1 class="h5 m-0">Editar línea #<?=$id?></h1>
    <span class="small text-muted">Responsable: <b><?=h($_SESSION['editor_user'] ?? '')?></b></span>
    <div class="d-flex gap-2">
      <a href="<?=h($return)?>" class="btn btn-outline-secondary">← Volver</a>
    </div>
  </div>

  <?php if($err): ?><div class="alert alert-danger"><?=$err?></div><?php endif; ?>
  <?php if($ok): ?><div class="alert alert-success"><?=$ok?></div><?php endif; ?>

  <form method="post" class="card p-3">
    <div class="row g-2">
      <div class="col-12 col-md-2">
        <label class="form-label">Tipo</label>
        <select name="tipo" class="form-select" required>
          <option value="LS" <?=$item['tipo_codigo']==='LS'?'selected':''?>>LS</option>
          <option value="F"  <?=$item['tipo_codigo']==='F'?'selected':''?>>F</option>
        </select>
      </div>
      <div class="col-12 col-md-3">
        <label class="form-label">Código</label>
        <input name="codigo" class="form-control" value="<?=h($item['codigo'])?>" required>
      </div>
      <div class="col-12 col-md-2">
        <label class="form-label">Máquina</label>
        <select name="maquina" class="form-select" required>
          <?php foreach ($mqs as $mq): ?>
            <option value="<?=$mq?>" <?=$mq===$item['maquina']?'selected':''?>><?=$mq?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-6 col-md-2">
        <label class="form-label">Total</label>
        <input type="number" min="0" name="cantidad_total" class="form-control" value="<?=h($item['cantidad_total'])?>">
      </div>
      <div class="col-6 col-md-1">
        <label class="form-label">Buenas</label>
        <input type="number" min="0" name="buenas" class="form-control" value="<?=h($item['buenas'])?>">
      </div>
      <div class="col-6 col-md-1">
        <label class="form-label">Malas</label>
        <input type="number" min="0" name="malas" class="form-control" value="<?=h($item['malas'])?>">
      </div>
      <div class="col-6 col-md-1">
        <label class="form-label">Exced.</label>
        <input type="number" min="0" name="excedente" class="form-control" value="<?=h($item['excedente'])?>">
      </div>
      <div class="col-12 col-md-3">
        <label class="form-label">Caja excedente</label>
        <input name="caja_excedente" class="form-control" value="<?=h($item['caja_excedente'])?>" placeholder="Caja N°">
      </div>
      <div class="col-12">
        <label class="form-label">Descripción</label>
        <input name="desc_trabajo" class="form-control" value="<?=h($item['desc_trabajo'])?>">
      </div>
      <div class="col-12">
        <label class="form-label">Observaciones</label>
        <input name="obs" class="form-control" value="<?=h($item['obs'])?>" placeholder="Notas">
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
