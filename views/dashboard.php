<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Panel de Control | SEG Guerrero</title>
    <style>
        :root{--guinda:#6c1d24;--dorado:#bfa276;--oscuro:#212529}*{box-sizing:border-box}body{margin:0;background:#f5f2ed;color:var(--oscuro);font-family:Arial,sans-serif}.seg-navbar{align-items:center;background:var(--guinda);color:#fff;display:flex;height:72px;justify-content:space-between;left:0;padding:0 28px;position:fixed;right:0;top:0;z-index:2}.seg-brand{color:#fff;display:grid;text-decoration:none}.seg-brand small{opacity:.72}.seg-badge{background:var(--dorado);border-radius:20px;color:#302719;font-size:12px;font-weight:700;padding:9px 14px}.seg-sidebar{background:var(--guinda);border-right:2px solid var(--dorado);bottom:0;left:0;padding:30px 14px;position:fixed;top:72px;width:230px}.seg-sidebar-title{color:var(--dorado);display:block;font-size:11px;font-weight:800;letter-spacing:2px;margin:0 14px 22px}.seg-sidebar a{border-radius:8px;color:#fff;display:block;margin-bottom:8px;padding:14px;text-decoration:none}.seg-sidebar a:hover,.seg-sidebar a.active{background:var(--dorado);color:#332719}.content{margin-left:230px;padding:112px 32px}.card{background:#fff;border-left:5px solid var(--dorado);border-radius:12px;box-shadow:0 10px 30px #0000000d;max-width:760px;padding:28px}.card h1{color:var(--guinda);margin-top:0}.card a{background:var(--guinda);border-radius:8px;color:#fff;display:inline-block;margin-top:12px;padding:12px 18px;text-decoration:none}@media(max-width:800px){.seg-sidebar{display:none}.content{margin-left:0}.seg-badge{display:none}}
    </style>
</head>
<body>
<?php include_once __DIR__ . '/fragments/navbar.php'; ?>
<?php include_once __DIR__ . '/fragments/sidebar.php'; ?>
<main class="content">
    <section class="card">
        <h1>Panel de Control</h1>
        <p>Módulo operativo para consolidar RPUs únicos de dos periodos CFE y vincularlos con las CCT de Guerrero.</p>
        <a href="consolidacion.php">Abrir consolidación</a>
    </section>
</main>
</body>
</html>
