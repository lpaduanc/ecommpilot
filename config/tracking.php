<?php

/**
 * Referência de Tracking e Analytics
 *
 * NOTA: As configurações de tracking são armazenadas POR LOJA no banco de dados
 * (campo `tracking_settings` na tabela `stores`), não em variáveis de ambiente.
 *
 * Este arquivo serve como documentação e referência dos snippets e eventos.
 *
 * Para configurar tracking via API:
 * - GET  /api/settings/tracking      - Obter configurações
 * - PUT  /api/settings/tracking      - Atualizar todas configurações
 * - PATCH /api/settings/tracking/{provider} - Atualizar provider específico
 *
 * Providers disponíveis: ga, gtag, meta_pixel, clarity, hotjar
 */

return [

    /*
    |--------------------------------------------------------------------------
    | Estrutura de Dados no Banco
    |--------------------------------------------------------------------------
    |
    | Armazenado em stores.tracking_settings (JSON):
    |
    | {
    |     "ga": {
    |         "enabled": true,
    |         "measurement_id": "G-XXXXXXXXXX"
    |     },
    |     "gtag": {
    |         "enabled": false,
    |         "tag_id": ""
    |     },
    |     "meta_pixel": {
    |         "enabled": true,
    |         "pixel_id": "123456789"
    |     },
    |     "clarity": {
    |         "enabled": true,
    |         "project_id": "abcdefghij"
    |     },
    |     "hotjar": {
    |         "enabled": false,
    |         "site_id": "",
    |         "snippet_version": 6
    |     }
    | }
    |
    */

    /*
    |--------------------------------------------------------------------------
    | Google Analytics 4 (GA4)
    |--------------------------------------------------------------------------
    |
    | Documentação: https://developers.google.com/analytics/devguides/collection/ga4
    |
    | O GA4 é a plataforma de analytics do Google para medir tráfego e engajamento.
    | Use o Measurement ID no formato G-XXXXXXXXXX
    |
    | Snippet base para adicionar no <head>:
    |
    | <!-- Google tag (gtag.js) -->
    | <script async src="https://www.googletagmanager.com/gtag/js?id=G-XXXXXXXXXX"></script>
    | <script>
    |     window.dataLayer = window.dataLayer || [];
    |     function gtag(){dataLayer.push(arguments);}
    |     gtag('js', new Date());
    |     gtag('config', 'G-XXXXXXXXXX');
    | </script>
    |
    */
    'ga_events' => [
        'view_item',        // Visualização de produto
        'view_item_list',   // Visualização de lista de produtos
        'select_item',      // Seleção de produto
        'add_to_cart',      // Adicionar ao carrinho
        'remove_from_cart', // Remover do carrinho
        'view_cart',        // Visualização do carrinho
        'begin_checkout',   // Início do checkout
        'add_shipping_info', // Informações de envio
        'add_payment_info', // Informações de pagamento
        'purchase',         // Compra finalizada
        'refund',           // Reembolso
    ],

    /*
    |--------------------------------------------------------------------------
    | Meta Pixel (Facebook Pixel)
    |--------------------------------------------------------------------------
    |
    | Documentação: https://developers.facebook.com/docs/meta-pixel
    |
    | O Meta Pixel rastreia a jornada do cliente entre Facebook, Instagram e seu site.
    | É gratuito e não tem limites de uso.
    |
    | IMPORTANTE para 2025+:
    | - Use Pixel + Conversions API (CAPI) juntos com deduplicação
    | - Mantenha Event Match Quality entre 8-9 para melhor performance
    |
    | Snippet base para adicionar no <head>:
    |
    | <!-- Meta Pixel Code -->
    | <script>
    | !function(f,b,e,v,n,t,s)
    | {if(f.fbq)return;n=f.fbq=function(){n.callMethod?
    | n.callMethod.apply(n,arguments):n.queue.push(arguments)};
    | if(!f._fbq)f._fbq=n;n.push=n;n.loaded=!0;n.version='2.0';
    | n.queue=[];t=b.createElement(e);t.async=!0;
    | t.src=v;s=b.getElementsByTagName(e)[0];
    | s.parentNode.insertBefore(t,s)}(window, document,'script',
    | 'https://connect.facebook.net/en_US/fbevents.js');
    | fbq('init', 'PIXEL_ID');
    | fbq('track', 'PageView');
    | </script>
    | <noscript><img height="1" width="1" style="display:none"
    | src="https://www.facebook.com/tr?id=PIXEL_ID&ev=PageView&noscript=1"/></noscript>
    | <!-- End Meta Pixel Code -->
    |
    */
    'meta_pixel_events' => [
        'PageView',           // Visualização de página
        'ViewContent',        // Visualização de conteúdo/produto
        'Search',             // Pesquisa
        'AddToCart',          // Adicionar ao carrinho
        'AddToWishlist',      // Adicionar à lista de desejos
        'InitiateCheckout',   // Iniciar checkout
        'AddPaymentInfo',     // Adicionar info de pagamento
        'Purchase',           // Compra finalizada
        'Lead',               // Lead gerado
        'CompleteRegistration', // Registro completo
    ],

    /*
    |--------------------------------------------------------------------------
    | Microsoft Clarity
    |--------------------------------------------------------------------------
    |
    | Documentação: https://learn.microsoft.com/en-us/clarity
    |
    | O Clarity é uma ferramenta gratuita de análise comportamental que oferece:
    | - Gravação de sessões
    | - Heatmaps (mapas de calor)
    | - Insights de UX
    |
    | Snippet base para adicionar no <head>:
    |
    | <script type="text/javascript">
    |     (function(c,l,a,r,i,t,y){
    |         c[a]=c[a]||function(){(c[a].q=c[a].q||[]).push(arguments)};
    |         t=l.createElement(r);t.async=1;t.src="https://www.clarity.ms/tag/"+i;
    |         y=l.getElementsByTagName(r)[0];y.parentNode.insertBefore(t,y);
    |     })(window, document, "clarity", "script", "PROJECT_ID");
    | </script>
    |
    */

    /*
    |--------------------------------------------------------------------------
    | Hotjar
    |--------------------------------------------------------------------------
    |
    | Documentação: https://help.hotjar.com/hc/en-us/sections/115002608787
    | NPM Package: https://github.com/hotjar/hotjar-js (@hotjar/browser)
    |
    | O Hotjar oferece:
    | - Heatmaps (cliques, movimento, scroll)
    | - Gravação de sessões
    | - Pesquisas e feedback
    | - Funis de conversão
    |
    | Snippet base para adicionar no <head>:
    |
    | <script>
    |     (function(h,o,t,j,a,r){
    |         h.hj=h.hj||function(){(h.hj.q=h.hj.q||[]).push(arguments)};
    |         h._hjSettings={hjid:HOTJAR_ID,hjsv:6};
    |         a=o.getElementsByTagName('head')[0];
    |         r=o.createElement('script');r.async=1;
    |         r.src=t+h._hjSettings.hjid+j+h._hjSettings.hjsv;
    |         a.appendChild(r);
    |     })(window,document,'https://static.hotjar.com/c/hotjar-','.js?sv=');
    | </script>
    |
    */

];
