<!DOCTYPE html>
<html lang="es">

<head>
<!-- header_orientador.php -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.8/dist/umd/popper.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.min.js"></script>
    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inicio - Sistema Escolar Orientador</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.4/font/bootstrap-icons.css" rel="stylesheet">
    <!-- Font Awesome for additional icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css"
        integrity="sha512-Kc323vGBEqzTmouAECnVceyQqyqdsSiqLQISBL29aUW4U/M7pSPA/gEUZQqv1cwx4OnYxTxve5UMg5GT6L4JJg=="
        crossorigin="anonymous" referrerpolicy="no-referrer" />
    <!-- Google Fonts -->
    <link
        href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700&family=Poppins:wght@400;600;700&display=swap"
        rel="stylesheet">
    <style>
        /* ---------------------------------------VARIABLES DE COLOR--------------------------------------- */
        :root {
            --color-navy: #43709dff;
            --color-navy-light: #3c4e60;
            --color-navy-lighter: #4c5e70;
            --color-accent: #3498db;
            --color-accent-light: #5dade2;
            --color-text: #333333;
            --color-text-light: #666666;
            --color-white: #ffffff;
            --gradient-navy: linear-gradient(135deg, var(--color-navy) 0%, var(--color-navy-light) 100%);
            --shadow-soft: 0 5px 15px rgba(0, 0, 0, 0.1);
            --shadow-medium: 0 8px 25px rgba(0, 0, 0, 0.15);
        }

        /* ---------------------------------------ESTILOS GENERALES--------------------------------------- */
        body {
            font-family: 'Poppins', sans-serif;
            background-color: #f8f9fa;
            color: var(--color-text);
            padding-top: 80px;
            overflow-x: hidden;
            min-height: 100vh;
        }

        /* ---------------------------------------ANIMACIONES PERSONALIZADAS--------------------------------------- */
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translate3d(0, 40px, 0);
            }

            to {
                opacity: 1;
                transform: translate3d(0, 0, 0);
            }
        }

        @keyframes fadeInLeft {
            from {
                opacity: 0;
                transform: translate3d(-40px, 0, 0);
            }

            to {
                opacity: 1;
                transform: translate3d(0, 0, 0);
            }
        }
            .dropdown-submenu .dropdown-menu {
                top: 0;
                left: 0;
                margin-left: 100%;
                border-radius: 12px;
                box-shadow: var(--shadow-medium);
                animation: fadeInLeft 0.2s ease-out;
                width: 125%;
                max-width: 600px;
                overflow: visible;
                position: absolute;
            }

            /* Mostrar submenús en offcanvas móvil */
            @media (max-width: 992px) {
                .dropdown-submenu .dropdown-menu {
                    position: static !important;
                    margin-left: 0 !important;
                    width: 100% !important;
                    max-width: none !important;
                    box-shadow: none !important;
                    animation: none !important;
                    display: block !important;
                }
            }
            

            to {
                transform: translate3d(0, 0, 0);
            }
            

        @keyframes subtleGlow {
            0% {
                box-shadow: 0 0 5px rgba(52, 152, 219, 0.3);
            }

            50% {
                box-shadow: 0 0 15px rgba(52, 152, 219, 0.5);
            }

            100% {
                box-shadow: 0 0 5px rgba(52, 152, 219, 0.3);
            }
        }

        @keyframes smoothBounce {
            0%, 20%, 50%, 80%, 100% {
                transform: translateY(0);
            }

            40% {
                transform: translateY(-10px);
            }

            60% {
                transform: translateY(-5px);
            }
        }

        @keyframes scaleIn {
            from {
                opacity: 0;
                transform: scale(0.9);
            }

            to {
                opacity: 1;
                transform: scale(1);
            }
        }

        @keyframes ring {
            0% {
                transform: rotate(0deg);
            }
            10% {
                transform: rotate(15deg);
            }
            20% {
                transform: rotate(-15deg);
            }
            30% {
                transform: rotate(15deg);
            }
            40% {
                transform: rotate(-15deg);
            }
            50% {
                transform: rotate(0deg);
            }
            100% {
                transform: rotate(0deg);
            }
        }

        /* ---------------------------------------MENÚ (SOLO ESTA PARTE ES AZUL MARINO)--------------------------------------- */
        .navbar-custom {
            background: var(--gradient-navy);
            border-bottom: 3px solid var(--color-accent);
            box-shadow: var(--shadow-medium);
            padding: 12px 0;
            animation: slideInDown 0.5s ease-out;
            z-index: 1000;
            animation-fill-mode: forwards; /* Asegurar que la animación se aplique completamente */
        }

        /* Aplicar animación al contenedor completo para que afecte a toda la barra */
        .navbar-custom .container-fluid {
            animation: slideInDown 0.5s ease-out;
            animation-fill-mode: forwards;
        }

        .navbar-custom .navbar-brand {
            color: var(--color-white);
            font-weight: 600;
            font-size: 1.15rem;
            transition: all 0.3s ease;
        }

        .navbar-custom .navbar-brand:hover {
            color: var(--color-accent-light);
            transform: translateY(-2px);
        }

        /* Estilo para el logo con fondo blanco */
        .navbar-brand {
            background-color: white;
            border-radius: 8px;
            padding: 5px;
            margin-right: 10px;
            animation: scaleIn 0.8s ease-out;
        }

        .navbar-brand img {
            transition: all 0.3s ease;
            filter: brightness(1.2) contrast(1.1);
            max-height: 40px;
        }

        .navbar-brand:hover img {
            transform: scale(1.05);
        }

        .navbar-toggler {
            border-color: var(--color-accent);
            background: rgba(52, 152, 219, 0.1);
            transition: all 0.3s ease;
        }

        .navbar-toggler:hover {
            background: rgba(52, 152, 219, 0.2);
            animation: subtleGlow 2s infinite;
        }

        .navbar-toggler-icon {
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 30 30'%3E%3Cpath stroke='rgba%2852, 152, 219, 1%29' stroke-width='2' stroke-linecap='round' stroke-miterlimit='10' d='M4 7h22M4 15h22M4 23h22'/%3E%3C/svg%3E");
        }

        /* ---------------------------------------ESTILO UNIFICADO PARA TODOS LOS BOTONES DEL MENÚ --------------------------------------- */
        .nav-item {
            margin: 0 5px;
        }

        .nav-link {
            background: rgba(52, 152, 219, 0.1);
            border-radius: 8px;
            color: var(--color-white) !important;
            font-weight: 600;
            font-size: 1.20rem;
            transition: all 0.3s ease;
            padding: 8px 15px;
            margin: 2px 0;
            display: flex;
            align-items: center;
        }

        .nav-link:hover,
        .nav-link:focus {
            background: rgba(52, 152, 219, 0.2);
            color: var(--color-white) !important;
            transform: translateY(-2px);
            animation: smoothBounce 1s;
        }

        .nav-link.dropdown-toggle::after {
            margin-left: 8px;
            vertical-align: middle;
            border-top: 0.4em solid;
            border-right: 0.4em solid transparent;
            border-left: 0.4em solid transparent;
        }

        /* Estilo especial para el enlace activo */
        .nav-item.active .nav-link {
            background: rgba(52, 152, 219, 0.25);
            font-weight: 700;
            color: var(--color-white) !important;
            box-shadow: 0 0 10px rgba(52, 152, 219, 0.3);
        }

        /* ---------------------------------------ESTILO PARA LA CAMPANA DE NOTIFICACIONES--------------------------------------- */
        #campanaNotificaciones {
            transition: all 0.3s ease;
            display: inline-block;
            transform-origin: top center;
        }

        #campanaNotificaciones.bi-bell-fill {
            filter: drop-shadow(0 0 8px rgba(255, 68, 68, 0.6));
        }

        /* ---------------------------------------MENÚ DESPLEGABLE--------------------------------------- */
        .dropdown-menu {
            background: #ffffff;
            border: none;
            border-radius: 12px;
            box-shadow: var(--shadow-medium);
            margin-top: 8px;
            animation: fadeInUp 0.3s ease-out;
            font-family: 'Poppins', sans-serif;
            transform-origin: top center;
            border: 1px solid rgba(52, 152, 219, 0.1);
        }

        /* Ajustes automáticos para el cuadro blanco en Contactos y Ajustes */
        #navbarDropdownContactos + .dropdown-menu,
        #ajustesDropdown + .dropdown-menu {
            width: auto; /* Ajustar el ancho automáticamente según el contenido */
            min-width: 200px; /* Establecer un ancho mínimo para evitar cuadros demasiado pequeños */
            max-width: 400px; /* Limitar el ancho máximo para evitar cuadros demasiado grandes */
            overflow: hidden; /* Evitar desbordamientos */
        }

        #navbarDropdownContactos + .dropdown-menu .dropdown-item,
        #ajustesDropdown + .dropdown-menu .dropdown-item {
            white-space: nowrap; /* Evitar que el texto se rompa en varias líneas */
            text-overflow: ellipsis; /* Agregar puntos suspensivos si el texto es demasiado largo */
            overflow: hidden; /* Ocultar el texto que exceda el contenedor */
        }

        .dropdown-menu .dropdown-item {
            color: var(--color-text);
            padding: 10px 15px;
            border-radius: 6px;
            font-size: 0.95rem;
            font-weight: 500;
            transition: all 0.3s ease;
            position: relative;
            overflow: visible;
            display: flex;
            align-items: center;
        }

        .dropdown-menu .dropdown-item:hover {
            background: #f0f8ff;
            color: var(--color-navy);
            transform: translateX(8px);
            font-weight: 600;
        }

        .dropdown-menu .dropdown-item::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            width: 0;
            height: 2px;
            background-color: var(--color-navy);
            transition: width 0.3s ease;
        }

        .dropdown-menu .dropdown-item:hover::after {
            width: 100%;
        }

        .dropdown-menu .dropdown-item i {
            margin-right: 10px;
            color: var(--color-navy);
            transition: transform 0.3s ease;
        }

        .dropdown-menu .dropdown-item:hover i {
            transform: scale(1.2);
        }

        /* ---------------------------------------SUBMENÚ Y SUB-SUBMENÚ--------------------------------------- */
        .dropdown-submenu {
            position: relative;
        }

        .dropdown-submenu > .dropdown-toggle::after {
            content: "\f285";
            font-family: "bootstrap-icons";
            border: none;
            float: right;
            margin-left: auto;
            font-size: 0.8rem;
        }

        .dropdown-submenu .dropdown-menu {
            top: 0;
            left: 0;
            margin-left: 100%;
            border-radius: 12px;
            box-shadow: var(--shadow-medium);
            animation: fadeInLeft 0.2s ease-out;
            width: 125%;
            max-width: 600px;
            overflow: visible;
            position: absolute;
            display: none;
        }

        .dropdown-submenu .dropdown-menu.show {
            display: block;
        }

        .dropdown-submenu .dropdown-item {
            padding: 10px 15px;
            color: var(--color-text);
            font-size: 0.85rem;
            font-weight: 500;
            transition: all 0.3s ease;
        }

        .dropdown-submenu .dropdown-item:hover {
            background: #f0f8ff;
            color: var(--color-navy);
            font-weight: 600;
            transform: translateX(8px);
        }

        .dropdown-submenu .dropdown-item::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            width: 0;
            height: 2px;
            background-color: var(--color-navy);
            transition: width 0.3s ease;
        }

        .dropdown-submenu .dropdown-item:hover::after {
            width: 100%;
        }

        .dropdown-submenu .dropdown-menu .dropdown-item {
            padding: 8px 15px;
            font-size: 0.85rem;
        }

        /* ---------------------------------------ESTILO RESPONSIVO--------------------------------------- */
        @media (max-width: 992px) {
            .navbar-custom {
                padding: 10px 0;
            }

            .navbar-brand img {
                max-height: 35px;
            }

            .nav-link {
                font-size: 1rem;
                padding: 8px 10px;
                margin: 3px 0;
            }

            .dropdown-menu {
                max-width: 100%;
                margin: 0;
                border-radius: 8px;
                box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
            }

            .dropdown-submenu .dropdown-menu {
                left: 0;
                margin-left: 10px;
                max-width: 100%;
                animation: fadeInRight 0.3s ease-out;
            }

            .dropdown-menu .dropdown-item {
                font-size: 0.9rem;
                padding: 8px 12px;
            }
        }

        @media (max-width: 768px) {
            .navbar-custom {
                padding: 8px 0;
            }

            .navbar-brand img {
                max-height: 30px;
            }

            .nav-link {
                font-size: 0.95rem;
                padding: 8px 10px;
                text-align: left;
                justify-content: flex-start;
            }

            .dropdown-menu,
            .dropdown-submenu .dropdown-menu {
                width: 100%;
                max-width: none;
                border-radius: 8px;
                box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
                animation: fadeInUp 0.3s ease-out;
                margin: 0;
                margin-left: 15px;
            }

            .dropdown-menu .dropdown-item {
                font-size: 0.85rem;
                padding: 8px 10px;
            }

            .dropdown-submenu .dropdown-menu {
                margin-left: 15px;
                animation: fadeInUp 0.3s ease-out;
            }

            .offcanvas-custom {
                width: 300px;
            }

            .offcanvas-body {
                padding: 10px;
            }
        }

        @media (max-width: 576px) {
            .navbar-custom {
                padding: 6px 0;
            }

            .navbar-brand {
                padding: 3px;
                margin-right: 5px;
            }

            .navbar-brand img {
                max-height: 25px;
            }

            .nav-link {
                font-size: 0.9rem;
                padding: 6px 8px;
            }

            .dropdown-menu .dropdown-item {
                font-size: 0.8rem;
                padding: 6px 8px;
            }

            .offcanvas-custom {
                width: 260px;
            }

            .offcanvas-title {
                font-size: 1rem;
            }
        }

        /* ---------------------------------------DECORATIVO--------------------------------------- */
        .dropdown-menu::before {
            content: '';
            position: absolute;
            top: -8px;
            left: 50%;
            transform: translateX(-50%);
            border: 8px solid transparent;
            border-bottom-color: #ffffff;
        }

        .dropdown-submenu .dropdown-menu::before {
            top: 10px;
            left: -16px;
            border: 8px solid transparent;
            border-right-color: #ffffff;
        }

        @media (max-width: 768px) {
            .dropdown-menu::before,
            .dropdown-submenu .dropdown-menu::before {
                display: none;
            }
        }

        /* Offcanvas personalizado */
        .offcanvas-custom {
            background: var(--gradient-navy);
            color: var(--color-white);
            font-family: 'Poppins', sans-serif;
            animation: slideInDown 0.5s ease-out;
            animation-fill-mode: forwards; /* Asegurar que la animación se aplique completamente */
        }

        .offcanvas-header {
            border-bottom: 1px solid var(--color-navy-light);
            padding: 10px 15px;
        }

        .offcanvas-title {
            color: var(--color-accent-light);
            font-weight: 700;
            font-size: 1.2rem;
            transition: text-shadow 0.3s ease;
            animation: fadeInRight 0.5s ease-out;
        }

        .offcanvas-title:hover {
            text-shadow: 0 0 10px rgba(52, 152, 219, 0.5);
        }

        .btn-close-white {
            filter: invert(1);
            transition: transform 0.3s ease;
        }

        .btn-close-white:hover {
            transform: rotate(90deg);
        }

        /* Animaciones para elementos del offcanvas */
        .offcanvas-body .nav-item {
            opacity: 0;
            transform: translateY(20px);
            animation: fadeInUp 0.5s forwards;
        }

        .offcanvas-body .nav-item:nth-child(1) { animation-delay: 0.1s; }
        .offcanvas-body .nav-item:nth-child(2) { animation-delay: 0.2s; }
        .offcanvas-body .nav-item:nth-child(3) { animation-delay: 0.3s; }
        .offcanvas-body .nav-item:nth-child(4) { animation-delay: 0.4s; }
        .offcanvas-body .nav-item:nth-child(5) { animation-delay: 0.5s; }
        .offcanvas-body .nav-item:nth-child(6) { animation-delay: 0.6s; }
        .offcanvas-body .nav-item:nth-child(7) { animation-delay: 0.7s; }
        .offcanvas-body .nav-item:nth-child(8) { animation-delay: 0.8s; }
        .offcanvas-body .nav-item:nth-child(9) { animation-delay: 0.9s; }
        .offcanvas-body .nav-item:nth-child(10) { animation-delay: 1.0s; }
        .offcanvas-body .nav-item:nth-child(11) { animation-delay: 1.1s; }

        /* Animaciones para navbar en desktop (izquierda a derecha) */
        @media (min-width: 992px) {
            .navbar-nav .nav-item {
                opacity: 0;
                transform: translateX(-40px);
                animation: fadeInLeft 0.6s forwards;
            }

            .navbar-nav .nav-item:nth-child(1) { animation-delay: 0.1s; }
            .navbar-nav .nav-item:nth-child(2) { animation-delay: 0.2s; }
            .navbar-nav .nav-item:nth-child(3) { animation-delay: 0.3s; }
            .navbar-nav .nav-item:nth-child(4) { animation-delay: 0.4s; }
            .navbar-nav .nav-item:nth-child(5) { animation-delay: 0.5s; }
            .navbar-nav .nav-item:nth-child(6) { animation-delay: 0.6s; }
            .navbar-nav .nav-item:nth-child(7) { animation-delay: 0.7s; }
            .navbar-nav .nav-item:nth-child(8) { animation-delay: 0.8s; }
            .navbar-nav .nav-item:nth-child(9) { animation-delay: 0.9s; }
            .navbar-nav .nav-item:nth-child(10) { animation-delay: 1.0s; }
            .navbar-nav .nav-item:nth-child(11) { animation-delay: 1.1s; }
        }

        /* Efecto de aparición escalonada para elementos de menú */
        .staggered-item {
            opacity: 0;
            transform: translateY(10px);
            transition: all 0.5s ease;
        }

        .staggered-item.visible {
            opacity: 1;
            transform: translateY(0);
        }

        /* Efecto de elevación suave para elementos interactivos */
        .hover-lift {
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }

        .hover-lift:hover {
            transform: translateY(-3px);
            box-shadow: var(--shadow-medium);
        }
    </style>
