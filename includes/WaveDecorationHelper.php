<?php
/**
 * RoseSMM - Flowing Multi-Line Wave Decoration System
 * 
 * Generates smooth, multi-line flowing gradient wave ribbons inspired by:
 * https://png.pngtree.com/png-vector/20220527/ourmid/pngtree-gradient-wave-lines-vector-png-image_4742826.png
 *
 * SPECIFICATION REQUIREMENTS:
 * - Multiple thin parallel flowing lines (14-22 lines per ribbon bundle)
 * - Lines bend and curve together naturally forming a flowing 3D wireframe ribbon
 * - Smooth wave/ribbon movement with gradient transitions across lines
 * - Transparent/subtle appearance (stroke-width 0.75-1.0, low opacity)
 * - Distinct compositions for Hero, Section Dividers, Services, Testimonials, and Pre-Footer
 * - Dynamic color tokens mapped to active theme CSS variables
 * - pointer-events: none, strictly behind content, zero horizontal overflow
 */

class WaveDecorationHelper {

    /**
     * Shared SVG gradient definitions mapped dynamically to theme CSS variables
     */
    public static function getSvgGradientDefs($prefix = 'w') {
        return <<<SVG
        <defs>
          <!-- Main Flow Gradient (Left to Right) -->
          <linearGradient id="{$prefix}-grad-main" x1="0%" y1="20%" x2="100%" y2="80%">
            <stop offset="0%" stop-color="var(--wave-c1)" stop-opacity="0.85" />
            <stop offset="25%" stop-color="var(--wave-c2)" stop-opacity="0.75" />
            <stop offset="50%" stop-color="var(--wave-c3)" stop-opacity="0.65" />
            <stop offset="75%" stop-color="var(--wave-c4)" stop-opacity="0.55" />
            <stop offset="100%" stop-color="var(--wave-c5)" stop-opacity="0.40" />
          </linearGradient>

          <!-- Reverse Flow Gradient (Right to Left) -->
          <linearGradient id="{$prefix}-grad-reverse" x1="100%" y1="10%" x2="0%" y2="90%">
            <stop offset="0%" stop-color="var(--wave-c2)" stop-opacity="0.80" />
            <stop offset="35%" stop-color="var(--wave-c3)" stop-opacity="0.70" />
            <stop offset="70%" stop-color="var(--wave-c4)" stop-opacity="0.55" />
            <stop offset="100%" stop-color="var(--wave-c6)" stop-opacity="0.30" />
          </linearGradient>

          <!-- Diagonal Highlight Gradient -->
          <linearGradient id="{$prefix}-grad-diag" x1="15%" y1="0%" x2="85%" y2="100%">
            <stop offset="0%" stop-color="var(--wave-c3)" stop-opacity="0.75" />
            <stop offset="40%" stop-color="var(--wave-c4)" stop-opacity="0.60" />
            <stop offset="75%" stop-color="var(--wave-c5)" stop-opacity="0.45" />
            <stop offset="100%" stop-color="var(--wave-c7)" stop-opacity="0.25" />
          </linearGradient>

          <!-- Soft Glow Filter -->
          <filter id="{$prefix}-soft-glow" x="-20%" y="-20%" width="140%" height="140%">
            <feGaussianBlur stdDeviation="35" result="blur" />
            <feComposite in="SourceGraphic" in2="blur" operator="over" />
          </filter>
        </defs>
SVG;
    }

