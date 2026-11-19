(function () {
    'use strict';

    var report = window.TuringSignSSLReport || null;

    if (report && window.Chart) {
        var productCanvas = document.getElementById('tsProductChart');

        if (productCanvas) {
            var productLabels = report.products && report.products.labels ? report.products.labels : [];
            var productAmounts = report.products && report.products.amounts ? report.products.amounts : [];
            var productCounts = report.products && report.products.counts ? report.products.counts : [];
            var productColors = report.products && report.products.colors ? report.products.colors : [];

            var amountTotal = productAmounts.reduce(function (sum, value) {
                return sum + Number(value || 0);
            }, 0);

            var productData = amountTotal > 0 ? productAmounts : productCounts;

            if (!productLabels.length) {
                productLabels = ['No data'];
                productData = [1];
                productColors = ['#9ca3af'];
            }

            new Chart(productCanvas, {
                type: 'pie',
                data: {
                    labels: productLabels,
                    datasets: [{
                        data: productData,
                        backgroundColor: productColors.length ? productColors : [
                            '#2f8de4', '#5bc758', '#ff9900', '#ed4040',
                            '#8e44ad', '#16a085', '#f1c40f', '#e67e22'
                        ],
                        borderColor: '#ffffff',
                        borderWidth: 2
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { position: 'bottom' },
                        tooltip: {
                            callbacks: {
                                label: function (context) {
                                    var label = context.label || '';
                                    var value = context.parsed || 0;

                                    if (amountTotal > 0) {
                                        return label + ': ' + value + ' (amount)';
                                    }

                                    return label + ': ' + value;
                                }
                            }
                        }
                    }
                }
            });
        }

        var statusCanvas = document.getElementById('tsStatusChart');

        if (statusCanvas) {
            var statusLabels = report.statuses && report.statuses.labels ? report.statuses.labels : [];
            var statusData = report.statuses && report.statuses.data ? report.statuses.data : [];
            var statusColors = report.statuses && report.statuses.colors ? report.statuses.colors : [];

            if (!statusLabels.length) {
                statusLabels = ['No data'];
                statusData = [1];
                statusColors = ['#9ca3af'];
            }

            new Chart(statusCanvas, {
                type: 'pie',
                data: {
                    labels: statusLabels,
                    datasets: [{
                        data: statusData,
                        backgroundColor: statusColors.length ? statusColors : [
                            '#2f8de4', '#5bc758', '#ff9900', '#ed4040',
                            '#8e44ad', '#16a085', '#f1c40f', '#e67e22'
                        ],
                        borderColor: '#ffffff',
                        borderWidth: 2
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
})();
