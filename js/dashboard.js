document.addEventListener('DOMContentLoaded', function () {

    if (typeof dashboardData === 'undefined') {
        console.error('Dashboard data not found');
        return;
    }

    const { comparativa, topProductos, acumulada } = dashboardData;

    // ─── 1. Comparativa de Efectividad ───────────────────────────────────────
    const canvasComp = document.getElementById('comparativaChart');
    if (canvasComp) {
        const hasData = comparativa && comparativa.labels && comparativa.labels.length > 0;
        if (!hasData) {
            canvasComp.closest('.chart-card').querySelector('.chart-body').innerHTML =
                '<div class="text-center text-muted py-4"><i class="bi bi-bar-chart-line fs-1 opacity-25"></i><p class="mt-2">Sin datos para este período</p></div>';
        } else {
            new Chart(canvasComp.getContext('2d'), {
                type: 'line',
                data: {
                    labels: comparativa.labels,
                    datasets: [
                        {
                            label: 'Período Actual',
                            data: comparativa.efectividad_actual,   // ← clave correcta
                            borderColor: 'rgba(102, 126, 234, 1)',
                            backgroundColor: 'rgba(102, 126, 234, 0.12)',
                            borderWidth: 2.5,
                            tension: 0.4,
                            fill: true,
                            pointBackgroundColor: 'rgba(102, 126, 234, 1)',
                            pointRadius: 4
                        },
                        {
                            label: 'Período Anterior',
                            data: comparativa.efectividad_anterior, // ← clave correcta
                            borderColor: 'rgba(201, 203, 207, 0.9)',
                            backgroundColor: 'transparent',
                            borderWidth: 2,
                            borderDash: [6, 3],
                            tension: 0.4,
                            fill: false,
                            pointRadius: 3
                        }
                    ]
                },
                options: {
                    responsive: true,
                    interaction: { mode: 'index', intersect: false },
                    plugins: {
                        legend: { position: 'top' },
                        tooltip: {
                            callbacks: {
                                label: ctx => ctx.dataset.label + ': ' + ctx.parsed.y + '%'
                            }
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            max: 100,
                            ticks: { callback: v => v + '%' }
                        }
                    }
                }
            });
        }
    }

    // ─── 2. Top Productos (Donut) ─────────────────────────────────────────────
    const canvasProd = document.getElementById('productosChart');
    if (canvasProd) {
        const hasData = topProductos && topProductos.nombres && topProductos.nombres.length > 0;
        if (!hasData) {
            canvasProd.closest('.chart-card').querySelector('.chart-body').innerHTML =
                '<div class="text-center text-muted py-4"><i class="bi bi-pie-chart fs-1 opacity-25"></i><p class="mt-2">Sin productos en este período</p></div>';
        } else {
            new Chart(canvasProd.getContext('2d'), {
                type: 'doughnut',
                data: {
                    labels: topProductos.nombres,
                    datasets: [{
                        data: topProductos.cantidades,
                        backgroundColor: ['#667eea', '#f093fb', '#4facfe', '#43e97b', '#fa709a'],
                        borderWidth: 2,
                        borderColor: '#fff',
                        hoverOffset: 6
                    }]
                },
                options: {
                    responsive: true,
                    plugins: {
                        legend: { position: 'bottom', labels: { boxWidth: 12, padding: 14 } },
                        tooltip: {
                            callbacks: {
                                label: ctx => ' ' + ctx.label + ': ' + ctx.parsed + ' uds.'
                            }
                        }
                    }
                }
            });
        }
    }

    // ─── 3. Entregas Acumuladas ───────────────────────────────────────────────
    const canvasAcum = document.getElementById('acumuladoChart');
    if (canvasAcum) {
        const hasData = acumulada && acumulada.labels && acumulada.labels.length > 0;
        if (!hasData) {
            canvasAcum.closest('.chart-card').querySelector('.chart-body').innerHTML =
                '<div class="text-center text-muted py-4"><i class="bi bi-graph-up fs-1 opacity-25"></i><p class="mt-2">Sin entregas en este período</p></div>';
        } else {
            // Calcular entregas diarias (diferencia del acumulado)
            const acumData = acumulada.data;
            const dailyData = acumData.map((v, i) => i === 0 ? v : v - acumData[i - 1]);

            new Chart(canvasAcum.getContext('2d'), {
                type: 'bar',
                data: {
                    labels: acumulada.labels,
                    datasets: [
                        {
                            type: 'bar',
                            label: 'Entregas del día',
                            data: dailyData,
                            backgroundColor: 'rgba(102, 126, 234, 0.55)',
                            borderColor: 'rgba(102, 126, 234, 0.9)',
                            borderWidth: 1,
                            borderRadius: 6,
                            order: 2
                        },
                        {
                            type: 'line',
                            label: 'Acumulado',
                            data: acumData,
                            borderColor: 'rgba(67, 233, 123, 1)',
                            backgroundColor: 'rgba(67, 233, 123, 0.08)',
                            borderWidth: 2.5,
                            fill: true,
                            tension: 0.3,
                            pointBackgroundColor: 'rgba(67, 233, 123, 1)',
                            pointRadius: 5,
                            order: 1
                        }
                    ]
                },
                options: {
                    responsive: true,
                    plugins: {
                        legend: { position: 'top', labels: { boxWidth: 12 } },
                        title: { display: false },
                        tooltip: {
                            callbacks: {
                                label: ctx => {
                                    if (ctx.dataset.label === 'Acumulado') return ' Acumulado: ' + ctx.parsed.y + ' entregas';
                                    return ' Día: ' + ctx.parsed.y + ' entregas';
                                }
                            }
                        }
                    },
                    scales: {
                        y: { beginAtZero: true, ticks: { stepSize: 1 } }
                    }
                }
            });
        }
    }
});
