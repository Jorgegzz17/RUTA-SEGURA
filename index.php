<?php
/**
 * Rutas Seguras - CBTis 258 General Escobedo
 * Página principal de la aplicación.
 */
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rutas Seguras - CBTis 258 General Escobedo</title>

    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Leaflet CSS & JS -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">

    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    colors: {
                        cbtis: {
                            wine: '#6b1426',
                            gold: '#d4af37',
                            navy: '#1e293b',
                            safe: '#10b981',
                            warning: '#f59e0b',
                            danger: '#ef4444'
                        }
                    },
                    fontFamily: { sans: ['Inter', 'sans-serif'] }
                }
            }
        }
    </script>
    <link rel="stylesheet" href="assets/css/style.css">
</head>

<body class="bg-slate-100 dark:bg-slate-950 font-sans text-slate-800 dark:text-slate-200 antialiased flex flex-col min-h-screen transition-colors">

    <!-- ======================= HEADER ======================= -->
    <header class="bg-white/90 dark:bg-slate-900/90 backdrop-blur sticky top-0 z-[600] shadow-sm border-b border-slate-200 dark:border-slate-800">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex items-center justify-between h-16">
                <div class="flex items-center space-x-3">
                    <div class="w-10 h-10 rounded-xl bg-cbtis-wine flex items-center justify-center font-bold text-lg text-white border border-cbtis-gold shadow-sm">258</div>
                    <div>
                        <h1 class="text-lg font-bold leading-none text-slate-900 dark:text-slate-100">Rutas Seguras</h1>
                        <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">CBTis 258 • Gral. Escobedo, N.L.</p>
                    </div>
                </div>

                <div class="flex items-center space-x-2">
                    <!-- Dark mode -->
                    <button onclick="toggleDarkMode()" title="Cambiar tema"
                        class="w-9 h-9 rounded-full flex items-center justify-center text-slate-500 dark:text-amber-300 hover:bg-slate-100 dark:hover:bg-slate-800 transition">
                        <i id="theme-icon" class="fa-solid fa-moon"></i>
                    </button>
                    <!-- SOS -->
                    <button onclick="toggleSOSModal()" class="sos-pulse bg-red-600 hover:bg-red-700 text-white px-3.5 py-1.5 rounded-full font-bold text-xs sm:text-sm flex items-center space-x-2 transition">
                        <i class="fa-solid fa-triangle-exclamation"></i>
                        <span>ALERTA SOS</span>
                    </button>
                </div>
            </div>
        </div>
    </header>

    <!-- ======================= MAIN ======================= -->
    <main class="flex-1 flex flex-col md:flex-row relative max-w-7xl w-full mx-auto md:overflow-hidden">

        <!-- ============ SIDEBAR ============ -->
        <aside id="sidebar" class="w-full md:w-96 bg-white dark:bg-slate-900 border-r border-slate-200 dark:border-slate-800 flex flex-col z-[550] md:h-[calc(100vh-64px)] overflow-y-auto custom-scrollbar order-2 md:order-1">

            <!-- Handle móvil -->
            <button id="sidebar-handle" onclick="toggleSidebar()" class="w-full py-3 items-center justify-center text-slate-400">
                <i class="fa-solid fa-chevron-up" id="sidebar-handle-icon"></i>
                <span class="ml-2 text-xs font-semibold">Panel de control</span>
            </button>

            <!-- Turno -->
            <div class="p-4 border-b border-slate-100 dark:border-slate-800">
                <label class="block text-xs font-semibold text-slate-400 uppercase tracking-wider mb-2">Turno &amp; Iluminación</label>
                <div class="grid grid-cols-2 gap-2 p-1 bg-slate-100 dark:bg-slate-800 rounded-lg">
                    <button id="btn-matutino" onclick="setShift('matutino')" class="py-2 px-3 rounded-md text-xs font-semibold flex items-center justify-center space-x-2 transition bg-amber-500 text-slate-950 shadow">
                        <i class="fa-solid fa-sun"></i><span>Matutino</span>
                    </button>
                    <button id="btn-vespertino" onclick="setShift('vespertino')" class="py-2 px-3 rounded-md text-xs font-semibold flex items-center justify-center space-x-2 transition text-slate-500 dark:text-slate-300 hover:text-slate-800 dark:hover:text-white">
                        <i class="fa-solid fa-moon"></i><span>Vespertino</span>
                    </button>
                </div>
            </div>

            <!-- Estadísticas -->
            <div class="px-4 py-3 border-b border-slate-100 dark:border-slate-800 grid grid-cols-3 gap-2 text-center" id="stats-bar">
                <div class="bg-slate-50 dark:bg-slate-800/60 rounded-xl py-2">
                    <p class="text-lg font-extrabold text-slate-800 dark:text-slate-100" id="stat-total">–</p>
                    <p class="text-[10px] text-slate-500 uppercase font-semibold">Reportes</p>
                </div>
                <div class="bg-amber-50 dark:bg-amber-500/10 rounded-xl py-2">
                    <p class="text-lg font-extrabold text-amber-600" id="stat-activos">–</p>
                    <p class="text-[10px] text-amber-600/80 uppercase font-semibold">Activos</p>
                </div>
                <div class="bg-emerald-50 dark:bg-emerald-500/10 rounded-xl py-2">
                    <p class="text-lg font-extrabold text-emerald-600" id="stat-resueltos">–</p>
                    <p class="text-[10px] text-emerald-600/80 uppercase font-semibold">Resueltos</p>
                </div>
            </div>

            <!-- Transporte -->
            <div class="p-4 border-b border-slate-100 dark:border-slate-800">
                <label class="block text-xs font-semibold text-slate-400 uppercase tracking-wider mb-2">Medio de Transporte</label>
                <div class="grid grid-cols-3 gap-1 bg-slate-100 dark:bg-slate-800 p-1 rounded-xl text-xs font-medium">
                    <button onclick="filterTransport('all')" id="tab-all" class="transport-tab py-2 rounded-lg text-center bg-white dark:bg-slate-700 shadow-sm text-slate-800 dark:text-slate-100 font-semibold">Todos</button>
                    <button onclick="filterTransport('walk')" id="tab-walk" class="transport-tab py-2 rounded-lg text-center text-slate-500 dark:text-slate-300 hover:bg-slate-200 dark:hover:bg-slate-700">
                        <i class="fa-solid fa-person-walking mr-1"></i>Peatonal
                    </button>
                    <button onclick="filterTransport('bus')" id="tab-bus" class="transport-tab py-2 rounded-lg text-center text-slate-500 dark:text-slate-300 hover:bg-slate-200 dark:hover:bg-slate-700">
                        <i class="fa-solid fa-bus mr-1"></i>Camión
                    </button>
                </div>
            </div>

            <!-- Capas -->
            <div class="p-4 border-b border-slate-100 dark:border-slate-800">
                <h3 class="text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider mb-3">Capas del Mapa</h3>
                <div class="space-y-2 text-xs">
                    <label class="flex items-center justify-between p-2 rounded-lg bg-slate-50 dark:bg-slate-800/60 border border-slate-200 dark:border-slate-700 cursor-pointer hover:border-emerald-400 transition">
                        <div class="flex items-center space-x-2.5">
                            <span class="w-3 h-3 rounded-full bg-emerald-500 inline-block"></span>
                            <span class="font-medium">Rutas Seguras</span>
                        </div>
                        <input type="checkbox" id="layer-routes" checked onchange="toggleMapLayer('routes')" class="rounded text-emerald-600 focus:ring-emerald-500 h-4 w-4">
                    </label>
                    <label class="flex items-center justify-between p-2 rounded-lg bg-slate-50 dark:bg-slate-800/60 border border-slate-200 dark:border-slate-700 cursor-pointer hover:border-blue-400 transition">
                        <div class="flex items-center space-x-2.5">
                            <i class="fa-solid fa-store text-blue-600"></i>
                            <span class="font-medium">Puntos Seguros</span>
                        </div>
                        <input type="checkbox" id="layer-safe-points" checked onchange="toggleMapLayer('safePoints')" class="rounded text-blue-600 focus:ring-blue-500 h-4 w-4">
                    </label>
                    <label class="flex items-center justify-between p-2 rounded-lg bg-slate-50 dark:bg-slate-800/60 border border-slate-200 dark:border-slate-700 cursor-pointer hover:border-amber-400 transition">
                        <div class="flex items-center space-x-2.5">
                            <i class="fa-solid fa-triangle-exclamation text-amber-500"></i>
                            <span class="font-medium">Reportes</span>
                        </div>
                        <input type="checkbox" id="layer-reports" checked onchange="toggleMapLayer('reports')" class="rounded text-amber-600 focus:ring-amber-500 h-4 w-4">
                    </label>
                </div>
            </div>

            <!-- Reportes -->
            <div class="p-4 flex-1 flex flex-col min-h-[300px]">
                <div class="flex items-center justify-between mb-3">
                    <h3 class="text-sm font-bold flex items-center space-x-2">
                        <i class="fa-solid fa-bullhorn text-cbtis-wine"></i>
                        <span>Reportes de la Comunidad</span>
                    </h3>
                    <button onclick="openReportModal()" class="text-xs bg-cbtis-wine hover:bg-red-900 text-white px-2.5 py-1.5 rounded-lg font-semibold flex items-center space-x-1 shadow-sm transition">
                        <i class="fa-solid fa-plus"></i><span>Reportar</span>
                    </button>
                </div>

                <!-- Buscador y filtros -->
                <div class="space-y-2 mb-3">
                    <div class="relative">
                        <i class="fa-solid fa-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-xs"></i>
                        <input type="text" id="search-input" oninput="onSearchInput(this.value)" placeholder="Buscar reporte..."
                            class="w-full bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl pl-8 pr-3 py-2 text-xs outline-none focus:ring-2 focus:ring-cbtis-wine">
                    </div>
                    <div class="flex space-x-1.5 overflow-x-auto pb-1" id="type-filters">
                        <button data-type="" onclick="setTypeFilter('')" class="type-filter-btn shrink-0 text-[10px] font-semibold px-2.5 py-1 rounded-full bg-cbtis-wine text-white">Todos</button>
                        <button data-type="Luz Fundida / Zona Oscura" onclick="setTypeFilter('Luz Fundida / Zona Oscura')" class="type-filter-btn shrink-0 text-[10px] font-semibold px-2.5 py-1 rounded-full bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300">💡 Luz</button>
                        <button data-type="Presencia Escasa de Vía" onclick="setTypeFilter('Presencia Escasa de Vía')" class="type-filter-btn shrink-0 text-[10px] font-semibold px-2.5 py-1 rounded-full bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300">👮 Vial</button>
                        <button data-type="Zona Solitaria / Sospechosa" onclick="setTypeFilter('Zona Solitaria / Sospechosa')" class="type-filter-btn shrink-0 text-[10px] font-semibold px-2.5 py-1 rounded-full bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300">👁️ Solitaria</button>
                        <button data-type="Tráfico / Banqueta Obstruida" onclick="setTypeFilter('Tráfico / Banqueta Obstruida')" class="type-filter-btn shrink-0 text-[10px] font-semibold px-2.5 py-1 rounded-full bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300">⚠️ Tráfico</button>
                        <button data-type="Acoso / Robo" onclick="setTypeFilter('Acoso / Robo')" class="type-filter-btn shrink-0 text-[10px] font-semibold px-2.5 py-1 rounded-full bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300">🚨 Acoso/Robo</button>
                    </div>
                    <label class="flex items-center space-x-2 text-[11px] text-slate-500 dark:text-slate-400 cursor-pointer select-none">
                        <input type="checkbox" id="show-resolved" onchange="loadReports()" class="rounded text-cbtis-wine focus:ring-cbtis-wine h-3.5 w-3.5">
                        <span>Mostrar reportes resueltos</span>
                    </label>
                </div>

                <div id="reports-container" class="space-y-2.5 flex-1 overflow-y-auto pr-1">
                    <!-- Dinámico -->
                </div>
            </div>

            <!-- Contactos -->
            <div class="p-3 bg-slate-50 dark:bg-slate-800/60 border-t border-slate-200 dark:border-slate-700">
                <p class="text-[10px] text-slate-500 uppercase font-bold mb-2 tracking-wider text-center">Contactos de Emergencia</p>
                <div class="grid grid-cols-2 gap-2 text-xs">
                    <a href="tel:8182869200" class="flex items-center justify-center space-x-1.5 py-2 px-2 bg-white dark:bg-slate-800 border border-slate-300 dark:border-slate-600 rounded-lg font-semibold hover:bg-slate-50 dark:hover:bg-slate-700 transition">
                        <i class="fa-solid fa-shield-halved text-blue-600"></i><span>C4 Escobedo</span>
                    </a>
                    <a href="tel:8183971666" class="flex items-center justify-center space-x-1.5 py-2 px-2 bg-white dark:bg-slate-800 border border-slate-300 dark:border-slate-600 rounded-lg font-semibold hover:bg-slate-50 dark:hover:bg-slate-700 transition">
                        <i class="fa-solid fa-school text-cbtis-wine"></i><span>CBTis 258</span>
                    </a>
                </div>
                <a href="admin/login.php" class="mt-2 block text-center text-[10px] text-slate-400 hover:text-slate-600 dark:hover:text-slate-200">
                    Acceso de administración
                </a>
            </div>
        </aside>

        <!-- ============ MAPA ============ -->
        <section class="flex-1 flex flex-col relative order-1 md:order-2">
            <div id="map"></div>

            <!-- Banner nocturno -->
            <div class="absolute top-4 left-4 right-4 z-[500] pointer-events-none flex justify-center">
                <div id="night-warning-banner" class="hidden bg-slate-900/90 backdrop-blur text-white px-4 py-2.5 rounded-2xl shadow-xl text-xs sm:text-sm border border-slate-700 pointer-events-auto flex items-center space-x-3 max-w-lg">
                    <i class="fa-solid fa-moon text-amber-400 text-lg"></i>
                    <div>
                        <p class="font-bold text-amber-400">Modo Salida Nocturna Activo</p>
                        <p class="text-slate-300 text-xs">Usa la avenida iluminada Raúl Salinas. Camina siempre en grupos hacia las paradas de Transmetro.</p>
                    </div>
                </div>
            </div>

            <!-- Botón mi ubicación -->
            <button onclick="locateMe()" title="Mi ubicación"
                class="absolute bottom-24 right-4 z-[500] w-11 h-11 bg-white dark:bg-slate-800 rounded-full shadow-lg border border-slate-200 dark:border-slate-700 flex items-center justify-center text-slate-600 dark:text-slate-200 hover:text-cbtis-wine transition">
                <i class="fa-solid fa-location-crosshairs text-lg"></i>
            </button>

            <!-- Recomendaciones -->
            <div class="bg-white dark:bg-slate-900 border-t border-slate-200 dark:border-slate-800 p-4">
                <div class="flex items-center justify-between mb-3">
                    <h2 class="text-sm font-bold flex items-center space-x-2">
                        <i class="fa-solid fa-user-shield text-emerald-600"></i>
                        <span>Recomendaciones de Seguridad</span>
                    </h2>
                    <span class="text-xs text-slate-400">Plantel CBTis 258</span>
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                    <div class="bg-slate-50 dark:bg-slate-800/60 p-3 rounded-xl border border-slate-200 dark:border-slate-700">
                        <div class="flex items-center space-x-2 font-semibold text-xs mb-1">
                            <i class="fa-solid fa-people-group text-blue-600"></i><span>Caminata en Grupo</span>
                        </div>
                        <p class="text-xs text-slate-500 dark:text-slate-400">Procura caminar acompañado en el tramo de Plinio D. Ordóñez hacia Raúl Salinas, especialmente al salir a las 20:00 hrs.</p>
                    </div>
                    <div class="bg-slate-50 dark:bg-slate-800/60 p-3 rounded-xl border border-slate-200 dark:border-slate-700">
                        <div class="flex items-center space-x-2 font-semibold text-xs mb-1">
                            <i class="fa-solid fa-mobile-screen-button text-amber-600"></i><span>Uso Discreto del Teléfono</span>
                        </div>
                        <p class="text-xs text-slate-500 dark:text-slate-400">Evita distracción con audífonos o teléfono visible mientras caminas hacia las paradas de la Ruta 207 o Ruta 1.</p>
                    </div>
                    <div class="bg-slate-50 dark:bg-slate-800/60 p-3 rounded-xl border border-slate-200 dark:border-slate-700">
                        <div class="flex items-center space-x-2 font-semibold text-xs mb-1">
                            <i class="fa-solid fa-store text-emerald-600"></i><span>Red de Comercios</span>
                        </div>
                        <p class="text-xs text-slate-500 dark:text-slate-400">Ante cualquier eventualidad, ingresa a las tiendas marcadas como Punto Seguro sobre Plinio D. Ordóñez.</p>
                    </div>
                </div>
            </div>
        </section>
    </main>

    <!-- ======================= MODAL: REPORTAR ======================= -->
    <div id="report-modal" class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm z-[1000] hidden flex items-center justify-center p-4">
        <div class="bg-white dark:bg-slate-900 w-full max-w-md rounded-2xl shadow-2xl overflow-hidden border border-slate-100 dark:border-slate-700 animate-fade-in-up max-h-[92vh] overflow-y-auto custom-scrollbar">
            <div class="bg-slate-900 dark:bg-slate-800 text-white p-4 flex items-center justify-between sticky top-0">
                <div class="flex items-center space-x-2">
                    <i class="fa-solid fa-triangle-exclamation text-amber-400"></i>
                    <h3 class="font-bold text-sm">Reportar Inseguridad o Riesgo</h3>
                </div>
                <button onclick="closeReportModal()" class="text-slate-400 hover:text-white transition"><i class="fa-solid fa-xmark text-lg"></i></button>
            </div>

            <form id="report-form" onsubmit="handleReportSubmit(event)" class="p-5 space-y-4">
                <div>
                    <label class="block text-xs font-bold text-slate-600 dark:text-slate-300 uppercase mb-1">Tipo de Reporte</label>
                    <select id="report-type" class="w-full bg-slate-50 dark:bg-slate-800 border border-slate-300 dark:border-slate-600 rounded-xl p-2.5 text-xs focus:ring-2 focus:ring-cbtis-wine outline-none">
                        <option value="Luz Fundida / Zona Oscura">💡 Luminaria Fundida / Falta de Luz</option>
                        <option value="Presencia Escasa de Vía">👮 Necesidad de Presencia Vial / Policía</option>
                        <option value="Zona Solitaria / Sospechosa">👁️ Zona Solitaria o Terreno Baldío</option>
                        <option value="Tráfico / Banqueta Obstruida">⚠️ Banqueta Obstruida / Tráfico Pesado</option>
                        <option value="Acoso / Robo">🚨 Acoso o Intento de Robo</option>
                        <option value="Otro">📌 Otro</option>
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-600 dark:text-slate-300 uppercase mb-1">Nivel de Riesgo</label>
                    <div class="grid grid-cols-3 gap-2" id="severity-group">
                        <button type="button" data-severity="baja" onclick="setSeverity('baja')" class="severity-btn py-2 rounded-xl text-xs font-bold border-2 border-slate-200 dark:border-slate-700 text-slate-500 transition">Baja</button>
                        <button type="button" data-severity="media" onclick="setSeverity('media')" class="severity-btn py-2 rounded-xl text-xs font-bold border-2 border-amber-400 bg-amber-50 dark:bg-amber-500/10 text-amber-600 transition">Media</button>
                        <button type="button" data-severity="alta" onclick="setSeverity('alta')" class="severity-btn py-2 rounded-xl text-xs font-bold border-2 border-slate-200 dark:border-slate-700 text-slate-500 transition">Alta</button>
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-600 dark:text-slate-300 uppercase mb-1">Ubicación / Referencia</label>
                    <input type="text" id="report-location" placeholder="Ej: Esquina Plinio D. Ordóñez y Topo Chico" required minlength="3" maxlength="255"
                        class="w-full bg-slate-50 dark:bg-slate-800 border border-slate-300 dark:border-slate-600 rounded-xl p-2.5 text-xs focus:ring-2 focus:ring-cbtis-wine outline-none">
                    <div class="flex items-center justify-between mt-2">
                        <p id="coords-label" class="text-[10px] text-slate-400">Ubicación requerida: selecciona GPS o marca el mapa</p>
                        <div class="flex space-x-1.5">
                            <button type="button" onclick="useMyLocation()" class="text-[10px] font-bold px-2 py-1 rounded-lg bg-blue-50 dark:bg-blue-500/10 text-blue-600 hover:bg-blue-100 transition">
                                <i class="fa-solid fa-location-crosshairs mr-1"></i>GPS
                            </button>
                            <button type="button" onclick="startMapPick()" class="text-[10px] font-bold px-2 py-1 rounded-lg bg-cbtis-wine/10 text-cbtis-wine hover:bg-cbtis-wine/20 transition">
                                <i class="fa-solid fa-map-pin mr-1"></i>Marcar en mapa
                            </button>
                        </div>
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-600 dark:text-slate-300 uppercase mb-1">Descripción breve</label>
                    <textarea id="report-description" rows="3" minlength="5" maxlength="1000" placeholder="Describe brevemente la situación para alertar a otros compañeros..." required
                        class="w-full bg-slate-50 dark:bg-slate-800 border border-slate-300 dark:border-slate-600 rounded-xl p-2.5 text-xs focus:ring-2 focus:ring-cbtis-wine outline-none"></textarea>
                </div>

                <div class="bg-amber-50 dark:bg-amber-500/10 border border-amber-200 dark:border-amber-500/30 rounded-xl p-3 flex items-start space-x-2">
                    <i class="fa-solid fa-circle-info text-amber-600 text-xs mt-0.5"></i>
                    <p class="text-[11px] text-amber-800 dark:text-amber-300 leading-tight">El reporte es anónimo y aparecerá en el mapa. No incluyas nombres ni datos personales. Si hay peligro inmediato, llama al 911.</p>
                </div>

                <div class="flex space-x-2 pt-1">
                    <button type="button" onclick="closeReportModal()" class="w-1/2 py-2.5 bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 rounded-xl font-bold text-xs transition">Cancelar</button>
                    <button type="submit" id="submit-btn" class="w-1/2 py-2.5 bg-cbtis-wine hover:bg-red-900 text-white rounded-xl font-bold text-xs shadow-md transition">Publicar Reporte</button>
                </div>
            </form>
        </div>
    </div>

    <!-- ======================= MODAL: SOS ======================= -->
    <div id="sos-modal" class="fixed inset-0 bg-red-950/80 backdrop-blur-md z-[1000] hidden flex items-center justify-center p-4">
        <div class="bg-white dark:bg-slate-900 w-full max-w-sm rounded-3xl shadow-2xl p-6 text-center border-2 border-red-500 animate-fade-in-up">
            <div class="w-16 h-16 bg-red-100 text-red-600 rounded-full flex items-center justify-center mx-auto mb-4 text-2xl border border-red-200">
                <i class="fa-solid fa-phone-flip animate-bounce"></i>
            </div>
            <h3 class="text-xl font-black text-slate-900 dark:text-white mb-1">Centro de Emergencias</h3>
            <p class="text-xs text-slate-500 dark:text-slate-400 mb-6">Líneas de respuesta inmediata para General Escobedo:</p>

            <div class="space-y-3">
                <a href="tel:911" class="w-full bg-red-600 hover:bg-red-700 text-white py-3 px-4 rounded-xl font-bold text-sm flex items-center justify-between shadow-lg shadow-red-200 transition">
                    <span class="flex items-center space-x-2"><i class="fa-solid fa-shield-halved"></i><span>Emergencias Nacionales</span></span>
                    <span class="bg-red-800 px-2 py-0.5 rounded text-xs">911</span>
                </a>
                <a href="tel:8182869200" class="w-full bg-slate-900 hover:bg-slate-800 text-white py-3 px-4 rounded-xl font-bold text-sm flex items-center justify-between shadow-md transition">
                    <span class="flex items-center space-x-2"><i class="fa-solid fa-building-shield"></i><span>C4 Escobedo (Policía)</span></span>
                    <span class="text-xs text-slate-400">81 8286 9200</span>
                </a>
                <a href="tel:8183971666" class="w-full bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-700 text-slate-800 dark:text-slate-100 py-3 px-4 rounded-xl font-bold text-sm flex items-center justify-between transition">
                    <span class="flex items-center space-x-2"><i class="fa-solid fa-school"></i><span>Dirección CBTis 258</span></span>
                    <span class="text-xs text-slate-500">Plantel</span>
                </a>
            </div>

            <button onclick="toggleSOSModal()" class="mt-6 text-xs text-slate-400 font-semibold hover:text-slate-600">Cerrar ventana</button>
        </div>
    </div>

    <!-- Toasts -->
    <div id="toast-container" class="fixed bottom-4 right-4 z-[1200] space-y-2 w-72"></div>

    <script src="assets/js/app.js"></script>
</body>
</html>