    /**
     * 1. Hero Multi-Line Wave System
     * Features:
     * - Primary ribbon: 20 thin lines sweeping diagonally from left across the background
     * - Secondary ribbon: 12 thin lines intersecting gently in counter-flow
     * - Ambient radial blur glow behind the sweep
     * - Subtle geometric arcs and light haze
     */
    public static function renderHeroWave() {
        $defs = self::getSvgGradientDefs('hero');

        // Generate 20 cubic bezier paths for primary flowing ribbon
        $primaryLines = '';
        $count1 = 20;
        for ($i = 0; $i < $count1; $i++) {
            $t = $i / ($count1 - 1); // 0 to 1
            
            // Mathematical envelope offsets for sweeping multi-line ribbon
            $y0 = 120 + ($t * 160);
            $cp1x = 220 + ($t * 60);
            $cp1y = 40 + ($t * 220);
            $cp2x = 540 - ($t * 40);
            $cp2y = 360 + ($t * 140);
            $mx = 820 + ($t * 40);
            $my = 180 + ($t * 90);
            $cp3x = 1100 + ($t * 60);
            $cp3y = 20 + ($t * 140);
            $cp4x = 1320 - ($t * 30);
            $cp4y = 320 + ($t * 120);
            $x1 = 1520;
            $y1 = 220 + ($t * 180);

            // Center lines are slightly more prominent, edges fade softly
            $distFromCenter = abs($t - 0.5) * 2; // 0 at center, 1 at edge
            $opacity = round(0.48 - ($distFromCenter * 0.22), 2);
            $strokeWidth = ($i % 2 === 0) ? '0.9' : '0.75';

            $primaryLines .= "    <path d=\"M -60 {$y0} C {$cp1x} {$cp1y}, {$cp2x} {$cp2y}, {$mx} {$my} C {$cp3x} {$cp3y}, {$cp4x} {$cp4y}, {$x1} {$y1}\" stroke=\"url(#hero-grad-main)\" stroke-width=\"{$strokeWidth}\" opacity=\"{$opacity}\" vector-effect=\"non-scaling-stroke\" />\n";
        }

        // Generate 12 cubic bezier paths for intersecting secondary counter-flow ribbon
        $secondaryLines = '';
        $count2 = 12;
        for ($j = 0; $j < $count2; $j++) {
            $u = $j / ($count2 - 1);
            $sy0 = 340 + ($u * 110);
            $scp1x = 280 - ($u * 30);
            $scp1y = 220 + ($u * 140);
            $scp2x = 640 + ($u * 50);
            $scp2y = 80 + ($u * 110);
            $smx = 940 - ($u * 30);
            $smy = 280 + ($u * 90);
            $scp3x = 1200 + ($u * 40);
            $scp3y = 420 + ($u * 100);
            $sx1 = 1540;
            $sy1 = 160 + ($u * 120);

            $uDist = abs($u - 0.5) * 2;
            $uOpacity = round(0.38 - ($uDist * 0.18), 2);

            $secondaryLines .= "    <path d=\"M -40 {$sy0} C {$scp1x} {$scp1y}, {$scp2x} {$scp2y}, {$smx} {$smy} C {$scp3x} {$scp3y}, 1400 {$sy1}, {$sx1} {$sy1}\" stroke=\"url(#hero-grad-diag)\" stroke-width=\"0.8\" opacity=\"{$uOpacity}\" stroke-dasharray=\"" . ($j % 4 === 1 ? '4 6' : 'none') . "\" vector-effect=\"non-scaling-stroke\" />\n";
        }

        $aurora = self::renderAuroraGlow();
        $streaks = self::renderLightStreaks('hero-streak');
        $corners = self::renderCornerGlow();

        return <<<HTML
        <div class="theme-decor-layer" aria-hidden="true">
          {$corners}
          {$aurora}
          <!-- Soft Ambient Radial Glows -->
          <div class="theme-ambient-glow w-[480px] h-[480px] -top-24 -left-20" style="background: radial-gradient(circle, var(--wave-glow-1) 0%, transparent 70%);"></div>
          <div class="theme-ambient-glow w-[520px] h-[520px] top-1/3 -right-24" style="background: radial-gradient(circle, var(--wave-glow-2) 0%, transparent 70%);"></div>
          <div class="theme-ambient-glow w-[360px] h-[360px] -bottom-16 left-1/3" style="background: radial-gradient(circle, var(--wave-glow-1) 0%, transparent 65%);"></div>

          <!-- Subtle Supporting Concentric Geometric Rings & Lines -->
          <div class="theme-decor-circle w-80 h-80 -top-12 right-1/4 hidden md:block opacity-25"></div>
          <div class="theme-decor-circle w-52 h-52 bottom-12 left-12 hidden lg:block opacity-20"></div>
          <div class="theme-decor-line w-36 h-[1px] top-28 left-10 hidden lg:block opacity-25"></div>
          <div class="theme-decor-line w-24 h-[1px] bottom-32 right-1/3 hidden lg:block opacity-20"></div>

          <!-- Flowing Multi-Line Wave Ribbon SVG -->
          <svg class="theme-wave-ribbon top-0 left-0 w-full h-full" viewBox="0 0 1440 680" fill="none" preserveAspectRatio="none">
            {$defs}
            <!-- Primary Sweeping Ribbon (20 Lines) -->
            <g class="wave-bundle-primary">
            {$primaryLines}
            </g>
            <!-- Intersecting Layered Ribbon (12 Lines) -->
            <g class="wave-bundle-secondary">
            {$secondaryLines}
            </g>
          </svg>
          {$streaks}
        </div>
HTML;
    }

