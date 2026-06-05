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
            <div v-if="payOrder.manual" class="flex flex-col items-center gap-4">
              <div class="grid grid-cols-2 gap-4 w-full max-w-md">
                <button
                  v-for="qrcode in payOrder.qrcodes"
                  :key="qrcode.method"
                  type="button"
                  class="border rounded-lg px-4 py-3 text-sm transition-all"
                  :class="getQrcodeOptionClass(qrcode.method)"
                  @click="selectManualQrcode(qrcode.method)"
                >
                  {{ qrcode.label }}
                </button>
              </div>
              <div v-if="selectedManualQrcode" class="flex flex-col items-center gap-3">
                <img
                  class="w-48 h-48 object-contain"
                  :src="selectedManualQrcode.image"
                  :alt="selectedManualQrcode.label"
                />
                <span class="text-sm text-gray-500">{{ selectedManualQrcode.label }}</span>
              </div>
              <ElEmpty v-else description="请选择收款码" />
            </div>
            <img
              v-else
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
              <span class="text-gray-800">{{ payMethodLabel(payOrder.pay_method) }}</span>
            </div>
            <div v-if="payOrder.manual" class="flex justify-between items-center mb-2">
              <span class="text-gray-500">收款渠道</span>
              <span class="text-gray-800">{{ selectedManualQrcode?.label || '请选择' }}</span>
            </div>
            <div class="flex justify-between items-center">
              <span class="text-gray-500">订单编号</span>
              <span class="text-gray-800 font-mono text-sm">{{ payOrder.order_no }}</span>
            </div>
          </div>

          <div class="flex gap-4">
            <ElButton @click="step = 1" size="large" plain>重新选择支付方式</ElButton>
            <ElButton v-if="payOrder.manual" type="primary" @click="confirmManualPaid" size="large">
              我已付款
            </ElButton>
            <ElButton v-else type="primary" @click="visible = false" size="large">支付完成</ElButton>
          </div>

          <div class="mt-8 text-xs text-red-400 text-center max-w-sm">
            <template v-if="payOrder.manual">
              提示：付款后请点击“我已付款”，管理员核对到账后订单才会变为已支付。
            </template>
            <template v-else> 提示：请在10分钟内完成支付，超时订单将自动关闭。 </template>
          </div>
        </div>
      </div>
    </div>
  </ElDialog>
</template>

<script setup lang="ts">
  import { ElMessage } from 'element-plus'
  import demoApi from '../../api/demo'

  interface PaymentQrcode {
    label: string
    method: string
    image: string
  }

  interface PaymentMethod {
    label: string
    value: string
    description: string
    icon: string
    theme: 'blue' | 'green' | 'orange' | 'red'
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
  const selectedManualQrcodeMethod = ref('')

  const selectedManualQrcode = computed<PaymentQrcode | null>(() => {
    const qrcodes = (payOrder.value.qrcodes || []) as PaymentQrcode[]
    return qrcodes.find((qrcode) => qrcode.method === selectedManualQrcodeMethod.value) || null
  })

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
      if (res.pay_url || res.manual) {
        step.value = 2
        payOrder.value = {
          pay_method: res.pay_method,
          pay_type: res.pay_type,
          code_url: res.pay_url,
          manual: res.manual,
          qrcodes: res.qrcodes || [],
          order_no: res.order_no,
          order_price: res.order_price
        }
        selectedManualQrcodeMethod.value = ''
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
        manual: res.manual,
        qrcodes: res.qrcodes || [],
        order_no: res.order_no,
        order_price: res.order_price,
        pay_method: res.pay_method
      }
      selectedManualQrcodeMethod.value = ''
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

  const confirmManualPaid = async () => {
    if (!selectedManualQrcode.value) {
      ElMessage.warning('请先选择实际付款的收款码')
      return
    }

    loading.value = true
    try {
      await demoApi.confirmManualPaid({
        order_no: payOrder.value.order_no,
        pay_channel: selectedManualQrcode.value.method
      })
      ElMessage.success('已提交付款确认，请等待管理员核对到账')
      visible.value = false
      emit('success')
    } finally {
      loading.value = false
    }
  }

  const selectManualQrcode = (method: string) => {
    selectedManualQrcodeMethod.value = method
  }

  const getQrcodeOptionClass = (method: string) => {
    return selectedManualQrcodeMethod.value === method
      ? 'border-blue-500 bg-blue-50 text-blue-600'
      : 'border-gray-200 text-gray-600 hover:border-blue-300'
  }

  const payMethodLabel = (method: string) => {
    const labelMap: Record<string, string> = {
      alipay: '支付宝支付',
      wechat: '微信支付',
      manual_scan: '扫码支付',
      unipay: '银联支付'
    }
    return labelMap[method] || method || '-'
  }

  const getMethodCardClass = (method: PaymentMethod) => {
    if (loading.value) {
      return 'opacity-50 pointer-events-none'
    }
    const borderMap = {
      blue: 'border-gray-200 hover:border-blue-500',
      green: 'border-gray-200 hover:border-green-500',
      orange: 'border-gray-200 hover:border-orange-500',
      red: 'border-gray-200 hover:border-red-500'
    }
    return borderMap[method.theme] || borderMap.blue
  }

  const getMethodIconWrapperClass = (method: PaymentMethod) => {
    const wrapperMap = {
      blue: 'bg-blue-50 group-hover:bg-blue-100',
      green: 'bg-green-50 group-hover:bg-green-100',
      orange: 'bg-orange-50 group-hover:bg-orange-100',
      red: 'bg-red-50 group-hover:bg-red-100'
    }
    return wrapperMap[method.theme] || wrapperMap.blue
  }

  const getMethodIconClass = (method: PaymentMethod) => {
    const iconMap = {
      blue: 'text-blue-500',
      green: 'text-green-500',
      orange: 'text-orange-500',
      red: 'text-red-500'
    }
    return iconMap[method.theme] || iconMap.blue
  }

  const getMethodActiveBorderClass = (method: PaymentMethod) => {
    const borderMap = {
      blue: 'border-blue-500',
      green: 'border-green-500',
      orange: 'border-orange-500',
      red: 'border-red-500'
    }
    return borderMap[method.theme] || borderMap.blue
  }
</script>
