// Renter UI helpers: collapsible filters, smooth scroll, small interactions
 (function(){
    function onReady(fn){
        if (document.readyState !== 'loading') fn(); else document.addEventListener('DOMContentLoaded', fn);
    }

    var fpCheckIn = null;
    var fpCheckOut = null;

    function fetchRangesAndDisable(unitId){
        if (!unitId) return;
        var url = '../modules/api/get_unit_bookings.php?unit_id=' + encodeURIComponent(unitId);
        fetch(url).then(function(res){ return res.json(); }).then(function(json){
            if (!json || !json.success) return;
            var ranges = json.ranges || [];
            var disabled = ranges.map(function(r){
                return { from: r.start, to: r.end };
            });
            if (fpCheckIn) fpCheckIn.set('disable', disabled);
            if (fpCheckOut) fpCheckOut.set('disable', disabled);
        }).catch(function(err){ console.warn('Failed to fetch booked ranges', err); });
    }

    onReady(function(){
        // Filter toggle (uses Bootstrap collapse)
        var filterToggle = document.getElementById('filterToggle');
        if (filterToggle){
            filterToggle.addEventListener('click', function(e){
                e.preventDefault();
                var target = document.getElementById('filterPanel');
                if (!target) return;
                var bs = bootstrap.Collapse.getInstance(target) || new bootstrap.Collapse(target, {toggle:false});
                bs.toggle();
            });
        }

        // Smooth scroll for unit card activation
        document.querySelectorAll('.unit-card[data-unit-id]').forEach(function(el){
            el.addEventListener('click', function(e){
                // ignore if clicking a button/link inside
                if (e.target.closest('a') || e.target.closest('button')) return;
                this.scrollIntoView({behavior:'smooth', block:'center'});
            });
        });

        // Toggle payment card selection on checkout page
        document.querySelectorAll('.radio-input').forEach(function(r){
            r.addEventListener('change', function(){
                document.querySelectorAll('.payment-method-card').forEach(function(c){ c.classList.remove('selected'); });
                var card = this.closest('label')?.querySelector('.payment-method-card');
                if (card) card.classList.add('selected');
            });
        });

        // Flatpickr init for reserve page date inputs
        if (typeof flatpickr !== 'undefined'){
            try{
                var checkIn = document.querySelector('input[name="check_in_date"]');
                var checkOut = document.querySelector('input[name="check_out_date"]');
                if (checkIn && checkOut){
                    fpCheckIn = flatpickr(checkIn, {
                        altInput: true,
                        altFormat: 'F j, Y',
                        dateFormat: 'Y-m-d',
                        minDate: 'today',
                        onChange: function(selectedDates, dateStr){
                            if (selectedDates.length && fpCheckOut){
                                fpCheckOut.set('minDate', dateStr);
                            }
                        }
                    });
                    fpCheckOut = flatpickr(checkOut, {
                        altInput: true,
                        altFormat: 'F j, Y',
                        dateFormat: 'Y-m-d',
                        minDate: new Date().fp_incr(1)
                    });

                    // If there is a unit id preselected on page, try fetching its booked ranges
                    var preselectedUnit = document.querySelector('.unit-card[data-unit-id]');
                    if (preselectedUnit){
                        var uid = preselectedUnit.getAttribute('data-unit-id');
                        if (uid) fetchRangesAndDisable(uid);
                    }
                }
            }catch(e){ console.warn('Flatpickr init error', e); }
        }

        // When unit modal opens, fetch its booked ranges and disable dates
        document.querySelectorAll('[id^="unitModal"]').forEach(function(modalEl){
            modalEl.addEventListener('show.bs.modal', function(event){
                var id = this.id.replace('unitModal','');
                if (id) fetchRangesAndDisable(id);
            });
        });

        // When clicking a unit card, fetch ranges for that unit (if data-unit-id present)
        document.querySelectorAll('.unit-card[data-unit-id]').forEach(function(card){
            card.addEventListener('click', function(e){
                var id = this.getAttribute('data-unit-id');
                if (id) fetchRangesAndDisable(id);
            });
        });
    });
})();