    /**
     * 2. Section Transition: Hero to Services
     * Smooth 14-line wave ribbon flowing horizontally with graceful crest and dip
     */
    public static function renderDividerHeroToServices() {
        $defs = self::getSvgGradientDefs('d1');

        $lines = '';
        $count = 14;
        for ($i = 0; $i < $count; $i++) {
            $t = $i / ($count - 1);
            $y0 = 35 + ($t * 45);
            $cp1x = 320 + ($t * 40);
            $cp1y = 10 + ($t * 60);
            $cp2x = 720 - ($t * 50);
            $cp2y = 80 - ($t * 30);
            $cp3x = 1100 + ($t * 30);
            $cp3y = 20 + ($t * 50);
            $y1 = 45 + ($t * 40);

            $dist = abs($t - 0.5) * 2;
            $op = round(0.42 - ($dist * 0.20), 2);
            $width = ($i % 3 === 0) ? '0.95' : '0.75';

            $lines .= "    <path d=\"M -40 {$y0} C {$cp1x} {$cp1y}, {$cp2x} {$cp2y}, 900 {$cp2y} C {$cp3x} {$cp3y}, 1320 {$cp3y}, 1480 {$y1}\" stroke=\"url(#d1-grad-main)\" stroke-width=\"{$width}\" opacity=\"{$op}\" vector-effect=\"non-scaling-stroke\" />\n";
        }

        return <<<HTML
        <div class="section-wave-divider relative overflow-hidden" aria-hidden="true">
          <svg viewBox="0 0 1440 120" fill="none" preserveAspectRatio="none">
            {$defs}
            {$lines}
          </svg>
        </div>
HTML;
    }

    /**
     * 3. Services Background Multi-Line Wave
     * Right-to-Left diagonal flowing 14-line wave ribbon behind popular services
     */
    public static function renderServicesWave() {
        $defs = self::getSvgGradientDefs('srv');

        $lines = '';
        $count = 14;
        for ($i = 0; $i < $count; $i++) {
            $t = $i / ($count - 1);
            $y0 = 80 + ($t * 120);
            $cp1x = 1180 - ($t * 40);
            $cp1y = 240 + ($t * 100);
            $cp2x = 840 + ($t * 30);
            $cp2y = 40 + ($t * 80);
            $cp3x = 420 - ($t * 50);
            $cp3y = 300 + ($t * 90);
            $y1 = 140 + ($t * 110);

            $dist = abs($t - 0.5) * 2;
            $op = round(0.32 - ($dist * 0.16), 2);

            $lines .= "    <path d=\"M 1480 {$y0} C {$cp1x} {$cp1y}, {$cp2x} {$cp2y}, 680 180 C {$cp3x} {$cp3y}, 180 120, -50 {$y1}\" stroke=\"url(#srv-grad-reverse)\" stroke-width=\"0.8\" opacity=\"{$op}\" vector-effect=\"non-scaling-stroke\" />\n";
        }

        return <<<HTML
        <div class="theme-decor-layer" aria-hidden="true">
          <div class="theme-ambient-glow w-[460px] h-[460px] -top-12 -right-16" style="background: radial-gradient(circle, var(--wave-glow-1) 0%, transparent 70%);"></div>
          <div class="theme-ambient-glow w-[420px] h-[420px] bottom-8 left-10" style="background: radial-gradient(circle, var(--wave-glow-2) 0%, transparent 70%);"></div>
          <div class="theme-decor-circle w-64 h-64 -top-8 left-8 hidden lg:block opacity-20"></div>

          <svg class="theme-wave-ribbon top-0 left-0 w-full h-full" viewBox="0 0 1440 600" fill="none" preserveAspectRatio="none">
            {$defs}
            {$lines}
          </svg>
        </div>
HTML;
    }

