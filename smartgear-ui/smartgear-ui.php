<?php
/**
 * Plugin Name: SmartGear UI Polish
 * Description: Adds hero homepage content, blue glow branding, hover effects, and light/dark toggle.
 * Version: 1.0.0
 * Author: Cosovan Stelian
 */
 
 if (!defined('ABSPATH')) exit;
 
 class SmartGear_UI_Polish {
 
     public function __construct() {
         add_action('wp_enqueue_scripts', [$this, 'enqueue_assets']);
         add_filter('the_content', [$this, 'replace_home_content'], 20);
         add_filter('wp_nav_menu_items', [$this, 'add_theme_toggle_to_menu'], 10, 2);
 
         // If theme doesn't use wp_nav_menu for header, we also inject button in wp_footer as fallback.
         add_action('wp_footer', [$this, 'toggle_fallback_in_footer']);
     }
 
     public function enqueue_assets() {
         $css = $this->css();
         $js  = $this->js();
 
         wp_register_style('smartgear-ui-polish', false, [], '1.0.0');
         wp_enqueue_style('smartgear-ui-polish');
         wp_add_inline_style('smartgear-ui-polish', $css);
 
         wp_register_script('smartgear-ui-polish', false, [], '1.0.0', true);
         wp_enqueue_script('smartgear-ui-polish');
         wp_add_inline_script('smartgear-ui-polish', $js);
     }
 
     /**
      * Replace the homepage content with a hero.
      * Works when your homepage is set to a page and uses `the_content()`.
      * If your homepage is "latest posts", this will affect the blog index page content.
      */
     public function replace_home_content($content) {
         if (!is_front_page()) return $content;
         if (is_admin()) return $content;
 
         $shop_url = function_exists('wc_get_page_permalink') ? wc_get_page_permalink('shop') : home_url('/shop/');
 
         $hero = '
         <section class="sg-hero" role="region" aria-label="SmartGear hero">
             <div class="sg-hero-inner">
                 <div class="sg-badge">⚡ SmartGear</div>
                 <h1 class="sg-title">Welcome to <span class="sg-glow">SmartGear</span></h1>
                 <p class="sg-subtitle">
                     Modern tech picks, clean deals, and gear that just works.
                     Browse our shop and find your next favorite gadget in minutes.
                 </p>
                 <div class="sg-actions">
                     <a class="sg-btn sg-btn-primary" href="' . esc_url($shop_url) . '">
                         <span class="sg-btn-icon">🛒</span>
                         Go Shopping
                     </a>
                     <a class="sg-btn sg-btn-ghost" href="' . esc_url(home_url('/my-account/')) . '">
                         <span class="sg-btn-icon">👤</span>
                         My Account
                     </a>
                 </div>
                 <div class="sg-mini">
                     <span class="sg-dot"></span> Fast checkout
                     <span class="sg-dot"></span> Tested products
                     <span class="sg-dot"></span> New drops weekly
                 </div>
             </div>
         </section>';
 
         // Keep original content below hero if you want:
         // return $hero . $content;
         return $hero;
     }
 
     /**
      * Add toggle icon into primary menu if the theme prints a WP menu.
      * Many themes use theme_location like 'primary' or 'menu-1'.
      */
     public function add_theme_toggle_to_menu($items, $args) {
         // Try to inject into common primary locations; if unknown, still add to the first menu rendered.
         static $added = false;
         if ($added) return $items;
 
         $primaryish = isset($args->theme_location) && in_array($args->theme_location, ['primary', 'menu-1', 'top', 'header'], true);
         if (!$primaryish && !empty($args->theme_location)) {
             // If theme_location is something else, we still allow first menu injection.
         }
 
         $toggle = '
         <li class="menu-item sg-theme-toggle-li">
             <button type="button" class="sg-theme-toggle" aria-label="Toggle dark mode" title="Toggle theme">
                 <span class="sg-theme-icon" aria-hidden="true">🌙</span>
             </button>
         </li>';
 
         $added = true;
         return $items . $toggle;
     }
 
     /**
      * Fallback toggle in footer if menu injection doesn't show.
      */
     public function toggle_fallback_in_footer() {
         ?>
         <div class="sg-toggle-fallback" aria-hidden="false">
             <button type="button" class="sg-theme-toggle sg-theme-toggle-float" aria-label="Toggle dark mode" title="Toggle theme">
                 <span class="sg-theme-icon" aria-hidden="true">🌙</span>
             </button>
         </div>
         <?php
     }
 
     private function css() {
         return <<<CSS
 /* ========= SmartGear UI Polish ========= */
 
 /* Theme variables */
 :root {
     --sg-blue: #2f7bff;
     --sg-blue2: #5aa7ff;
     --sg-bg: #ffffff;
     --sg-text: #0b1220;
     --sg-muted: rgba(11, 18, 32, 0.65);
     --sg-card: rgba(0,0,0,0.03);
     --sg-border: rgba(0,0,0,0.10);
     --sg-shadow: 0 12px 40px rgba(0, 0, 0, 0.10);
 }
 
 html.sg-dark {
     --sg-bg: #070a10;
     --sg-text: #eaf1ff;
     --sg-muted: rgba(234, 241, 255, 0.72);
     --sg-card: rgba(255,255,255,0.06);
     --sg-border: rgba(255,255,255,0.14);
     --sg-shadow: 0 16px 60px rgba(0, 0, 0, 0.45);
 }
 
 body {
     background: var(--sg-bg) !important;
     color: var(--sg-text);
     transition: background 160ms ease, color 160ms ease;
 }
 
 /* Logo / Site title glow (works for many themes) */
 .site-title a,
 .site-logo a,
 .custom-logo-link,
 header .site-title a,
 .wp-block-site-title a {
     color: var(--sg-blue) !important;
     text-shadow:
         0 0 10px rgba(47,123,255,0.55),
         0 0 22px rgba(90,167,255,0.28);
     font-weight: 800;
     letter-spacing: 0.2px;
 }
 
 /* Link hover (header + general) */
 a:hover,
 .nav-menu a:hover,
 .main-navigation a:hover {
     color: var(--sg-blue) !important;
 }
 
 /* Buttons hover -> blue */
 button,
 input[type="submit"],
 .wp-block-button__link,
 .woocommerce a.button,
 .woocommerce button.button,
 .woocommerce input.button {
     transition: transform 120ms ease, background 120ms ease, color 120ms ease, box-shadow 120ms ease, border-color 120ms ease;
 }
 
 button:hover,
 input[type="submit"]:hover,
 .wp-block-button__link:hover,
 .woocommerce a.button:hover,
 .woocommerce button.button:hover,
 .woocommerce input.button:hover {
     transform: translateY(-1px);
     border-color: rgba(47,123,255,0.65) !important;
     box-shadow: 0 10px 28px rgba(47,123,255,0.18);
 }
 
 /* ========= Hero ========= */
 .sg-hero {
     display: flex;
     justify-content: center;
     padding: clamp(48px, 6vw, 92px) 18px;
 }
 
 .sg-hero-inner {
     max-width: 980px;
     width: 100%;
     border: 1px solid var(--sg-border);
     border-radius: 22px;
     background: linear-gradient(
         135deg,
         rgba(47,123,255,0.12),
         rgba(0,0,0,0) 42%,
         rgba(90,167,255,0.10)
     );
     box-shadow: var(--sg-shadow);
     padding: clamp(22px, 4vw, 42px);
     position: relative;
     overflow: hidden;
 }
 
 .sg-hero-inner:before {
     content: "";
     position: absolute;
     inset: -120px;
     background: radial-gradient(circle at 22% 18%, rgba(47,123,255,0.22), transparent 42%),
                 radial-gradient(circle at 78% 38%, rgba(90,167,255,0.18), transparent 45%),
                 radial-gradient(circle at 50% 80%, rgba(47,123,255,0.10), transparent 55%);
     filter: blur(0px);
     pointer-events: none;
 }
 
 .sg-badge {
     display: inline-flex;
     align-items: center;
     gap: 8px;
     padding: 8px 12px;
     border-radius: 999px;
     background: var(--sg-card);
     border: 1px solid var(--sg-border);
     color: var(--sg-text);
     font-weight: 600;
     position: relative;
     z-index: 1;
 }
 
 .sg-title {
     margin: 18px 0 12px;
     font-size: clamp(34px, 4.2vw, 62px);
     line-height: 1.05;
     letter-spacing: -0.02em;
     position: relative;
     z-index: 1;
 }
 
 .sg-glow {
     color: var(--sg-blue);
     text-shadow:
         0 0 12px rgba(47,123,255,0.55),
         0 0 26px rgba(90,167,255,0.30);
 }
 
 .sg-subtitle {
     max-width: 58ch;
     font-size: clamp(15px, 1.5vw, 18px);
     color: var(--sg-muted);
     margin: 0 0 18px;
     position: relative;
     z-index: 1;
 }
 
 .sg-actions {
     display: flex;
     gap: 12px;
     flex-wrap: wrap;
     position: relative;
     z-index: 1;
 }
 
 .sg-btn {
     display: inline-flex;
     align-items: center;
     gap: 10px;
     padding: 12px 16px;
     border-radius: 14px;
     text-decoration: none !important;
     border: 1px solid var(--sg-border);
     background: var(--sg-card);
     color: var(--sg-text) !important;
     font-weight: 700;
 }
 
 .sg-btn-primary {
     background: linear-gradient(135deg, rgba(47,123,255,0.95), rgba(90,167,255,0.92));
     border-color: rgba(47,123,255,0.35);
     color: #fff !important;
     box-shadow: 0 14px 30px rgba(47,123,255,0.25);
 }
 
 .sg-btn-ghost:hover,
 .sg-btn-primary:hover {
     filter: brightness(1.03);
     box-shadow: 0 16px 46px rgba(47,123,255,0.28);
 }
 
 .sg-btn-icon {
     display: inline-flex;
     width: 22px;
     height: 22px;
     justify-content: center;
     align-items: center;
 }
 
 .sg-mini {
     margin-top: 16px;
     color: var(--sg-muted);
     font-size: 13px;
     display: flex;
     gap: 10px;
     flex-wrap: wrap;
     position: relative;
     z-index: 1;
 }
 
 .sg-dot {
     display: inline-block;
     width: 6px;
     height: 6px;
     border-radius: 50%;
     background: var(--sg-blue);
     box-shadow: 0 0 10px rgba(47,123,255,0.65);
     margin: 0 4px 1px 0;
 }
 
 /* ========= Theme toggle button ========= */
 .sg-theme-toggle {
     border: 1px solid var(--sg-border);
     background: var(--sg-card);
     color: var(--sg-text);
     border-radius: 999px;
     padding: 8px 10px;
     cursor: pointer;
     line-height: 1;
 }
 
 .sg-theme-toggle:hover {
     border-color: rgba(47,123,255,0.7);
     box-shadow: 0 10px 28px rgba(47,123,255,0.18);
 }
 
 .sg-theme-toggle-li {
     display: flex;
     align-items: center;
 }
 
 /* Fallback floating toggle */
 .sg-toggle-fallback {
     position: fixed;
     right: 16px;
     bottom: 16px;
     z-index: 9999;
 }
 
 .sg-theme-toggle-float {
     width: 44px;
     height: 44px;
     display: grid;
     place-items: center;
 }
 
 /* Improve WooCommerce button hover to blue */
 .woocommerce a.button:hover,
 .woocommerce button.button:hover,
 .woocommerce input.button:hover {
     background: rgba(47,123,255,0.95) !important;
     color: #fff !important;
 }
 CSS;
     }
 
     private function js() {
         return <<<JS
 (function(){
     const STORAGE_KEY = "sg_theme";
     const html = document.documentElement;
 
     function setTheme(mode){
         if(mode === "dark"){
             html.classList.add("sg-dark");
             localStorage.setItem(STORAGE_KEY, "dark");
         }else{
             html.classList.remove("sg-dark");
             localStorage.setItem(STORAGE_KEY, "light");
         }
         updateIcons();
     }
 
     function getTheme(){
         const saved = localStorage.getItem(STORAGE_KEY);
         if(saved === "dark" || saved === "light") return saved;
         // default: match system
         return (window.matchMedia && window.matchMedia("(prefers-color-scheme: dark)").matches) ? "dark" : "light";
     }
 
     function updateIcons(){
         const isDark = html.classList.contains("sg-dark");
         document.querySelectorAll(".sg-theme-icon").forEach(el => {
             el.textContent = isDark ? "☀️" : "🌙";
         });
         document.querySelectorAll(".sg-theme-toggle").forEach(btn => {
             btn.setAttribute("aria-label", isDark ? "Switch to light mode" : "Switch to dark mode");
             btn.setAttribute("title", isDark ? "Switch to light mode" : "Switch to dark mode");
         });
     }
 
     function bindToggle(btn){
         btn.addEventListener("click", function(){
             const isDark = html.classList.contains("sg-dark");
             setTheme(isDark ? "light" : "dark");
         });
     }
 
     // init
     document.addEventListener("DOMContentLoaded", function(){
         setTheme(getTheme());
         document.querySelectorAll(".sg-theme-toggle").forEach(bindToggle);
 
         // If theme menu toggle didn't render yet, icons still update via updateIcons()
         updateIcons();
     });
 })();
 JS;
     }
 }
 
 new SmartGear_UI_Polish();
 