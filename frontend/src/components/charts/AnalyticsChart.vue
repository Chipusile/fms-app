<script setup lang="ts">
import { computed } from 'vue'
import { use } from 'echarts/core'
import { BarChart, LineChart, PieChart } from 'echarts/charts'
import {
  GridComponent,
  LegendComponent,
  TooltipComponent,
} from 'echarts/components'
import { CanvasRenderer } from 'echarts/renderers'
import VChart from 'vue-echarts'
import type { DashboardChart } from '@/types'

use([
  CanvasRenderer,
  BarChart,
  LineChart,
  PieChart,
  GridComponent,
  LegendComponent,
  TooltipComponent,
])

const props = defineProps<{
  chart: DashboardChart
  height?: string
}>()

const palette = ['#0f766e', '#1d4ed8', '#f59e0b', '#dc2626', '#7c3aed', '#0891b2']

const hasData = computed(() => {
  if (props.chart.type === 'pie') {
    return (props.chart.items?.length ?? 0) > 0
  }

  return (props.chart.series?.some((series) => series.data.length > 0) ?? false)
})

const option = computed(() => {
  if (props.chart.type === 'pie') {
    return {
      color: palette,
      tooltip: { trigger: 'item' },
      legend: { bottom: 0, icon: 'circle' },
      series: [
        {
          type: 'pie',
          radius: ['42%', '70%'],
          center: ['50%', '44%'],
          itemStyle: {
            borderColor: '#ffffff',
            borderWidth: 2,
          },
          label: {
            formatter: '{b}: {c}',
          },
          data: props.chart.items ?? [],
        },
      ],
    }
  }

  return {
    color: palette,
    tooltip: { trigger: 'axis' },
    legend: { top: 0, icon: 'circle' },
    grid: {
      top: 44,
      right: 16,
      bottom: 16,
      left: 16,
      containLabel: true,
    },
    xAxis: {
      type: 'category',
      boundaryGap: true,
      axisTick: { show: false },
      axisLine: { lineStyle: { color: '#cbd5e1' } },
      axisLabel: { color: '#475569' },
      data: props.chart.categories ?? [],
    },
    yAxis: {
      type: 'value',
      axisLine: { show: false },
      splitLine: { lineStyle: { color: '#e2e8f0' } },
      axisLabel: { color: '#475569' },
    },
    series: (props.chart.series ?? []).map((series) => ({
      ...series,
      smooth: series.type === 'line',
      barMaxWidth: series.type === 'bar' ? 26 : undefined,
    })),
  }
})
</script>

<template>
  <div class="relative">
    <VChart
      v-if="hasData"
      :option="option"
      :autoresize="true"
      :style="{ height: height ?? '320px' }"
    />
    <div
      v-else
      class="flex min-h-[320px] items-center justify-center rounded-2xl border border-dashed border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-900/50 text-sm text-slate-500 dark:text-slate-400"
    >
      No chart data available for the selected filters.
    </div>
  </div>
</template>
