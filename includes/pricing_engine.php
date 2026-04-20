<?php
/**
 * BookIT Authoritative Pricing & Availability Engine
 * 
 * This module is the single source of truth for:
 * 1. Booking cost calculations (stay, fees, add-ons, promos)
 * 2. Real-time availability validation (blackouts, overlaps)
 * 
 * MODULAR SEPARATION: Logic is decoupled from UI rendering.
 */

include_once __DIR__ . '/db.php';
include_once __DIR__ . '/functions.php';

class BookIT_PricingEngine {

    /**
     * Authoritative calculation for a unit stay
     * 
     * @param int $unit_id
     * @param string $check_in (Y-m-d)
     * @param string $check_out (Y-m-d)
     * @param int $total_guests
     * @param array $addon_ids
     * @param string $promo_code
     * @param int|null $reservation_id (Optional: for existing bookings)
     * @return array Response with totals and breakdown
     */
    public static function calculatePrice($unit_id, $check_in, $check_out, $total_guests = 1, $addon_ids = [], $promo_code = '', $reservation_id = null) {
        $unit_id = (int)$unit_id;
        $total_guests = (int)$total_guests;

        // 1. Fetch Unit Data
        $unit = get_single_result("SELECT * FROM units WHERE unit_id = ?", [$unit_id]);
        if (!$unit) return ['success' => false, 'error' => 'Unit not found'];

        // 2. Validate Dates & Availability
        if (!validateDateRange($check_in, $check_out)) return ['success' => false, 'error' => 'Invalid date range'];
        
        $availability = self::checkAvailability($unit_id, $check_in, $check_out);
        if (!$availability['available']) return ['success' => false, 'error' => $availability['reason']];

        // 3. Compute Basic Stay
        $in_dt = new DateTime($check_in);
        $out_dt = new DateTime($check_out);
        $nights = (int)$out_dt->diff($in_dt)->days;

        // Enforce Min/Max Stay
        $min_stay = (int)($unit['min_stay'] ?? 1);
        $max_stay = (int)($unit['max_stay'] ?? 30);
        if ($nights < $min_stay) return ['success' => false, 'error' => "This unit requires a minimum stay of $min_stay nights."];
        if ($nights > $max_stay) return ['success' => false, 'error' => "The maximum allowed stay for this unit is $max_stay nights."];
        
        $pricing_type = $unit['pricing_type'] ?? 'nightly';
        $base_rate = (in_array($pricing_type, ['nightly', 'daily'])) 
                     ? (float)($unit['price_per_night'] ?? 0) 
                     : (float)($unit['price_per_month'] ?? 0) / 30;

        // 4. Dynamic Pricing Rules (Weekends / Seasonal)
        $rules = self::getPricingRules($unit_id);
        $nightly_breakdown = [];
        $subtotal = 0.0;
        $cursor = clone $in_dt;

        for ($i = 0; $i < $nights; $i++) {
            $current_rate = $base_rate;
            $applied_rule = null;

            foreach ($rules as $rule) {
                if (self::ruleApplies($rule, $cursor)) {
                    $current_rate = self::applyAdjustment($base_rate, $rule['adjustment_type'], $rule['adjustment_value']);
                    $applied_rule = $rule; 
                    break; // Priority to first matching rule (usually seasonal)
                }
            }

            $nightly_breakdown[] = [
                'date' => $cursor->format('Y-m-d'),
                'rate' => $current_rate,
                'rule' => $applied_rule ? $applied_rule['rule_type'] : null
            ];
            $subtotal += $current_rate;
            $cursor->modify('+1 day');
        }

        // 5. Extra Guest Fees
        $max_base = (int)($unit['max_occupancy'] ?? 1);
        $extra_allowed = (int)($unit['extra_guests_allowed'] ?? 0);
        $extra_fee = (float)($unit['extra_guest_fee'] ?? 0);
        
        if ($total_guests > ($max_base + $extra_allowed)) {
             return ['success' => false, 'error' => "Capacity exceeded. Max: ".($max_base + $extra_allowed)];
        }
        $extra_guest_count = max(0, $total_guests - $max_base);
        $extra_guest_charges = $extra_guest_count * $extra_fee * $nights;

        // 6. Add-ons (FILTERED BY STATUS & PRICE LOCK)
        $addons_total = 0.0;
        $pending_addons_total = 0.0;
        $active_addons = [];

        if ($reservation_id !== null) {
            // For EXISTING bookings: Check booking_addons table (Authoritative Source)
            $rows = get_multiple_results("SELECT ba.*, ua.name FROM booking_addons ba JOIN unit_addons ua ON ba.addon_id = ua.addon_id WHERE ba.booking_id = ?", [$reservation_id]);
            foreach ($rows as $r) {
                if ($r['status'] === 'approved') {
                    $price = (float)($r['approved_price'] ?: $r['price']);
                    $addons_total += $price;
                    $active_addons[] = array_merge($r, ['price' => $price]);
                } else if ($r['status'] === 'pending') {
                    $pending_addons_total += (float)$r['price'];
                    $active_addons[] = $r;
                }
            }
        } else if (!empty($addon_ids)) {
            // For NEW bookings (Preview Mode)
            $placeholders = implode(',', array_fill(0, count($addon_ids), '?'));
            $rows = get_multiple_results("SELECT addon_id, name, price FROM unit_addons WHERE unit_id = ? AND addon_id IN ($placeholders) AND is_active = 1", array_merge([$unit_id], $addon_ids));
            foreach ($rows as $r) {
                $pending_addons_total += (float)$r['price'];
                $active_addons[] = array_merge($r, ['status' => 'pending']);
            }
        }

        // 7. Cleaning & Service Fees
        $cleaningFee = (float)($unit['cleaning_fee'] ?? 0);
        $serviceFee = (float)($unit['service_fee'] ?? 0);
        // Authoritative Total excludes pending addons
        $gross_total = $subtotal + $extra_guest_charges + $addons_total + $cleaningFee + $serviceFee;

        // 8. Promo Discount
        $discount = 0.0;
        $promo_info = null;
        if (!empty($promo_code)) {
            $promo = get_single_result("SELECT * FROM promo_codes WHERE code = ? AND is_active = 1 AND (valid_until IS NULL OR valid_until >= CURDATE())", [$promo_code]);
            if ($promo) {
                // Scoping check
                $valid = true;
                if ($promo['scope'] === 'host' && (int)$promo['host_id'] !== (int)$unit['host_id']) $valid = false;
                if ($promo['scope'] === 'branch' && (int)$promo['branch_id'] !== (int)$unit['branch_id']) $valid = false;
                
                if ($valid && $gross_total >= (float)$promo['min_booking_amount']) {
                    $val = (float)$promo['discount_value'];
                    $discount = ($promo['discount_type'] === 'percentage') ? ($subtotal * ($val/100)) : $val;
                    if (!empty($promo['max_discount'])) $discount = min($discount, (float)$promo['max_discount']);
                    $promo_info = $promo;
                }
            }
        }

        return [
            'success' => true,
            'nights' => $nights,
            'subtotal' => $subtotal,
            'extra_guest_charges' => $extra_guest_charges,
            'extra_guests' => $extra_guest_count,
            'addons_total' => $addons_total,
            'pending_addons_total' => $pending_addons_total,
            'addons' => $active_addons,
            'fees' => $cleaningFee + $serviceFee,
            'security_deposit' => (float)($unit['security_deposit'] ?? 0),
            'discount' => round($discount, 2),
            'total' => round($gross_total - $discount, 2),
            'nightly_breakdown' => $nightly_breakdown,
            'promo' => $promo_info ? $promo_info['code'] : null
        ];
    }

