<?php
/**
 * HTTP Security Headers
 * Hub de Links - Painel de Analytics
 * 
 * Implementa headers de segurança modernos para proteger contra:
 * - XSS (Cross-Site Scripting)
 * - Clickjacking
 * - MIME Sniffing
 * - Man-in-the-Middle
 * - Data Injection
 */

// ========== STRICT TRANSPORT SECURITY (HSTS) ==========
// Força navegadores a usarem APENAS HTTPS por 2 anos
// Protege contra: Man-in-the-middle, protocol downgrade
header("Strict-Transport-Security: max-age=63072000; includeSubDomains; preload");

// ========== CONTENT SECURITY POLICY (CSP) ==========
// Define fontes permitidas para carregar recursos
// Protege contra: XSS, injection attacks, unauthorized scripts
$csp = [
    "default-src 'self'",                                               // Padrão: apenas mesma origem
    "script-src 'self' 'unsafe-inline' https://unpkg.com",              // Scripts: mesma origem + inline (necessário para dashboard) + Ion Icons
    "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com https://fonts.gstatic.com",       // CSS: mesma origem + inline
    "img-src 'self' data: https:",                                      // Imagens: mesma origem + data URIs + HTTPS
    "font-src 'self' https://fonts.googleapis.com https://fonts.gstatic.com",                     // Fontes: apenas mesma origem
    "connect-src 'self' https://unpkg.com",                             // AJAX/Fetch: apenas mesma origem
    "frame-ancestors 'none'",                                           // Não permite embedding em iframes
    "form-action 'self'",                                               // Formulários: apenas mesma origem
    "base-uri 'self'",                                                  // Base tag: apenas mesma origem
    "object-src 'none'",                                                // Desabilita plugins (Flash, Java, etc)
    "upgrade-insecure-requests"                                         // Força upgrade HTTP → HTTPS
];
header("Content-Security-Policy: " . implode("; ", $csp));

// ========== PERMISSIONS POLICY ==========
// Desabilita recursos perigosos do navegador
// Protege contra: acesso não autorizado a camera, microfone, geolocalização
$permissions = [
    "camera=()",              // Desabilita camera
    "microphone=()",          // Desabilita microfone
    "geolocation=()",         // Desabilita geolocalização
    "payment=()",             // Desabilita payment API
    "usb=()",                 // Desabilita USB
    "magnetometer=()",        // Desabilita magnetômetro
    "gyroscope=()",           // Desabilita giroscópio
    "accelerometer=()"        // Desabilita acelerômetro
];
header("Permissions-Policy: " . implode(", ", $permissions));

// ========== X-CONTENT-TYPE-OPTIONS ==========
// Previne MIME sniffing (navegador interpretando arquivo incorretamente)
// Protege contra: script execution via MIME confusion
header("X-Content-Type-Options: nosniff");

// ========== X-FRAME-OPTIONS ==========
// Previne que a página seja carregada em iframe/frame
// Protege contra: clickjacking attacks
header("X-Frame-Options: DENY");

// ========== REFERRER-POLICY ==========
// Controla quais informações são enviadas no header Referer
// Protege contra: vazamento de informações sensíveis
header("Referrer-Policy: strict-origin-when-cross-origin");

// ========== X-XSS-PROTECTION (Legacy, mas ainda útil) ==========
// Ativa filtro XSS em navegadores antigos
header("X-XSS-Protection: 1; mode=block");

// ========== CACHE CONTROL (Para páginas administrativas) ==========
// Previne cache de páginas sensíveis
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");
header("Expires: 0");