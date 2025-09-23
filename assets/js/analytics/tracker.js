/**
 * Analytics Tracker Client-Side
 * Provides JavaScript API for tracking user interactions and events
 */
class AnalyticsTracker {
    constructor(options = {}) {
        this.config = {
            endpoint: options.endpoint || '/admin/analytics/api/track',
            enabled: options.enabled !== false,
            debugMode: options.debug || false,
            autoTrack: options.autoTrack !== false,
            sessionTimeout: options.sessionTimeout || 30 * 60 * 1000, // 30 minutes
            batchSize: options.batchSize || 10,
            batchTimeout: options.batchTimeout || 5000, // 5 seconds
            ...options
        };

        this.eventQueue = [];
        this.sessionId = this.getOrCreateSessionId();
        this.pageLoadTime = Date.now();
        this.lastActivity = Date.now();
        this.batchTimer = null;

        if (this.config.enabled) {
            this.init();
        }
    }

    init() {
        this.setupAutoTracking();
        this.startSessionMonitoring();
        this.trackPageView();
        
        // Process any queued events
        this.processBatch();
        
        this.log('Analytics tracker initialized');
    }

    /**
     * Track custom event
     */
    track(eventType, category, action = null, label = null, value = null, properties = null) {
        if (!this.config.enabled) return;

        const event = {
            eventType,
            category,
            action,
            label,
            value,
            url: window.location.href,
            properties: {
                ...properties,
                timestamp: Date.now(),
                sessionId: this.sessionId,
                userAgent: navigator.userAgent,
                viewport: {
                    width: window.innerWidth,
                    height: window.innerHeight
                },
                screen: {
                    width: screen.width,
                    height: screen.height
                }
            }
        };

        this.addToQueue(event);
        this.log('Event tracked:', event);
    }

    /**
     * Track page view
     */
    trackPageView(title = null) {
        this.track('page_view', 'navigation', 'view', title || document.title, 1, {
            url: window.location.href,
            referrer: document.referrer,
            path: window.location.pathname,
            search: window.location.search,
            hash: window.location.hash
        });
    }

    /**
     * Track click events
     */
    trackClick(element, label = null, value = null) {
        const elementInfo = this.getElementInfo(element);
        this.track('click', 'interaction', 'click', label || elementInfo.text, value, {
            element: elementInfo,
            position: this.getClickPosition(event)
        });
    }

    /**
     * Track form submissions
     */
    trackFormSubmission(form, success = true, errors = null) {
        const formInfo = this.getFormInfo(form);
        this.track('form', 'interaction', success ? 'submit_success' : 'submit_error', formInfo.name, success ? 1 : 0, {
            form: formInfo,
            errors: errors
        });
    }

    /**
     * Track scroll depth
     */
    trackScrollDepth(percentage) {
        this.track('scroll', 'engagement', 'scroll_depth', `${percentage}%`, percentage, {
            page_height: document.documentElement.scrollHeight,
            viewport_height: window.innerHeight,
            scroll_position: window.pageYOffset
        });
    }

    /**
     * Track time on page
     */
    trackTimeOnPage() {
        const timeSpent = Date.now() - this.pageLoadTime;
        this.track('timing', 'engagement', 'time_on_page', window.location.pathname, Math.round(timeSpent / 1000), {
            time_spent_ms: timeSpent,
            url: window.location.href
        });
    }

    /**
     * Track downloads
     */
    trackDownload(url, filename = null) {
        this.track('download', 'file', 'download', filename || this.getFilenameFromUrl(url), 1, {
            file_url: url,
            file_type: this.getFileExtension(url)
        });
    }

    /**
     * Track external links
     */
    trackExternalLink(url, text = null) {
        this.track('outbound', 'navigation', 'external_link', text, 1, {
            destination: url,
            from_page: window.location.href
        });
    }

    /**
     * Track search events
     */
    trackSearch(query, results = null, filters = null) {
        this.track('search', 'site_search', 'search', query, results, {
            query: query,
            results_count: results,
            filters: filters,
            search_page: window.location.href
        });
    }

    /**
     * Track e-commerce events
     */
    trackEcommerce(action, data = {}) {
        this.track('ecommerce', 'commerce', action, data.label, data.value, {
            ...data,
            currency: data.currency || 'EUR'
        });
    }

    /**
     * Track video events
     */
    trackVideo(action, videoTitle, position = null, duration = null) {
        this.track('video', 'media', action, videoTitle, position, {
            video_title: videoTitle,
            position: position,
            duration: duration,
            percentage: duration ? Math.round((position / duration) * 100) : null
        });
    }

