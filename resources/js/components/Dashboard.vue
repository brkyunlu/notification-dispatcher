<template>
    <div class="min-h-screen bg-gray-50">
        <!-- Header -->
        <header class="bg-white shadow-sm border-b border-gray-200">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
                <div class="flex items-center justify-between">
                    <div>
                        <h1 class="text-3xl font-bold text-gray-900 flex items-center space-x-3">
                            <span>🔔</span>
                            <span>Notification Dashboard</span>
                        </h1>
                        <p class="mt-1 text-sm text-gray-500">
                            Real-time notification monitoring and analytics
                        </p>
                    </div>
                    
                    <!-- Connection status -->
                    <div class="flex items-center space-x-4">
                        <div v-if="isConnected" class="flex items-center space-x-2 px-4 py-2 bg-green-50 border border-green-200 rounded-lg">
                            <span class="relative flex h-3 w-3">
                                <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-green-400 opacity-75"></span>
                                <span class="relative inline-flex rounded-full h-3 w-3 bg-green-500"></span>
                            </span>
                            <span class="text-sm font-medium text-green-700">WebSocket Connected</span>
                        </div>
                        <div v-else class="flex items-center space-x-2 px-4 py-2 bg-gray-50 border border-gray-200 rounded-lg">
                            <span class="inline-flex rounded-full h-3 w-3 bg-gray-400"></span>
                            <span class="text-sm font-medium text-gray-600">Disconnected</span>
                        </div>
                    </div>
                </div>
            </div>
        </header>
        
        <!-- Main content -->
        <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
            <!-- Metrics overview -->
            <section class="mb-8">
                <h2 class="text-xl font-semibold text-gray-900 mb-4">Overview</h2>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                    <MetricsCard
                        id="total-sent"
                        title="Total Sent"
                        :value="metrics.total_sent"
                        icon="✅"
                        icon-bg-color="bg-green-100"
                        description="Successfully delivered notifications"
                        :change="metrics.sent_change"
                        :show-chart="true"
                        :chart-data="metrics.sent_history"
                    />
                    
                    <MetricsCard
                        id="total-failed"
                        title="Total Failed"
                        :value="metrics.total_failed"
                        icon="❌"
                        icon-bg-color="bg-red-100"
                        description="Failed delivery attempts"
                        :change="metrics.failed_change"
                    />
                    
                    <MetricsCard
                        id="avg-latency"
                        title="Avg Latency"
                        :value="metrics.avg_latency"
                        icon="⚡"
                        icon-bg-color="bg-yellow-100"
                        description="Average delivery time"
                        format="duration"
                    />
                    
                    <MetricsCard
                        id="success-rate"
                        title="Success Rate"
                        :value="metrics.success_rate"
                        icon="📊"
                        icon-bg-color="bg-blue-100"
                        description="Delivery success percentage"
                        format="percentage"
                    />
                </div>
            </section>
            
            <!-- Channel metrics -->
            <section class="mb-8">
                <h2 class="text-xl font-semibold text-gray-900 mb-4">Channel Performance</h2>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                    <MetricsCard
                        v-for="(value, channel) in metrics.channels"
                        :key="channel"
                        :id="`channel-${channel}`"
                        :title="`${channel.toUpperCase()}`"
                        :value="value"
                        :icon="getChannelIcon(channel)"
                        :icon-bg-color="getChannelBgColor(channel)"
                        :description="`${channel} notifications sent`"
                    />
                </div>
            </section>
            
            <!-- Queue status and notifications side by side -->
            <section class="mb-8">
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    <QueueStatus />
                    
                    <!-- Real-time events -->
                    <div class="bg-white rounded-lg shadow-md p-6">
                        <h2 class="text-xl font-bold text-gray-900 mb-4 flex items-center space-x-2">
                            <span>⚡</span>
                            <span>Real-time Events</span>
                        </h2>
                        
                        <div v-if="recentEvents.length === 0" class="text-center py-8 text-gray-500">
                            <p class="text-4xl mb-2">👂</p>
                            <p>Listening for events...</p>
                        </div>
                        
                        <div v-else class="space-y-2 max-h-96 overflow-y-auto">
                            <div 
                                v-for="(event, index) in recentEvents.slice(0, 10)" 
                                :key="index"
                                class="p-3 bg-gray-50 border border-gray-200 rounded-md text-sm"
                            >
                                <div class="flex items-center justify-between mb-1">
                                    <span :class="getEventTypeClass(event.type)" class="px-2 py-1 text-xs font-medium rounded">
                                        {{ event.type.toUpperCase() }}
                                    </span>
                                    <span class="text-xs text-gray-500">
                                        {{ formatTime(event.timestamp) }}
                                    </span>
                                </div>
                                <p class="text-xs text-gray-700 truncate">
                                    {{ event.event.recipient || 'N/A' }} - {{ event.event.channel || 'N/A' }}
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </section>
            
            <!-- Recent notifications -->
            <section>
                <NotificationList 
                    :is-connected="isConnected"
                    :recent-events="recentEvents"
                />
            </section>
        </main>
        
        <!-- Footer -->
        <footer class="bg-white border-t border-gray-200 mt-12">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
                <p class="text-center text-sm text-gray-500">
                    Notification Dispatcher Dashboard - Built with Vue.js 3 & Laravel 11
                </p>
            </div>
        </footer>
    </div>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue';
