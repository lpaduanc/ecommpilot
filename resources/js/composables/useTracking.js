/**
 * Composable para gerenciar tracking e analytics
 *
 * Integra:
 * - Google Analytics 4 (GA4) / gtag.js
 * - Meta Pixel (Facebook Pixel)
 * - Microsoft Clarity
 * - Hotjar
 *
 * Documentações:
 * - GA4: https://developers.google.com/analytics/devguides/collection/ga4
 * - Meta Pixel: https://developers.facebook.com/docs/meta-pixel
 * - Clarity: https://learn.microsoft.com/en-us/clarity
 * - Hotjar: https://help.hotjar.com/hc/en-us/sections/115002608787
 */

import { ref, reactive } from 'vue'
import api from '@/services/api'
import { logger } from '@/utils/logger'

// Configuração padrão
const defaultConfig = {
    ga: {
        enabled: false,
        measurementId: '',
    },
    metaPixel: {
        enabled: false,
        pixelId: '',
    },
    clarity: {
        enabled: false,
        projectId: '',
    },
    hotjar: {
        enabled: false,
        siteId: '',
        snippetVersion: 6,
    },
}

// Estado global reativo
const trackingConfig = reactive({ ...defaultConfig })
const isConfigLoaded = ref(false)
const isLoading = ref(false)

// Estado global dos scripts carregados
const scriptsLoaded = {
    gtag: false,
    metaPixel: false,
    clarity: false,
    hotjar: false,
}

/**
 * Carrega configuração de tracking do backend
 */
async function loadConfig() {
    if (isConfigLoaded.value || isLoading.value) {
        return trackingConfig
    }

    isLoading.value = true

    try {
        const response = await api.get('/settings/tracking')
        if (response.data?.data) {
            Object.assign(trackingConfig, response.data.data)
            isConfigLoaded.value = true
        }
    } catch (error) {
        logger.warn('Não foi possível carregar configurações de tracking:', error.message)
    } finally {
        isLoading.value = false
    }

    return trackingConfig
}

/**
 * Obtém a configuração de tracking
 */
function getConfig() {
    return trackingConfig
}

/**
 * Inicializa Google Analytics (gtag.js)
 *
 * @see https://developers.google.com/analytics/devguides/collection/ga4
 */
function initGoogleAnalytics() {
    const config = getConfig()
    if (!config.ga?.enabled || !config.ga?.measurementId || scriptsLoaded.gtag) {
        return
    }

    // Carrega o script gtag.js
    const script = document.createElement('script')
    script.async = true
    script.src = `https://www.googletagmanager.com/gtag/js?id=${config.ga.measurementId}`
    document.head.appendChild(script)

    // Inicializa o dataLayer
    window.dataLayer = window.dataLayer || []
    window.gtag = function () {
        window.dataLayer.push(arguments)
    }
    window.gtag('js', new Date())
    window.gtag('config', config.ga.measurementId)

    scriptsLoaded.gtag = true
}

/**
 * Inicializa Meta Pixel (Facebook Pixel)
 *
 * @see https://developers.facebook.com/docs/meta-pixel
 */
function initMetaPixel() {
    const config = getConfig()
    if (!config.metaPixel?.enabled || !config.metaPixel?.pixelId || scriptsLoaded.metaPixel) {
        return
    }

    // Meta Pixel base code
    !(function (f, b, e, v, n, t, s) {
        if (f.fbq) return
        n = f.fbq = function () {
            n.callMethod ? n.callMethod.apply(n, arguments) : n.queue.push(arguments)
        }
        if (!f._fbq) f._fbq = n
        n.push = n
        n.loaded = !0
        n.version = '2.0'
        n.queue = []
        t = b.createElement(e)
        t.async = !0
        t.src = v
        s = b.getElementsByTagName(e)[0]
        s.parentNode.insertBefore(t, s)
    })(window, document, 'script', 'https://connect.facebook.net/en_US/fbevents.js')

    window.fbq('init', config.metaPixel.pixelId)
    window.fbq('track', 'PageView')

    scriptsLoaded.metaPixel = true
}

/**
 * Inicializa Microsoft Clarity
 *
 * @see https://learn.microsoft.com/en-us/clarity
 */
