<?php

session_start();

?>

<!doctype html>

<html lang="es">

<head>

    <meta charset="utf-8">

    <title>Empaque · Menú Principal</title>

    <meta name="viewport" content="width=device-width, initial-scale=1" />

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

    <style>
    body {
        background: #f8fafc;
    }

    .menu-card {

        border-radius: 16px;
        border: 1px solid #e5e7eb;

        background: white;
        padding: 30px;
        text-align: center;

        transition: all .2s ease;
        box-shadow: 0 1px 4px rgba(0, 0, 0, .05);

    }

    .menu-card:hover {
        transform: translateY(-3px);
        box-shadow: 0 4px 12px rgba(0, 0, 0, .1);
    }

    .menu-card .icon {
        font-size: 40px;
        margin-bottom: 12px;
    }

    .menu-card h5 {
        font-weight: 600;
        margin-bottom: 8px;
    }
    </style>

</head>

<body>

    <div class="container py-5">

        <h1 class="h3 mb-4 text-center">📋 Menú Principal · Empaque SRI</h1>



        <div class="row g-4 justify-content-center">

            <!-- Carga -->

            <div class="col-12 col-md-5">

                <a href="add_unificado.php" class="text-decoration-none text-dark">

                    <div class="menu-card">

                        <div class="icon">✍️</div>

                        <h5>Cargar OP</h5>

                        <p class="text-muted mb-0">Ingresar nueva orden de producción y líneas (LS / F)</p>

                    </div>

                </a>

            </div>



            <!-- Reportes -->

            <div class="col-12 col-md-5">

                <a href="reportes.php" class="text-decoration-none text-dark">

                    <div class="menu-card">

                        <div class="icon">📊</div>

                        <h5>Reportes</h5>

                        <p class="text-muted mb-0">Ver listado de OP cargadas y sus detalles</p>

                    </div>

                </a>

            </div>

        </div>

    </div>

</body>

</html>