document.addEventListener('DOMContentLoaded', function () {

    // Check if data is available
    if (typeof dashboardData === 'undefined') {
        console.error('Dashboard data not found');
        return;
    }

    const { comparativa, topProductos, acumulada } = dashboardData;

    // 1. Comparativa Chart
    const canvasComp = document.getElementById('comparativaChart');
    if (canvasComp) {
        const ctxComp = canvasComp.getContext('2d');
        new Chart(ctxComp, {
            type: 'line',
            data: {
                labels: comparativa.labels,
                datasets: [
                    {
                        label: 'Mes Actual',
                        data: comparativa.actual,
                        borderColor: 'rgba(54, 162, 235, 1)',
                        backgroundColor: 'rgba(54, 162, 235, 0.1)',
                        borderWidth: 2,
                        tension: 0.4,
                        fill: true
                    },
                    {
                        label: 'Mes Anterior',
                        data: comparativa.anterior,
                        borderColor: 'rgba(201, 203, 207, 1)',
                        backgroundColor: 'rgba(201, 203, 207, 0.1)',
                        borderWidth: 2,
                        borderDash: [5, 5],
                        tension: 0.4,
                        fill: false
                    }
                ]
            },
            options: {
                responsive: true,
                interaction: { mode: 'index', intersect: false },
                plugins: { legend: { position: 'top' } },
                scales: { y: { beginAtZero: true } }
            }
        });
    }

    // 2. Top Productos Chart
    const canvasProd = document.getElementById('productosChart');
    if (canvasProd) {
        const ctxProd = canvasProd.getContext('2d');
        new Chart(ctxProd, {
            type: 'doughnut',
            data: {
                labels: topProductos.nombres,
                datasets: [{
                    data: topProductos.cantidades,
                    backgroundColor: [
                        '#FF6384', '#36A2EB', '#FFCE56', '#4BC0C0', '#9966FF'
                    ]
                }]
            },
            options: {
                responsive: true,
                plugins: { legend: { position: 'bottom' } }
            }
        });
    }

    // 3. Acumulado Chart
    const canvasAcum = document.getElementById('acumuladoChart');
    if (canvasAcum) {
        const ctxAcum = canvasAcum.getContext('2d');
        new Chart(ctxAcum, {
            type: 'line',
            data: {
                labels: acumulada.labels,
                datasets: [{
                    label: 'Venta Acumulada',
                    data: acumulada.data,
                    borderColor: 'rgba(75, 192, 192, 1)',
                    backgroundColor: 'rgba(75, 192, 192, 0.2)',
                    borderWidth: 3,
                    fill: true,
                    tension: 0.1
                }]
            },
            options: {
                responsive: true,
                plugins: { legend: { display: false }, title: { display: true, text: 'Crecimiento de Ingresos' } },
                scales: { y: { beginAtZero: true } }
            }
        });
    }
});
