<?php
// report_operarios.php
session_start();
include("conexion.php"); // define $con (mysqli)

/* ==== Seguridad simple por clave (multiples) ==== */
$config = require __DIR__ . '/config.local.php';
$ACCESS_KEYS_OPER = $config['access_keys'] ?? [];

if (isset($_GET['logout'])) {
  unset($_SESSION['oper_ok'], $_SESSION['oper_user']);
  header("Location: report_operarios.php");
  exit;
}

if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['clave'])) {
  $clave = trim($_POST['clave'] ?? '');
  if (isset($ACCESS_KEYS_OPER[$clave])) {
    $_SESSION['oper_ok']   = true;
    $_SESSION['oper_user'] = $ACCESS_KEYS_OPER[$clave];
    header("Location: report_operarios.php");
    exit;
  } else {
    $login_error = "Clave incorrecta.";
  }
}

if (empty($_SESSION['oper_ok'])) {
  // Pantalla de login
  ?>
<!doctype html>
<html lang="es">

<head>
    <meta charset="utf-8">
    <title>Acceso - Reporte por operario</title>
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>

<body class="bg-light d-flex align-items-center" style="min-height:100vh;">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-12 col-md-4">
                <div class="card shadow-sm">
                    <div class="card-body">
                        <h1 class="h5 text-center mb-3">Reporte por operario</h1>
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
                <p class="text-center text-muted mt-3" style="font-size:.85rem"></p>
            </div>
        </div>
    </div>
</body>

</html>
<?php
  exit;
}
// === Fin seguridad ===

/* --------- Helpers ---------- */
function g($k,$d=''){ return isset($_GET[$k]) ? trim($_GET[$k]) : $d; }
function h($s){ return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }
function turnoName($t){ return $t==1?'Manana':($t==2?'Tarde':($t==3?'Noche':'-')); }

/* --------- Filtros (simples) ---------- */
$desde    = g('desde', '');
$hasta    = g('hasta', '');
$operario = g('operario', '');   // legajo (num) guardado en h.operario
$sort     = g('sort', 'legajo'); // legajo|nombre|buenas|malas|excedente
$dir      = strtolower(g('dir', 'asc')) === 'desc' ? 'DESC' : 'ASC';

$sortMap = [
  'legajo'    => 'legajo',
  'nombre'    => 'emp_nombre',
  'buenas'    => 'buenas',
  'malas'     => 'malas',
  'excedente' => 'excedente'
];
if (!isset($sortMap[$sort])) $sort = 'legajo';
$orderBy = $sortMap[$sort].' '.$dir.', legajo ASC';

/* Sugerencia de mes para el export */
$mes_pref = '';
if ($desde!=='')      { $mes_pref = substr($desde,0,7); }
elseif ($hasta!=='')  { $mes_pref = substr($hasta,0,7); }
else                  { $mes_pref = date('Y-m'); }

/* --------- WHERE ---------- */
$where = "1=1";
if ($desde!=='')    $where .= " AND h.fecha >= STR_TO_DATE('".mysqli_real_escape_string($con,$desde)."','%Y-%m-%d')";
if ($hasta!=='')    $where .= " AND h.fecha <= STR_TO_DATE('".mysqli_real_escape_string($con,$hasta)."','%Y-%m-%d')";
if ($operario!=='') $where .= " AND h.operario = ".intval($operario);

/* --------- Consultas --------- */

/* Totales por Operario (rango) + nombre por legajo */
$sqlTotalesOp = "
  SELECT
    h.operario                                           AS legajo,
    TRIM(CONCAT(e.apellido, ' ', e.nombre))             AS emp_nombre,
    SUM(COALESCE(i.buenas,0))    AS buenas,
    SUM(COALESCE(i.malas,0))     AS malas,
    SUM(COALESCE(i.excedente,0)) AS excedente
  FROM op_header h
  JOIN op_item   i ON i.header_id = h.id
  LEFT JOIN empleado e ON e.legajo = h.operario
  WHERE $where
  GROUP BY legajo, emp_nombre
  ORDER BY $orderBy
  LIMIT 5000
