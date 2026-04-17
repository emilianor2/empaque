<?php
// editor_registros.php (con claves multiples y responsable en sesion)
session_start();
include("conexion.php"); // define $con (mysqli)

/* ========= Seguridad por clave (multiples) ========= */
$config = require __DIR__ . '/config.local.php';
$ACCESS_KEYS = $config['access_keys'] ?? [];

if (isset($_GET['logout'])) {
  unset($_SESSION['editor_ok'], $_SESSION['editor_user']);
  header("Location: editor_registros.php");
  exit;
}

if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['clave'])) {
  $clave = trim($_POST['clave'] ?? '');
  if (isset($ACCESS_KEYS[$clave])) {
    $_SESSION['editor_ok']   = true;
    $_SESSION['editor_user'] = $ACCESS_KEYS[$clave]; // responsable
    header("Location: editor_registros.php");
    exit;
  } else {
    $login_error = "Clave incorrecta.";
  }
}

if (empty($_SESSION['editor_ok'])) {
  ?>
  <!doctype html>
  <html lang="es">
  <head>
    <meta charset="utf-8">
    <title>Acceso - Editor</title>
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  </head>
  <body class="bg-light d-flex align-items-center" style="min-height:100vh;">
    <div class="container">
      <div class="row justify-content-center">
        <div class="col-12 col-md-4">
          <div class="card shadow-sm">
            <div class="card-body">
              <h1 class="h5 text-center mb-3">Acceso al Editor</h1>
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

/* ========= Helpers ========= */
function g($k,$d=''){ return isset($_GET[$k]) ? trim($_GET[$k]) : $d; }
function h($s){ return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }
function turnoName($t){ return $t==1?'Manana':($t==2?'Tarde':($t==3?'Noche':'-')); }

/* ========= Filtros ========= */
$fil_fecha = g('fecha', '');
$fil_op    = g('op', '');
$fil_codigo = g('codigo', '');
if ($fil_fecha==='' && $fil_op==='' && $fil_codigo==='') $fil_fecha = date('Y-m-d');

/* ========= Query de datos (agrupado) ========= */
$where = "1=1";
$conds = [];
if ($fil_fecha!=='') {
  $conds[] = "h.fecha = STR_TO_DATE('".mysqli_real_escape_string($con,$fil_fecha)."','%Y-%m-%d')";
}
if ($fil_op!=='') {
  $conds[] = "h.op = '".mysqli_real_escape_string($con,$fil_op)."'";
}
if ($fil_codigo!=='') {
  $codEsc = mysqli_real_escape_string($con, $fil_codigo);
  $conds[] = "i.codigo LIKE '%$codEsc%'";
}
if (!empty($conds)) {
  $where .= " AND (".implode(' OR ', $conds).")";
}

$sql = "
SELECT
  h.id      AS hid,
  h.fecha   AS fecha,
  h.turno   AS turno,
  h.operario AS operario,
  h.op      AS op,
  h.created_at,
  i.id      AS iid,
  i.tipo_codigo, i.codigo, i.maquina,
  i.cantidad_total, i.buenas, i.malas, i.excedente,
  i.desc_trabajo, i.obs, i.caja_excedente
FROM op_header h
LEFT JOIN op_item i ON i.header_id=h.id
WHERE $where
ORDER BY h.fecha ASC,
         h.turno ASC,
         h.op ASC,
         i.id ASC
LIMIT 2000
";
$res = mysqli_query($con, $sql);

/* Estructura: $data[fecha][turno][op][hid]['header'] + ['items'][] */
$data = [];
if ($res) {
  while ($r = mysqli_fetch_assoc($res)) {
    $f   = $r['fecha'];
    $t   = (int)$r['turno'];
    $op  = $r['op'];
    $hid = (int)$r['hid'];

    if (!isset($data[$f][$t][$op][$hid])) {
      $data[$f][$t][$op][$hid] = [
        'header' => [
          'hid'       => $hid,
          'fecha'     => $r['fecha'],
          'turno'     => (int)$r['turno'],
          'operario'  => (int)$r['operario'],
          'op'        => $r['op'],
          'created_at'=> $r['created_at'],
        ],
        'items' => []
      ];
    }
    if (!is_null($r['iid'])) {
      $data[$f][$t][$op][$hid]['items'][] = [
        'iid'            => (int)$r['iid'],
        'tipo'           => $r['tipo_codigo'],
        'codigo'         => $r['codigo'],
        'maquina'        => $r['maquina'],
        'cantidad_total' => (int)$r['cantidad_total'],
        'buenas'         => (int)$r['buenas'],
        'malas'          => (int)$r['malas'],
        'excedente'      => (int)$r['excedente'],
        'desc_trabajo'   => $r['desc_trabajo'],
        'obs'            => $r['obs'],
        'caja_excedente' => $r['caja_excedente'],
      ];
    }
  }
}

$RESPONSABLE = $_SESSION['editor_user'] ?? '-';
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title>Editor de registros - Empaque</title>
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    body{background:#f8fafc;}
    .report-card{border:1px solid #e5e7eb;background:#fff;border-radius:12px;padding:16px;}
    .section-hdr{font-weight:800;margin:6px 0;}
    .subtle{color:#6b7280;}
    .row-turno{background:#f8fafc;border-left:6px solid #cbd5e1;padding:.25rem .5rem;font-weight:600;}
    .row-op{background:#eef2ff;border-left:6px solid #a5b4fc;padding:.25rem .5rem;font-weight:600;}
    .table thead th{background:#f1f5f9}
    .badge-m{background:#dcfce7;color:#166534;border:1px solid #16a34a;}
    .badge-t{background:#e0f2fe;color:#075985;border:1px solid #0ea5e9;}
    .badge-n{background:#fee2e2;color:#991b1b;border:1px solid #ef4444;}
    .btn-chip{
      display:inline-flex;align-items:center;gap:.35rem;
      border-radius:999px;padding:.35rem .75rem;font-weight:600;
    }
    .btn-edit{border:1px solid #60a5fa;color:#1d4ed8;background:#e0f2fe;}
    .btn-edit:hover{background:#dbeafe;border-color:#3b82f6;}
    .btn-del{border:1px solid #f87171;color:#991b1b;background:#fee2e2;}
    .btn-del:hover{background:#fecaca;border-color:#ef4444;}
  </style>
</head>
<body class="bg-light">
<div class="container py-4">

  <div class="d-flex justify-content-between align-items-center mb-3">
    <div>
      <h1 class="h5 m-0">Editor de registros</h1>
      <div class="subtle">Responsable: <b><?=h($RESPONSABLE)?></b></div>
    </div>
    <div class="d-flex gap-2">
      <a href="reportes.php?logout=1" class="btn btn-outline-secondary">&larr; Volver</a>
      <a href="editor_registros.php?logout=1" class="btn btn-outline-danger">Cerrar acceso</a>
    </div>
  </div>

  <!-- Filtros -->
  <div class="report-card mb-3">
    <form class="row g-2 align-items-end" method="get">
      <div class="col-12 col-md-3">
        <label class="form-label">Fecha</label>
        <input type="date" name="fecha" value="<?=h($fil_fecha)?>" class="form-control">
      </div>
      <div class="col-12 col-md-3">
        <label class="form-label">OP (exacta)</label>
        <input type="text" name="op" value="<?=h($fil_op)?>" class="form-control">
      </div>
      <div class="col-12 col-md-3">
        <label class="form-label">Codigo LS/F</label>
        <input type="text" name="codigo" value="<?=h($fil_codigo)?>" class="form-control" placeholder="Ej: 1234">
      </div>
      <div class="col-12 col-md-3 d-flex gap-2">
        <button class="btn btn-primary flex-fill" type="submit">Buscar</button>
        <a class="btn btn-outline-secondary" href="editor_registros.php">Limpiar</a>
      </div>
      <div class="col-12 text-md-end subtle">
        Orden: <b>Dia -> Turno -> OP</b>
      </div>
    </form>
  </div>

  <!-- Vista de resultados (sin edicion inline) -->
  <div class="report-card">
    <?php if (empty($data)): ?>
      <div class="text-center text-muted py-4">Sin resultados para los filtros.</div>
    <?php else: ?>
      <?php foreach ($data as $fecha => $turnos): ?>
        <div class="section-hdr">Fecha: <b><?=h($fecha)?></b></div>

        <?php foreach ($turnos as $t => $ops): ?>
          <div class="row-turno mb-2">
            <?php $badge = $t==1?'badge-m':($t==2?'badge-t':'badge-n'); ?>
            Turno: <span class="badge <?=$badge?>"><?=turnoName($t)?></span>
          </div>

          <?php foreach ($ops as $op => $headers): ?>
            <div class="row-op mb-2">OP: <code><?=h($op)?></code></div>

            <?php foreach ($headers as $hid => $pack): ?>
              <?php $H = $pack['header']; $items = $pack['items']; ?>

              <div class="mb-3 p-2 border rounded-3">
                <!-- Header -->
                <div class="row g-2 align-items-end">
                  <div class="col-12 col-lg-9">
                    <div class="row g-2">
                      <div class="col-6 col-md-3"><small class="text-muted">Fecha</small><div><?=h($H['fecha'])?></div></div>
                      <div class="col-6 col-md-3"><small class="text-muted">Turno</small><div><?=turnoName($H['turno'])?></div></div>
                      <div class="col-6 col-md-3"><small class="text-muted">Operario</small><div><?=h($H['operario'])?></div></div>
                      <div class="col-6 col-md-3"><small class="text-muted">Creada</small><div><?=h($H['created_at'])?></div></div>
                    </div>
                  </div>
                  <div class="col-12 col-lg-3 text-lg-end d-flex gap-2 justify-content-start justify-content-lg-end">
                    <a class="btn btn-sm btn-chip btn-edit" href="edit_header.php?id=<?=$H['hid']?>">
                      Editar cabecera
                    </a>
                    <a class="btn btn-sm btn-chip btn-del"
                       onclick="return confirm('Eliminar cabecera #<?=$H['hid']?> y TODAS sus lineas? Esta accion no se puede deshacer.')"
                       href="delete_header.php?id=<?=$H['hid']?>&return=<?=urlencode('editor_registros.php?'.http_build_query(['fecha'=>$fil_fecha,'op'=>$fil_op,'codigo'=>$fil_codigo]))?>">
                      Eliminar cabecera
                    </a>
                  </div>
                </div>

                <!-- Items -->
                <div class="table-responsive mt-2">
                  <table class="table table-sm align-middle">
                    <thead>
                      <tr>
                        <th style="width:70px">Tipo</th>
                        <th style="width:120px">Codigo</th>
                        <th style="width:110px">Maquina</th>
                        <th class="text-end" style="width:110px">Total</th>
                        <th class="text-end text-success" style="width:110px">Buenas</th>
                        <th class="text-end text-danger" style="width:110px">Malas</th>
                        <th class="text-end text-warning" style="width:110px">Exced.</th>
                        <th>Descripcion</th>
                        <th>Obs</th>
                        <th style="width:210px"></th>
                      </tr>
                    </thead>
                    <tbody>
                      <?php if (empty($items)): ?>
                        <tr><td colspan="10" class="text-muted">Sin lineas</td></tr>
                      <?php else: foreach ($items as $I): ?>
                        <tr>
                          <td><span class="badge <?=$I['tipo']==='LS'?'text-bg-primary':'text-bg-warning'?>"><?=h($I['tipo'])?></span></td>
                          <td><code><?=h($I['codigo'])?></code></td>
                          <td><?=h($I['maquina'])?></td>
                          <td class="text-end"><?=number_format($I['cantidad_total'],0,',','.')?></td>
                          <td class="text-end text-success"><?=number_format($I['buenas'],0,',','.')?></td>
                          <td class="text-end text-danger"><?=number_format($I['malas'],0,',','.')?></td>
                          <td class="text-end text-warning"><?=number_format($I['excedente'],0,',','.')?></td>
                          <td><?=h($I['desc_trabajo'])?></td>
                          <td><?=h($I['obs'])?></td>
                          <td class="text-end">
                            <div class="d-inline-flex gap-2">
                              <a class="btn btn-sm btn-chip btn-edit" href="edit_item.php?id=<?=$I['iid']?>">
                                Editar linea
                              </a>
                              <a class="btn btn-sm btn-chip btn-del"
                                 onclick="return confirm('Eliminar la linea #<?=$I['iid']?>? Esta accion no se puede deshacer.')"
                                 href="delete_item.php?id=<?=$I['iid']?>&return=<?=urlencode('editor_registros.php?'.http_build_query(['fecha'=>$fil_fecha,'op'=>$fil_op,'codigo'=>$fil_codigo]))?>">
                                Eliminar linea
                              </a>
                            </div>
                          </td>
                        </tr>
                      <?php endforeach; endif; ?>
                    </tbody>
                  </table>
                </div>
              </div>

            <?php endforeach; // headers ?>
          <?php endforeach; // ops ?>
        <?php endforeach; // turnos ?>
      <?php endforeach; // fechas ?>
    <?php endif; ?>
  </div>

</div>
</body>
</html>

