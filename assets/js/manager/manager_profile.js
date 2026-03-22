// Initialize tabs
        var triggerTabList = [].slice.call(document.querySelectorAll('#profileTabs button'));
        triggerTabList.forEach(function (triggerEl) {
            var tabTrigger = new bootstrap.Tab(triggerEl);
            
            triggerEl.addEventListener('click', function (event) {
                event.preventDefault();
                tabTrigger.show();
            });
        });