";
$resTotalesOp = mysqli_query($con, $sqlTotalesOp);

/* Resumen por Operario y Mes + nombre */
$sqlResumenMes = "
  SELECT
    h.operario                                           AS legajo,
    TRIM(CONCAT(e.apellido, ' ', e.nombre))             AS emp_nombre,
    DATE_FORMAT(h.fecha, '%Y-%m')                        AS ym,
    SUM(COALESCE(i.buenas,0))    AS buenas,
    SUM(COALESCE(i.malas,0))     AS malas,
    SUM(COALESCE(i.excedente,0)) AS excedente,
    GROUP_CONCAT(DISTINCT h.op ORDER BY h.op SEPARATOR ', ') AS ops,
    GROUP_CONCAT(DISTINCT CASE WHEN i.tipo_codigo='LS' AND i.codigo IS NOT NULL AND i.codigo<>'' THEN i.codigo END ORDER BY i.codigo SEPARATOR ', ') AS cod_ls,
    GROUP_CONCAT(DISTINCT CASE WHEN i.tipo_codigo='F'  AND i.codigo IS NOT NULL AND i.codigo<>'' THEN i.codigo END ORDER BY i.codigo SEPARATOR ', ') AS cod_f
  FROM op_header h
  JOIN op_item   i ON i.header_id = h.id
  LEFT JOIN empleado e ON e.legajo = h.operario
  WHERE $where
  GROUP BY legajo, emp_nombre, ym
  ORDER BY ym DESC, legajo ASC
  LIMIT 5000
";
$resResumenMes = mysqli_query($con, $sqlResumenMes);

$RESPONSABLE = $_SESSION['oper_user'] ?? '-';

function sort_link($col, $label, $sort, $dir, $desde, $hasta, $operario) {
  $isActive = ($sort === $col);
  $nextDir = ($isActive && $dir === 'ASC') ? 'desc' : 'asc';
  $arrow = '';
  if ($isActive) {
    $arrow = ($dir === 'ASC') ? ' &uarr;' : ' &darr;';
  }
  $url = 'report_operarios.php?'.http_build_query([
    'desde' => $desde,
    'hasta' => $hasta,
    'operario' => $operario,
    'sort' => $col,
    'dir' => $nextDir
  ]);
  return '<a class="text-decoration-none text-reset" href="'.h($url).'">'.h($label).$arrow.'</a>';
}
?>
<!doctype html>
<html lang="es">

<head>
    <meta charset="utf-8">
    <title>Reporte por operario - Empaque</title>
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/styles.css">
    <style>
    body {
        background: #f8fafc;
    }

    .report-card {
        border: 1px solid #e5e7eb;
        background: #fff;
        border-radius: 12px;
        padding: 16px;
    }

    .hdr {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
    }

    .hdr .title {
        font-size: 1.25rem;
        font-weight: 800;
    }

    .badge-exc {
        background: #fef9c3;
        color: #854d0e;
        border: 1px solid #facc15;
    }

    .nowrap {
        white-space: nowrap;
    }

    .table thead th {
        background: #f1f5f9;
        color: #0f172a;
        border-bottom: 1px solid #e5e7eb;
    }

    .table tbody tr:nth-child(even) {
        background: #fafafa;
    }
    </style>
</head>

