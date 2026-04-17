<?php
// delete_header.php
session_start();
include("conexion.php");

if (empty($_SESSION['editor_ok'])) { header("Location: editor_registros.php"); exit; }
$EDITOR_USER = $_SESSION['editor_user'] ?? '';
$return = isset($_GET['return']) ? $_GET['return'] : 'editor_registros.php';
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

function audit_log($con, $entity, $entity_id, $action, $by, $beforeArr, $afterArr){
  $entity = mysqli_real_escape_string($con, $entity);
  $action = mysqli_real_escape_string($con, $action);
  $by     = mysqli_real_escape_string($con, $by ?: 'SIN_NOMBRE');
  $before = $beforeArr ? json_encode($beforeArr, JSON_UNESCAPED_UNICODE) : null;
  $after  = $afterArr  ? json_encode($afterArr, JSON_UNESCAPED_UNICODE)  : null;
  $b = is_null($before) ? "NULL" : "'".mysqli_real_escape_string($con,$before)."'";
  $a = is_null($after)  ? "NULL" : "'".mysqli_real_escape_string($con,$after)."'";
  mysqli_query($con, "INSERT INTO audit_log(entity, entity_id, action, changed_by, before_state, after_state)
                      VALUES('$entity',$entity_id,'$action','$by',$b,$a)");
}
function fetch_header_bundle($con, $hid){
  $hid = (int)$hid;
  $hQ = mysqli_query($con,"SELECT * FROM op_header WHERE id=$hid");
  $header = $hQ? mysqli_fetch_assoc($hQ):null;
  if(!$header) return null;
  $it = [];
  $iQ = mysqli_query($con,"SELECT * FROM op_item WHERE header_id=$hid");
  if($iQ){ while($r=mysqli_fetch_assoc($iQ)) $it[]=$r; }
  return ['header'=>$header,'items'=>$it];
}

if ($id<=0){ $_SESSION['flash_err']="ID inválido."; header("Location: $return"); exit; }
if ($EDITOR_USER===''){ $_SESSION['flash_err']="No hay responsable (clave)."; header("Location: $return"); exit; }

$bundle = fetch_header_bundle($con, $id);
if (!$bundle){ $_SESSION['flash_err']="Cabecera no encontrada."; header("Location: $return"); exit; }

mysqli_begin_transaction($con);
try {
  // Borrado: gracias al FK ON DELETE CASCADE se van las líneas.
  if (!mysqli_query($con, "DELETE FROM op_header WHERE id=$id"))
    throw new Exception(mysqli_error($con));

  audit_log($con, 'header', $id, 'delete', $EDITOR_USER, $bundle, null);
  mysqli_commit($con);
  $_SESSION['flash_ok'] = "Cabecera #$id eliminada correctamente.";
} catch(Exception $e){
  mysqli_rollback($con);
  $_SESSION['flash_err'] = "Error eliminando: ".$e->getMessage();
}
header("Location: $return");
