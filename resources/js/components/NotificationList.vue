<template>
    <div class="bg-white rounded-lg shadow-md p-6">
        <!-- Header -->
        <div class="flex items-center justify-between mb-6">
            <h2 class="text-xl font-bold text-gray-900 flex items-center space-x-2">
                <span>📬</span>
                <span>Recent Notifications</span>
            </h2>
            
            <div class="flex items-center space-x-2">
                <!-- Real-time indicator -->
                <div v-if="isConnected" class="flex items-center space-x-2 text-sm text-green-600">
                    <span class="relative flex h-3 w-3">
                        <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-green-400 opacity-75"></span>
                        <span class="relative inline-flex rounded-full h-3 w-3 bg-green-500"></span>
                    </span>
                    <span class="font-medium">Live</span>
                </div>
                <div v-else class="flex items-center space-x-2 text-sm text-gray-400">
                    <span class="inline-flex rounded-full h-3 w-3 bg-gray-300"></span>
                    <span>Offline</span>
                </div>
            </div>
        </div>
        
        <!-- Error message -->
        <div v-if="error" class="mb-4 p-4 bg-red-50 border border-red-200 rounded-md">
            <p class="text-sm text-red-800">{{ error }}</p>
        </div>
        
        <!-- Loading state -->
        <div v-if="isLoading && notifications.length === 0" class="flex items-center justify-center py-12">
            <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-blue-600"></div>
        </div>
        
        <!-- Empty state -->
        <div v-else-if="notifications.length === 0" class="text-center py-12">
            <div class="text-6xl mb-4">📭</div>
            <p class="text-gray-500">No notifications yet</p>
        </div>
        
        <!-- Notifications list -->
        <div v-else class="space-y-3">
            <div 
                v-for="notification in notifications" 
                :key="notification.id"
                class="border border-gray-200 rounded-lg p-4 hover:border-blue-300 hover:shadow-md transition-all duration-200"
                :class="getNotificationBorderClass(notification)"
            >
                <div class="flex items-start justify-between">
                    <!-- Content -->
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center space-x-2 mb-2">
                            <!-- Status icon -->
                            <span class="text-xl">{{ getStatusIcon(notification.status) }}</span>
                            
                            <!-- Channel badge -->
                            <span :class="getChannelBadgeClass(notification.channel)" class="px-2 py-1 text-xs font-medium rounded">
                                {{ notification.channel.toUpperCase() }}
                            </span>
                            
                            <!-- Priority badge -->
                            <span :class="getPriorityBadgeClass(notification.priority)" class="px-2 py-1 text-xs font-medium rounded">
                                {{ notification.priority.toUpperCase() }}
                            </span>
                            
                            <!-- Status badge -->
                            <span :class="getStatusBadgeClass(notification.status)" class="px-2 py-1 text-xs font-medium rounded">
                                {{ notification.status.toUpperCase() }}
                            </span>
                        </div>
                        
                        <!-- Recipient -->
                        <p class="text-sm font-medium text-gray-900 mb-1">
                            To: {{ notification.recipient }}
                        </p>
                        
                        <!-- Subject/Content -->
                        <p v-if="notification.subject" class="text-sm text-gray-600 mb-1 truncate">
                            {{ notification.subject }}
                        </p>
                        <p class="text-xs text-gray-500 truncate">
                            {{ notification.content }}
                        </p>
                        
                        <!-- Timestamps -->
                        <div class="flex items-center space-x-4 mt-2 text-xs text-gray-400">
                            <span>Created: {{ formatDate(notification.created_at) }}</span>
                            <span v-if="notification.sent_at">Sent: {{ formatDate(notification.sent_at) }}</span>
                        </div>
                        
                        <!-- Error message -->
                        <div v-if="notification.last_error" class="mt-2 p-2 bg-red-50 rounded text-xs text-red-700">
                            {{ notification.last_error }}
                        </div>
                    </div>
                    
                    <!-- Actions -->
                    <div class="ml-4 flex-shrink-0">
                        <button 
                            @click="viewDetails(notification.id)"
                            class="text-sm text-blue-600 hover:text-blue-800 font-medium"
                        >
                            View
                        </button>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Pagination -->
        <div v-if="notifications.length > 0" class="mt-6 flex items-center justify-between border-t border-gray-200 pt-4">
            <div class="text-sm text-gray-500">
                Showing {{ notifications.length }} notifications
            </div>
            <button 
                @click="loadMore"
                :disabled="isLoading"
                class="px-4 py-2 text-sm font-medium text-blue-600 hover:bg-blue-50 rounded-md transition-colors disabled:opacity-50"
            >
                {{ isLoading ? 'Loading...' : 'Load More' }}
            </button>
        </div>
    </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import axios from 'axios';

// Props
const props = defineProps({
    isConnected: {
        type: Boolean,
        default: false,
    },
    recentEvents: {
        type: Array,
        default: () => [],
    },
});

