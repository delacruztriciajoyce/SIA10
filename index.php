
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Northwind Orders Calendar</title>
    <link href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/index.global.min.css" rel="stylesheet" />
    <link rel="stylesheet" href="style.css" />
</head>
</head>
<body>
    <header>
        <h1>Northwind Calendar</h1>
    </header>
    <div class="page-content">
        <div class="main-panel">
            <div id="calendar"></div>
            <div class="panel-card chart-container" id="chart-card">
                <h2>Order volume</h2>
                <canvas id="ordersChart"></canvas>
            </div>
            <div id="reports-grid">
                <div class="panel-card chart-container">
                    <h2>Annual Revenue</h2>
                    <canvas id="annualRevenueChart"></canvas>
                </div>
                <div class="panel-card chart-container">
                    <h2>Top 10 Customers</h2>
                    <canvas id="topCustomersChart"></canvas>
                </div>
                <div class="panel-card chart-container">
                    <h2>Best-Selling Products</h2>
                    <canvas id="bestSellingChart"></canvas>
                </div>
                <div class="panel-card chart-container">
                    <h2>Revenue by Product</h2>
                    <canvas id="revenueByProductChart"></canvas>
                </div>
            </div>
        </div>
        <div class="side-panel">
            <div class="panel-card">
                <h2>Filter orders</h2>
                <div class="filter-row">
                    <label for="search-input">Search order, customer, or ship name</label>
                    <input id="search-input" type="text" placeholder="Search term..." />
                </div>
                <div class="filter-row">
                    <label for="start-date">Start date</label>
                    <input id="start-date" type="date" />
                </div>
                <div class="filter-row">
                    <label for="end-date">End date</label>
                    <input id="end-date" type="date" />
                </div>
                <div class="button-row">
                    <button id="filter-button">Apply filter</button>
                    <button id="reset-button" type="button" class="secondary">Reset</button>
                </div>
            </div>
            <div class="panel-card">
                <h2>Event Details</h2>
                <div id="event-details">
                    <p>Select an order on the calendar to see details.</p>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/index.global.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const calendarEl = document.getElementById('calendar');
            const detailsEl = document.getElementById('event-details');
            const searchInput = document.getElementById('search-input');
            const startDateInput = document.getElementById('start-date');
            const endDateInput = document.getElementById('end-date');
            const filterButton = document.getElementById('filter-button');
            const resetButton = document.getElementById('reset-button');

            const calendar = new FullCalendar.Calendar(calendarEl, {
                initialView: 'dayGridMonth',
                headerToolbar: {
                    left: 'prev,next today',
                    center: 'title',
                    right: 'dayGridMonth,timeGridWeek,timeGridDay'
                },
                height: 'auto',
                eventDisplay: 'block',
                eventColor: '#1d5b99',
                eventTextColor: '#fff',
                events: [],
                eventClick: function(info) {
                    const event = info.event;
                    const props = event.extendedProps;
                    detailsEl.innerHTML = `
                        <div class="event-title">${event.title}</div>
                        <p><strong>Customer:</strong> ${props.customerId || 'N/A'}</p>
                        <p><strong>Employee:</strong> ${props.employeeId || 'N/A'}</p>
                        <p><strong>Order Date:</strong> ${props.orderDate || event.startStr}</p>
                        <p><strong>Required Date:</strong> ${props.requiredDate || 'N/A'}</p>
                        <p><strong>Ship Date:</strong> ${props.shippedDate || 'Pending'}</p>
                        <p><strong>Ship Name:</strong> ${props.shipName || 'N/A'}</p>
                    `;
                }
            });

            calendar.render();

            const chartCtx = document.getElementById('ordersChart').getContext('2d');
            const annualCtx = document.getElementById('annualRevenueChart').getContext('2d');
            const topCustomersCtx = document.getElementById('topCustomersChart').getContext('2d');
            const bestSellingCtx = document.getElementById('bestSellingChart').getContext('2d');
            const revenueByProductCtx = document.getElementById('revenueByProductChart').getContext('2d');

            let orderChart = null;
            let annualChart = null;
            let topCustomersChart = null;
            let bestSellingChart = null;
            let revenueByProductChart = null;

            const formatMonthLabel = (key) => {
                const [year, month] = key.split('-').map(Number);
                const date = new Date(year, month - 1, 1);
                return date.toLocaleDateString('en-US', { month: 'short', year: 'numeric' });
            };

            const createBarChart = (ctx, existingChart, labels, data, label, color, xLabel = 'Category') => {
                const chartData = {
                    labels,
                    datasets: [{
                        label,
                        data,
                        backgroundColor: color,
                        borderColor: '#163f6e',
                        borderWidth: 1,
                        borderRadius: 6,
                        maxBarThickness: 48
                    }]
                };

                const chartConfig = {
                    type: 'bar',
                    data: chartData,
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        aspectRatio: 2,
                        plugins: {
                            legend: { display: false },
                            tooltip: { mode: 'index', intersect: false }
                        },
                        scales: {
                            x: { title: { display: true, text: xLabel } },
                            y: {
                                beginAtZero: true,
                                title: { display: true, text: label }
                            }
                        }
                    }
                };

                if (existingChart) {
                    existingChart.destroy();
                }

                return new Chart(ctx, chartConfig);
            };

            const updateChart = (events) => {
                const counts = {};

                events.forEach((event) => {
                    const date = event.start;
                    if (!date) return;
                    const d = new Date(date);
                    if (Number.isNaN(d.getTime())) return;
                    const key = `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}`;
                    counts[key] = (counts[key] || 0) + 1;
                });

                const labels = Object.keys(counts).sort();
                const data = labels.map((label) => counts[label]);

                orderChart = createBarChart(
                    chartCtx,
                    orderChart,
                    labels.map(formatMonthLabel),
                    data,
                    'Order count',
                    '#1d5b99',
                    'Month'
                );
            };

            const loadReports = () => {
                const query = buildQuery();
                const url = query ? `fetch_reports.php?${query}` : 'fetch_reports.php';

                fetch(url)
                    .then(response => response.json())
                    .then(data => {
                        if (!data || !Array.isArray(data.annualRevenue)) {
                            throw new Error('Invalid report data');
                        }

                        annualChart = createBarChart(
                            annualCtx,
                            annualChart,
                            data.annualRevenue.map(item => item.year),
                            data.annualRevenue.map(item => Number(item.revenue)),
                            'Revenue',
                            '#4b90d7'
                        );

                        bestSellingChart = createBarChart(
                            bestSellingCtx,
                            bestSellingChart,
                            data.bestSellingProducts.map(item => item.productName),
                            data.bestSellingProducts.map(item => Number(item.totalQuantity)),
                            'Units sold',
                            '#1d5b99'
                        );

                        revenueByProductChart = createBarChart(
                            revenueByProductCtx,
                            revenueByProductChart,
                            data.revenueByProduct.map(item => item.productName),
                            data.revenueByProduct.map(item => Number(item.revenue)),
                            'Revenue',
                            '#2d8f5f'
                        );

                        topCustomersChart = createBarChart(
                            topCustomersCtx,
                            topCustomersChart,
                            data.topCustomers.map(item => item.customerName),
                            data.topCustomers.map(item => Number(item.spent)),
                            'Amount spent',
                            '#c95f3f'
                        );
                    })
                    .catch(error => {
                        console.error('Report load error:', error);
                    });
            };

            const buildQuery = () => {
                const params = new URLSearchParams();
                const search = searchInput.value.trim();
                const startDate = startDateInput.value;
                const endDate = endDateInput.value;

                if (search) {
                    params.set('search', search);
                }
                if (startDate) {
                    params.set('startDate', startDate);
                }
                if (endDate) {
                    params.set('endDate', endDate);
                }

                return params.toString();
            };

            const loadEvents = () => {
                detailsEl.innerHTML = '<div class="loading">Loading orders...</div>';
                const query = buildQuery();
                const url = query ? `fetch_orders.php?${query}` : 'fetch_orders.php';

                fetch(url)
                    .then(response => response.json())
                    .then(events => {
                        if (!Array.isArray(events)) {
                            throw new Error('Invalid event data');
                        }

                        calendar.removeAllEventSources();
                        calendar.removeAllEvents();
                        calendar.addEventSource(events);
                        updateChart(events);
                        loadReports();

                        if (events.length > 0) {
                            calendar.gotoDate(events[0].start);
                            detailsEl.innerHTML = '<p>Select an order on the calendar to see details.</p>';
                        } else {
                            detailsEl.innerHTML = '<div class="loading">No orders match the filter.</div>';
                        }
                    })
                    .catch(error => {
                        detailsEl.innerHTML = '<div class="loading">Unable to load order events.</div>';
                        console.error('Calendar load error:', error);
                    });
            };

            filterButton.addEventListener('click', loadEvents);
            resetButton.addEventListener('click', function () {
                searchInput.value = '';
                startDateInput.value = '';
                endDateInput.value = '';
                loadEvents();
            });

            loadEvents();
        });
    </script>
</body>
</html>