    /**
     * 4. Section Transition: Services to Features
     * Left-to-Right wave band with 12 smoothly varying lines
     */
    public static function renderDividerServicesToFeatures() {
        $defs = self::getSvgGradientDefs('d2');

        $lines = '';
        $count = 12;
        for ($i = 0; $i < $count; $i++) {
            $t = $i / ($count - 1);
            $y0 = 60 + ($t * 40);
            $cp1x = 380 - ($t * 30);
            $cp1y = 15 + ($t * 50);
            $cp2x = 760 + ($t * 40);
            $cp2y = 85 - ($t * 40);
            $cp3x = 1140 - ($t * 30);
            $cp3y = 25 + ($t * 45);
            $y1 = 55 + ($t * 35);

            $dist = abs($t - 0.5) * 2;
            $op = round(0.38 - ($dist * 0.18), 2);

            $lines .= "    <path d=\"M -40 {$y0} C {$cp1x} {$cp1y}, {$cp2x} {$cp2y}, 960 45 C {$cp3x} {$cp3y}, 1320 75, 1480 {$y1}\" stroke=\"url(#d2-grad-diag)\" stroke-width=\"0.8\" opacity=\"{$op}\" vector-effect=\"non-scaling-stroke\" />\n";
        }

        return <<<HTML
        <div class="section-wave-divider relative overflow-hidden" aria-hidden="true">
          <svg viewBox="0 0 1440 110" fill="none" preserveAspectRatio="none">
            {$defs}
            {$lines}
          </svg>
        </div>
HTML;
    }

