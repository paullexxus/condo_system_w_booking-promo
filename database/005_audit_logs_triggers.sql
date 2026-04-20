DELIMITER //

DROP TRIGGER IF EXISTS trg_reservations_audit //
CREATE TRIGGER trg_reservations_audit
AFTER UPDATE ON reservations
FOR EACH ROW
BEGIN
    IF OLD.status <> NEW.status THEN
        INSERT INTO audit_logs (user_id, action_type, entity_type, entity_id, from_status, to_status, details)
        VALUES (NULL, 'status_change', 'reservation', NEW.reservation_id, OLD.status, NEW.status, CONCAT('Reservation status changed from ', OLD.status, ' to ', NEW.status));
    END IF;
    
    IF OLD.payment_status <> NEW.payment_status THEN
        INSERT INTO audit_logs (user_id, action_type, entity_type, entity_id, from_status, to_status, details)
        VALUES (NULL, 'payment_status_change', 'reservation', NEW.reservation_id, OLD.payment_status, NEW.payment_status, CONCAT('Payment status changed from ', OLD.payment_status, ' to ', NEW.payment_status));
    END IF;
END //

DROP TRIGGER IF EXISTS trg_payouts_audit //
CREATE TRIGGER trg_payouts_audit
AFTER UPDATE ON payouts
FOR EACH ROW
BEGIN
    IF OLD.status <> NEW.status THEN
        INSERT INTO audit_logs (user_id, action_type, entity_type, entity_id, from_status, to_status, details)
        VALUES (NEW.host_id, 'payout_status_change', 'payout', NEW.payout_id, OLD.status, NEW.status, CONCAT('Payout status changed from ', OLD.status, ' to ', NEW.status));
    END IF;
END //

DROP TRIGGER IF EXISTS trg_host_applications_audit //
CREATE TRIGGER trg_host_applications_audit
AFTER UPDATE ON host_applications
FOR EACH ROW
BEGIN
    IF OLD.status <> NEW.status THEN
        INSERT INTO audit_logs (user_id, action_type, entity_type, entity_id, from_status, to_status, details)
        VALUES (NEW.reviewed_by, 'application_status_change', 'host_application', NEW.application_id, OLD.status, NEW.status, CONCAT('Host application status changed from ', OLD.status, ' to ', NEW.status));
    END IF;
END //

DELIMITER ;
