<?php
/**
 * RoseSMM - Coupon & Discount Engine
 * Validates promo codes, calculates percentage and fixed reductions, enforces per-user and total limits.
 */

require_once __DIR__ . '/../config/database.php';

class CouponHelper {

    /**
     * Validate coupon applicability and calculate discount
     */
    public static function validateCoupon($code, $userId, $subtotalUSD, $serviceId = 0, $categoryId = 0) {
        $db = getDB();

        if (get_setting('coupons_enabled', '1') !== '1') {
            return ['valid' => false, 'error' => 'Coupon system is currently disabled'];
        }

        $code = strtoupper(trim($code));
        if (empty($code)) {
            return ['valid' => false, 'error' => 'Please enter a coupon code'];
        }

        $stmt = $db->prepare("SELECT * FROM coupons WHERE UPPER(code) = ?");
        $stmt->execute([$code]);
        $coupon = $stmt->fetch();

        if (!$coupon) {
            return ['valid' => false, 'error' => 'Invalid coupon code'];
        }

        if (empty($coupon['is_enabled'])) {
            return ['valid' => false, 'error' => 'This coupon has been disabled'];
        }

        $now = time();
        if (!empty($coupon['starts_at']) && strtotime($coupon['starts_at']) > $now) {
            return ['valid' => false, 'error' => 'This coupon is not yet active'];
        }
        if (!empty($coupon['expires_at']) && strtotime($coupon['expires_at']) < $now) {
            return ['valid' => false, 'error' => 'This coupon has expired'];
        }

        // Check global usage limit
        $usageLimit = (int)($coupon['total_usage_limit'] ?? 0);
        if ($usageLimit > 0 && (int)$coupon['used_count'] >= $usageLimit) {
            return ['valid' => false, 'error' => 'This coupon has reached its maximum global usage limit'];
        }

        // Check per-user limit
        $perUserLimit = (int)($coupon['per_user_limit'] ?? 1);
        if ($perUserLimit > 0 && $userId > 0) {
            $uStmt = $db->prepare("SELECT COUNT(*) FROM coupon_usages WHERE coupon_id = ? AND user_id = ?");
            $uStmt->execute([$coupon['id'], $userId]);
            $userUsageCount = (int)$uStmt->fetchColumn();

            if ($userUsageCount >= $perUserLimit) {
                return ['valid' => false, 'error' => "You have already used this coupon maximum allowed times ({$perUserLimit}x)"];
            }
        }

        // Check minimum order subtotal (in USD)
        $minOrder = (float)($coupon['min_order_amount'] ?? 0);
        if ($subtotalUSD < $minOrder) {
            $userCurr = get_user_currency();
            $minFmt = format_price($minOrder, $userCurr, 'USD');
            return ['valid' => false, 'error' => "Minimum order amount of {$minFmt} required to use this coupon"];
        }

        // Check service restrictions
        if (!empty($coupon['service_ids']) && $serviceId > 0) {
            $allowedServices = array_filter(array_map('intval', explode(',', $coupon['service_ids'])));
            if (!empty($allowedServices) && !in_array((int)$serviceId, $allowedServices)) {
                return ['valid' => false, 'error' => 'This coupon is not valid for the selected service'];
            }
        }

        // Check category restrictions
        if (!empty($coupon['category_ids']) && $categoryId > 0) {
            $allowedCats = array_filter(array_map('intval', explode(',', $coupon['category_ids'])));
            if (!empty($allowedCats) && !in_array((int)$categoryId, $allowedCats)) {
                return ['valid' => false, 'error' => 'This coupon is not valid for the selected category'];
            }
        }

        // Calculate discount
        $discountAmount = 0.0;
        $discVal = (float)$coupon['discount_value'];

        if ($coupon['discount_type'] === 'percentage') {
            $discountAmount = round($subtotalUSD * ($discVal / 100.0), 4);
        } else { // fixed
            $discountAmount = $discVal;
        }

        // Check max discount cap
        if (!empty($coupon['max_discount']) && (float)$coupon['max_discount'] > 0) {
            $maxCap = (float)$coupon['max_discount'];
            if ($discountAmount > $maxCap) {
                $discountAmount = $maxCap;
            }
        }

        // Cap discount so order is never negative
        if ($discountAmount > $subtotalUSD) {
            $discountAmount = $subtotalUSD;
        }

        $finalAmount = round(max(0, $subtotalUSD - $discountAmount), 4);
        $userCurr = get_user_currency();

        return [
            'valid' => true,
            'coupon_id' => (int)$coupon['id'],
            'code' => $coupon['code'],
            'description' => $coupon['description'],
            'discount_type' => $coupon['discount_type'],
            'discount_value' => $discVal,
            'discount_amount' => $discountAmount,
            'original_amount' => $subtotalUSD,
            'final_amount' => $finalAmount,
            'formatted_discount' => format_price($discountAmount, $userCurr, 'USD'),
            'formatted_final' => format_price($finalAmount, $userCurr, 'USD')
        ];
    }

    /**
     * Record coupon usage after order confirmation
     */
    public static function applyUsage($couponId, $userId, $orderId, $discountUSD, $originalUSD, $finalUSD) {
        $db = getDB();

        $db->prepare("
            INSERT INTO coupon_usages (coupon_id, user_id, order_id, discount_amount, original_amount, final_amount, currency, created_at)
            VALUES (?, ?, ?, ?, ?, ?, 'USD', NOW())
        ")->execute([$couponId, $userId, $orderId, $discountUSD, $originalUSD, $finalUSD]);

        $db->prepare("UPDATE coupons SET used_count = used_count + 1 WHERE id = ?")->execute([$couponId]);
        $db->prepare("UPDATE orders SET coupon_id = ?, discount_amount = ? WHERE id = ?")->execute([$couponId, $discountUSD, $orderId]);
    }
}
