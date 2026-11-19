(function () {
    'use strict';

    var report = window.TuringSignSSLReport || null;

    if (report && window.Chart) {
        var statusCanvas = document.getElementById('tsStatusChart');
        if (statusCanvas) {
            var statusLabels = report.status && report.status.labels ? report.status.labels : [];
            var statusData = report.status && report.status.data ? report.status.data : [];

            if (!statusLabels.length) {
                statusLabels = ['No data'];
                statusData = [1];
            }

            new Chart(statusCanvas, {
                type: 'doughnut',
                data: {
                    labels: statusLabels,
                    datasets: [{
                        data: statusData,
                        backgroundColor: [
                            '#28a745', '#ffc107', '#dc3545', '#17a2b8',
                            '#6c757d', '#007bff', '#fd7e14', '#6f42c1'
                        ]
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { position: 'bottom' }
                    }
                }
            });
        }

        var productCanvas = document.getElementById('tsProductChart');
        if (productCanvas) {
            var productLabels = report.products && report.products.labels ? report.products.labels : [];
            var productData = report.products && report.products.data ? report.products.data : [];

            if (!productLabels.length) {
                productLabels = ['No data'];
                productData = [1];
            }

            new Chart(productCanvas, {
                type: 'doughnut',
                data: {
                    labels: productLabels,
                    datasets: [{
                        data: productData,
                        backgroundColor: [
                            '#007bff', '#28a745', '#ffc107', '#dc3545',
                            '#17a2b8', '#6f42c1', '#fd7e14', '#6c757d'
                        ]
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { position: 'bottom' }
                    }
                }
            });
        }
    }

    var search = document.getElementById('tsSslSearch');
    if (search) {
        search.addEventListener('input', function () {
            var query = this.value.toLowerCase();
            var rows = document.querySelectorAll('#tsSslTable tbody tr');

            rows.forEach(function (row) {
                row.style.display = row.textContent.toLowerCase().indexOf(query) !== -1 ? '' : 'none';
            });
        });
    }

    var period = document.getElementById('tsPeriod');
    var customRange = document.getElementById('tsCustomRange');

    if (period && customRange) {
        var toggleCustomRange = function () {
            customRange.style.display = period.value === 'custom' ? 'inline-flex' : 'none';
        };

        period.addEventListener('change', toggleCustomRange);
        toggleCustomRange();
    }
})();
