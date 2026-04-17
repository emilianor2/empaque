<?php
session_start();
include("conexion.php"); // define $con (mysqli)

function g($k, $d = '') { return isset($_GET[$k]) ? trim($_GET[$k]) : $d; }
function p($k, $d = '') { return isset($_POST[$k]) ? trim($_POST[$k]) : $d; }
function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function upper_txt($s) {
  $s = trim((string)$s);
  if (function_exists('mb_strtoupper')) return mb_strtoupper($s, 'UTF-8');
  return strtoupper($s);
}

/* ========= Seguridad por clave ========= */
$config = require __DIR__ . '/config.local.php';
$ACCESS_KEYS = $config['access_keys'] ?? [];

if (isset($_GET['logout'])) {
  unset($_SESSION['emp_ok'], $_SESSION['emp_user']);
  header("Location: empleados.php");
  exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['clave'])) {
  $clave = trim($_POST['clave'] ?? '');
  if (isset($ACCESS_KEYS[$clave])) {
    $_SESSION['emp_ok'] = true;
    $_SESSION['emp_user'] = $ACCESS_KEYS[$clave];
    header("Location: empleados.php");
    exit;
  } else {
    $login_error = "Clave incorrecta.";
  }
}

if (empty($_SESSION['emp_ok'])) {
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title>Acceso - Empleados</title>
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light d-flex align-items-center" style="min-height:100vh;">
  <div class="container">
    <div class="row justify-content-center">
      <div class="col-12 col-md-4">
        <div class="card shadow-sm">
          <div class="card-body">
            <h1 class="h5 text-center mb-3">Acceso a Empleados</h1>
            <?php if (!empty($login_error)): ?>
              <div class="alert alert-danger"><?=$login_error?></div>
            <?php endif; ?>
            <form method="post">
              <div class="mb-3">
                <label class="form-label">Clave de acceso</label>
                <input type="password" name="clave" class="form-control" autofocus required>
              </div>
              <button class="btn btn-primary w-100" type="submit">Ingresar</button>
            </form>
            <div class="mt-3 text-center">
              <a href="reportes.php" class="btn btn-sm btn-outline-secondary">&larr; Volver</a>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</body>
</html>
<?php
  exit;
}

$ok = '';
$err = '';
$closeEdit = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
  $action = p('action');

  if ($action === 'add') {
    $cuil = p('cuil');
    $legajo = p('legajo');
    $apellido = upper_txt(p('apellido'));
    $nombre = upper_txt(p('nombre'));

    if ($cuil === '' || $legajo === '' || $apellido === '' || $nombre === '') {
      $err = "Completa CUIL, Legajo, Apellido y Nombre.";
    } elseif (!ctype_digit($legajo)) {
      $err = "El legajo debe ser numerico.";
    } else {
      $cuilEsc = mysqli_real_escape_string($con, $cuil);
      $legajoNum = (int)$legajo;
      $apellidoEsc = mysqli_real_escape_string($con, $apellido);
      $nombreEsc = mysqli_real_escape_string($con, $nombre);

      $sqlIns = "
        INSERT INTO empleado (cuil, legajo, apellido, nombre)
        VALUES ('$cuilEsc', $legajoNum, '$apellidoEsc', '$nombreEsc')
      ";
      if (mysqli_query($con, $sqlIns)) {
        $ok = "Empleado agregado correctamente.";
      } else {
        if ((int)mysqli_errno($con) === 1062) {
          $err = "CUIL o Legajo ya existe.";
        } else {
          $err = "Error al agregar empleado: ".mysqli_error($con);
        }
      }
    }
  }

  if ($action === 'update') {
    $id = p('id');
    $cuil = p('cuil');
    $legajo = p('legajo');
    $apellido = upper_txt(p('apellido'));
    $nombre = upper_txt(p('nombre'));

    if ($id === '' || !ctype_digit($id)) {
      $err = "ID de empleado invalido.";
    } elseif ($cuil === '' || $legajo === '' || $apellido === '' || $nombre === '') {
      $err = "Completa CUIL, Legajo, Apellido y Nombre.";
    } elseif (!ctype_digit($legajo)) {
      $err = "El legajo debe ser numerico.";
    } else {
      $idNum = (int)$id;
      $cuilEsc = mysqli_real_escape_string($con, $cuil);
      $legajoNum = (int)$legajo;
      $apellidoEsc = mysqli_real_escape_string($con, $apellido);
      $nombreEsc = mysqli_real_escape_string($con, $nombre);

      $sqlUpd = "
        UPDATE empleado
        SET cuil = '$cuilEsc',
            legajo = $legajoNum,
            apellido = '$apellidoEsc',
            nombre = '$nombreEsc'
        WHERE id = $idNum
        LIMIT 1
      ";
      if (mysqli_query($con, $sqlUpd)) {
        if (mysqli_affected_rows($con) >= 0) {
          $ok = "Empleado actualizado correctamente.";
          $closeEdit = true;
        } else {
          $err = "No se pudo actualizar el empleado.";
        }
      } else {
        if ((int)mysqli_errno($con) === 1062) {
          $err = "CUIL o Legajo ya existe.";
        } else {
          $err = "Error al actualizar empleado: ".mysqli_error($con);
        }
      }
    }
  }

  if ($action === 'delete') {
    $id = p('id');
    if ($id === '' || !ctype_digit($id)) {
      $err = "ID de empleado invalido.";
    } else {
      $idNum = (int)$id;
      $sqlDel = "DELETE FROM empleado WHERE id = $idNum LIMIT 1";
      if (mysqli_query($con, $sqlDel)) {
        if (mysqli_affected_rows($con) > 0) {
          $ok = "Empleado eliminado correctamente.";
        } else {
          $err = "No se encontro el empleado indicado.";
        }
      } else {
        $err = "Error al eliminar empleado: ".mysqli_error($con);
      }
    }
  }
}

