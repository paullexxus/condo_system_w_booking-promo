// Simple search functionality
        document.getElementById('globalSearch').addEventListener('input', function(e) {
            const searchTerm = e.target.value.toLowerCase();
            const tables = document.querySelectorAll('.table tbody tr');
            
            tables.forEach(row => {
                const text = row.textContent.toLowerCase();
                row.style.display = text.includes(searchTerm) ? '' : 'none';
            });
        });
         // Export to PDF function (placeholder)
        function exportToPDF() {
            alert('PDF export functionality would be implemented here. For now, use the Print feature and save as PDF.');
            // In a real implementation, you would use a library like jsPDF or make a server request
        }