function initClarity() {
    const config = getConfig()
    if (!config.clarity?.enabled || !config.clarity?.projectId || scriptsLoaded.clarity) {
        return
    }

    ;(function (c, l, a, r, i, t, y) {
        c[a] =
            c[a] ||
            function () {
                ;(c[a].q = c[a].q || []).push(arguments)
            }
        t = l.createElement(r)
        t.async = 1
        t.src = 'https://www.clarity.ms/tag/' + i
        y = l.getElementsByTagName(r)[0]
        y.parentNode.insertBefore(t, y)
    })(window, document, 'clarity', 'script', config.clarity.projectId)

    scriptsLoaded.clarity = true
}

/**
 * Inicializa Hotjar
 *
 * @see https://help.hotjar.com/hc/en-us/sections/115002608787
 */
function initHotjar() {
    const config = getConfig()
    if (!config.hotjar?.enabled || !config.hotjar?.siteId || scriptsLoaded.hotjar) {
        return
    }

    ;(function (h, o, t, j, a, r) {
        h.hj =
            h.hj ||
            function () {
                ;(h.hj.q = h.hj.q || []).push(arguments)
            }
        h._hjSettings = {
            hjid: config.hotjar.siteId,
            hjsv: config.hotjar.snippetVersion || 6,
        }
        a = o.getElementsByTagName('head')[0]
        r = o.createElement('script')
        r.async = 1
        r.src = t + h._hjSettings.hjid + j + h._hjSettings.hjsv
        a.appendChild(r)
    })(window, document, 'https://static.hotjar.com/c/hotjar-', '.js?sv=')

    scriptsLoaded.hotjar = true
}

/**
 * Composable principal para tracking
 */