$q = g('q', '');
$editId = g('edit', '');
if ($closeEdit) $editId = '';

$editEmp = null;
if ($editId !== '' && ctype_digit($editId)) {
  $idNum = (int)$editId;
  $sqlEdit = "SELECT id, cuil, legajo, apellido, nombre FROM empleado WHERE id = $idNum LIMIT 1";
  $resEdit = mysqli_query($con, $sqlEdit);
  if ($resEdit && mysqli_num_rows($resEdit) > 0) {
    $editEmp = mysqli_fetch_assoc($resEdit);
  } else {
    $err = $err !== '' ? $err : "No se encontro el empleado para editar.";
    $editId = '';
  }
}

$where = "1=1";
if ($q !== '') {
  $qEsc = mysqli_real_escape_string($con, $q);
  $where .= " AND (
    CAST(legajo AS CHAR) LIKE '%$qEsc%'
    OR cuil LIKE '%$qEsc%'
    OR apellido LIKE '%$qEsc%'
    OR nombre LIKE '%$qEsc%'
    OR CONCAT(apellido, ' ', nombre) LIKE '%$qEsc%'
  )";
}

$sql = "
  SELECT id, cuil, legajo, apellido, nombre
  FROM empleado
  WHERE $where
  ORDER BY legajo ASC
  LIMIT 5000