<body class="bg-light">
    <div class="container py-4">

        <div class="hdr mb-3">
            <div>
                <div class="title">Reporte por operario</div>
                <div class="text-muted" style="font-size:.9rem">Responsable: <b><?=h($RESPONSABLE)?></b></div>
            </div>
            <div class="d-flex gap-2">
                <a class="btn btn-outline-secondary" href="reportes.php">&larr; Volver</a>
                <a class="btn btn-outline-danger" href="report_operarios.php?logout=1">Cerrar acceso</a>
            </div>
        </div>

        <!-- Filtros simples -->
        <div class="report-card mb-3">
            <form class="row g-3 align-items-end" method="get">
                <input type="hidden" name="sort" value="<?=h($sort)?>">
                <input type="hidden" name="dir" value="<?=h(strtolower($dir))?>">
                <div class="col-12 col-md-3">
                    <label class="form-label">Desde</label>
                    <input type="date" name="desde" value="<?=h($desde)?>" class="form-control">
                </div>
                <div class="col-12 col-md-3">
                    <label class="form-label">Hasta</label>
                    <input type="date" name="hasta" value="<?=h($hasta)?>" class="form-control">
                </div>
                <div class="col-12 col-md-3">
                    <label class="form-label">Operario (Legajo)</label>
                    <input type="number" name="operario" value="<?=h($operario)?>" class="form-control" placeholder="">
                </div>
                <div class="col-12 col-md-3">
                    <label class="form-label d-block">&nbsp;</label>
                    <div class="d-grid">
                        <button class="btn btn-primary" type="submit">Buscar</button>
                    </div>
                </div>
            </form>

            <!-- Acciones: limpiar + exportar con texto intermedio -->
            <div class="d-flex flex-wrap align-items-center justify-content-between mt-2 gap-2">
                <a class="btn btn-outline-secondary" href="report_operarios.php">Limpiar filtros</a>

                <?php if ($operario==='' || $desde==='' || $hasta===''): ?>
                <small class="text-muted mx-2">Ingresa legajo, rango de fechas y presiona "Buscar" para habilitar el Excel.</small>
                <?php endif; ?>

                <a class="btn btn-success <?= ($operario==='' || $desde==='' || $hasta==='') ? 'disabled' : '' ?>" href="export_excel_operaria_rango.php?<?= http_build_query([
      'operario' => $operario,
      'desde'    => $desde,
      'hasta'    => $hasta
    ]) ?>">
                    Descargar Excel (rango)
                </a>
            </div>



        </div>


        <!-- Totales por Operario (rango) -->
        <div class="report-card mb-3">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <div class="fw-bold">Totales por operario (en el rango)</div>
                <small class="text-muted">
                    <?= $desde ? 'Desde '.h($desde): 'Sin limite desde' ?> ·
                    <?= $hasta ? 'Hasta '.h($hasta): 'Sin limite hasta' ?>
                </small>
            </div>
            <div class="table-responsive">
                <table class="table table-sm align-middle">
                    <thead>
                        <tr>
                            <th class="nowrap"><?= sort_link('legajo', 'Legajo', $sort, $dir, $desde, $hasta, $operario) ?></th>
                            <th><?= sort_link('nombre', 'Nombre', $sort, $dir, $desde, $hasta, $operario) ?></th>
                            <th class="text-success"><?= sort_link('buenas', 'Buenas', $sort, $dir, $desde, $hasta, $operario) ?></th>
                            <th class="text-danger"><?= sort_link('malas', 'Malas', $sort, $dir, $desde, $hasta, $operario) ?></th>
                            <th class="text-warning"><?= sort_link('excedente', 'Excedente', $sort, $dir, $desde, $hasta, $operario) ?></th>
                            <th style="width:160px;"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($resTotalesOp && mysqli_num_rows($resTotalesOp)>0): ?>
                        <?php while($t = mysqli_fetch_assoc($resTotalesOp)): ?>
                        <tr>
                            <td class="nowrap"><?= (int)$t['legajo'] ?></td>
                            <td><?= h($t['emp_nombre'] ?: '-') ?></td>
                            <td class="text-success fw-semibold"><?= number_format((int)$t['buenas'], 0, ',', '.') ?></td>
                            <td class="text-danger fw-semibold"><?= number_format((int)$t['malas'], 0, ',', '.') ?></td>
                            <td><?php $exc=(int)$t['excedente']; echo $exc>0?'<span class="badge badge-exc">'.number_format($exc, 0, ',', '.').'</span>':'0'; ?>
                            </td>
                            <td class="text-end">
                                <a class="btn btn-sm btn-outline-primary" href="report_operario_detalle.php?<?= http_build_query([
                       'operario'=>$t['legajo'], 'desde'=>$desde, 'hasta'=>$hasta
                     ]) ?>">
                                    Ver detalle (rango)
                                </a>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                        <?php else: ?>
                        <tr>
                            <td colspan="6" class="text-center text-muted py-3">Sin datos</td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </div>
</body>

</html>