    /**
     * Check if a unit is available for specific dates
     */
    public static function checkAvailability($unit_id, $check_in, $check_out) {
        // 0. Check Visibility Status
        $unit = get_single_result("SELECT status_visibility FROM units WHERE unit_id = ?", [$unit_id]);
        if (!$unit || $unit['status_visibility'] === 'hidden') return ['available' => false, 'reason' => 'Listing is currently private.'];
        if ($unit['status_visibility'] === 'maintenance') return ['available' => false, 'reason' => 'Listing is under maintenance.'];

        // 1. Check Blackouts (unit_blackouts or calendar_blackouts)
        // We'll try unit_blackouts first as it's used in newer property_settings.php
        $blackout = get_single_result("SELECT * FROM unit_blackouts 
                                      WHERE unit_id = ? AND ((start_date <= ? AND end_date >= ?) OR (start_date <= ? AND end_date >= ?))", 
                                      [$unit_id, $check_in, $check_in, $check_out, $check_out]);
        if ($blackout) return ['available' => false, 'reason' => 'Selected dates are blocked for maintenance.'];

        // 2. Check Existing Reservations
        $overlap = get_single_result("SELECT reservation_id FROM reservations 
                                     WHERE unit_id = ? AND status IN ('pending', 'approved', 'confirmed')
                                     AND ((check_in_date < ? AND check_out_date > ?) OR (check_in_date < ? AND check_out_date > ?))", 
                                     [$unit_id, $check_out, $check_in, $check_out, $check_in]);
        if ($overlap) return ['available' => false, 'reason' => 'Selected dates are already booked.'];

        return ['available' => true];
    }

    private static function getPricingRules($unit_id) {
        return get_multiple_results("SELECT * FROM unit_pricing_rules WHERE unit_id = ? AND is_active = 1", [$unit_id]);
    }

    private static function ruleApplies($rule, $date) {
        if ($rule['rule_type'] === 'weekend') {
            $w = (int)$date->format('w');
            return ($w === 0 || $w === 6);
        }
        if ($rule['rule_type'] === 'seasonal' || $rule['rule_type'] === 'date_range') {
            $s = new DateTime($rule['start_date']);
            $e = new DateTime($rule['end_date']);
            return ($date >= $s && $date <= $e);
        }
        return false;
    }

    private static function applyAdjustment($base, $type, $val) {
        if ($type === 'percentage') return $base + ($base * ($val / 100));
        return $base + $val;
    }
}