// State
const notifications = ref([]);
const isLoading = ref(false);
const error = ref(null);
const page = ref(1);
const limit = ref(10);

// Fetch notifications from API
const fetchNotifications = async () => {
    isLoading.value = true;
    error.value = null;
    
    try {
        const apiKey = import.meta.env.VITE_API_KEY || localStorage.getItem('api_key');
        
        const response = await axios.get('/api/v1/notifications', {
            params: {
                page: page.value,
                limit: limit.value,
            },
            headers: apiKey ? {
                'Authorization': `Bearer ${apiKey}`
            } : {},
        });
        
        if (response.data.success) {
            if (page.value === 1) {
                notifications.value = response.data.data || [];
            } else {
                notifications.value.push(...(response.data.data || []));
            }
        } else {
            throw new Error(response.data.message || 'Failed to fetch notifications');
        }
    } catch (err) {
        error.value = err.response?.data?.message || err.message || 'Failed to fetch notifications';
        console.error('[NotificationList] Error:', err);
    } finally {
        isLoading.value = false;
    }
};

// Load more notifications
const loadMore = () => {
    page.value++;
    fetchNotifications();
};

// View notification details
const viewDetails = (id) => {
    window.open(`/api/v1/notifications/${id}`, '_blank');
};

// Get status icon
const getStatusIcon = (status) => {
    const icons = {
        'pending': '⏳',
        'queued': '📤',
        'sent': '✅',
        'failed': '❌',
        'cancelled': '🚫',
    };
    return icons[status] || '❓';
};

// Get status badge class
const getStatusBadgeClass = (status) => {
    const classes = {
        'pending': 'bg-gray-100 text-gray-700',
        'queued': 'bg-blue-100 text-blue-700',
        'sent': 'bg-green-100 text-green-700',
        'failed': 'bg-red-100 text-red-700',
        'cancelled': 'bg-yellow-100 text-yellow-700',
    };
    return classes[status] || 'bg-gray-100 text-gray-700';
};

// Get channel badge class
const getChannelBadgeClass = (channel) => {
    const classes = {
        'email': 'bg-purple-100 text-purple-700',
        'sms': 'bg-green-100 text-green-700',
        'push': 'bg-blue-100 text-blue-700',
        'webhook': 'bg-orange-100 text-orange-700',
    };
    return classes[channel] || 'bg-gray-100 text-gray-700';
};

// Get priority badge class
const getPriorityBadgeClass = (priority) => {
    const classes = {
        'high': 'bg-red-100 text-red-700',
        'normal': 'bg-yellow-100 text-yellow-700',
        'low': 'bg-green-100 text-green-700',
    };
    return classes[priority] || 'bg-gray-100 text-gray-700';
};

// Get notification border class for animation
const getNotificationBorderClass = (notification) => {
    // Check if this notification was recently updated (within last 3 seconds)
    const recentEvent = props.recentEvents.find(e => e.event.id === notification.id);
    if (recentEvent) {
        const eventTime = new Date(recentEvent.timestamp);
        const now = new Date();
        if (now - eventTime < 3000) {
            return 'border-blue-400 animate-pulse';
        }
    }
    return '';
};

// Format date
const formatDate = (dateString) => {
    if (!dateString) return 'N/A';
    const date = new Date(dateString);
    return date.toLocaleString();
};

// Watch for real-time events and update notifications
import { watch } from 'vue';

watch(() => props.recentEvents.length, (newLength, oldLength) => {
    // Only process when a NEW event is added (length increases)
    if (newLength <= oldLength || props.recentEvents.length === 0) return;
    
    // Get the latest (newest) event - it's at index 0 since they're prepended
    const latestEvent = props.recentEvents[0];
    const eventData = latestEvent.event;
    
    if (!eventData || !eventData.id) return;
    
    console.log('[NotificationList] Processing new event:', latestEvent.type, eventData);
    
    // Create full notification object from event data
    const notification = {
        id: eventData.id,
        recipient: eventData.recipient,
        channel: eventData.channel,
        status: eventData.status,
        priority: eventData.priority,
        batch_id: eventData.batch_id,
        sent_at: eventData.sent_at,
        external_message_id: eventData.external_message_id,
        subject: eventData.subject,
        content: eventData.content,
        created_at: eventData.created_at,
        last_error: eventData.last_error,
        attempts: eventData.attempts,
    };
    
    // Update existing notification or prepend new one
    const index = notifications.value.findIndex(n => n.id === notification.id);
    
    if (index !== -1) {
        // Update existing notification
        console.log('[NotificationList] Updating existing notification:', notification.id);
        notifications.value[index] = { ...notifications.value[index], ...notification };
    } else {
        // Prepend new notification to the list
        console.log('[NotificationList] Adding new notification:', notification.id);
        notifications.value.unshift(notification);
        
        // Keep list at reasonable size (max 50 items)
        if (notifications.value.length > 50) {
            notifications.value.pop();
        }
    }
});

// Initial fetch
onMounted(() => {
    fetchNotifications();
});
</script>