</head>

<body>
    <!-- Menú -->
    <nav class="navbar navbar-expand-lg navbar-custom fixed-top">
        <div class="container-fluid">
            <a class="navbar-brand" href="https://denedig.online/">
                <img src="../../assets/images/alianzas/denedig.png" alt="Logo de Denedig" height="40">
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="offcanvas" data-bs-target="#offcanvasNavbar"
                aria-controls="offcanvasNavbar">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="offcanvas offcanvas-end offcanvas-custom" tabindex="-1" id="offcanvasNavbar"
                aria-labelledby="offcanvasNavbarLabel">
                <div class="offcanvas-header">
                    <h5 class="offcanvas-title" id="offcanvasNavbarLabel">Menú Orientador</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="offcanvas"
                        aria-label="Close"></button>
                </div>
                <div class="offcanvas-body">
                    <ul class="navbar-nav">
                        <!-- Inicio -->
                        <li class="nav-item">
                            <a class="nav-link" href="index_orientador.php">
                                <i class="bi bi-house"></i> Inicio
                            </a>
                        </li>

                        <!-- Gestor Escolar -->
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle" href="#" id="gestorEscolarDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                <i class="bi bi-journal-bookmark-fill"></i> Gestor Escolar  
                            </a>
                            <ul class="dropdown-menu" aria-labelledby="gestorEscolarDropdown">
                                <!-- Listados -->
                                <li class="dropdown-submenu">
                                    <a class="dropdown-item dropdown-toggle" href="#"><i class="bi bi-list-ul"></i> Listados</a>
                                    <ul class="dropdown-menu">
                                        <li><a class="dropdown-item" href="tabla_alumnos.php"><i class="bi bi-person-lines-fill"></i> Lista de Alumnos</a></li>
                                        <li><a class="dropdown-item" href="tabla_maestros.php"><i class="bi bi-person-badge"></i> Lista de Maestros</a></li>
                                    </ul>
                                </li>

                                <!-- Conteo -->
                                <li class="dropdown-submenu">
                                    <a class="dropdown-item dropdown-toggle" href="#"><i class="bi bi-calculator-fill"></i> Conteo</a>
                                    <ul class="dropdown-menu">
                                        <li><a class="dropdown-item" href="Conteo_alumnos.php"><i class="bi bi-people-fill"></i> Conteo de Alumnos</a></li>
                                        <li><a class="dropdown-item" href="vista_general_grupos_beta.php"><i class="bi bi-diagram-3-fill"></i> Vista General de Grupos</a></li>
                                    </ul>
                                </li>

                                <!-- Gestor Familiar -->
                                <li class="dropdown-submenu">
                                    <a class="dropdown-item dropdown-toggle" href="#"><i class="bi bi-house-heart-fill"></i> Gestor Familiar</a>
                                    <ul class="dropdown-menu">
                                        <li><a class="dropdown-item" href="alumnos_relacion.php"><i class="bi bi-people-fill"></i> Registro Familiar</a></li>
                                        <li><a class="dropdown-item" href="padres_registro.php"><i class="bi bi-person-vcard-fill"></i> Información Familiares</a></li>
                                        <li><a class="dropdown-item" href="vista_grupo_expediente.php"><i class="bi bi-folder2-open"></i> Expedientes de Grupo</a></li>
                                        <li><a class="dropdown-item" href="expediente_academico.php"><i class="bi bi-file-earmark-person"></i> Expediente Académico</a></li>
                                    </ul>
                                </li>

                                <!-- Incidencias -->
                                <li class="dropdown-submenu">
                                    <a class="dropdown-item dropdown-toggle" href="#"><i class="bi bi-exclamation-triangle-fill"></i> Incidencias</a>
                                    <ul class="dropdown-menu">
                                        <li><a class="dropdown-item" href="crear_inicidencia2.php"><i class="bi bi-file-earmark-plus"></i> Crear Incidencia</a></li>
                                        <li><a class="dropdown-item" href="semaforo_incidencias2.php"><i class="bi bi-traffic-light"></i> Ver Incidencias</a></li>
                                    </ul>
                                </li>

                                <!-- Contactos -->
                                <li class="dropdown-submenu">
                                    <a class="dropdown-item dropdown-toggle" href="#"><i class="bi bi-telephone-fill"></i> Contactos</a>
                                    <ul class="dropdown-menu">
                                        <li><a class="dropdown-item" href="buscadorAgregarContacto.php"><i class="bi bi-person-plus-fill"></i> Agregar Números de Emergencia</a></li>
                                        <li><a class="dropdown-item" href="buscadorEditarNumeroEmergencia.php"><i class="bi bi-pencil-square"></i> Editar Números de Emergencia</a></li>
                                    </ul>
                                </li>

                                <!-- Sistema de Asistencias -->
                                <li class="dropdown-submenu">
                                    <a class="dropdown-item dropdown-toggle" href="#"><i class="bi bi-calendar-check-fill"></i> Sistema de Asistencias</a>
                                    <ul class="dropdown-menu">
                                        <li><a class="dropdown-item" href="entrada_salida_automatizado.php"><i class="bi bi-door-open-fill"></i> Entrada/Salida Automatizado</a></li>
                                        <li><a class="dropdown-item" href="entrada_salida.php"><i class="bi bi-door-closed-fill"></i> Entrada/Salida Manual</a></li>
                                        <li><a class="dropdown-item" href="registro_asistencia_general.php"><i class="bi bi-clipboard-check"></i> Registro Asistencia General</a></li>
                                        <li><a class="dropdown-item" href="registro_asistencia_diario.php"><i class="bi bi-calendar-check"></i> Registro Asistencia Diario</a></li>
                                        <li><a class="dropdown-item" href="registro_inasistencia_diario.php"><i class="bi bi-calendar-x"></i> Registro Inasistencia Diario</a></li>
                                        <li><a class="dropdown-item" href="F_tabla_asistencia.php"><i class="bi bi-table"></i> Tabla de Asistencias</a></li>
                                    </ul>
                                </li>

                                <!-- Control de Calificaciones -->
                                <li class="dropdown-submenu">
                                    <a class="dropdown-item dropdown-toggle" href="#"><i class="bi bi-file-earmark-bar-graph-fill"></i> Control de Calificaciones</a>
                                    <ul class="dropdown-menu">
                                        <li><a class="dropdown-item" href="vista_general_grupos_beta.php"><i class="bi bi-grid-3x3-gap-fill"></i> Grupos</a></li>
                                    </ul>
                                </li>


                            </ul>
                        </li>



                        <!-- Buscadores -->
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle" href="#" id="buscadoresDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                <i class="bi bi-search"></i> Buscadores
                            </a>
                            <ul class="dropdown-menu" aria-labelledby="buscadoresDropdown">
                                <li><a class="dropdown-item" href="buscador.php"><i class="bi bi-search-heart"></i> Buscador CURP</a></li>
                                <li><a class="dropdown-item" href="scaner_datos.php"><i class="bi bi-qr-code-scan"></i> Buscador QR</a></li>
                            </ul>
                        </li>

                        <!-- Notificaciones -->
                        <li class="nav-item">
                            <a class="nav-link" href="notificaciones_orientadores.php">
                                <i id="campanaNotificaciones" class="bi bi-bell"></i> Notificaciones
                            </a>
                        </li>

                        <!-- Ajustes -->
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle" href="#" id="ajustesDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                <i class="bi bi-gear-fill"></i> Ajustes
                            </a>
                            <ul class="dropdown-menu" aria-labelledby="ajustesDropdown">
                                <li><a class="dropdown-item" href="perfil.php"><i class="bi bi-person-badge-fill"></i> Datos personales</a></li>
                                <li><a class="dropdown-item" href="token_orientador.php"><i class="bi bi-key-fill"></i> Cambiar Token</a></li>
                            </ul>
                        </li>
                        
                        <!-- Cerrar sesión -->
                        <li class="nav-item">
                            <a class="nav-link" href="../logout.php">
                                <i class="bi bi-box-arrow-right"></i> Cerrar sesión
                            </a>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </nav>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const dropdownToggles = document.querySelectorAll('.dropdown-toggle');
            const dropdownMenus = document.querySelectorAll('.dropdown-menu');

            function closeAllMenus() {
                dropdownMenus.forEach(menu => {
                    menu.classList.remove('show');
                });
            }

            // Manejar clicks en dropdowns principales
            dropdownToggles.forEach(toggle => {
                toggle.addEventListener('click', function (e) {
                    e.preventDefault();
                    e.stopPropagation();

                    const dropdownMenu = this.nextElementSibling;
                    if (dropdownMenu && dropdownMenu.classList.contains('dropdown-menu')) {
                        const isOpen = dropdownMenu.classList.contains('show');
                        
                        // Cerrar otros menús del mismo nivel
                        const parentMenu = this.closest('.dropdown-menu');
                        if (parentMenu) {
                            const siblingMenus = parentMenu.querySelectorAll(':scope > .dropdown-submenu > .dropdown-menu');
                            siblingMenus.forEach(menu => {
                                if (menu !== dropdownMenu) {
                                    menu.classList.remove('show');
                                }
                            });
                        } else {
                            // Si es un menú principal, cerrar todos los demás menús principales
                            document.querySelectorAll('.navbar-nav > .nav-item > .dropdown-menu').forEach(menu => {
                                if (menu !== dropdownMenu) {
                                    menu.classList.remove('show');
                                }
                            });
                        }
                        
                        // Toggle el menú actual
                        dropdownMenu.classList.toggle('show');
                    }
                });
            });

            // Cerrar menús al hacer click fuera
            document.addEventListener('click', function (e) {
                if (!e.target.closest('.dropdown-toggle') && !e.target.closest('.dropdown-menu')) {
                    closeAllMenus();
                }
            });

            // Manejar hover en desktop para submenús
            const submenus = document.querySelectorAll('.dropdown-submenu');
            submenus.forEach(submenu => {
                submenu.addEventListener('mouseenter', function () {
                    if (window.innerWidth > 992) {
                        const dropdownMenu = submenu.querySelector(':scope > .dropdown-menu');
                        if (dropdownMenu) {
                            dropdownMenu.classList.add('show');
                        }
                    }
                });

                submenu.addEventListener('mouseleave', function () {
                    if (window.innerWidth > 992) {
                        const dropdownMenu = submenu.querySelector(':scope > .dropdown-menu');
                        if (dropdownMenu) {
                            dropdownMenu.classList.remove('show');
                        }
                    }
                });

                // Manejar click en mobile para submenús
                const submenuToggle = submenu.querySelector(':scope > .dropdown-toggle');
                if (submenuToggle) {
                    submenuToggle.addEventListener('click', function(e) {
                        if (window.innerWidth <= 992) {
                            e.preventDefault();
                            e.stopPropagation();
                            const submenuDropdown = this.nextElementSibling;
                            if (submenuDropdown) {
                                submenuDropdown.classList.toggle('show');
                            }
                        }
                    });
                }
            });

            // Cerrar menús al cerrar offcanvas
            const offcanvasElement = document.getElementById('offcanvasNavbar');
            if (offcanvasElement) {
                offcanvasElement.addEventListener('hidden.bs.offcanvas', function () {
                    closeAllMenus();
                });

                offcanvasElement.addEventListener('shown.bs.offcanvas', function () {
                    closeAllMenus();
                });
            }

            // Animación de items del menú
            const navItems = document.querySelectorAll('.offcanvas-body .nav-item');
            navItems.forEach((item, index) => {
                setTimeout(() => {
                    item.classList.add('visible');
                }, index * 100);
            });

            // Animación al hacer scroll
            function animateOnScroll() {
                const elements = document.querySelectorAll('.staggered-item');
                elements.forEach(element => {
                    const elementPosition = element.getBoundingClientRect().top;
                    const screenPosition = window.innerHeight / 1.3;
                    if (elementPosition < screenPosition) {
                        element.classList.add('visible');
                    }
                });
            }

            window.addEventListener('scroll', animateOnScroll);
            animateOnScroll();
        });
    </script>

    <!-- Script para verificar notificaciones -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Verificar notificaciones al cargar la página
            verificarNotificaciones();
            
            // Verificar cada 30 segundos
            setInterval(verificarNotificaciones, 30000);
            
            function verificarNotificaciones() {
                fetch('verificar_notificaciones.php')
                    .then(response => response.json())
                    .then(data => {
                        const campana = document.getElementById('campanaNotificaciones');
                        if (campana) {
                            if (data.hayNoVistas) {
                                // Cambiar a campana rellena y color rojo
                                campana.classList.remove('bi-bell');
                                campana.classList.add('bi-bell-fill');
                                campana.style.color = '#ff4444';
                                campana.style.animation = 'ring 2s ease-in-out infinite';
                            } else {
                                // Campana normal
                                campana.classList.remove('bi-bell-fill');
                                campana.classList.add('bi-bell');
                                campana.style.color = '';
                                campana.style.animation = '';
                            }
                        }
                    })
                    .catch(error => console.error('Error al verificar notificaciones:', error));
            }
        });
    </script>

    <!-- Bootstrap JS Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.8/dist/umd/popper.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.min.js"></script>
</body>

</html>