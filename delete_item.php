<?php
// delete_item.php
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
function fetch_item($con,$iid){
  $iid=(int)$iid;
  $q=mysqli_query($con,"SELECT * FROM op_item WHERE id=$iid");
  return $q? mysqli_fetch_assoc($q):null;
}

if ($id<=0){ $_SESSION['flash_err']="ID inválido."; header("Location: $return"); exit; }
if ($EDITOR_USER===''){ $_SESSION['flash_err']="No hay responsable (clave)."; header("Location: $return"); exit; }

$item = fetch_item($con,$id);
if(!$item){ $_SESSION['flash_err']="Línea no encontrada."; header("Location: $return"); exit; }

if (mysqli_query($con,"DELETE FROM op_item WHERE id=$id")) {
  audit_log($con,'item',$id,'delete',$EDITOR_USER,$item,null);
  $_SESSION['flash_ok']="Línea #$id eliminada correctamente.";
} else {
  $_SESSION['flash_err']="Error eliminando: ".mysqli_error($con);
}
header("Location: $return");