export function useTracking() {
    /**
     * Carrega configurações do backend e inicializa scripts
     */
    async function initAll() {
        await loadConfig()
        initGoogleAnalytics()
        initMetaPixel()
        initClarity()
        initHotjar()
    }

    /**
     * Recarrega configurações do backend
     */
    async function reloadConfig() {
        isConfigLoaded.value = false
        scriptsLoaded.gtag = false
        scriptsLoaded.metaPixel = false
        scriptsLoaded.clarity = false
        scriptsLoaded.hotjar = false
        return await loadConfig()
    }

    /**
     * Salva configurações de tracking no backend
     */
    async function saveConfig(settings) {
        try {
            const response = await api.put('/settings/tracking', settings)
            if (response.data?.data) {
                Object.assign(trackingConfig, response.data.data)
            }
            return { success: true, data: response.data }
        } catch (error) {
            return { success: false, error: error.response?.data?.message || error.message }
        }
    }

    /**
     * Salva configuração de um provider específico
     */
    async function saveProviderConfig(provider, settings) {
        try {
            const response = await api.patch(`/settings/tracking/${provider}`, settings)
            if (response.data?.data) {
                trackingConfig[provider] = response.data.data
            }
            return { success: true, data: response.data }
        } catch (error) {
            return { success: false, error: error.response?.data?.message || error.message }
        }
    }

    // =========================================================================
    // Google Analytics 4 Events
    // @see https://developers.google.com/analytics/devguides/collection/ga4/ecommerce
    // =========================================================================

    /**
     * Envia evento genérico para GA4
     */
    function trackGAEvent(eventName, params = {}) {
        if (window.gtag) {
            window.gtag('event', eventName, params)
        }
    }

    /**
     * Visualização de produto (GA4)
     */
    function trackViewItem(item) {
        trackGAEvent('view_item', {
            currency: item.currency || 'BRL',
            value: item.price,
            items: [formatGAItem(item)],
        })
    }

    /**
     * Adicionar ao carrinho (GA4)
     */
    function trackAddToCart(item, quantity = 1) {
        trackGAEvent('add_to_cart', {
            currency: item.currency || 'BRL',
            value: item.price * quantity,
            items: [formatGAItem(item, quantity)],
        })
    }

    /**
     * Remover do carrinho (GA4)
     */
    function trackRemoveFromCart(item, quantity = 1) {
        trackGAEvent('remove_from_cart', {
            currency: item.currency || 'BRL',
            value: item.price * quantity,
            items: [formatGAItem(item, quantity)],
        })
    }

    /**
     * Início do checkout (GA4)
     */
    function trackBeginCheckout(items, value, coupon = null) {
        trackGAEvent('begin_checkout', {
            currency: 'BRL',
            value: value,
            coupon: coupon,
            items: items.map((item) => formatGAItem(item, item.quantity)),
        })
    }

    /**
     * Compra finalizada (GA4)
     */
    function trackPurchase(transaction) {
        trackGAEvent('purchase', {
            transaction_id: transaction.id,
            value: transaction.value,
            tax: transaction.tax || 0,
            shipping: transaction.shipping || 0,
            currency: transaction.currency || 'BRL',
            coupon: transaction.coupon || null,
            items: transaction.items.map((item) => formatGAItem(item, item.quantity)),
        })
    }

    /**
     * Formata item para padrão GA4
     */
    function formatGAItem(item, quantity = 1) {
        return {
            item_id: item.id || item.sku,
            item_name: item.name,
            item_brand: item.brand || '',
            item_category: item.category || '',
            item_variant: item.variant || '',
            price: item.price,
            quantity: quantity,
            discount: item.discount || 0,
        }
    }

    // =========================================================================
    // Meta Pixel Events
    // @see https://developers.facebook.com/docs/meta-pixel/reference
    // =========================================================================

    /**
     * Envia evento para Meta Pixel
     */
    function trackMetaEvent(eventName, params = {}) {
        if (window.fbq) {
            window.fbq('track', eventName, params)
        }
    }

    /**
     * Envia evento customizado para Meta Pixel
     */
    function trackMetaCustomEvent(eventName, params = {}) {
        if (window.fbq) {
            window.fbq('trackCustom', eventName, params)
        }
    }

    /**
     * Visualização de conteúdo/produto (Meta)
     */
    function trackMetaViewContent(item) {
        trackMetaEvent('ViewContent', {
            content_ids: [item.id || item.sku],
            content_name: item.name,
            content_type: 'product',
            content_category: item.category || '',
            value: item.price,
            currency: item.currency || 'BRL',
        })
    }

    /**
     * Adicionar ao carrinho (Meta)
     */
    function trackMetaAddToCart(item, quantity = 1) {
        trackMetaEvent('AddToCart', {
            content_ids: [item.id || item.sku],
            content_name: item.name,
            content_type: 'product',
            value: item.price * quantity,
            currency: item.currency || 'BRL',
            contents: [{ id: item.id || item.sku, quantity: quantity }],
        })
    }

    /**
     * Início do checkout (Meta)
     */
    function trackMetaInitiateCheckout(items, value) {
        trackMetaEvent('InitiateCheckout', {
            content_ids: items.map((item) => item.id || item.sku),
            content_type: 'product',
            num_items: items.reduce((sum, item) => sum + (item.quantity || 1), 0),
            value: value,
            currency: 'BRL',
            contents: items.map((item) => ({
                id: item.id || item.sku,
                quantity: item.quantity || 1,
            })),
        })
    }

    /**
     * Compra finalizada (Meta)
     */
    function trackMetaPurchase(transaction) {
        trackMetaEvent('Purchase', {
            content_ids: transaction.items.map((item) => item.id || item.sku),
            content_type: 'product',
            value: transaction.value,
            currency: transaction.currency || 'BRL',
            num_items: transaction.items.reduce((sum, item) => sum + (item.quantity || 1), 0),
            contents: transaction.items.map((item) => ({
                id: item.id || item.sku,
                quantity: item.quantity || 1,
            })),
        })
    }

    /**
     * Pesquisa (Meta)
     */
    function trackMetaSearch(searchTerm) {
        trackMetaEvent('Search', {
            search_string: searchTerm,
        })
    }

    /**
     * Lead gerado (Meta)
     */
    function trackMetaLead(value = null) {
        const params = {}
        if (value) {
            params.value = value
            params.currency = 'BRL'
        }
        trackMetaEvent('Lead', params)
    }

    // =========================================================================
    // Clarity Events
    // @see https://learn.microsoft.com/en-us/clarity/setup-and-installation/clarity-api
    // =========================================================================

    /**
     * Define tag customizada no Clarity
     */
    function setClarityTag(key, value) {
        if (window.clarity) {
            window.clarity('set', key, value)
        }
    }

    /**
     * Identifica usuário no Clarity
     */
    function setClarityUser(userId, sessionId = null, pageId = null) {
        if (window.clarity) {
            window.clarity('identify', userId, sessionId, pageId)
        }
    }

    /**
     * Marca evento no Clarity
     */
    function trackClarityEvent(eventName) {
        if (window.clarity) {
            window.clarity('event', eventName)
        }
    }

    // =========================================================================
    // Hotjar Events
    // @see https://help.hotjar.com/hc/en-us/articles/115011867948
    // =========================================================================

    /**
     * Dispara evento no Hotjar
     */
    function trackHotjarEvent(eventName) {
        if (window.hj) {
            window.hj('event', eventName)
        }
    }

    /**
     * Identifica usuário no Hotjar
     */
    function setHotjarUser(userId, attributes = {}) {
        if (window.hj) {
            window.hj('identify', userId, attributes)
        }
    }

    /**
     * Notifica mudança de rota (SPA)
     */
    function trackHotjarStateChange(path) {
        if (window.hj) {
            window.hj('stateChange', path)
        }
    }

    // =========================================================================
    // Métodos combinados (disparam para todas as plataformas)
    // =========================================================================

    /**
     * Rastreia visualização de produto em todas as plataformas
     */
    function trackProductView(item) {
        trackViewItem(item)
        trackMetaViewContent(item)
        trackClarityEvent('product_view')
        trackHotjarEvent('product_view')
    }

    /**
     * Rastreia adição ao carrinho em todas as plataformas
     */
    function trackCartAdd(item, quantity = 1) {
        trackAddToCart(item, quantity)
        trackMetaAddToCart(item, quantity)
        trackClarityEvent('add_to_cart')
        trackHotjarEvent('add_to_cart')
    }

    /**
     * Rastreia início de checkout em todas as plataformas
     */
    function trackCheckoutStart(items, value, coupon = null) {
        trackBeginCheckout(items, value, coupon)
        trackMetaInitiateCheckout(items, value)
        trackClarityEvent('checkout_start')
        trackHotjarEvent('checkout_start')
    }

    /**
     * Rastreia compra em todas as plataformas
     */
    function trackPurchaseComplete(transaction) {
        trackPurchase(transaction)
        trackMetaPurchase(transaction)
        trackClarityEvent('purchase')
        trackHotjarEvent('purchase')
    }

    /**
     * Identifica usuário em todas as plataformas
     */
    function identifyUser(userId, attributes = {}) {
        // GA4 - set user_id
        if (window.gtag) {
            window.gtag('set', { user_id: userId })
        }

        // Clarity
        setClarityUser(userId)

        // Hotjar
        setHotjarUser(userId, attributes)
    }

    /**
     * Rastreia mudança de página (para SPAs)
     */
    function trackPageView(path, title = null) {
        // GA4
        if (window.gtag) {
            const config = getConfig()
            window.gtag('config', config.ga?.measurementId, {
                page_path: path,
                page_title: title,
            })
        }

        // Meta Pixel
        if (window.fbq) {
            window.fbq('track', 'PageView')
        }

        // Hotjar (SPA)
        trackHotjarStateChange(path)
    }

    return {
        // Estado
        config: trackingConfig,
        isConfigLoaded,
        isLoading,

        // Configuração
        loadConfig,
        reloadConfig,
        saveConfig,
        saveProviderConfig,

        // Inicialização
        initAll,
        initGoogleAnalytics,
        initMetaPixel,
        initClarity,
        initHotjar,

        // GA4
        trackGAEvent,
        trackViewItem,
        trackAddToCart,
        trackRemoveFromCart,
        trackBeginCheckout,
        trackPurchase,

        // Meta Pixel
        trackMetaEvent,
        trackMetaCustomEvent,
        trackMetaViewContent,
        trackMetaAddToCart,
        trackMetaInitiateCheckout,
        trackMetaPurchase,
        trackMetaSearch,
        trackMetaLead,

        // Clarity
        setClarityTag,
        setClarityUser,
        trackClarityEvent,

        // Hotjar
        trackHotjarEvent,
        setHotjarUser,
        trackHotjarStateChange,

        // Combinados
        trackProductView,
        trackCartAdd,
        trackCheckoutStart,
        trackPurchaseComplete,
        identifyUser,
        trackPageView,
    }
}

export default useTracking
