import { ref, onMounted, onUnmounted } from 'vue';
import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

/**
 * WebSocket Composable for real-time notification updates
 * 
 * Connects to Laravel Reverb and subscribes to notification channels
 * Returns reactive state for connection status and real-time events
 */
export function useWebSocket() {
    const isConnected = ref(false);
    const error = ref(null);
    const recentEvents = ref([]);
    
    let echo = null;
    
    // Initialize Echo instance
    const connect = () => {
        try {
            window.Pusher = Pusher;
            
            console.log('[WebSocket] Initializing with config:', {
                key: import.meta.env.VITE_REVERB_APP_KEY,
                wsHost: import.meta.env.VITE_REVERB_HOST,
                wsPort: import.meta.env.VITE_REVERB_PORT ?? 8080,
                scheme: import.meta.env.VITE_REVERB_SCHEME ?? 'http',
            });
            
            echo = new Echo({
                broadcaster: 'reverb',
                key: import.meta.env.VITE_REVERB_APP_KEY,
                wsHost: import.meta.env.VITE_REVERB_HOST,
                wsPort: parseInt(import.meta.env.VITE_REVERB_PORT ?? '8080'),
                wssPort: parseInt(import.meta.env.VITE_REVERB_PORT ?? '8080'),
                forceTLS: (import.meta.env.VITE_REVERB_SCHEME ?? 'https') === 'https',
                enabledTransports: ['ws', 'wss'],
                encrypted: false,
                disableStats: true,
            });
            
            // Enable Pusher logging for debugging
            Pusher.logToConsole = true;
            console.log('[WebSocket] 🔧 Initializing with config:', {
                key: import.meta.env.VITE_REVERB_APP_KEY,
                wsHost: import.meta.env.VITE_REVERB_HOST,
                wsPort: parseInt(import.meta.env.VITE_REVERB_PORT ?? '8080'),
                scheme: import.meta.env.VITE_REVERB_SCHEME ?? 'http'
            });
            
            // Connection event handlers
            echo.connector.pusher.connection.bind('connected', () => {
                isConnected.value = true;
                error.value = null;
                console.log('[WebSocket] ✅ Connected to Reverb');
            });
            
            echo.connector.pusher.connection.bind('connecting', () => {
                console.log('[WebSocket] 🔄 Connecting...');
            });
            
            echo.connector.pusher.connection.bind('disconnected', () => {
                isConnected.value = false;
                console.log('[WebSocket] ⚠️ Disconnected');
            });
            
            echo.connector.pusher.connection.bind('unavailable', () => {
                isConnected.value = false;
                console.error('[WebSocket] ❌ Connection unavailable');
            });
            
            echo.connector.pusher.connection.bind('error', (err) => {
                isConnected.value = false;
                error.value = err.message || 'Connection error';
                console.error('[WebSocket] ❌ Error:', err);
            });
            
            echo.connector.pusher.connection.bind('state_change', (states) => {
                console.log('[WebSocket] State change:', states.previous, '->', states.current);
            });
            
        } catch (err) {
            error.value = err.message;
            console.error('[WebSocket] Initialization failed:', err);
        }
    };
    
    // Subscribe to notifications channel for all events
    const subscribeToNotifications = (callback) => {
        if (!echo) {
            console.error('[WebSocket] Cannot subscribe - Echo not initialized');
            return null;
        }
        
        console.log('[WebSocket] 📡 Subscribing to notifications channel...');
        
        const channel = echo.channel('notifications');
        
        // Debug channel subscription
        channel.subscribed(() => {
            console.log('[WebSocket] ✅ Successfully subscribed to notifications channel');
        });
        
        channel.error((error) => {
            console.error('[WebSocket] ❌ Channel subscription error:', error);
        });
        
        // Listen for notification queued events
        channel.listen('.notification.queued', (event) => {
            console.log('[WebSocket] 📬 Notification Queued:', event);
            addRecentEvent('queued', event);
            if (callback) callback('queued', event);
        });
        
        // Listen for notification sent events
        channel.listen('.notification.sent', (event) => {
            console.log('[WebSocket] ✅ Notification Sent:', event);
            addRecentEvent('sent', event);
            if (callback) callback('sent', event);
        });
        
        // Listen for notification failed events
        channel.listen('.notification.failed', (event) => {
            console.log('[WebSocket] ❌ Notification Failed:', event);
            addRecentEvent('failed', event);
            if (callback) callback('failed', event);
        });
        
        console.log('[WebSocket] Event listeners attached');
        
        return channel;
    };
    
    // Subscribe to specific notification by ID
    const subscribeToNotification = (notificationId, callback) => {
        if (!echo) return null;
        
        const channel = echo.channel(`notification.${notificationId}`);
        
        channel.listen('.notification.queued', (event) => {
            console.log(`[WebSocket] Notification ${notificationId} Queued:`, event);
            if (callback) callback('queued', event);
        });
        
        channel.listen('.notification.sent', (event) => {
            console.log(`[WebSocket] Notification ${notificationId} Sent:`, event);
            if (callback) callback('sent', event);
        });
        
        channel.listen('.notification.failed', (event) => {
            console.log(`[WebSocket] Notification ${notificationId} Failed:`, event);
            if (callback) callback('failed', event);
        });
        
        return channel;
    };
    
    // Add event to recent events list (max 50)
    const addRecentEvent = (type, event) => {
        recentEvents.value.unshift({
            type,
            event,
            timestamp: new Date().toISOString(),
        });
        
        // Keep only last 50 events
        if (recentEvents.value.length > 50) {
            recentEvents.value.pop();
        }
    };
    
    // Disconnect and cleanup
    const disconnect = () => {
        if (echo) {
            echo.disconnect();
            echo = null;
        }
        isConnected.value = false;
    };
    
    // Auto-connect on mount
    onMounted(() => {
        connect();
    });
    
    // Auto-disconnect on unmount
    onUnmounted(() => {
        disconnect();
    });
    
    return {
        isConnected,
        error,
        recentEvents,
        subscribeToNotifications,
        subscribeToNotification,
        disconnect,
    };
}
