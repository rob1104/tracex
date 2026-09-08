<div class="space-y-6">
    <div class="flex justify-between items-center">
        <h3 class="text-2xl font-bold bg-clip-text text-transparent bg-gradient-to-r from-violet-600 to-indigo-600">
            Resumen de la Campaña
        </h3>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
        <!-- Hoy -->
        <div class="bg-gradient-to-br from-indigo-50 to-violet-50 p-6 rounded-2xl border border-indigo-100 shadow-sm transition-transform hover:scale-[1.02]">
            <div class="text-sm font-bold text-indigo-700 uppercase tracking-wide">Evidencias Hoy</div>
            <div class="mt-2 text-5xl font-black text-indigo-600">{{ $todayCount }}</div>
        </div>
        
        <!-- Semana -->
        <div class="bg-gradient-to-br from-emerald-50 to-teal-50 p-6 rounded-2xl border border-emerald-100 shadow-sm transition-transform hover:scale-[1.02]">
            <div class="text-sm font-bold text-emerald-700 uppercase tracking-wide">Esta Semana</div>
            <div class="mt-2 text-5xl font-black text-emerald-600">{{ $weekCount }}</div>
        </div>
        
        <!-- Mes -->
        <div class="bg-gradient-to-br from-amber-50 to-orange-50 p-6 rounded-2xl border border-amber-100 shadow-sm transition-transform hover:scale-[1.02]">
            <div class="text-sm font-bold text-amber-700 uppercase tracking-wide">Este Mes</div>
            <div class="mt-2 text-5xl font-black text-amber-600">{{ $monthCount }}</div>
        </div>

        <!-- Usuarios -->
        <div class="bg-gradient-to-br from-rose-50 to-pink-50 p-6 rounded-2xl border border-rose-100 shadow-sm transition-transform hover:scale-[1.02]">
            <div class="text-sm font-bold text-rose-700 uppercase tracking-wide">Equipo Activo</div>
            <div class="mt-2 text-5xl font-black text-rose-600">{{ $usersCount }}</div>
        </div>
    </div>

    <!-- Charts Section -->
    <div class="mt-8">
        <div class="flex flex-col sm:flex-row justify-between items-center mb-4 gap-4">
            <h3 class="text-xl font-bold text-slate-800">Análisis de Productividad</h3>
            
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
            
            <!-- Chart 1: Growth -->
            <div class="bg-white p-6 rounded-2xl border border-gray-100 shadow-sm">
                <h4 class="text-sm font-bold text-gray-500 uppercase mb-4">Crecimiento (Línea de Tiempo)</h4>
                <div class="relative h-64 w-full" wire:ignore>
                    <canvas id="chartGrowth"></canvas>
                </div>
            </div>

            <!-- Chart 2: Top Users -->
            <div class="bg-white p-6 rounded-2xl border border-gray-100 shadow-sm">
                <h4 class="text-sm font-bold text-gray-500 uppercase mb-4">Top 5 Colaboradores</h4>
                <div class="relative h-64 w-full" wire:ignore>
                    <canvas id="chartUsers"></canvas>
                </div>
            </div>

            <!-- Chart 3: By Type -->
            <div class="bg-white p-6 rounded-2xl border border-gray-100 shadow-sm">
                <h4 class="text-sm font-bold text-gray-500 uppercase mb-4">Distribución por Tipo</h4>
                <div class="relative h-64 w-full flex justify-center" wire:ignore>
                    <canvas id="chartTypes"></canvas>
                </div>
            </div>

            <!-- Chart 4: By Network -->
            <div class="bg-white p-6 rounded-2xl border border-gray-100 shadow-sm">
                <h4 class="text-sm font-bold text-gray-500 uppercase mb-4">Evidencias por Red Social</h4>
                <div class="relative h-64 w-full flex justify-center" wire:ignore>
                    <canvas id="chartNetworks"></canvas>
                </div>
            </div>
            
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.data('dashboardCharts', () => {
                let charts = {}; // Private variable, prevents Alpine from making Chart.js instances reactive
                return {
                    init() {
                        const dDate = Object.values(this.$wire.evidencesByDate || {});
                        const dUser = Object.values(this.$wire.evidencesByUser || {});
                        const dType = Object.values(this.$wire.evidencesByType || {});
                        const dNet = Object.values(this.$wire.evidencesByNetwork || {});

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
                                    backgroundColor: '#0ea5e9',
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
                    },
                    updateCharts(newData) {
                        const dDate = Object.values(newData.dataDate || {});
                        const dUser = Object.values(newData.dataUser || {});
                        const dType = Object.values(newData.dataType || {});
                        const dNet = Object.values(newData.dataNet || {});

                        // Update Growth
                        charts.growth.data.labels = dDate.map(d => d.date);
                        charts.growth.data.datasets[0].data = dDate.map(d => d.count);
                        charts.growth.update();

                        // Update Users
                        charts.users.data.labels = dUser.map(d => d.name);
                        charts.users.data.datasets[0].data = dUser.map(d => d.count);
                        charts.users.update();

                        // Update Types
                        charts.types.data.labels = dType.map(d => d.name);
                        charts.types.data.datasets[0].data = dType.map(d => d.count);
                        charts.types.update();

                        // Update Networks
                        charts.networks.data.labels = dNet.map(d => d.name);
                        charts.networks.data.datasets[0].data = dNet.map(d => d.count);
                        charts.networks.update();
                    }
                };
            });
        });
    </script>
</div>
