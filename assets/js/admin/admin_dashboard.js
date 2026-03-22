// BookIT Admin Dashboard JavaScript
document.addEventListener('DOMContentLoaded', function() {
    // Number animation for statistics
    const statNumbers = document.querySelectorAll('.stat-number');
    statNumbers.forEach(stat => {
        const isRevenue = stat.textContent.includes('₱');
        const isRate = stat.textContent.includes('%');

        // Save original formatted text
        const originalText = stat.textContent;
        
        // For animation, extract numerical value
        const numericText = originalText.replace(/[^0-9.]/g, '');
        const finalValue = parseFloat(numericText) || 0;
        let currentValue = 0;
        const increment = finalValue / 30;

        const timer = setInterval(() => {
            currentValue += increment;
            if (currentValue >= finalValue) {
                clearInterval(timer);
                // Restore ORIGINAL formatted text
                stat.textContent = originalText;
            } else {
                // During animation, maintain formatting
                if (isRevenue) {
                    stat.textContent = `₱${currentValue.toFixed(2)}`;
                } else if (isRate) {
                    stat.textContent = `${currentValue.toFixed(1)}%`;
                } else {
                    stat.textContent = Math.floor(currentValue);
                }
            }
        }, 40);
    });

    // Initialize Charts
    initializeCharts();

    // Recent reservations table enhancement
    const reservationsTable = document.querySelector('.table');
    if (reservationsTable) {
        const rows = reservationsTable.querySelectorAll('tbody tr');
        rows.forEach((row, index) => {
            // Add hover effect
            row.addEventListener('mouseenter', function() {
                this.style.backgroundColor = '#f8f9fa';
            });
            
            row.addEventListener('mouseleave', function() {
                this.style.backgroundColor = '';
            });
            
            // Add staggered animation
            setTimeout(() => {
                row.style.opacity = '0';
                row.style.transform = 'translateY(20px)';
                row.style.transition = 'all 0.3s ease';
                
                setTimeout(() => {
                    row.style.opacity = '1';
                    row.style.transform = 'translateY(0)';
                }, 100);
            }, index * 100);
        });
    }

    // Statistics cards hover effects
    const statCards = document.querySelectorAll('.stat-card, .breakdown-item');
    statCards.forEach(card => {
        card.addEventListener('mouseenter', function() {
            this.style.transform = 'translateY(-5px)';
            this.style.boxShadow = '0 8px 25px rgba(0,0,0,0.15)';
        });
        
        card.addEventListener('mouseleave', function() {
            this.style.transform = 'translateY(0)';
            this.style.boxShadow = '0 2px 10px rgba(0,0,0,0.1)';
        });
    });

    // Auto-refresh dashboard data (optional)
    // setInterval(() => {
    //     location.reload();
    // }, 300000); // Refresh every 5 minutes

    // Responsive table handling
    function handleResponsiveTables() {
        const tables = document.querySelectorAll('.table-responsive');
        tables.forEach(table => {
            if (table.scrollWidth > table.clientWidth) {
                table.style.border = '1px solid #dee2e6';
                table.style.borderRadius = '0.375rem';
            }
        });
    }
    
    handleResponsiveTables();
    window.addEventListener('resize', handleResponsiveTables);
});

// Chart Initialization Function
function initializeCharts() {
    // Revenue Trend Chart
    if (typeof monthlyRevenueData !== 'undefined') {
        const revenueCtx = document.getElementById('revenueChart').getContext('2d');
        new Chart(revenueCtx, {
            type: 'line',
            data: {
                labels: monthlyRevenueData.labels.map(label => {
                    const date = new Date(label + '-01');
                    return date.toLocaleDateString('en-US', { month: 'short', year: 'numeric' });
                }).reverse(),
                datasets: [{
                    label: 'Monthly Revenue',
                    data: monthlyRevenueData.values.reverse(),
                    borderColor: '#3498db',
                    backgroundColor: 'rgba(52, 152, 219, 0.1)',
                    borderWidth: 2,
                    fill: true,
                    tension: 0.4
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return '₱' + value.toLocaleString();
                            }
                        }
                    }
                }
            }
        });
    }

    // Reservations Status Chart
    if (typeof reservationsStatusData !== 'undefined') {
        const reservationsCtx = document.getElementById('reservationsChart').getContext('2d');
        new Chart(reservationsCtx, {
            type: 'doughnut',
            data: {
                labels: reservationsStatusData.labels.map(label => label.charAt(0).toUpperCase() + label.slice(1)),
                datasets: [{
                    data: reservationsStatusData.values,
                    backgroundColor: [
                        '#fff3cd', // pending
                        '#d1ecf1', // confirmed
                        '#f8d7da', // cancelled
                        '#d4edda', // completed
                        '#e2e3e5'  // checked_in
                    ],
                    borderColor: '#fff',
                    borderWidth: 2
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });
    }

    // Units by Type Chart
    if (typeof unitsByTypeData !== 'undefined') {
        const unitsCtx = document.getElementById('unitsChart').getContext('2d');
        new Chart(unitsCtx, {
            type: 'bar',
            data: {
                labels: unitsByTypeData.labels,
                datasets: [{
                    label: 'Units',
                    data: unitsByTypeData.values,
                    backgroundColor: '#2ecc71',
                    borderColor: '#27ae60',
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });
    }

    // Users by Role Chart
    if (typeof usersByRoleData !== 'undefined') {
        const usersCtx = document.getElementById('usersChart').getContext('2d');
        new Chart(usersCtx, {
            type: 'pie',
            data: {
                labels: usersByRoleData.labels.map(label => label.charAt(0).toUpperCase() + label.slice(1)),
                datasets: [{
                    data: usersByRoleData.values,
                    backgroundColor: [
                        '#3498db', // admin
                        '#9b59b6', // manager
                        '#2ecc71'  // renter
                    ],
                    borderColor: '#fff',
                    borderWidth: 2
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });
    }
}