    /**
     * 5. Testimonials Section Multi-Line Wave System
     * Features:
     * - 16-line smooth ribbon weaving through the background of the testimonial cards
     * - Soft ambient aura glow behind the section center
     * - Subtle geometric orbital rings
     */
    public static function renderTestimonialsWave() {
        $defs = self::getSvgGradientDefs('tst');

        $lines = '';
        $count = 16;
        for ($i = 0; $i < $count; $i++) {
            $t = $i / ($count - 1);
            
            $y0 = 160 + ($t * 130);
            $cp1x = 260 + ($t * 50);
            $cp1y = 60 + ($t * 180);
            $cp2x = 580 - ($t * 40);
            $cp2y = 320 - ($t * 60);
            $cp3x = 890 + ($t * 40);
            $cp3y = 80 + ($t * 120);
            $cp4x = 1220 - ($t * 30);
            $cp4y = 340 - ($t * 80);
            $y1 = 180 + ($t * 120);

            $dist = abs($t - 0.5) * 2;
            $op = round(0.44 - ($dist * 0.22), 2);
            $strokeWidth = ($i % 2 === 0) ? '0.85' : '0.75';

            $lines .= "    <path d=\"M -50 {$y0} C {$cp1x} {$cp1y}, {$cp2x} {$cp2y}, 740 210 C {$cp3x} {$cp3y}, {$cp4x} {$cp4y}, 1500 {$y1}\" stroke=\"url(#tst-grad-main)\" stroke-width=\"{$strokeWidth}\" opacity=\"{$op}\" vector-effect=\"non-scaling-stroke\" />\n";
        }

        // Secondary counter-line accent (6 hairline strokes)
        $accentLines = '';
        for ($k = 0; $k < 6; $k++) {
            $w = $k / 5;
            $ay0 = 280 + ($w * 60);
            $acp1x = 340 + ($w * 30);
            $acp1y = 140 + ($w * 70);
            $acp2x = 820 - ($w * 40);
            $acp2y = 380 - ($w * 50);
            $ay1 = 120 + ($w * 60);
            $accentLines .= "    <path d=\"M -40 {$ay0} C {$acp1x} {$acp1y}, {$acp2x} {$acp2y}, 1480 {$ay1}\" stroke=\"url(#tst-grad-diag)\" stroke-width=\"0.7\" opacity=\"0.22\" stroke-dasharray=\"3 5\" vector-effect=\"non-scaling-stroke\" />\n";
        }

        return <<<HTML
        <div class="theme-decor-layer" aria-hidden="true">
          <!-- Soft Ambient Section Glow -->
          <div class="theme-ambient-glow w-[540px] h-[540px] -top-16 left-1/4" style="background: radial-gradient(circle, var(--wave-glow-1) 0%, transparent 70%);"></div>
          <div class="theme-ambient-glow w-[460px] h-[460px] bottom-0 right-12" style="background: radial-gradient(circle, var(--wave-glow-2) 0%, transparent 70%);"></div>

          <!-- Subtle Geometric Rings -->
          <div class="theme-decor-circle w-56 h-56 top-10 right-24 hidden md:block opacity-25"></div>
          <div class="theme-decor-circle w-72 h-72 -bottom-12 left-16 hidden lg:block opacity-20"></div>

          <svg class="theme-wave-ribbon top-0 left-0 w-full h-full" viewBox="0 0 1440 600" fill="none" preserveAspectRatio="none">
            {$defs}
            {$lines}
            {$accentLines}
          </svg>
        </div>
HTML;
    }

    /**
     * 6. Pre-Footer Multi-Line Wave Divider
     * 12-line gentle ribbon transitioning into the page footer
     */
    public static function renderDividerPreFooter() {
        $defs = self::getSvgGradientDefs('ft');

        $lines = '';
        $count = 12;
        for ($i = 0; $i < $count; $i++) {
            $t = $i / ($count - 1);
            $y0 = 35 + ($t * 35);
            $cp1x = 340 + ($t * 40);
            $cp1y = 80 - ($t * 30);
            $cp2x = 760 - ($t * 50);
            $cp2y = 15 + ($t * 40);
            $cp3x = 1140 + ($t * 30);
            $cp3y = 75 - ($t * 35);
            $y1 = 40 + ($t * 35);

            $dist = abs($t - 0.5) * 2;
            $op = round(0.36 - ($dist * 0.18), 2);

            $lines .= "    <path d=\"M -40 {$y0} C {$cp1x} {$cp1y}, {$cp2x} {$cp2y}, 940 50 C {$cp3x} {$cp3y}, 1300 20, 1480 {$y1}\" stroke=\"url(#ft-grad-main)\" stroke-width=\"0.8\" opacity=\"{$op}\" vector-effect=\"non-scaling-stroke\" />\n";
        }

        return <<<HTML
        <div class="section-wave-divider relative overflow-hidden" aria-hidden="true">
          <svg viewBox="0 0 1440 100" fill="none" preserveAspectRatio="none">
            {$defs}
            {$lines}
          </svg>
        </div>
HTML;
    }

    /**
     * =========================================================================
     * 7. AURORA GLOW EFFECT
     * Very soft, large-scale blurred ambient glow behind content.
     * Controlled by Admin toggle 'decor_aurora_glow'.
     * =========================================================================
     */
    public static function renderAuroraGlow(string $containerClass = ''): string {
        if (!is_aurora_glow_enabled()) {
            return '';
        }
        return <<<HTML
        <div class="theme-aurora-glow-container {$containerClass}" aria-hidden="true">
          <div class="aurora-orb aurora-orb-1"></div>
          <div class="aurora-orb aurora-orb-2"></div>
          <div class="aurora-orb aurora-orb-3"></div>
        </div>
HTML;
    }

