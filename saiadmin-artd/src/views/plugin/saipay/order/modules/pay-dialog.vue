<template>
  <ElDialog
    v-model="visible"
    title="收银台"
    width="60%"
    :close-on-click-modal="false"
    @closed="handleClose"
  >
    <div class="p-4" v-loading="loading">
      <!-- 步骤条 -->
      <div class="flex justify-center w-full">
        <ElSteps style="min-width: 600px" :active="step" finish-status="success" align-center>
          <ElStep title="选择支付方式" />
          <ElStep title="扫码支付" />
        </ElSteps>
      </div>

      <!-- 步骤1: 选择支付方式 -->
      <div v-if="step === 1" class="py-8 px-4">
        <div class="flex flex-col items-center justify-center">
          <div class="text-gray-500 mb-2 text-base">
            订单编号：<span class="text-gray-800 font-mono mr-4">{{ orderData.order_no }}</span>
            支付金额：<span class="text-red-500 font-bold text-xl"
              >{{ orderData.order_price }} 元</span
            >
          </div>
          <div class="text-gray-400 text-sm mb-8">请选择支付方式进行支付</div>

          <div v-if="paymentMethods.length > 0" class="flex justify-center gap-8 w-full max-w-2xl">
            <div
              v-for="method in paymentMethods"
              :key="method.value"
              class="flex-1 border rounded-xl p-6 cursor-pointer transition-all hover:shadow-lg hover:-translate-y-1 group relative overflow-hidden"
              :class="getMethodCardClass(method)"
              @click="handlePay(method.value)"
            >
              <div class="flex flex-col items-center gap-4">
                <div
                  class="w-16 h-16 rounded-full flex items-center justify-center transition-colors"
                  :class="getMethodIconWrapperClass(method)"
                >
                  <span class="text-3xl iconfont" :class="[method.icon, getMethodIconClass(method)]">
                    {{ method.label.slice(0, 1) }}
                  </span>
                </div>
                <div class="text-center">
                  <div class="font-bold text-lg text-gray-800 mb-1">{{ method.label }}</div>
                  <div class="text-xs text-gray-400">{{ method.description }}</div>
                </div>
              </div>
              <div
                class="absolute inset-0 border-2 rounded-xl opacity-0 group-hover:opacity-100 transition-opacity pointer-events-none"
                :class="getMethodActiveBorderClass(method)"
              ></div>
            </div>
          </div>
          <ElEmpty v-else description="暂无可用支付方式" />
        </div>
      </div>

      <!-- 步骤2: 支付二维码 -->
      <div v-if="step === 2" class="py-4">
        <div class="flex flex-col items-center">
          <div class="text-xl font-medium text-gray-800 mb-6">请使用手机扫码支付</div>

          <div class="bg-white p-4 rounded-lg shadow-sm border border-gray-100 mb-6 relative group">
            <img
              class="w-48 h-48 object-contain"
              :src="'https://api.pwmqr.com/qrcode/create/?url=' + payOrder.code_url"
              alt="支付二维码"
            />
          </div>

          <div class="w-full max-w-md bg-gray-50 rounded-lg p-6 mb-8">
            <div
              class="flex justify-between items-center mb-3 pb-3 border-b border-gray-200 border-dashed"
            >
              <span class="text-gray-500">订单金额</span>
              <span class="text-2xl font-bold text-red-500">¥ {{ payOrder.order_price }}</span>
            </div>
            <div class="flex justify-between items-center mb-2">
              <span class="text-gray-500">支付方式</span>
              <SaDict :value="payOrder.pay_method" dict="saipay_method" />
            </div>
            <div class="flex justify-between items-center">
              <span class="text-gray-500">订单编号</span>
              <span class="text-gray-800 font-mono text-sm">{{ payOrder.order_no }}</span>
            </div>
          </div>

          <div class="flex gap-4">
            <ElButton @click="step = 1" size="large" plain>重新选择支付方式</ElButton>
            <ElButton type="primary" @click="visible = false" size="large">支付完成</ElButton>
          </div>

          <div class="mt-8 text-xs text-red-400 text-center max-w-sm">
            提示：请在10分钟内完成支付，超时订单将自动关闭。
          </div>
        </div>
      </div>
    </div>
  </ElDialog>