";
$res = mysqli_query($con, $sql);
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title>Gestion de Empleados - Empaque</title>
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    body { background:#f8fafc; }
    .section-card { border:1px solid #e5e7eb; border-radius:12px; background:#fff; padding:16px; }
  </style>
</head>
<body>
<div class="container py-4">

  <div class="d-flex justify-content-between align-items-center mb-3">
    <div>
      <h1 class="h4 m-0">Gestion de Empleados</h1>
      <div class="text-muted small">Responsable: <b><?=h($_SESSION['emp_user'] ?? '-')?></b></div>
    </div>
    <div class="d-flex gap-2">
      <a href="reportes.php" class="btn btn-outline-secondary">&larr; Volver</a>
      <a href="empleados.php?logout=1" class="btn btn-outline-danger">Cerrar acceso</a>
    </div>
  </div>

  <?php if ($ok !== ''): ?>
    <div class="alert alert-success"><?=$ok?></div>
  <?php endif; ?>
  <?php if ($err !== ''): ?>
    <div class="alert alert-danger"><?=$err?></div>
  <?php endif; ?>

  <div class="section-card mb-3">
    <div class="fw-bold mb-2">Agregar empleado</div>
    <form method="post" class="row g-2 align-items-end">
      <input type="hidden" name="action" value="add">
      <div class="col-12 col-md-3">
        <label class="form-label">CUIL</label>
        <input type="text" name="cuil" class="form-control" placeholder="Ej: 27-12345678-9" required>
      </div>
      <div class="col-12 col-md-2">
        <label class="form-label">Legajo</label>
        <input type="number" name="legajo" class="form-control" min="1" required>
      </div>
      <div class="col-12 col-md-3">
        <label class="form-label">Apellido</label>
        <input type="text" name="apellido" class="form-control js-upper" style="text-transform: uppercase;" required>
      </div>
      <div class="col-12 col-md-3">
        <label class="form-label">Nombre</label>
        <input type="text" name="nombre" class="form-control js-upper" style="text-transform: uppercase;" required>
      </div>
      <div class="col-12 col-md-1 d-grid">
        <button class="btn btn-primary" type="submit">Agregar</button>
      </div>
    </form>
  </div>

  <?php if ($editEmp): ?>
  <div class="section-card mb-3 border-primary">
    <div class="d-flex justify-content-between align-items-center mb-2">
      <div class="fw-bold text-primary">Editar empleado ID <?= (int)$editEmp['id'] ?></div>
      <a class="btn btn-sm btn-outline-secondary" href="empleados.php<?= $q!=='' ? '?q='.urlencode($q) : '' ?>">Cancelar</a>
    </div>
    <form method="post" class="row g-2 align-items-end">
      <input type="hidden" name="action" value="update">
      <input type="hidden" name="id" value="<?= (int)$editEmp['id'] ?>">
      <div class="col-12 col-md-3">
        <label class="form-label">CUIL</label>
        <input type="text" name="cuil" value="<?=h($editEmp['cuil'])?>" class="form-control" required>
      </div>
      <div class="col-12 col-md-2">
        <label class="form-label">Legajo</label>
        <input type="number" name="legajo" value="<?= (int)$editEmp['legajo'] ?>" class="form-control" min="1" required>
      </div>
      <div class="col-12 col-md-3">
        <label class="form-label">Apellido</label>
        <input type="text" name="apellido" value="<?=h($editEmp['apellido'])?>" class="form-control js-upper" style="text-transform: uppercase;" required>
      </div>
      <div class="col-12 col-md-3">
        <label class="form-label">Nombre</label>
        <input type="text" name="nombre" value="<?=h($editEmp['nombre'])?>" class="form-control js-upper" style="text-transform: uppercase;" required>
      </div>
      <div class="col-12 col-md-1 d-grid">
        <button class="btn btn-primary" type="submit">Guardar</button>
      </div>
    </form>
  </div>
  <?php endif; ?>

  <div class="section-card">
    <div class="d-flex justify-content-between align-items-center mb-2 gap-2 flex-wrap">
      <div class="fw-bold">Listado de empleados</div>
      <form method="get" class="d-flex gap-2">
        <input type="text" name="q" value="<?=h($q)?>" class="form-control" placeholder="Buscar por legajo, cuil o nombre">
        <button class="btn btn-outline-primary" type="submit">Buscar</button>
        <a class="btn btn-outline-secondary" href="empleados.php">Limpiar</a>
      </form>
    </div>

    <div class="table-responsive">
      <table class="table table-sm align-middle">
        <thead class="table-light">
          <tr>
            <th>ID</th>
            <th>CUIL</th>
            <th>Legajo</th>
            <th>Apellido</th>
            <th>Nombre</th>
            <th style="width:220px;"></th>
          </tr>
        </thead>
        <tbody>
        <?php if ($res && mysqli_num_rows($res) > 0): ?>
          <?php while ($r = mysqli_fetch_assoc($res)): ?>
            <tr>
              <td><?= (int)$r['id'] ?></td>
              <td><?= h($r['cuil']) ?></td>
              <td><?= (int)$r['legajo'] ?></td>
              <td><?= h($r['apellido']) ?></td>
              <td><?= h($r['nombre']) ?></td>
              <td class="text-end">
                <div class="d-inline-flex gap-2">
                  <a class="btn btn-sm btn-outline-primary" href="empleados.php?<?= http_build_query(['q'=>$q, 'edit'=>$r['id']]) ?>">Editar</a>
                  <form method="post" onsubmit="return confirm('Eliminar empleado de legajo <?= (int)$r['legajo'] ?>?');">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                    <button class="btn btn-sm btn-outline-danger" type="submit">Quitar</button>
                  </form>
                </div>
              </td>
            </tr>
          <?php endwhile; ?>
        <?php else: ?>
          <tr><td colspan="6" class="text-center text-muted py-4">Sin empleados para mostrar.</td></tr>
        <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

</div>
<script>
document.querySelectorAll('.js-upper').forEach(function(el) {
  el.addEventListener('input', function() {
    this.value = this.value.toUpperCase();
  });
});
</script>
</body>
</html>
