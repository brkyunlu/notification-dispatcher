<template>
    <div class="bg-white rounded-lg shadow-md p-6 hover:shadow-lg transition-shadow duration-200">
        <!-- Header -->
        <div class="flex items-center justify-between mb-4">
            <div class="flex items-center space-x-3">
                <div :class="[
                    'w-12 h-12 rounded-full flex items-center justify-center',
                    iconBgColor
                ]">
                    <span class="text-2xl">{{ icon }}</span>
                </div>
                <div>
                    <h3 class="text-sm font-medium text-gray-600">{{ title }}</h3>
                    <p class="text-2xl font-bold text-gray-900">
                        {{ formattedValue }}
                    </p>
                </div>
            </div>
            
            <!-- Change indicator -->
            <div v-if="change !== null" :class="[
                'flex items-center space-x-1 text-sm font-medium px-2 py-1 rounded',
                changeColor
            ]">
                <span>{{ changeSymbol }}</span>
                <span>{{ Math.abs(change) }}%</span>
            </div>
        </div>
        
        <!-- Description or subtitle -->
        <p v-if="description" class="text-sm text-gray-500 mt-2">
            {{ description }}
        </p>
        
        <!-- Mini chart or additional info -->
        <div v-if="showChart && chartData.length > 0" class="mt-4">
            <canvas :id="`chart-${id}`" class="w-full h-16"></canvas>
        </div>
    </div>
</template>

<script setup>
import { computed, onMounted, watch } from 'vue';
import { Chart, LineController, LineElement, PointElement, LinearScale, CategoryScale } from 'chart.js';

// Register Chart.js components
Chart.register(LineController, LineElement, PointElement, LinearScale, CategoryScale);

// Props
const props = defineProps({
    id: {
        type: String,
        required: true,
    },
    title: {
        type: String,
        required: true,
    },
    value: {
        type: [Number, String],
        required: true,
    },
    icon: {
        type: String,
        default: '📊',
    },
    iconBgColor: {
        type: String,
        default: 'bg-blue-100',
    },
    description: {
        type: String,
        default: null,
    },
    change: {
        type: Number,
        default: null,
    },
    format: {
        type: String,
        default: 'number', // 'number', 'percentage', 'duration', 'bytes'
    },
    showChart: {
        type: Boolean,
        default: false,
    },
    chartData: {
        type: Array,
        default: () => [],
    },
});

// Formatted value based on format type
const formattedValue = computed(() => {
    const val = props.value;
    
    switch (props.format) {
        case 'percentage':
            return `${val}%`;
        case 'duration':
            return formatDuration(val);
        case 'bytes':
            return formatBytes(val);
        case 'number':
        default:
            return typeof val === 'number' ? val.toLocaleString() : val;
    }
});

// Format duration (milliseconds to human readable)
const formatDuration = (ms) => {
    if (ms < 1000) return `${ms}ms`;
    if (ms < 60000) return `${(ms / 1000).toFixed(1)}s`;
    if (ms < 3600000) return `${(ms / 60000).toFixed(1)}m`;
    return `${(ms / 3600000).toFixed(1)}h`;
};

// Format bytes to human readable
const formatBytes = (bytes) => {
    if (bytes < 1024) return `${bytes}B`;
    if (bytes < 1048576) return `${(bytes / 1024).toFixed(1)}KB`;
    if (bytes < 1073741824) return `${(bytes / 1048576).toFixed(1)}MB`;
    return `${(bytes / 1073741824).toFixed(1)}GB`;
};

// Change color based on positive/negative
const changeColor = computed(() => {
    if (props.change === null) return '';
    return props.change >= 0 
        ? 'bg-green-100 text-green-800' 
        : 'bg-red-100 text-red-800';
});

// Change symbol
const changeSymbol = computed(() => {
    if (props.change === null) return '';
    return props.change >= 0 ? '↑' : '↓';
});

// Chart instance
let chartInstance = null;

// Initialize mini chart
const initChart = () => {
    if (!props.showChart || props.chartData.length === 0) return;
    
    const canvas = document.getElementById(`chart-${props.id}`);
    if (!canvas) return;
    
    const ctx = canvas.getContext('2d');
    
    // Destroy existing chart
    if (chartInstance) {
        chartInstance.destroy();
    }
    
    // Create new chart
    chartInstance = new Chart(ctx, {
        type: 'line',
        data: {
            labels: props.chartData.map((_, i) => i),
            datasets: [{
                data: props.chartData,
                borderColor: '#3B82F6',
                backgroundColor: 'rgba(59, 130, 246, 0.1)',
                borderWidth: 2,
                pointRadius: 0,
                tension: 0.4,
                fill: true,
            }],
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: false,
                },
            },
            scales: {
                x: {
                    display: false,
                },
                y: {
                    display: false,
                    beginAtZero: true,
                },
            },
        },
    });
};

// Initialize chart on mount
onMounted(() => {
    if (props.showChart) {
        setTimeout(() => initChart(), 100);
    }
});

// Re-initialize chart when data changes
watch(() => props.chartData, () => {
    if (props.showChart) {
        initChart();
    }
}, { deep: true });
</script>
