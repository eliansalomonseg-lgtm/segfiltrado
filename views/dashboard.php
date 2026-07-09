<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Panel de Control | SEG Guerrero</title>
    <style>
        :root{--guinda:#761f29;--guinda-oscuro:#54151c;--dorado:#c5a56f;--oscuro:#25282c;--fondo:#f5f2ed}*{box-sizing:border-box}body{margin:0;background:radial-gradient(circle at 82% 10%,#c5a56f2b,transparent 30%),var(--fondo);color:var(--oscuro);font-family:Arial,sans-serif}.seg-navbar{align-items:center;background:linear-gradient(105deg,var(--guinda-oscuro),var(--guinda));box-shadow:0 8px 24px #3010142b;color:#fff;display:flex;height:76px;justify-content:space-between;left:0;padding:0 30px;position:fixed;right:0;top:0;z-index:2}.seg-brand{color:#fff;display:grid;text-decoration:none}.seg-brand strong{font-size:17px}.seg-brand small{font-size:11px;opacity:.72}.seg-badge{background:var(--dorado);border-radius:20px;color:#302719;font-size:11px;font-weight:700;padding:9px 14px}.seg-sidebar{background:linear-gradient(180deg,var(--guinda),var(--guinda-oscuro));bottom:0;box-shadow:8px 0 25px #30101416;left:0;padding:30px 14px;position:fixed;top:76px;width:230px}.seg-sidebar-title{color:var(--dorado);display:block;font-size:10px;font-weight:800;letter-spacing:2px;margin:0 14px 22px}.seg-sidebar a{border-left:3px solid transparent;border-radius:9px;color:#ffffffd9;display:block;font-size:14px;margin-bottom:8px;padding:14px;text-decoration:none;transition:.2s}.seg-sidebar a:hover,.seg-sidebar a.active{background:var(--dorado);border-left-color:#fff;color:#332719;transform:translateX(2px)}.content{margin-left:230px;min-height:100vh;padding:112px 34px 45px}.hero{background:linear-gradient(120deg,#fff 0%,#fff 62%,#f0e7d9 100%);border:1px solid #e6dfd5;border-radius:20px;box-shadow:0 18px 45px #30271912;display:grid;gap:28px;grid-template-columns:1.35fr .65fr;padding:42px}.eyebrow{color:var(--guinda);font-size:10px;font-weight:800;letter-spacing:2px}.hero h1{color:var(--guinda);font-size:38px;line-height:1.1;margin:10px 0 14px}.hero p{color:#666b70;font-size:15px;line-height:1.6;margin:0;max-width:660px}.hero a{background:var(--guinda);border-radius:10px;box-shadow:0 8px 18px #761f2938;color:#fff;display:inline-block;font-size:13px;font-weight:700;margin-top:24px;padding:14px 20px;text-decoration:none;transition:.2s}.hero a:hover{background:var(--guinda-oscuro);transform:translateY(-2px)}.process{align-content:center;display:grid;gap:10px}.step{align-items:center;background:#ffffffb8;border:1px solid #dfd4c4;border-radius:12px;display:grid;gap:12px;grid-template-columns:34px 1fr;padding:13px}.step b{align-items:center;background:var(--guinda);border-radius:50%;color:#fff;display:flex;font-size:12px;height:34px;justify-content:center;width:34px}.step strong{display:block;font-size:12px}.step small{color:#777b7f;font-size:10px}.metrics{display:grid;gap:16px;grid-template-columns:repeat(3,1fr);margin-top:20px}.metric{background:#fff;border:1px solid #e6dfd5;border-radius:14px;padding:20px}.metric strong{color:var(--guinda);display:block;font-size:18px;margin-bottom:5px}.metric span{color:#73777b;font-size:11px}@media(max-width:900px){.seg-sidebar{display:none}.content{margin-left:0;padding:100px 18px 30px}.hero{grid-template-columns:1fr;padding:28px}.metrics{grid-template-columns:1fr}.seg-badge{display:none}}@media(max-width:520px){.hero h1{font-size:30px}.seg-navbar{padding:0 18px}}
    </style>
</head>
<body>
<?php $segBasePath = ''; ?>
<?php include_once __DIR__ . '/fragments/navbar.php'; ?>
<?php include_once __DIR__ . '/fragments/sidebar.php'; ?>
<main class="content">
    <section class="hero">
        <div>
            <span class="eyebrow">SISTEMA INTEGRAL SEG</span>
            <h1>Consolida escuelas y medidores en un solo flujo</h1>
            <p>Analiza los catálogos oficiales de SEG y CFE, encuentra las coincidencias más relevantes y confirma cada vínculo antes de guardarlo.</p>
            <a href="consolidacion/consolidacion.php">Iniciar consolidación →</a>
        </div>
        <div class="process" aria-label="Proceso de consolidación">
            <div class="step"><b>1</b><div><strong>Carga los catálogos</strong><small>Archivos Excel SEG y CFE</small></div></div>
            <div class="step"><b>2</b><div><strong>Analiza coincidencias</strong><small>Localidad, nombre y nivel educativo</small></div></div>
            <div class="step"><b>3</b><div><strong>Confirma los vínculos</strong><small>Guarda únicamente resultados revisados</small></div></div>
        </div>
    </section>
    <section class="metrics">
        <div class="metric"><strong>RPU únicos</strong><span>Evita medidores duplicados durante el análisis.</span></div>
        <div class="metric"><strong>3 sugerencias</strong><span>Presenta las escuelas con mayor coincidencia.</span></div>
        <div class="metric"><strong>Control manual</strong><span>Ningún vínculo se guarda sin confirmación.</span></div>
    </section>
</main>
</body>
</html>