import axios from 'axios';
import MetricsCard from './MetricsCard.vue';
import QueueStatus from './QueueStatus.vue';
import NotificationList from './NotificationList.vue';
import { useWebSocket } from '../composables/useWebSocket';

// WebSocket composable
const { isConnected, recentEvents, subscribeToNotifications } = useWebSocket();

// Metrics state
const metrics = reactive({
    total_sent: 0,
    total_failed: 0,
    avg_latency: 0,
    success_rate: 0,
    sent_change: 0,
    failed_change: 0,
    sent_history: [],
    channels: {
        email: 0,
        sms: 0,
        push: 0,
    },
});

// Fetch metrics from API
const fetchMetrics = async () => {
    try {
        const apiKey = import.meta.env.VITE_API_KEY || localStorage.getItem('api_key');
        
        const response = await axios.get('/api/v1/metrics', {
            headers: apiKey ? {
                'Authorization': `Bearer ${apiKey}`
            } : {},
        });
        
        if (response.data.success) {
            const data = response.data.data;
            
            // Update metrics from notifications.last_24h
            if (data.notifications?.last_24h) {
                const last24h = data.notifications.last_24h;
                
                metrics.total_sent = last24h.by_status?.sent || 0;
                metrics.total_failed = last24h.by_status?.failed || 0;
                metrics.success_rate = Math.round(last24h.success_rate_percent || 0);
                
                // Update channels from by_channel
                if (last24h.by_channel) {
                    Object.keys(metrics.channels).forEach(channel => {
                        metrics.channels[channel] = last24h.by_channel[channel] || 0;
                    });
                }
            }
            
            // Update latency from database
            if (data.database?.latency_ms) {
                metrics.avg_latency = Math.round(data.database.latency_ms);
            }
            
            // Update sent history for chart (mock data for now)
            if (metrics.sent_history.length === 0) {
                metrics.sent_history = Array(20).fill(0).map(() => Math.floor(Math.random() * 100));
            }
        }
    } catch (err) {
        console.error('[Dashboard] Failed to fetch metrics:', err);
    }
};

// Subscribe to real-time notifications
const setupWebSocket = () => {
    subscribeToNotifications((type, event) => {
        console.log('[Dashboard] Real-time event:', type, event);
        
        // Update metrics based on event type
        if (type === 'sent') {
            metrics.total_sent++;
        } else if (type === 'failed') {
            metrics.total_failed++;
        }
        
        // Recalculate success rate
        const total = metrics.total_sent + metrics.total_failed;
        metrics.success_rate = total > 0 
            ? Math.round((metrics.total_sent / total) * 100) 
            : 0;
    });
};

// Get channel icon
const getChannelIcon = (channel) => {
    const icons = {
        email: '📧',
        sms: '📱',
        push: '🔔',
    };
    return icons[channel] || '📊';
};

// Get channel background color
const getChannelBgColor = (channel) => {
    const colors = {
        email: 'bg-purple-100',
        sms: 'bg-green-100',
        push: 'bg-blue-100',
    };
    return colors[channel] || 'bg-gray-100';
};

// Get event type class
const getEventTypeClass = (type) => {
    const classes = {
        queued: 'bg-blue-100 text-blue-700',
        sent: 'bg-green-100 text-green-700',
        failed: 'bg-red-100 text-red-700',
    };
    return classes[type] || 'bg-gray-100 text-gray-700';
};

// Format time
const formatTime = (timestamp) => {
    const date = new Date(timestamp);
    return date.toLocaleTimeString();
};

// Initialize
onMounted(() => {
    fetchMetrics();
    setupWebSocket();
    
    // Auto-refresh metrics every 10 seconds
    setInterval(() => {
        fetchMetrics();
    }, 10000);
});
</script>