</template>

<script setup lang="ts">
  import { ElMessage } from 'element-plus'
  import demoApi from '../../api/demo'

  interface PaymentMethod {
    label: string
    value: string
    description: string
    icon: string
    theme: 'blue' | 'green'
    enabled: boolean
  }

  interface Props {
    modelValue: boolean
    data?: Record<string, any>
  }

  interface Emits {
    (e: 'update:modelValue', value: boolean): void
    (e: 'success'): void
  }

  const props = withDefaults(defineProps<Props>(), {
    modelValue: false,
    data: () => ({})
  })

  const emit = defineEmits<Emits>()

  // 状态
  const visible = computed({
    get: () => props.modelValue,
    set: (val) => emit('update:modelValue', val)
  })
  const loading = ref(false)
  const step = ref(1)

  // 订单数据
  const orderData = computed(() => props.data || {})

  // 支付订单信息
  const payOrder = ref<Record<string, any>>({})
  const paymentMethods = ref<PaymentMethod[]>([])

  const loadPaymentMethods = async () => {
    paymentMethods.value = await demoApi.paymentMethods()
  }

  // 初始化
  const initForm = async () => {
    loading.value = true
    try {
      await loadPaymentMethods()
      if (paymentMethods.value.length === 0) {
        step.value = 1
        payOrder.value = {}
        return
      }

      const res = await demoApi.payOrder({
        order_no: orderData.value.order_no
      })
      if (res.pay_url) {
        step.value = 2
        payOrder.value = {
          pay_method: res.pay_method,
          pay_type: res.pay_type,
          code_url: res.pay_url,
          order_no: res.order_no,
          order_price: res.order_price
        }
        return
      }
      step.value = 1
      payOrder.value = {}
    } catch (e) {
      step.value = 1
      payOrder.value = {}
      console.error(e)
    } finally {
      loading.value = false
    }
  }

  // 监听弹窗打开
  watch(visible, (val) => {
    if (val) {
      initForm()
    }
  })

  // 发起支付
  const handlePay = async (type: string) => {
    loading.value = true
    try {
      const params = {
        order_no: orderData.value.order_no,
        pay_method: type // 'alipay' or 'wechat'
      }
      const res = await demoApi.payOrder(params)

      payOrder.value = {
        code_url: res.pay_url,
        order_no: res.order_no,
        order_price: res.order_price,
        pay_method: res.pay_method
      }
      step.value = 2
      ElMessage.success('支付二维码已生成')
    } catch (e) {
      // 错误处理交由拦截器或在此处处理
      console.error(e)
    } finally {
      loading.value = false
    }
  }

  // 关闭弹窗
  const handleClose = () => {
    // cleanup
  }

  const getMethodCardClass = (method: PaymentMethod) => {
    if (loading.value) {
      return 'opacity-50 pointer-events-none'
    }
    return method.theme === 'green'
      ? 'border-gray-200 hover:border-green-500'
      : 'border-gray-200 hover:border-blue-500'
  }

  const getMethodIconWrapperClass = (method: PaymentMethod) => {
    return method.theme === 'green'
      ? 'bg-green-50 group-hover:bg-green-100'
      : 'bg-blue-50 group-hover:bg-blue-100'
  }

  const getMethodIconClass = (method: PaymentMethod) => {
    return method.theme === 'green' ? 'text-green-500' : 'text-blue-500'
  }

  const getMethodActiveBorderClass = (method: PaymentMethod) => {
    return method.theme === 'green' ? 'border-green-500' : 'border-blue-500'
  }
</script>