    /**
     * Setup automatic tracking
     */
    setupAutoTracking() {
        if (!this.config.autoTrack) return;

        // Track clicks on links
        document.addEventListener('click', (e) => {
            const link = e.target.closest('a');
            if (link) {
                const href = link.getAttribute('href');
                if (href) {
                    if (this.isExternalLink(href)) {
                        this.trackExternalLink(href, link.textContent.trim());
                    } else if (this.isDownloadLink(href)) {
                        this.trackDownload(href, link.textContent.trim());
                    }
                }
            }

            // Track clicks on buttons and interactive elements
            const button = e.target.closest('button, [role="button"], .btn');
            if (button) {
                this.trackClick(button);
            }
        });

        // Track form submissions
        document.addEventListener('submit', (e) => {
            this.trackFormSubmission(e.target);
        });

        // Track scroll depth
        let maxScrollDepth = 0;
        const scrollThresholds = [25, 50, 75, 90, 100];
        
        window.addEventListener('scroll', this.throttle(() => {
            const scrollPercent = Math.round((window.pageYOffset / (document.documentElement.scrollHeight - window.innerHeight)) * 100);
            
            for (const threshold of scrollThresholds) {
                if (scrollPercent >= threshold && maxScrollDepth < threshold) {
                    maxScrollDepth = threshold;
                    this.trackScrollDepth(threshold);
                }
            }
        }, 500));

        // Track page visibility changes
        document.addEventListener('visibilitychange', () => {
            if (document.hidden) {
                this.trackTimeOnPage();
            }
        });

        // Track before page unload
        window.addEventListener('beforeunload', () => {
            this.trackTimeOnPage();
            this.flush(); // Send any remaining events
        });
    }

    /**
     * Start session monitoring
     */
    startSessionMonitoring() {
        // Update last activity on user interactions
        ['click', 'scroll', 'keypress', 'mousemove'].forEach(event => {
            document.addEventListener(event, () => {
                this.lastActivity = Date.now();
            });
        });

        // Check for session timeout
        setInterval(() => {
            if (Date.now() - this.lastActivity > this.config.sessionTimeout) {
                this.sessionId = this.generateSessionId();
                this.lastActivity = Date.now();
                this.log('New session started due to inactivity');
            }
        }, 60000); // Check every minute
    }

    /**
     * Add event to queue and process if needed
     */
    addToQueue(event) {
        this.eventQueue.push(event);

        if (this.eventQueue.length >= this.config.batchSize) {
            this.processBatch();
        } else if (!this.batchTimer) {
            this.batchTimer = setTimeout(() => {
                this.processBatch();
            }, this.config.batchTimeout);
        }
    }

    /**
     * Process batch of events
     */
    async processBatch() {
        if (this.eventQueue.length === 0) return;

        const events = this.eventQueue.splice(0, this.config.batchSize);
        
        if (this.batchTimer) {
            clearTimeout(this.batchTimer);
            this.batchTimer = null;
        }

        try {
            await this.sendEvents(events);
            this.log(`Sent batch of ${events.length} events`);
        } catch (error) {
            this.log('Error sending events:', error);
            // Re-queue events on failure (with limit to prevent infinite growth)
            if (this.eventQueue.length < 100) {
                this.eventQueue.unshift(...events);
            }
        }
    }

    /**
     * Send events to server
     */
    async sendEvents(events) {
        const batch = events.length === 1 ? events[0] : { events };
        
        const response = await fetch(this.config.endpoint, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify(batch)
        });

        if (!response.ok) {
            throw new Error(`HTTP ${response.status}: ${response.statusText}`);
        }

        return response.json();
    }

    /**
     * Flush all pending events immediately
     */
    async flush() {
        while (this.eventQueue.length > 0) {
            await this.processBatch();
        }
    }

    /**
     * Utility functions
     */
    getOrCreateSessionId() {
        let sessionId = sessionStorage.getItem('analytics_session_id');
        if (!sessionId) {
            sessionId = this.generateSessionId();
            sessionStorage.setItem('analytics_session_id', sessionId);
        }
        return sessionId;
    }

    generateSessionId() {
        return Date.now().toString(36) + Math.random().toString(36).substr(2);
    }

    getElementInfo(element) {
        return {
            tagName: element.tagName.toLowerCase(),
            id: element.id,
            className: element.className,
            text: element.textContent.trim().substring(0, 100),
            href: element.href,
            type: element.type
        };
    }

    getFormInfo(form) {
        return {
            name: form.name || form.id,
            action: form.action,
            method: form.method,
            fieldCount: form.elements.length
        };
    }

    getClickPosition(event) {
        return event ? {
            x: event.clientX,
            y: event.clientY,
            pageX: event.pageX,
            pageY: event.pageY
        } : null;
    }

    isExternalLink(href) {
        return href.startsWith('http') && !href.includes(window.location.hostname);
    }

    isDownloadLink(href) {
        const downloadExtensions = ['.pdf', '.doc', '.docx', '.xls', '.xlsx', '.zip', '.rar', '.tar', '.gz'];
        return downloadExtensions.some(ext => href.toLowerCase().includes(ext));
    }

    getFilenameFromUrl(url) {
        return url.split('/').pop().split('?')[0];
    }

    getFileExtension(url) {
        return url.split('.').pop().split('?')[0];
    }

    throttle(func, limit) {
        let inThrottle;
        return function() {
            const args = arguments;
            const context = this;
            if (!inThrottle) {
                func.apply(context, args);
                inThrottle = true;
                setTimeout(() => inThrottle = false, limit);
            }
        }
    }

    log(...args) {
        if (this.config.debugMode) {
            console.log('[Analytics]', ...args);
        }
    }
}

// Auto-initialize if in browser environment
if (typeof window !== 'undefined') {
    // Wait for DOM to be ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => {
            window.analytics = new AnalyticsTracker(window.analyticsConfig || {});
        });
    } else {
        window.analytics = new AnalyticsTracker(window.analyticsConfig || {});
    }
}

// Export for module systems
if (typeof module !== 'undefined' && module.exports) {
    module.exports = AnalyticsTracker;
}