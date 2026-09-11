<div class="space-y-6">
    <div class="flex justify-between items-center">
        <h3 class="text-2xl font-bold bg-clip-text text-transparent bg-gradient-to-r from-violet-600 to-indigo-600">
            Resumen de la Campaña
        </h3>
        <button wire:click="refreshDashboard" wire:loading.attr="disabled" class="inline-flex items-center px-4 py-2 bg-white border border-gray-300 rounded-md font-semibold text-xs text-gray-700 uppercase tracking-widest shadow-sm hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 disabled:opacity-50 transition ease-in-out duration-150">
            <svg wire:loading.class="animate-spin" wire:target="refreshDashboard" class="w-4 h-4 mr-2 -ml-1 text-gray-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182m0-4.991v4.99" />
            </svg>
            <span>Actualizar</span>
        </button>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
        <!-- Hoy -->
        <div class="bg-gradient-to-br from-indigo-50 to-violet-50 p-6 rounded-2xl border border-indigo-100 shadow-sm transition-transform hover:scale-[1.02]">
            <div class="text-sm font-bold text-indigo-700 uppercase tracking-wide">Evidencias Hoy</div>
            <div class="mt-2 text-5xl font-black text-indigo-600">{{ $todayCount }}</div>
        </div>

        <!-- Semana -->
        <div class="bg-gradient-to-br from-emerald-50 to-teal-50 p-6 rounded-2xl border border-emerald-100 shadow-sm transition-transform hover:scale-[1.02]">
            <div class="text-sm font-bold text-emerald-700 uppercase tracking-wide">Evidencias Semana</div>
            <div class="mt-2 text-5xl font-black text-emerald-600">{{ $weekCount }}</div>
        </div>

        <!-- Mes -->
        <div class="bg-gradient-to-br from-amber-50 to-orange-50 p-6 rounded-2xl border border-amber-100 shadow-sm transition-transform hover:scale-[1.02]">
            <div class="text-sm font-bold text-amber-700 uppercase tracking-wide">Evidencias Mes</div>
            <div class="mt-2 text-5xl font-black text-amber-600">{{ $monthCount }}</div>
        </div>

        <!-- Usuarios -->
        <div class="bg-gradient-to-br from-rose-50 to-pink-50 p-6 rounded-2xl border border-rose-100 shadow-sm transition-transform hover:scale-[1.02]">
            <div class="text-sm font-bold text-rose-700 uppercase tracking-wide">Equipo Activo</div>
            <div class="mt-2 text-5xl font-black text-rose-600">{{ $usersCount }}</div>
        </div>
    </div>

    <div class="flex justify-between items-center mt-10">
        <h3 class="text-2xl font-bold bg-clip-text text-transparent bg-gradient-to-r from-blue-600 to-cyan-600">
            Resumen de Gestión de Cuentas
        </h3>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
        <!-- Cuentas Hoy -->
        <div class="bg-gradient-to-br from-blue-50 to-cyan-50 p-6 rounded-2xl border border-blue-100 shadow-sm transition-transform hover:scale-[1.02]">
            <div class="text-sm font-bold text-blue-700 uppercase tracking-wide">Cuentas Hoy</div>
            <div class="mt-2 text-5xl font-black text-blue-600">{{ $accountsToday }}</div>
        </div>

        <!-- Cuentas Semana -->
        <div class="bg-gradient-to-br from-cyan-50 to-sky-50 p-6 rounded-2xl border border-cyan-100 shadow-sm transition-transform hover:scale-[1.02]">
            <div class="text-sm font-bold text-cyan-700 uppercase tracking-wide">Cuentas Semana</div>
            <div class="mt-2 text-5xl font-black text-cyan-600">{{ $accountsWeek }}</div>
        </div>

        <!-- Cuentas Mes -->
        <div class="bg-gradient-to-br from-sky-50 to-blue-50 p-6 rounded-2xl border border-sky-100 shadow-sm transition-transform hover:scale-[1.02]">
            <div class="text-sm font-bold text-sky-700 uppercase tracking-wide">Cuentas Mes</div>
            <div class="mt-2 text-5xl font-black text-sky-600">{{ $accountsMonth }}</div>
        </div>

        <!-- Gestores Activos -->
        <div class="bg-gradient-to-br from-slate-50 to-gray-50 p-6 rounded-2xl border border-slate-100 shadow-sm transition-transform hover:scale-[1.02]">
            <div class="text-sm font-bold text-slate-700 uppercase tracking-wide">Gestores de Cuentas</div>
            <div class="mt-2 text-5xl font-black text-slate-600">{{ $cuentasUsersCount }}</div>
        </div>

        <!-- Perfiles Hoy -->
        <div class="bg-gradient-to-br from-emerald-50 to-teal-50 p-6 rounded-2xl border border-emerald-100 shadow-sm transition-transform hover:scale-[1.02]">
            <div class="text-sm font-bold text-emerald-700 uppercase tracking-wide">Perfiles Hoy</div>
            <div class="mt-2 text-5xl font-black text-emerald-600">{{ $profilesToday }}</div>
        </div>
        
        <!-- Perfiles Semana -->
        <div class="bg-gradient-to-br from-teal-50 to-cyan-50 p-6 rounded-2xl border border-teal-100 shadow-sm transition-transform hover:scale-[1.02]">
            <div class="text-sm font-bold text-teal-700 uppercase tracking-wide">Perfiles Semana</div>
            <div class="mt-2 text-5xl font-black text-teal-600">{{ $profilesWeek }}</div>
        </div>
        
        <!-- Perfiles Mes -->
        <div class="bg-gradient-to-br from-cyan-50 to-sky-50 p-6 rounded-2xl border border-cyan-100 shadow-sm transition-transform hover:scale-[1.02]">
            <div class="text-sm font-bold text-cyan-700 uppercase tracking-wide">Perfiles Mes</div>
            <div class="mt-2 text-5xl font-black text-cyan-600">{{ $profilesMonth }}</div>
        </div>
    </div>

    <!-- Charts Section -->
    <div class="mt-12">
        <div class="flex flex-col sm:flex-row justify-between items-center mb-6 gap-4">
            <h3 class="text-xl font-bold text-slate-800">Análisis Global (Evidencias y Cuentas)</h3>

            <div class="flex items-center gap-2 bg-white p-2 rounded-lg border border-gray-200 shadow-sm">
                <span class="text-sm font-medium text-gray-500">Filtrar por fecha:</span>
                <input type="date" wire:model.live="filterDateFrom" value="{{ $filterDateFrom }}" class="text-sm border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md">
                <span class="text-gray-400">-</span>
                <input type="date" wire:model.live="filterDateTo" value="{{ $filterDateTo }}" class="text-sm border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md">
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6"
             x-data="dashboardCharts()"
             @update-charts.window="updateCharts($event.detail)">

            <!-- EVIDENCES CHARTS -->
            <div class="bg-white p-6 rounded-2xl border border-gray-100 shadow-sm">
                <h4 class="text-sm font-bold text-indigo-500 uppercase mb-4">Crecimiento de Evidencias (Línea)</h4>
                <div class="relative h-64 w-full" wire:ignore>
                    <canvas id="chartGrowth"></canvas>
                </div>
            </div>

            <div class="bg-white p-6 rounded-2xl border border-gray-100 shadow-sm">
                <h4 class="text-sm font-bold text-indigo-500 uppercase mb-4">Top 5 Creadores de Evidencias</h4>
                <div class="relative h-64 w-full" wire:ignore>
                    <canvas id="chartUsers"></canvas>
                </div>
            </div>

            <div class="bg-white p-6 rounded-2xl border border-gray-100 shadow-sm">
                <h4 class="text-sm font-bold text-indigo-500 uppercase mb-4">Distribución por Tipo de Evidencia</h4>
                <div class="relative h-64 w-full flex justify-center" wire:ignore>
                    <canvas id="chartTypes"></canvas>
                </div>
            </div>

            <div class="bg-white p-6 rounded-2xl border border-gray-100 shadow-sm">
                <h4 class="text-sm font-bold text-indigo-500 uppercase mb-4">Evidencias por Red Social</h4>
                <div class="relative h-64 w-full flex justify-center" wire:ignore>
                    <canvas id="chartNetworks"></canvas>
                </div>
            </div>

            <!-- ACCOUNTS CHARTS -->
            <div class="bg-white p-6 rounded-2xl border border-gray-100 shadow-sm">
                <h4 class="text-sm font-bold text-cyan-600 uppercase mb-4">Crecimiento de Cuentas (Línea)</h4>
                <div class="relative h-64 w-full" wire:ignore>
                    <canvas id="chartAccountGrowth"></canvas>
                </div>
            </div>

            <div class="bg-white p-6 rounded-2xl border border-gray-100 shadow-sm">
                <h4 class="text-sm font-bold text-cyan-600 uppercase mb-4">Top 5 Creadores de Cuentas</h4>
                <div class="relative h-64 w-full" wire:ignore>
                    <canvas id="chartTopAccountCreators"></canvas>
                </div>
            </div>

            <div class="bg-white p-6 rounded-2xl border border-gray-100 shadow-sm lg:col-span-2">
                <h4 class="text-sm font-bold text-cyan-600 uppercase mb-4">Total Cuentas por Gestor Asignado</h4>
                <div class="relative h-64 w-full" wire:ignore>
                    <canvas id="chartAccountsByGestor"></canvas>
                </div>
            </div>

        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/chart.js" data-navigate-once></script>
    @script
    <script>
        Alpine.data('dashboardCharts', () => {
            let charts = {}; // Private variable, prevents Alpine from making Chart.js instances reactive
            return {
                init() {
                    const renderCharts = () => {
                        if (typeof Chart === 'undefined') {
                            setTimeout(renderCharts, 50);
                            return;
                        }

                        const dDate = Object.values(this.$wire.evidencesByDate || {});
                        const dUser = Object.values(this.$wire.evidencesByUser || {});
                        const dType = Object.values(this.$wire.evidencesByType || {});
                        const dNet = Object.values(this.$wire.evidencesByNetwork || {});

                        const dAccDate = Object.values(this.$wire.accountsByDate || {});
                        const dAccGestor = Object.values(this.$wire.accountsByGestor || {});
                        const dTopCreators = Object.values(this.$wire.topAccountCreators || {});

                        // Chart 1: Growth (Line)
                        charts.growth = new Chart(document.getElementById('chartGrowth'), {
                            type: 'line',
                            data: {
                                labels: dDate.map(d => d.date),
                                datasets: [{
                                    label: 'Evidencias',
                                    data: dDate.map(d => d.count),
                                    borderColor: '#4f46e5',
                                    backgroundColor: 'rgba(79, 70, 229, 0.1)',
                                    borderWidth: 3,
                                    fill: true,
                                    tension: 0.4
                                }]
                            },
                            options: { maintainAspectRatio: false, plugins: { legend: { display: false } } }
                        });

                        // Chart 2: Top Users (Bar)
                        charts.users = new Chart(document.getElementById('chartUsers'), {
                            type: 'bar',
                            data: {
                                labels: dUser.map(d => d.name),
                                datasets: [{
                                    label: 'Total',
                                    data: dUser.map(d => d.count),
                                    backgroundColor: '#8b5cf6',
                                    borderRadius: 4
                                }]
                            },
                            options: { maintainAspectRatio: false, plugins: { legend: { display: false } } }
                        });

                        // Chart 3: By Type (Doughnut)
                        charts.types = new Chart(document.getElementById('chartTypes'), {
                            type: 'doughnut',
                            data: {
                                labels: dType.map(d => d.name),
                                datasets: [{
                                    data: dType.map(d => d.count),
                                    backgroundColor: ['#8b5cf6', '#10b981', '#f59e0b', '#ef4444', '#3b82f6', '#ec4899']
                                }]
                            },
                            options: { maintainAspectRatio: false }
                        });

                        // Chart 4: By Network (Pie)
                        charts.networks = new Chart(document.getElementById('chartNetworks'), {
                            type: 'pie',
                            data: {
                                labels: dNet.map(d => d.name),
                                datasets: [{
                                    data: dNet.map(d => d.count),
                                    backgroundColor: ['#3b5998', '#E1306C', '#1DA1F2', '#000000', '#0077b5', '#ff0000']
                                }]
                            },
                            options: { maintainAspectRatio: false }
                        });

                        // Chart 5: Account Growth (Line)
                        charts.accountGrowth = new Chart(document.getElementById('chartAccountGrowth'), {
                            type: 'line',
                            data: {
                                labels: dAccDate.map(d => d.date),
                                datasets: [{
                                    label: 'Cuentas Creadas',
                                    data: dAccDate.map(d => d.count),
                                    borderColor: '#0891b2',
                                    backgroundColor: 'rgba(8, 145, 178, 0.1)',
                                    borderWidth: 3,
                                    fill: true,
                                    tension: 0.4
                                }]
                            },
                            options: { maintainAspectRatio: false, plugins: { legend: { display: false } } }
                        });

                        // Chart 6: Top Account Creators (Bar)
                        charts.topCreators = new Chart(document.getElementById('chartTopAccountCreators'), {
                            type: 'bar',
                            data: {
                                labels: dTopCreators.map(d => d.name),
                                datasets: [{
                                    label: 'Total Creadas',
                                    data: dTopCreators.map(d => d.count),
                                    backgroundColor: '#0ea5e9',
                                    borderRadius: 4
                                }]
                            },
                            options: { maintainAspectRatio: false, plugins: { legend: { display: false } } }
                        });

                        // Chart 7: Accounts by Gestor (Bar)
                        charts.accByGestor = new Chart(document.getElementById('chartAccountsByGestor'), {
                            type: 'bar',
                            data: {
                                labels: dAccGestor.map(d => d.name),
                                datasets: [{
                                    label: 'Cuentas Asignadas',
                                    data: dAccGestor.map(d => d.count),
                                    backgroundColor: '#0284c7',
                                    borderRadius: 4
                                }]
                            },
                            options: { maintainAspectRatio: false, plugins: { legend: { display: false } } }
                        });
                    };

                    renderCharts();
                },
                updateCharts(newData) {
                    const dDate = Object.values(newData.dataDate || {});
                    const dUser = Object.values(newData.dataUser || {});
                    const dType = Object.values(newData.dataType || {});
                    const dNet = Object.values(newData.dataNet || {});

                    const dAccDate = Object.values(newData.dataAccountDate || {});
                    const dAccGestor = Object.values(newData.dataAccountGestor || {});
                    const dTopCreators = Object.values(newData.dataTopCreators || {});

                    charts.growth.data.labels = dDate.map(d => d.date);
                    charts.growth.data.datasets[0].data = dDate.map(d => d.count);
                    charts.growth.update();

                    charts.users.data.labels = dUser.map(d => d.name);
                    charts.users.data.datasets[0].data = dUser.map(d => d.count);
                    charts.users.update();

                    charts.types.data.labels = dType.map(d => d.name);
                    charts.types.data.datasets[0].data = dType.map(d => d.count);
                    charts.types.update();

                    charts.networks.data.labels = dNet.map(d => d.name);
                    charts.networks.data.datasets[0].data = dNet.map(d => d.count);
                    charts.networks.update();

                    charts.accountGrowth.data.labels = dAccDate.map(d => d.date);
                    charts.accountGrowth.data.datasets[0].data = dAccDate.map(d => d.count);
                    charts.accountGrowth.update();

                    charts.topCreators.data.labels = dTopCreators.map(d => d.name);
                    charts.topCreators.data.datasets[0].data = dTopCreators.map(d => d.count);
                    charts.topCreators.update();

                    charts.accByGestor.data.labels = dAccGestor.map(d => d.name);
                    charts.accByGestor.data.datasets[0].data = dAccGestor.map(d => d.count);
                    charts.accByGestor.update();
                }
            };
        });
    </script>
    @endscript
</div>
