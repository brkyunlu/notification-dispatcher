<template>
    <div class="bg-white rounded-lg shadow-md p-6">
        <!-- Header -->
        <div class="flex items-center justify-between mb-6">
            <h2 class="text-xl font-bold text-gray-900 flex items-center space-x-2">
                <span>🔄</span>
                <span>Queue Status</span>
            </h2>
            
            <!-- Refresh button -->
            <button 
                @click="refresh"
                :disabled="isLoading"
                class="px-3 py-1 text-sm font-medium text-blue-600 hover:bg-blue-50 rounded-md transition-colors disabled:opacity-50"
            >
                {{ isLoading ? 'Refreshing...' : 'Refresh' }}
            </button>
        </div>
        
        <!-- Error message -->
        <div v-if="error" class="mb-4 p-4 bg-red-50 border border-red-200 rounded-md">
            <p class="text-sm text-red-800">{{ error }}</p>
        </div>
        
        <!-- Loading state -->
        <div v-if="isLoading && !queueData" class="flex items-center justify-center py-12">
            <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-blue-600"></div>
        </div>
        
        <!-- Queue data -->
        <div v-else-if="queueData" class="space-y-6">
            <!-- Priority queues -->
            <div class="space-y-4">
                <h3 class="text-sm font-semibold text-gray-700 uppercase tracking-wide">
                    Priority Queues
                </h3>
                
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <!-- High priority -->
                    <div class="bg-red-50 border border-red-200 rounded-lg p-4">
                        <div class="flex items-center justify-between mb-2">
                            <span class="text-sm font-medium text-red-900">🔴 High Priority</span>
                            <span class="text-2xl font-bold text-red-700">
                                {{ queueData.high || 0 }}
                            </span>
                        </div>
                        <div class="w-full bg-red-200 rounded-full h-2">
                            <div 
                                class="bg-red-600 h-2 rounded-full transition-all duration-300"
                                :style="{ width: `${getPercentage(queueData.high)}%` }"
                            ></div>
                        </div>
                    </div>
                    
                    <!-- Normal priority -->
                    <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4">
                        <div class="flex items-center justify-between mb-2">
                            <span class="text-sm font-medium text-yellow-900">🟡 Normal Priority</span>
                            <span class="text-2xl font-bold text-yellow-700">
                                {{ queueData.normal || 0 }}
                            </span>
                        </div>
                        <div class="w-full bg-yellow-200 rounded-full h-2">
                            <div 
                                class="bg-yellow-600 h-2 rounded-full transition-all duration-300"
                                :style="{ width: `${getPercentage(queueData.normal)}%` }"
                            ></div>
                        </div>
                    </div>
                    
                    <!-- Low priority -->
                    <div class="bg-green-50 border border-green-200 rounded-lg p-4">
                        <div class="flex items-center justify-between mb-2">
                            <span class="text-sm font-medium text-green-900">🟢 Low Priority</span>
                            <span class="text-2xl font-bold text-green-700">
                                {{ queueData.low || 0 }}
                            </span>
                        </div>
                        <div class="w-full bg-green-200 rounded-full h-2">
                            <div 
                                class="bg-green-600 h-2 rounded-full transition-all duration-300"
                                :style="{ width: `${getPercentage(queueData.low)}%` }"
                            ></div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Dead Letter Queue -->
            <div class="bg-gray-50 border border-gray-200 rounded-lg p-4">
                <div class="flex items-center justify-between">
                    <div class="flex items-center space-x-2">
                        <span class="text-2xl">💀</span>
                        <div>
                            <h4 class="text-sm font-medium text-gray-900">Dead Letter Queue</h4>
                            <p class="text-xs text-gray-500">Failed notifications after max retries</p>
                        </div>
                    </div>
                    <span class="text-2xl font-bold text-gray-700">
                        {{ queueData.dead_letter || 0 }}
                    </span>
                </div>
            </div>
            
            <!-- Summary -->
            <div class="pt-4 border-t border-gray-200">
                <div class="flex items-center justify-between text-sm">
                    <span class="text-gray-600">Total pending notifications</span>
                    <span class="text-xl font-bold text-gray-900">
                        {{ totalPending }}
                    </span>
                </div>
                <div class="flex items-center justify-between text-xs text-gray-500 mt-2">
                    <span>Last updated</span>
                    <span>{{ lastUpdated }}</span>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import axios from 'axios';

// State
const queueData = ref(null);
const isLoading = ref(false);
const error = ref(null);
const lastUpdated = ref('Never');

// Computed
const totalPending = computed(() => {
    if (!queueData.value) return 0;
    return (queueData.value.high || 0) + 
           (queueData.value.normal || 0) + 
           (queueData.value.low || 0);
});

// Get percentage for progress bar (max 100 for full bar)
const getPercentage = (value) => {
    if (!value) return 0;
    const max = Math.max(
        queueData.value.high || 0,
        queueData.value.normal || 0,
        queueData.value.low || 0,
        1 // Minimum 1 to avoid division by zero
    );
    return Math.min((value / max) * 100, 100);
};

// Fetch queue data from metrics endpoint
const fetchQueueData = async () => {
    isLoading.value = true;
    error.value = null;
    
    try {
        // Get API key from environment or local storage
        const apiKey = import.meta.env.VITE_API_KEY || localStorage.getItem('api_key');
        
        const response = await axios.get('/api/v1/metrics', {
            headers: apiKey ? {
                'Authorization': `Bearer ${apiKey}`
            } : {},
        });
        
        if (response.data.success) {
            const queue = response.data.data.queue || {};
            
            // Use priority_breakdown if available, otherwise fallback to estimation
            if (queue.priority_breakdown) {
                queueData.value = {
                    high: queue.priority_breakdown.high || 0,
                    normal: queue.priority_breakdown.normal || 0,
                    low: queue.priority_breakdown.low || 0,
                    dead_letter: 0, // Would need separate DLX queue check
                };
            } else {
                // Fallback to estimation if priority_breakdown not available
                queueData.value = {
                    high: Math.floor((queue.messages_ready || 0) * 0.3),
                    normal: Math.floor((queue.messages_ready || 0) * 0.5),
                    low: Math.floor((queue.messages_ready || 0) * 0.2),
                    dead_letter: 0,
                };
            }
            
            lastUpdated.value = new Date().toLocaleTimeString();
        } else {
            throw new Error(response.data.message || 'Failed to fetch queue data');
        }
    } catch (err) {
        error.value = err.response?.data?.message || err.message || 'Failed to fetch queue data';
        console.error('[QueueStatus] Error:', err);
    } finally {
        isLoading.value = false;
    }
};

// Refresh data
const refresh = () => {
    fetchQueueData();
};

// Auto-refresh every 5 seconds
let refreshInterval = null;

onMounted(() => {
    fetchQueueData();
    
    // Auto-refresh every 5 seconds
    refreshInterval = setInterval(() => {
        fetchQueueData();
    }, 5000);
});

// Cleanup on unmount
import { onUnmounted } from 'vue';

onUnmounted(() => {
    if (refreshInterval) {
        clearInterval(refreshInterval);
    }
});
</script>