    /**
     * =========================================================================
     * 8. LIGHT STREAKS EFFECT
     * Elegant thin flowing light streaks complementing the multi-line waves.
     * Controlled by Admin toggle 'decor_light_streaks'.
     * =========================================================================
     */
    public static function renderLightStreaks(string $prefix = 'streak'): string {
        if (!is_light_streaks_enabled()) {
            return '';
        }
        return <<<HTML
        <div class="theme-light-streaks-container" aria-hidden="true">
          <svg class="theme-light-streaks-svg" viewBox="0 0 1440 650" fill="none" preserveAspectRatio="none">
            <defs>
              <linearGradient id="{$prefix}-trail-1" x1="0%" y1="0%" x2="100%" y2="100%">
                <stop offset="0%" stop-color="var(--streak-c1)" stop-opacity="0" />
                <stop offset="20%" stop-color="var(--streak-c1)" stop-opacity="0.85" />
                <stop offset="50%" stop-color="var(--streak-c2)" stop-opacity="0.95" />
                <stop offset="80%" stop-color="var(--streak-c1)" stop-opacity="0.75" />
                <stop offset="100%" stop-color="var(--streak-c2)" stop-opacity="0" />
              </linearGradient>
              <linearGradient id="{$prefix}-trail-2" x1="100%" y1="0%" x2="0%" y2="100%">
                <stop offset="0%" stop-color="var(--streak-c2)" stop-opacity="0" />
                <stop offset="25%" stop-color="var(--streak-c1)" stop-opacity="0.90" />
                <stop offset="70%" stop-color="var(--streak-c2)" stop-opacity="0.80" />
                <stop offset="100%" stop-color="var(--streak-c1)" stop-opacity="0" />
              </linearGradient>
            </defs>
            <!-- Illuminated Trails weaving smoothly alongside the multi-line ribbon -->
            <path class="light-streak-trail" d="M -40,160 C 260,40 540,320 880,180 C 1160,70 1340,240 1520,150" stroke="url(#{$prefix}-trail-1)" stroke-width="1.2" stroke-dasharray="220 90" />
            <path class="light-streak-trail light-streak-trail-2" d="M -20,290 C 320,150 640,400 990,250 C 1240,140 1390,320 1510,240" stroke="url(#{$prefix}-trail-2)" stroke-width="1.0" stroke-dasharray="160 120" />
            <path class="light-streak-trail light-streak-trail-3" d="M -30,90 C 380,210 720,80 1060,280 C 1270,390 1400,170 1530,200" stroke="url(#{$prefix}-trail-1)" stroke-width="0.85" stroke-dasharray="140 140" />
          </svg>
        </div>
HTML;
    }

    /**
     * =========================================================================
     * 9. CORNER GLOW EFFECT
     * Subtle, elegant glow emanating from page corners grounding the layout.
     * Controlled by Admin toggle 'decor_corner_glow'.
     * =========================================================================
     */
    public static function renderCornerGlow(): string {
        if (!is_corner_glow_enabled()) {
            return '';
        }
        return <<<HTML
        <div class="theme-corner-glow-container" aria-hidden="true">
          <div class="corner-glow-tl"></div>
          <div class="corner-glow-tr"></div>
          <div class="corner-glow-br"></div>
        </div>
HTML;
    }

    /**
     * =========================================================================
     * 10. GLOBAL DECORATION WRAPPER
     * Renders all active background ambience effects in proper z-index order.
     * Each effect renders ONLY if its respective admin toggle is active.
     * =========================================================================
     */
    public static function renderGlobalDecorations(): string {
        $out = '';
        $out .= self::renderCornerGlow();
        $out .= self::renderAuroraGlow();
        $out .= self::renderLightStreaks('global-streak');
        return $out;
    }
}
