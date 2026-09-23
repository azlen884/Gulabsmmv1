<?php
/**
 * RoseSMM - Flash Sale Engine
 * Real-time limited-time deals, countdown timers, price recalculations, and auto-expiration.
 */

require_once __DIR__ . '/../config/database.php';

class FlashSaleHelper {

    /**
     * Fetch all currently active and unexpired flash sales
     */
    public static function getActiveFlashSales() {
        $db = getDB();

        if (get_setting('flash_sales_enabled', '1') !== '1') {
            return [];
        }

        $stmt = $db->query("
            SELECT * FROM flash_sales 
            WHERE is_enabled = 1 
              AND starts_at <= NOW() 
              AND ends_at > NOW()
              AND (sale_limit = 0 OR sales_count < sale_limit)
            ORDER BY id DESC
        ");
        return $stmt->fetchAll();
    }

    /**
     * Check if a specific service is covered by an active flash sale and calculate discounted rate
     */
    public static function getDiscountForService($serviceId, $categoryId, $baseRateUSD) {
        $activeSales = self::getActiveFlashSales();
        if (empty($activeSales)) {
            return [
                'has_sale' => false,
                'rate' => $baseRateUSD,
                'original_rate' => $baseRateUSD,
                'savings_percent' => 0
            ];
        }

        foreach ($activeSales as $sale) {
            $isMatch = false;

            if ($sale['applies_to'] === 'all') {
                $isMatch = true;
            } elseif ($sale['applies_to'] === 'category') {
                $cats = array_filter(array_map('intval', explode(',', $sale['target_ids'] ?? '')));
                if (in_array((int)$categoryId, $cats)) {
                    $isMatch = true;
                }
            } elseif ($sale['applies_to'] === 'service') {
                $srvs = array_filter(array_map('intval', explode(',', $sale['target_ids'] ?? '')));
                if (in_array((int)$serviceId, $srvs)) {
                    $isMatch = true;
                }
            }

            if ($isMatch) {
                $discVal = (float)$sale['discount_value'];
                $discountedRate = $baseRateUSD;
                $savingsPercent = 0;

                if ($sale['discount_type'] === 'percentage') {
                    $savingsPercent = $discVal;
                    $discountedRate = round($baseRateUSD * (1 - ($discVal / 100.0)), 4);
                } elseif ($sale['discount_type'] === 'fixed_discount') {
                    $discountedRate = round(max(0.0001, $baseRateUSD - $discVal), 4);
                    $savingsPercent = round((($baseRateUSD - $discountedRate) / max(0.0001, $baseRateUSD)) * 100);
                } elseif ($sale['discount_type'] === 'fixed_price') {
                    $discountedRate = $discVal;
                    $savingsPercent = round((($baseRateUSD - $discountedRate) / max(0.0001, $baseRateUSD)) * 100);
                }

                return [
                    'has_sale' => true,
                    'sale_id' => $sale['id'],
                    'sale_title' => $sale['title'],
                    'badge_text' => $sale['badge_text'] ?: 'FLASH SALE',
                    'banner_text' => $sale['banner_text'],
                    'rate' => $discountedRate,
                    'original_rate' => $baseRateUSD,
                    'savings_percent' => $savingsPercent,
                    'ends_at' => $sale['ends_at'],
                    'seconds_remaining' => max(0, strtotime($sale['ends_at']) - time())
                ];
            }
        }

        return [
            'has_sale' => false,
            'rate' => $baseRateUSD,
            'original_rate' => $baseRateUSD,
            'savings_percent' => 0
        ];
    }

    /**
     * Record a flash sale purchase and manage sales limits
     */
    public static function recordPurchase($saleId) {
        $db = getDB();
        $db->prepare("
            UPDATE flash_sales 
            SET sales_count = sales_count + 1 
            WHERE id = ?
        ")->execute([$saleId]);

        // Auto-disable if limit reached
        $stmt = $db->prepare("SELECT sale_limit, sales_count FROM flash_sales WHERE id = ?");
        $stmt->execute([$saleId]);
        $row = $stmt->fetch();
        if ($row && (int)$row['sale_limit'] > 0 && (int)$row['sales_count'] >= (int)$row['sale_limit']) {
            $db->prepare("UPDATE flash_sales SET is_enabled = 0 WHERE id = ?")->execute([$saleId]);
        }
    }
}
